"""Les fonctions de compare.py, sans base ni instance : éprouve-t-il ce qu'il prétend voir ?

    python3 -m unittest discover -s dev/compare

Chaque cas part d'une erreur qu'un portage peut faire, et vérifie qu'elle n'est pas prise pour un
arrondi ni effacée par une normalisation. Les erreurs sur les données elles-mêmes se jouent sur une
copie de base : compare.py selftest.
"""

import datetime
import importlib.util
import io
import re
import sys
import tempfile
import unittest
from contextlib import redirect_stdout
from pathlib import Path

sys.dont_write_bytecode = True
spec = importlib.util.spec_from_file_location('compare', Path(__file__).with_name('compare.py'))
compare = importlib.util.module_from_spec(spec)
spec.loader.exec_module(compare)


class Rounding(unittest.TestCase):
    """same_but_rounding : seuls les nombres décimaux s'arrondissent."""

    def test_admitted(self):
        for name, old, new in [
            ('dernier chiffre, en trois décimales', '20/01/2024 12:00:00;9.938;v;;', '20/01/2024 12:00:00;9.939;v;;'),
            ('une retenue', 'valeur 9.999', 'valeur 10.000'),
            ('-0 et 0', 'valeur -0.000', 'valeur 0.000'),
            ('virgule de l\'ancien, même valeur', 'altitude 3,34', 'altitude 3.34'),
            ('zéros de fin', 'valeur 1.50', 'valeur 1.5'),
            ('15 et 17 chiffres, PostgreSQL 11 et 17', 'valeur 2.8675', 'valeur 2.8674999999999998'),
            ('15 et 17 chiffres, entier apparent', 'valeur 100', 'valeur 100.00000000000001'),
            ('notation scientifique, même valeur', 'valeur 1.0E-5', 'valeur 1.0e-5'),
        ]:
            with self.subTest(name):
                self.assertTrue(compare.same_but_rounding(old, new))

    def test_refused(self):
        for name, old, new in [
            ('heure décalée d\'une heure', '05/01/2024 12:00:00;3.500;v', '05/01/2024 13:00:00;3.500;v'),
            ('jour décalé', '05/01/2024 12:00:00;3.500;v', '06/01/2024 12:00:00;3.500;v'),
            ('année décalée', '05/01/2024;3.5', '05/01/2025;3.5'),
            ('date ISO décalée', '2024-01-05 12:00:00;3.5', '2024-01-06 12:00:00;3.5'),
            ('date ISO, une heure', '2024-01-05T12:00:00+01:00', '2024-01-05T13:00:00+01:00'),
            ('fuseau d\'une date', '2024-01-05T12:00:00+01:00', '2024-01-05T12:00:00+00:00'),
            ('minutes', 'à 12:30', 'à 12:31'),
            ('deux unités du dernier chiffre', 'valeur 3.500', 'valeur 3.502'),
            ('nombre de mesures', 'nbmesures 1000', 'nbmesures 1001'),
            ('compteur en JSON', '{"id": 7, "nbmesures": 1000}', '{"id": 7, "nbmesures": 1001}'),
            ('identifiant dans une adresse', '/obs/chronique/1234/measures', '/obs/chronique/1235/measures'),
            ('code de sortie', 'code de sortie 0', 'code de sortie 1'),
            ('statut', 'statut 500', 'statut 501'),
            ('qualité dans une ligne d\'état', '7\t1000\t2024;3.5;12', '7\t1000\t2024;3.5;13'),
            ('virgule décimale, autre valeur', 'altitude 3,34', 'altitude 4,30'),
            ('virgule contre point, autre valeur', 'altitude 3,34', 'altitude 3.35'),
            ('entier devenu décimal', 'valeur 100', 'valeur 100.9'),
            ('autres décimales, autre valeur', 'valeur 3.5', 'valeur 3.51'),
            ('liste d\'entiers', '{1,2}', '{1,3}'),
            ('nombre dans un mot', 'chronique Q12', 'chronique Q13'),
            ('empreinte', '7\t10\tabc123', '7\t10\tabc124'),
            ('signe', 'valeur 1.000', 'valeur -1.000'),
            ('texte', 'valeur 3.5 m', 'valeur 3.5 cm'),
        ]:
            with self.subTest(name):
                self.assertFalse(compare.same_but_rounding(old, new))


