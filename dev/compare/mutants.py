#!/usr/bin/env python3
"""Remet des fautes dans le nouveau BDOH, une à la fois : la comparaison les voit-elle ?

    dev/compare/mutants.py [--base bdoh_small] [--only TEXTE ...] [--list]

Chaque faute est une erreur plausible d'un portage sur ce qui écrit ou sort des données : un piège de
dev/decisions.md, ou une ligne qui se recopie mal. Le lanceur modifie le fichier, ou une fonction SQL de la
copie du nouveau, joue le mode qui devrait la voir, compare son rapport à celui de la même passe sans faute,
puis remet tout en place. Une faute non vue n'est pas un échec à masquer : elle dit qu'il manque un cas.

Le verdict lit les fichiers d'écarts sans les montrer : un cas de plus en écart, ou un écart qui change. Un
mode qui s'arrête en route ne prouve rien tant qu'on n'a pas lu pourquoi (un Apache de l'ancien tombé a déjà
passé pour une faute vue) : il ne compte pas parmi les fautes vues, et le rapport donne sa dernière ligne d'erreur.

Un seul lancement à la fois, sur un dépôt propre : il travaille sur les fichiers de src/. Le rapport, qui
ne contient que le nom des fautes et des comptes, va dans ../bdoh_reference/compare/.
"""

import argparse
import datetime
import hashlib
import re
import subprocess
import sys
import tempfile
import time
from pathlib import Path

sys.dont_write_bytecode = True
import compare  # noqa: E402  (dev/compare/compare.py)

ROOT = Path(__file__).resolve().parent.parent.parent
SRC = 'src/Irstea/'
IMPORTER = SRC + 'BdohAdminBundle/Importer/Measure/Importer.php'
CONVERT = SRC + 'BdohDataBundle/Entity/Repository/ChroniqueConvertieRepository.php'
MEASURES = SRC + 'BdohDataBundle/Entity/Repository/MesureRepository.php'
EXTRACTOR = SRC + 'BdohDataBundle/Exporter/Measure/Extractor/AbstractExtractor.php'
RUNNER = SRC + 'BdohBundle/Job/RunJobsCommand.php'
STATIONS = SRC + 'BdohDataBundle/Entity/Repository/StationRepository.php'

