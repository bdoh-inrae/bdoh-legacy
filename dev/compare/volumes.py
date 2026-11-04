#!/usr/bin/env python3
"""Temps et mémoire des plus grosses opérations, de l'ancien et du nouveau BDOH.

    dev/compare/volumes.py [--base bdoh] [--small bdoh_small]

Sur les plus grosses chroniques visibles : les exports en ligne de commande, le calcul et les taux de
remplissage dans une transaction annulée (les fonctions de PostgreSQL 11 contre celles de PostgreSQL 17), les
pages les plus lourdes d'un administrateur, et un gros import, en archive zip, sur des copies de la base réduite.
Les instances sont branchées sur la base complète le temps de la mesure, puis sur la base réduite.

Une seule passe, rien d'autre sur la machine : ce sont des ordres de grandeur. Ne sortent que des durées, des
statuts, des tailles et des pics de mémoire (VmHWM : ce que le processus a réellement occupé, tampons de
PostgreSQL compris), jamais une valeur. Le rapport va dans ../bdoh_reference/compare/.
"""

import argparse
import datetime
import io
import re
import subprocess
import sys
import time
import zipfile
from pathlib import Path

sys.dont_write_bytecode = True
import compare  # noqa: E402  (dev/compare/compare.py)

HERE = Path(__file__).resolve().parent
PEAK_FILE = '/tmp/bdoh_peak.php'
PEAK_CODE = r"""<?php
register_shutdown_function(function () {
    if (preg_match('/VmHWM:\s+(\d+)/', (string) @file_get_contents('/proc/self/status'), $m)) {
        fwrite(STDERR, "\nPIC {$m[1]}\n");
    }
});
"""
PERIOD = ['1800-01-01T00:00:00+00:00', '2030-01-01T00:00:00+00:00']
OPTIONS = ['--format', 'bdoh', '--timezone', "'+00:00'"]
SERVERS = {'ancien': compare.CASES_SERVER, 'nouveau': compare.NEW_SERVER}
OLD, NEW = compare.SIDES['reference'], compare.SIDES['new']


def container(side):
    return side.console[side.console.index('exec') + 1]


def timed_console(side, command):
    """Une commande de la console, sa sortie jetée : (durée, pic en Mo, code)."""
    php = side.console.index('php') + 1
    argv = [*side.console[:php], '-d', f'auto_prepend_file={PEAK_FILE}', *side.console[php:], *command, '--env=prod']
    start = time.monotonic()
    r = subprocess.run(argv, stdout=subprocess.DEVNULL, stderr=subprocess.PIPE, text=True, errors='replace')
    seconds = time.monotonic() - start
    found = re.findall(r'^PIC (\d+)$', r.stderr, re.M)
    return seconds, int(found[-1]) // 1024 if found else None, r.returncode


def timed_sql(server, base, sql):
    """Une requête dans une transaction annulée : (durée, code rendu par la fonction)."""
    start = time.monotonic()
    r = subprocess.run(['docker', 'exec', '-i', server, 'psql', '-U', 'bdoh', '-d', base, '-At', '-v', 'ON_ERROR_STOP=1'],
                       input=f'BEGIN;\n{sql};\nROLLBACK;\n', capture_output=True, text=True)
    seconds = time.monotonic() - start
    lines = [line for line in r.stdout.splitlines() if line not in ('BEGIN', 'ROLLBACK')]
    return seconds, (lines[0] if r.returncode == 0 and lines else f'erreur {r.returncode}')


def timed_page(client, path):
    """Une page : (durée, statut, taille en Mo)."""
    start = time.monotonic()
    status, _, body = client.get(path)
    return time.monotonic() - start, status, len(body) / 1e6


def import_case(base, cid):
    """Toutes les mesures d'une chronique dans la base réduite, à écraser par elles-mêmes, en zip : la limite
    de 2 Mo par fichier d'Apache ne laisse passer un gros fichier que comme ça."""
    slug, station, code, unite = compare.query(base, f"""
        SELECT c.slug, c.station, c.code, u.libelle FROM ({compare.CHRONIQUES}) c
          JOIN chronique ch ON ch.id = c.id JOIN unite u ON u.id = ch.unite_id WHERE c.id = {cid}""", compare.NEW_SERVER)[0]
    rows = compare.query(base, f"""
        SELECT to_char(m.date, 'DD/MM/YYYY HH24:MI:SS'), coalesce(m.valeur::text, '-9999'), q.code
          FROM mesure m JOIN qualite q ON q.id = m.qualite_id WHERE m.chronique_id = {cid} ORDER BY m.date""", compare.NEW_SERVER)
    text = '\n'.join(['Station;Fuseau;Chronique;Unite', f'{station};UTC+00;{code};{unite}', 'DateHeure;Valeur;Qualite',
                      *[';'.join(r) for r in rows]]) + '\n'
    archive = io.BytesIO()
    with zipfile.ZipFile(archive, 'w', zipfile.ZIP_DEFLATED) as z:
        z.writestr('import.txt', text)
    return {'name': 'gros import', 'format': 'Bdoh', 'content': archive.getvalue(), 'filename': 'import.zip',
            'type': 'overwriteExisting', 'cid': cid, 'slug': slug}, len(rows)