class Verdict(unittest.TestCase):
    """classify : la catégorie du rapport."""

    def test_categories(self):
        lines = ['statut 200', 'T Une ligne', 'T 3.500']
        self.assertEqual(compare.classify(lines, lines)[0], 'identiques')
        self.assertEqual(compare.classify(lines, [*lines[:2], 'T 3.501'])[0], 'à un arrondi près')
        self.assertEqual(compare.classify(lines, [*lines[:2], 'T 3.502'])[0], 'différents')
        self.assertEqual(compare.classify(lines, [lines[1], lines[0], lines[2]])[0], 'même contenu, autre ordre')
        self.assertEqual(compare.classify(lines, lines[:2])[0], 'différents')
        self.assertEqual(compare.classify(lines, [])[0], 'différents')
        self.assertEqual(compare.classify(['conforme'], ['non conforme : 2 au lieu de 3'])[0], 'différents')

    def test_a_database_state_has_no_tolerance(self):
        self.assertEqual(compare.classify(['{"a": 3.500}'], ['{"a": 3.501}'], exact=True)[0], 'différents')
        self.assertEqual(compare.classify(['{"a": 3.500}'], ['{"a": 3.501}'])[0], 'à un arrondi près')

    def test_a_shifted_export_is_a_difference(self):
        # Toute la chronique décalée d'une heure : la plus coûteuse des erreurs de fuseau
        old = [f'05/01/2024 {h:02}:00:00;1.000;v;;' for h in range(8)]
        new = [f'05/01/2024 {h + 1:02}:00:00;1.000;v;;' for h in range(8)]
        self.assertEqual(compare.classify(old, new)[0], 'différents')

    def test_lengths_differ_and_rounding(self):
        old = ['T a', 'T 3.500', 'T b']
        new = ['T a', 'T 3.501', 'T b', 'T c']
        self.assertEqual(compare.classify(old, new)[0], 'différents')


class Pages(unittest.TestCase):
    """page_lines : une normalisation ne doit pas effacer ce qu'une page montre."""

    @staticmethod
    def lines(body, status=200, kind='text/html'):
        return compare.page_lines('http://site', status, {'Content-Type': kind}, body.encode())

    def test_shown_content_counts(self):
        for name, old, new in [
            ('texte', '<p>12 mesures</p>', '<p>13 mesures</p>'),
            ('lien', '<a href="/a/1">x</a>', '<a href="/a/2">x</a>'),
            ('champ', '<input name="a" value="1">', '<input name="a" value="2">'),
            ('champ coché', '<input type="checkbox" name="a" checked>', '<input type="checkbox" name="a">'),
            ('option choisie', '<select><option value="1" selected></select>', '<select><option value="1"></select>'),
            ('option', '<select><option value="1"></select>', '<select><option value="2"></select>'),
            ('attribut de données', '<div data-valeur="1"></div>', '<div data-valeur="2"></div>'),
            ('image', '<img src="/a.png">', '<img src="/b.png">'),
            ('formulaire', '<form action="/a"></form>', '<form action="/b"></form>'),
            ('script', '<script>var a = 1;</script>', '<script>var a = 2;</script>'),
        ]:
            with self.subTest(name):
                self.assertNotEqual(self.lines(old), self.lines(new))

    def test_status_and_redirect_count(self):
        self.assertNotEqual(self.lines('x', 200), self.lines('x', 500))
        a = compare.page_lines('http://site', 302, {'Content-Type': 'text/html', 'Location': 'http://site/a'}, b'')
        b = compare.page_lines('http://site', 302, {'Content-Type': 'text/html', 'Location': 'http://site/b'}, b'')
        self.assertNotEqual(a, b)

    def test_markup_and_wiring_do_not_count(self):
        for name, old, new in [
            ('balisage', '<div class="a"><p>x</p></div>', '<section class="b"><p>x</p></section>'),
            ('empreinte d\'un fichier', '<script src="/assets/a.0123456789abcdef.js"></script>',
             '<script src="/assets/a.fedcba9876543210.js"></script>'),
            ('jeton CSRF', '<input type="hidden" name="_csrf_token" value="a">',
             '<input type="hidden" name="_csrf_token" value="b">'),
            ('câblage de Stimulus', '<div data-controller="a"></div>', '<div></div>'),
            ('paramètres dans un autre ordre', '<a href="/x?b=2&a=1"></a>', '<a href="/x?a=1&b=2"></a>'),
            ('espace codée autrement', '<a href="/x?a=b+c"></a>', '<a href="/x?a=b%20c"></a>'),
        ]:
            with self.subTest(name):
                self.assertEqual(self.lines(old), self.lines(new))

    def test_json_values_count(self):
        self.assertNotEqual(self.lines('{"a": 1}', kind='application/json'), self.lines('{"a": 2}', kind='application/json'))
        self.assertEqual(self.lines('{"a": 1, "b": 2}', kind='application/json'),
                         self.lines('{"b": 2, "a": 1}', kind='application/json'))