# (nom, mode, --only, fichier, texte, remplacement) ; le texte doit se trouver, et tous ses exemplaires changent
PHP = [
    ('import : minimum et maximum échangés', 'imports', 'minimum au-dessus', IMPORTER,
     "            is_numeric($minimum) ? $minimum : '\\\\N',\n            is_numeric($maximum) ? $maximum : '\\\\N',\n        ];\n        fputcsv($this->csv, $fields, ';', '\"', '\\\\');\n    }\n\n    /**\n     * Process of a 'Plage'",
     "            is_numeric($maximum) ? $maximum : '\\\\N',\n            is_numeric($minimum) ? $minimum : '\\\\N',\n        ];\n        fputcsv($this->csv, $fields, ';', '\"', '\\\\');\n    }\n\n    /**\n     * Process of a 'Plage'"),
    ("import : décalage horaire du fichier oublié", 'imports', 'fuseau UTC-05', IMPORTER,
     '$measureRepo->adjustDates($tmpTz, $this->tmpSqlTable);', '// $measureRepo->adjustDates($tmpTz, $this->tmpSqlTable);'),
    ('import, écraser : la dernière date gardée', 'imports', 'écraser', MEASURES,
     "\"DELETE FROM mesure WHERE (date >= '$firstDate') AND (date <= '$lastDate') \"",
     "\"DELETE FROM mesure WHERE (date >= '$firstDate') AND (date < '$lastDate') \""),
    ('import, garder : les mesures existantes écrasées', 'imports', 'garder', MEASURES,
     """$sqlSelect .= " AND ((date < '$firstExistingDate') OR (date > '$lastExistingDate'))";""", '// sans filtre'),
    ('import de plages : valeur changée', 'imports', 'plages', SRC + 'BdohAdminBundle/Importer/Measure/BdohPlageImporter.php',
     '$this->processPlage($iChronique, $debut, $fin, $value, $quality, $min, $max, $numLine);',
     '$this->processPlage($iChronique, $debut, $fin, is_numeric($value) ? $value + 1 : $value, $quality, $min, $max, $numLine);'),
    ('points de contrôle : minimum et maximum échangés', 'imports', 'contrôle', SRC + 'BdohAdminBundle/Importer/Controle/Manager.php',
     "            is_numeric($minimum) ? $minimum : '\\\\N',\n            is_numeric($maximum) ? $maximum : '\\\\N',\n        ];",
     "            is_numeric($maximum) ? $maximum : '\\\\N',\n            is_numeric($minimum) ? $minimum : '\\\\N',\n        ];"),
    ('import QJO : trou compté par ->d (piège de PHP)', 'imports', 'QJO', SRC + 'BdohAdminBundle/Importer/Measure/QjoImporter.php',
     '$days = $interval->invert ? 0 : $interval->days;', '$days = $interval->invert ? 0 : $interval->d;'),
    ('shapefile : latitude et longitude échangées', 'imports', 'shapefile', STATIONS,
     'latitude=ST_X(ST_GeometryN(t.geom, 1))', 'latitude=ST_Y(ST_GeometryN(t.geom, 1))'),
    ('calcul en échec : la chronique vidée (ancien comportement)', 'state', '', SRC + 'BdohDataBundle/Command/ComputeChroniqueCommand.php',
     "throw new \\RuntimeException(sprintf('Le calcul de la chronique", "if (false) throw new \\RuntimeException(sprintf('Le calcul de la chronique"),
    ('calcul : pas de propagation aux filles', 'state', '', SRC + 'BdohDataBundle/EventListener/ChronicleListener.php',
     '$this->jobManager->enqueueChronicleComputation($transfo->getSortie(), $event->getUser(), true);',
     '$this->jobManager->enqueueChronicleComputation($transfo->getSortie(), $event->getUser(), false);'),
    ('calcul : taux de remplissage non demandés', 'state', '', SRC + 'BdohDataBundle/EventListener/FillingRateListener.php',
     'if ($chronique->isContinue()) {', 'if (false) {'),
    ('calcul : dates des mesures non recalculées', 'state', '', SRC + 'BdohDataBundle/Entity/Repository/ChroniqueCalculeeRepository.php',
     '$this->updateDatesMesuresByChronique($chroniqueId);', '// sans dates'),
    ('conversion : nombres lus en PHP, 14 chiffres (piège)', 'state', '', CONVERT,
     'valeur::text, minimum::text, maximum::text, qualite_id', 'valeur, minimum, maximum, qualite_id'),
    ("conversion : trou au seuil exact de l'intervalle", 'state', '', CONVERT,
     'if ($last_fin_plus_intervalle_date < $debut_date) {', 'if ($last_fin_plus_intervalle_date <= $debut_date) {'),
    ('conversion : plages contiguës non séparées', 'state', '', CONVERT,
     'if ($last_fin === $debut) {', 'if ($last_fin !== $debut) {'),
    ('vider une période : pas de propagation', 'state', '', SRC + 'BdohConsultBundle/Controller/ChroniqueController.php',
     "$propagate = $request->request->get('propagate', false) === 'propagate';\n", "$propagate = false;\n"),
    ('file : une tâche en échec notée finie', 'state', '', RUNNER,
     '$process->getExitCode() === 0 ? Job::STATE_FINISHED : Job::STATE_FAILED', 'Job::STATE_FINISHED'),
    ('file : deux tâches à la fois dans une file', 'queue', '', RUNNER,
     '$job = $repo->findStartableJob($busyQueues);', '$job = $repo->findStartableJob([]);'),
    ('export : arrondi sans les 15 chiffres (piège de PHP 8.4)', 'exports', '', EXTRACTOR,
     "number_format((float) sprintf('%.15h', $value), $this->precision", 'number_format((float) $value, $this->precision'),
    ('export : quatre décimales', 'exports', '', EXTRACTOR, "$parameters['precision'] ?? 3", "$parameters['precision'] ?? 4"),
    ("export : fuseau de la demande ignoré", 'exports', '', SRC + 'BdohDataBundle/Command/AbstractExportCommand.php',
     """return new \\DateTimeZone(str_replace(':', '', trim($value, "'")));""", "return new \\DateTimeZone('UTC');"),
]