def main():
    parser = argparse.ArgumentParser(description=__doc__.split('\n')[0])
    parser.add_argument('--base', default='bdoh')
    parser.add_argument('--small', default='bdoh_small')
    args = parser.parse_args()

    out = compare.OUT / f"{datetime.datetime.now():%Y-%m-%d_%H%M%S}_volumes"
    out.mkdir(parents=True)
    report = [f'Base : {args.base}', *compare.provenance(), '']

    def say(line):
        report.append(line)
        print(line, flush=True)
        (out / 'rapport.txt').write_text('\n'.join(report) + '\n')

    use_base = lambda base: subprocess.run([str(HERE / 'use-base.sh'), base], check=True, capture_output=True)
    biggest = lambda base, dtype: compare.query(base, f"""
        SELECT c.id, c.nbmesures FROM chronique c
         WHERE c.estvisible AND c.dtype = '{dtype}' AND c.nbmesures > 0 ORDER BY c.nbmesures DESC LIMIT 1""", compare.NEW_SERVER)[0]

    for side in (OLD, NEW):
        subprocess.run(['docker', 'exec', '-i', container(side), 'sh', '-c', f'cat > {PEAK_FILE}'],
                       input=PEAK_CODE, text=True, check=True)
    use_base(args.base)
    try:
        continuous, measures = biggest(args.base, 'continue')
        calculated, calculated_measures = biggest(args.base, 'calculee')
        say(f'Chronique continue de {measures} mesures, calculée de {calculated_measures} mesures')

        say('')
        say(f"{'Export':<34}{'ancien':>18}{'nouveau':>18}")
        exports = [('à l\'identique', ['bdoh:export:identical', *PERIOD, continuous, *OPTIONS]),
                   ('instantané, 1 heure', ['bdoh:export:instantaneous', '60', *PERIOD, continuous, *OPTIONS]),
                   ('moyenne, 1 heure', ['bdoh:export:mean', '5', *PERIOD, continuous, *OPTIONS]),
                   ('cumul, 1 jour', ['bdoh:export:cumulative', '7', *PERIOD, continuous, *OPTIONS])]
        for name, command in exports:
            cells = []
            for side in (OLD, NEW):
                seconds, peak, code = timed_console(side, command)
                cells.append(f'{seconds:6.0f} s {peak or 0:5} Mo' + (f' code {code}' if code else ''))
            say(f'{name:<34}{cells[0]:>18}{cells[1]:>18}')

        say('')
        say(f"{'Calcul, transaction annulée':<34}{'PostgreSQL 11':>18}{'PostgreSQL 17':>18}")
        for name, sql in [('calcul', f'SELECT bdoh_compute_chronique({calculated})'),
                          ('taux de remplissage', f"SELECT bdoh_update_filling_rates({continuous}, '-infinity', 'infinity')")]:
            cells = []
            for who in ('ancien', 'nouveau'):
                seconds, code = timed_sql(SERVERS[who], args.base, sql)
                cells.append(f'{seconds:6.0f} s' + ('' if code in ('0', '') else f' ({code})'))
            say(f'{name:<34}{cells[0]:>18}{cells[1]:>18}')

        slug = compare.query(args.base, f"""SELECT slug FROM ({compare.CHRONIQUES}) c WHERE c.id = {continuous}""", compare.NEW_SERVER)[0][0]
        email = compare.query(args.base, "SELECT email FROM utilisateur WHERE roleadmin = 'FCT' ORDER BY id LIMIT 1", compare.NEW_SERVER)[0][0]
        clients = [compare.Client(side, email, slug) for side in (OLD, NEW)]
        pages = [('nos données', f'/{slug}/nos-donnees'), ('visualiseur, liste', f'/{slug}/viewer/load?start=2024-03-01T00:00:00Z&end=2024-04-01T00:00:00Z'),
                 ('mesures, toute la période', f'/{slug}/chronique/{continuous}/measures?beginDate=1800-01-01T00:00:00Z&endDate=2030-01-01T00:00:00Z'),
                 ('historique', f'/{slug}/historique'), ('historique récent', f'/{slug}/historique/recent')]
        say('')
        say(f"{'Page, administrateur':<34}{'ancien':>24}{'nouveau':>24}")
        for name, path in pages:
            cells = [f'{seconds:6.1f} s {status} {size:6.1f} Mo' for seconds, status, size in (timed_page(c, path) for c in clients)]
            say(f'{name:<34}{cells[0]:>24}{cells[1]:>24}')
    finally:
        use_base(args.small)

    # Les imports écrivent : sur des copies de la base réduite, l'ancien code et le nouveau sous PostgreSQL 17
    cid = compare.query(args.small, """SELECT c.id FROM chronique c WHERE c.estvisible AND c.dtype = 'continue'
                                        ORDER BY c.nbmesures DESC NULLS LAST LIMIT 1""", compare.NEW_SERVER)[0][0]
    case, rows = import_case(args.small, cid)
    say('')
    say(f"Import d'une archive de {len(case['content']) / 1e6:.1f} Mo, {rows} lignes, écrasant la période")
    manager = compare.query(args.small, f"""
        SELECT u.email FROM utilisateur u JOIN role r ON r.utilisateur_id = u.id
          JOIN observatoire o ON o.id = r.observatoire_id
         WHERE r.valeur = 'gestionnaire' AND o.slug = '{case['slug']}' AND (u.finacces IS NULL OR u.finacces > now())
         ORDER BY u.id LIMIT 1""", compare.NEW_SERVER)[0][0]
    bases = {'ancien': 'bdoh_volumes_old', 'nouveau': 'bdoh_volumes_new'}
    for who, base in bases.items():
        compare.copy_base(args.small, base)
        context = (compare.old_17_on(base) if who == 'ancien' else compare.new_on(base, args.small))
        side = compare.SIDES['reference-17'] if who == 'ancien' else NEW
        with context:
            client = compare.Client(side, manager, case['slug'])
            start = time.monotonic()
            log = compare.import_steps(client, side.url, case)
            seconds = time.monotonic() - start
        statuses = ' '.join(line.split()[1] for line in log if line.startswith('statut '))
        say(f"{who:<34}{seconds:6.0f} s, statuts {statuses}")
    print(f'Rapport : {out}')


if __name__ == '__main__':
    sys.exit(main())