class Moments(unittest.TestCase):
    """Les masques du moment : ce qui change d'une exécution à l'autre, et rien d'autre."""

    def test_the_day_of_a_qtvar_header(self):
        today, yesterday = compare.recent_days()
        line = 'DEB;EXP-HYDRO;"EXPORT DE DONNEES A PARTIR DE BDOH";{};IRSTEA;1997-1;;;'
        self.assertEqual(compare.export_line(line.format(f'{today:%Y%m%d}')),
                         compare.export_line(line.format(f'{yesterday:%Y%m%d}')))
        self.assertNotEqual(compare.export_line(line.format(f'{today:%Y%m%d}')), compare.export_line(line.format('20240105')))

    def test_a_measure_of_the_same_day_stays(self):
        today = compare.recent_days()[0]
        line = f'01;X;{today:%Y%m%d};1200;3.500;;'
        self.assertEqual(compare.export_line(line), line)

    def test_the_head_of_an_export(self):
        self.assertEqual(compare.export_line('Export du 05/01/2024 12:00 au format Bdoh'),
                         compare.export_line('Export du 06/01/2024 13:00 au format Bdoh'))

    def test_moments_of_a_job(self):
        mask = compare.moment_mask()
        today, yesterday = compare.recent_days()
        a = f'bdoh:compute:filling_rates ["12", "{today}T00:01:02+00:00"]'
        b = f'bdoh:compute:filling_rates ["12", "{yesterday}T23:59:02+00:00"]'
        self.assertEqual(mask.sub('<m>', a), mask.sub('<m>', b))
        for kept in ['["12", "2024-01-05T00:01:02+00:00"]',
                     f'["13", "{today}T00:01:02+00:00"]']:
            with self.subTest(kept):
                self.assertNotEqual(mask.sub('<m>', a), mask.sub('<m>', kept))

    def test_two_days_only(self):
        old = datetime.date.today() - datetime.timedelta(days=2)
        self.assertEqual(compare.moment_mask().sub('<m>', f'{old}T00:00:00'), f'{old}T00:00:00')


class Reports(unittest.TestCase):
    """Report : un fichier d'écarts par cas, même quand leurs noms se confondent."""

    def test_two_names_that_look_alike(self):
        with tempfile.TemporaryDirectory() as out:
            compare.OUT = Path(out)
            report = compare.Report('essai')
            with redirect_stdout(io.StringIO()):
                report.add("export 12 '+01:00'", ['a'], ['b'])
                report.add("export 12 '-01:00'", ['a'], ['c'])
            self.assertEqual(len(list(report.dir.glob('*.diff'))), 2)

    def test_the_same_name_keeps_its_file(self):
        with tempfile.TemporaryDirectory() as out:
            compare.OUT = Path(out)
            names = []
            for kind in ('un', 'deux'):
                report = compare.Report(kind)
                with redirect_stdout(io.StringIO()):
                    report.add('un cas', ['a'], ['b'])
                names.append(next(report.dir.glob('*.diff')).name)
            self.assertEqual(*names)