# (nom, mode, fonction SQL, texte, remplacement) : une fonction changée dans la copie du nouveau seul, comme
# le ferait une migration fautive. Les calculs ne sont pas tous joués : c'est ce que ces fautes mesurent.
SQL = [
    ('SQL : facteur multiplicatif oublié', 'state', 'bdoh_insert_mesures_two_transformations',
     'valeurMainInsert[1] * valeurSecondInsert[1] * coefficient', 'valeurMainInsert[1] * valeurSecondInsert[1]'),
    ('SQL : début de validité exclu', 'state', 'bdoh_transformed_mesures', 'm.date >= bjb.debutValidite', 'm.date > bjb.debutValidite'),
    ('SQL : lacune de barème rendue autrement', 'state', 'bdoh_transformed_mesures', 'ARRAY[-9999, 100]', 'ARRAY[-9999, 200]'),
    ('SQL : délai de propagation en secondes', 'state', 'bdoh_insert_mesures_two_transformations',
     "date + delayMain * INTERVAL '1 MINUTE'", "date + delayMain * INTERVAL '1 SECOND'"),
    ('SQL : limites à moitié de valeur fausses', 'state', 'bdoh_transformed_mesures', 'coeffLimitsMult := 0.5', 'coeffLimitsMult := 0.25'),
    ('SQL : limites en lacune ignorées', 'state', 'bdoh_transformed_mesures', 'turnLimitsToGap := true', 'turnLimitsToGap := false'),
    ('SQL : borne haute du barème incluse à tort', 'state', 'bdoh_interp_for_bareme',
     'entree_adaptee > maxBareme THEN', 'entree_adaptee >= maxBareme THEN'),
    ('SQL : qualité croisée prise au maximum', 'state', 'bdoh_cross_quality_orders', 'ELSIF ordre1 < ordre2 THEN', 'ELSIF ordre1 > ordre2 THEN'),
]


def sql_fault(function, old, new):
    """Un bloc qui change le texte d'une fonction, et échoue si le texte n'y est pas."""
    return f"""DO $$ DECLARE r record; done boolean := false; src text; BEGIN
        FOR r IN SELECT p.oid FROM pg_proc p JOIN pg_namespace n ON n.oid = p.pronamespace
                  WHERE n.nspname = 'bdoh' AND p.proname = {literal(function)} AND p.prokind = 'f' LOOP
            src := pg_get_functiondef(r.oid);
            IF position({literal(old)} in src) > 0 THEN
                EXECUTE replace(src, {literal(old)}, {literal(new)});
                done := true;
            END IF;
        END LOOP;
        IF NOT done THEN RAISE EXCEPTION 'texte introuvable : %', {literal(old)}; END IF;
    END $$;"""


def literal(text):
    return "'" + text.replace("'", "''") + "'"


def play(mode, only, base, inject=None):
    """Le mode, sa sortie dans un journal qu'on ne lit pas : ses comptes, l'empreinte de chaque fichier
    d'écarts (sans le lire ici pour le montrer), et sa durée. Sans rapport écrit, le mode s'est arrêté :
    les comptes valent alors None, et les écarts laissent place à sa dernière ligne d'erreur."""
    command = [sys.executable, '-B', str(ROOT / 'dev/compare/compare.py'), mode, '--base', base]
    if mode in ('pages', 'exports'):
        command += ['--old', 'reference', '--new', 'new']
    if only:
        command += ['--only', only]
    if inject:
        command += ['--inject-sql', inject]
    before = set(compare.OUT.glob(f'*_{mode}'))
    start = time.monotonic()
    done = subprocess.run(command, capture_output=True, text=True)
    seconds = time.monotonic() - start
    written = sorted(path for path in set(compare.OUT.glob(f'*_{mode}')) - before if (path / 'rapport.txt').exists())
    if not written:
        return None, (done.stderr.strip().splitlines() or ['sans message'])[-1][:200], seconds
    text = (written[-1] / 'rapport.txt').read_text()
    counts = {key: int(n) for n, key in re.findall(r'^\s*(\d+) (.+)$', text, re.M)
              if key in ('identiques', 'à un arrondi près', 'même contenu, autre ordre', 'différents')}
    gaps = {path.name: hashlib.sha1(path.read_bytes()).hexdigest() for path in written[-1].glob('*.diff')}
    return counts, gaps, seconds


