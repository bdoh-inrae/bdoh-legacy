"""Les fonctions de compare.py, sans base ni instance : éprouve-t-il ce qu'il prétend voir ?

    python3 -m unittest discover -s dev/compare

Chaque cas part d'une erreur qu'un portage peut faire, et vérifie qu'elle n'est pas prise pour un
arrondi ni effacée par une normalisation. Les erreurs sur les données elles-mêmes se jouent sur une
copie de base : compare.py selftest.
"""

import importlib.util
import sys
import unittest
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