class Register(unittest.TestCase):
    """Le registre des écarts admis : ce qu'il admet, et surtout ce qu'il n'admet pas."""

    RULE = compare.expected.Rule('essai', r'page .*', 'essai', lines=('-T ancien', '+T nouveau', compare.expected.RX(r'\+T \d+ points?')))

    @staticmethod
    def rule(**fields):
        return compare.expected.Rule('essai', r'page .*', 'essai', **fields)

    def verdict(self, rules, name, old, new, exact=False):
        key, _, rounding = compare.classify(old, new, exact)
        return compare.registered(rules, name, key, old, new, exact, rounding)[0]

    def test_a_value_that_changes_is_never_admitted(self):
        # 3.500 et 3.501 : la comparaison le range à un arrondi près, le registre ne l'admet pas
        old, new = ['T a', 'T 3.500'], ['T a', 'T 3.501']
        self.assertEqual(self.verdict([], 'page x', old, new), 'différents')
        # Seule l'écriture change : la virgule de l'ancien, un zéro négatif, un zéro final
        for before, after in (('T 3,500', 'T 3.500'), ('T -0.000', 'T 0.000'), ('T 3.5', 'T 3.500')):
            with self.subTest(before=before):
                self.assertEqual(self.verdict([], 'page x', ['T a', before], ['T a', after]), 'à un arrondi près')

    def test_the_listed_lines_and_no_other(self):
        self.assertEqual(self.verdict([self.RULE], 'page a', ['T ancien'], ['T nouveau', 'T 3 points']), 'écarts admis')
        self.assertEqual(self.verdict([self.RULE], 'page a', ['T ancien', 'T x'], ['T nouveau', 'T y']), 'différents')

    def test_an_expression_covers_the_whole_line(self):
        self.assertEqual(self.verdict([self.RULE], 'page a', [], ['T 3 points de plus']), 'différents')

    def test_a_rule_only_for_its_cases(self):
        self.assertEqual(self.verdict([self.RULE], 'autre page', ['T ancien'], ['T nouveau']), 'différents')
        self.assertEqual(self.verdict([self.RULE], 'une page a', ['T ancien'], ['T nouveau']), 'différents')

    def test_a_mask_admits_one_field_only(self):
        rule = self.rule(masks=((r'^(\{"id": 8, .*"a": )\d+', r'\1<m>'),))
        admitted = ['{"id": 8, "a": 1, "b": 2}'], ['{"id": 8, "a": 5, "b": 2}']
        self.assertEqual(self.verdict([rule], 'page a', *admitted, exact=True), 'écarts admis')
        for name, old, new in [('un autre champ', '{"id": 8, "a": 1, "b": 2}', '{"id": 8, "a": 5, "b": 3}'),
                               ('une autre ligne', '{"id": 9, "a": 1, "b": 2}', '{"id": 9, "a": 5, "b": 2}')]:
            with self.subTest(name):
                self.assertEqual(self.verdict([rule], 'page a', [old], [new], exact=True), 'différents')

    def test_what_must_be_there(self):
        rule = compare.expected.replaced('essai', r'page .*', 'essai', 301, '+redirection /a')
        self.assertEqual(self.verdict([rule], 'page a', ['statut 200', 'T x'], ['statut 301', 'redirection /a']), 'écarts admis')
        # La page a perdu une ligne, sans être remplacée : ce n'est pas l'écart admis
        self.assertEqual(self.verdict([rule], 'page a', ['statut 200', 'T x'], ['statut 200']), 'différents')
        self.assertEqual(self.verdict([rule], 'page a', ['statut 200'], ['statut 500']), 'différents')

    def test_another_order_needs_a_named_list(self):
        old, new = ['T a', 'T b'], ['T b', 'T a']
        self.assertEqual(self.verdict([], 'page a', old, new), 'différents')
        self.assertEqual(self.verdict([self.RULE], 'page a', old, new), 'différents')
        self.assertEqual(self.verdict([self.rule(order=True)], 'page a', old, new), 'même contenu, autre ordre')

    def test_a_named_list_admits_the_order_only(self):
        self.assertEqual(self.verdict([self.rule(order=True)], 'page a', ['T a'], ['T b']), 'différents')

    def test_a_report_fails_on_what_is_not_admitted(self):
        with tempfile.TemporaryDirectory() as out:
            compare.OUT = Path(out)
            compare.Report.failed = False
            report = compare.Report('essai')
            report.rules = [self.RULE]
            with redirect_stdout(io.StringIO()):
                report.add('page a', ['T ancien'], ['T nouveau'])
                report.close()
            self.assertEqual(report.counts['écarts admis'], 1)
            self.assertFalse(compare.Report.failed)
            # Un écart admis garde son fichier : il se relit, et mutants.py en lit l'empreinte
            self.assertEqual(len(list(report.dir.glob('*.diff'))), 1)
            with redirect_stdout(io.StringIO()):
                report.add('page b', ['T x'], ['T y'])
                report.close()
            self.assertTrue(compare.Report.failed)
            compare.Report.failed = False

    def test_a_report_names_the_rules_that_served_nothing(self):
        with tempfile.TemporaryDirectory() as out:
            compare.OUT = Path(out)
            report = compare.Report('essai')
            report.rules = [self.RULE]
            with redirect_stdout(io.StringIO()):
                report.close()
            self.assertIn('Règle sans écart', (report.dir / 'rapport.txt').read_text())

    def test_each_rule_says_why_in_the_differences(self):
        text = (Path(__file__).resolve().parent.parent / 'differences.md').read_text()
        for rule in compare.expected.RULES:
            with self.subTest(rule.case):
                self.assertIn(f'**{rule.why}', text)

    def test_each_rule_reads(self):
        for rule in compare.expected.RULES:
            with self.subTest(rule.case):
                re.compile(rule.case)
                for pattern, _ in rule.masks:
                    re.compile(pattern)
                self.assertTrue(rule.lines or rule.masks or rule.order)

    def test_a_faulty_export_is_not_admitted(self):
        # Une valeur fausse à côté de l'écart admis des licences : le mode échoue
        rules = [r for r in compare.expected.RULES if r.mode == 'exports']
        old = ['code de sortie 0', '# Ignoring ConditionsGeneralesUtilisation.pdf', '01/01/2024;1.500;v']
        licence = ['# Ignoring Licence-Ouverte-2.0-fr.pdf', '# Ignoring Open-Licence-2.0-en.pdf']
        name = 'bdoh:export:identical x'
        self.assertEqual(self.verdict(rules, name, old, ['code de sortie 0', *licence, '01/01/2024;1.500;v']), 'écarts admis')
        self.assertEqual(self.verdict(rules, name, old, ['code de sortie 0', *licence, '01/01/2024;2.500;v']), 'différents')
        # Une valeur arrondie autrement, loin de l'écart admis des licences : l'arrondi ne se cache plus derrière lui.
        # La virgule de l'ancien (l'en-tête Qtvar d'une station), elle, n'est qu'une écriture.
        head = ['code de sortie 0', '# Ignoring ConditionsGeneralesUtilisation.pdf', 'Station X', '950;X;Y;3,34;;;;']
        new_head = ['code de sortie 0', *licence, 'Station X', '950;X;Y;3.34;;;;']
        self.assertEqual(self.verdict(rules, name, [*head, '01/01/2024;1.500;v'], [*new_head, '01/01/2024;1.500;v']), 'écarts admis')
        self.assertEqual(self.verdict(rules, name, [*head, '01/01/2024;1.500;v'], [*new_head, '01/01/2024;1.501;v']), 'différents')
        # La licence perdue : pas davantage
        self.assertEqual(self.verdict(rules, name, old, ['code de sortie 0', '01/01/2024;1.500;v']), 'différents')

    def test_a_faulty_import_on_another_chronique_is_not_admitted(self):
        rules = [r for r in compare.expected.RULES if r.mode == 'imports']
        self.assertEqual(self.verdict(rules, 'état mesure', ['mesure\t9\t10\t111'], ['mesure\t9\t20\t222'], exact=True), 'écarts admis')
        self.assertEqual(self.verdict(rules, 'état mesure', ['mesure\t10\t10\t111'], ['mesure\t10\t20\t222'], exact=True), 'différents')

    def test_the_points_of_a_chronique_show_nothing_else(self):
        rules = [r for r in compare.expected.RULES if r.mode == 'pages']
        name = 'anonyme /DRAIX/LAVAL-AMONT/HT'
        self.assertEqual(self.verdict(rules, name, ['T a'], ['T a', 'T Points de contrôle (?) : Aucun']), 'écarts admis')
        self.assertEqual(self.verdict(rules, name, ['T a'], ['T a', 'T Points de contrôle (?) : Aucun', 'T 3.5']), 'différents')
        self.assertEqual(self.verdict(rules, 'gestionnaire /DRAIX/LAVAL-AMONT/HT', ['T a'], ['T a', 'T Points de contrôle (?) : Aucun']), 'différents')


class Fingerprints(unittest.TestCase):
    """L'empreinte de dev/reference/fingerprint.sql : le fichier est bien lu, et sûr pour les NULL."""

    def test_every_table_of_measures_is_read(self):
        self.assertEqual(sorted(compare.FINGERPRINTS), ['mesure', 'plage', 'pointcontrole', 'tauxremplissage'])
        for table in compare.FINGERPRINTS:
            self.assertIn(table, compare.STATE_QUERIES)

    def test_null_keeps_its_place(self):
        # concat_ws saute les NULL : un minimum et un maximum échangés s'y confondent
        for sql in compare.FINGERPRINTS.values():
            self.assertNotIn('concat_ws', sql)
            self.assertIn('format(', sql)
            self.assertIn('float8send', sql)


if __name__ == '__main__':
    unittest.main()