def main():
    parser = argparse.ArgumentParser(description=__doc__.split('\n')[0])
    parser.add_argument('--base', default='bdoh_small')
    parser.add_argument('--only', nargs='*', default=[], help='les fautes dont le nom contient l\'un de ces textes')
    parser.add_argument('--list', action='store_true')
    args = parser.parse_args()
    faults = [f for f in [*PHP, *SQL] if not args.only or any(text in f[0] for text in args.only)]
    if args.list:
        print('\n'.join(f[0] for f in faults))
        return 0

    # Chaque texte se vérifie d'abord : une faute périmée ne doit pas passer pour non vue
    for name, mode, only, file, old, new in [f for f in faults if len(f) == 6]:
        if old not in (ROOT / file).read_text():
            sys.exit(f'Texte introuvable pour « {name} » dans {file}')
    for name, mode, function, old, new in [f for f in faults if len(f) == 5]:
        found = compare.query(args.base, f"""SELECT count(*) FROM pg_proc p JOIN pg_namespace n ON n.oid = p.pronamespace
            WHERE n.nspname = 'bdoh' AND p.proname = {literal(function)} AND p.prokind = 'f'
              AND position({literal(old)} in pg_get_functiondef(p.oid)) > 0""", compare.NEW_SERVER)
        if found[0][0] == '0':
            sys.exit(f'Texte introuvable pour « {name} » dans la fonction {function}')
    if subprocess.run(['git', '-C', str(ROOT), 'status', '--porcelain', '--', 'src'], capture_output=True, text=True).stdout:
        sys.exit('Des fichiers de src/ sont modifiés : ce lanceur a besoin de pouvoir les rétablir.')

    out = compare.OUT / f"{datetime.datetime.now():%Y-%m-%d_%H%M%S}_mutants"
    out.mkdir(parents=True)
    lines, baselines = [*compare.provenance(), ''], {}

    def baseline(mode, only):
        """La passe sans faute d'un mode : ce à quoi se comparent ses fautes, une seule fois chacune."""
        if (mode, only) not in baselines:
            baselines[mode, only] = play(mode, only, args.base)
            if baselines[mode, only][0] is None or not sum(baselines[mode, only][0].values()):
                sys.exit(f'Sans faute, {mode} {only!r} ne joue aucun cas, ou ne va pas au bout : à revoir.')
        return baselines[mode, only]

    def verdict(name, mode, only, inject=None):
        reference, reference_gaps, _ = baseline(mode, only)
        counts, gaps, seconds = play(mode, only, args.base, inject)
        if counts is None:
            line = f'ARRÊT   | {name} | {mode} | à lire : {gaps} | {seconds:.0f} s'
        else:
            appeared = len(gaps.keys() - reference_gaps.keys())
            changed = sum(gaps[key] != reference_gaps[key] for key in gaps.keys() & reference_gaps.keys())
            state = 'vue    ' if appeared or changed else 'NON VUE'
            line = (f"{state} | {name} | {mode} | {appeared} cas en écart de plus, {changed} écarts changés,"
                    f" {reference.get('différents', 0)} → {counts.get('différents', 0)} différents | {seconds:.0f} s")
        lines.append(line)
        print(line, flush=True)
        (out / 'rapport.txt').write_text('\n'.join(lines) + '\n')

    for fault in faults:
        if len(fault) == 6:
            name, mode, only, file, old, new = fault
            baseline(mode, only)  # avant la faute : la passe de référence ne doit pas la contenir
            path = ROOT / file
            original = path.read_text()
            try:
                path.write_text(original.replace(old, new))
                time.sleep(3)  # le cache d'opcodes des pages relit les fichiers toutes les 2 s
                verdict(name, mode, only)
            finally:
                path.write_text(original)
                time.sleep(3)
        else:
            name, mode, function, old, new = fault
            baseline(mode, '')
            with tempfile.NamedTemporaryFile('w', suffix='.sql', delete=False) as sql:
                sql.write(sql_fault(function, old, new))
            verdict(name, mode, '', sql.name)
            Path(sql.name).unlink()
    seen = sum(line.startswith('vue') for line in lines)
    lines.append(f'{seen} fautes vues sur {len(faults)}')
    (out / 'rapport.txt').write_text('\n'.join(lines) + '\n')
    print(lines[-1])
    return 0


if __name__ == '__main__':
    sys.exit(main())
