#!/usr/bin/env python3
"""Recalcule tout, et compare à ce que l'ancien BDOH avait écrit au fil des années.

    dev/compare/recompute.py [--base bdoh] [--only-calculs] [--only-taux] [--jobs 4]

Sur une copie de BASE (nouveau serveur) et par les vraies commandes du nouveau BDOH : chaque
chronique calculée ou convertie, les plus profondes d'abord et sans propagation, si bien que chacune se
calcule depuis ses mères telles qu'elles sont en base, et qu'un écart est bien le sien ; puis les taux de
remplissage de toutes. Compare enfin la copie à BASE par l'empreinte au bit près.

Une chronique identique n'est prouvée que si elle a vraiment été recalculée : un calcul en échec
(code 32) laisse les mesures en place. Chaque code est donc compté, et les identifiants des mesures
doivent avoir changé. Une chronique qui diffère est recalculée par les fonctions de PostgreSQL 11, dans
une transaction annulée, pour séparer l'histoire de la chronique (l'ancien y donne comme le nouveau) d'un
effet du nouveau serveur ou du portage (dev/audits/2026-11-03_validation-plan.md, constat 3).

Les chroniques cachées ne sortent qu'en comptes, y compris dans les fichiers : l'outil a tourné sur leurs
vraies valeurs, et pourra y retourner sur la VM. Le rapport va dans ../bdoh_reference/compare/, hors du
dépôt : rapport.txt pour tout, visibles.txt pour le détail des chroniques visibles.
"""

import argparse
import collections
import concurrent.futures
import datetime
import subprocess
import sys
import time

sys.dont_write_bytecode = True
import compare  # noqa: E402  (dev/compare/compare.py)

SERVER = compare.NEW_SERVER
COPY = 'bdoh_recompute'


def query(base, sql, server=SERVER):
    return compare.query(base, sql, server)


def console(*command):
    """Une commande du nouveau BDOH, sur la copie : (code de sortie, durée en secondes)."""
    start = time.monotonic()
    r = subprocess.run(['docker', 'exec', '-e', f'DATABASE_URL={compare.NEW_URL.format(COPY)}', 'bdoh-app',
                        'php', 'bin/console', *command, '--no-interaction', '--env=prod'], capture_output=True)
    return r.returncode, time.monotonic() - start


def plan(base):
    """Les chroniques à recalculer, de la plus profonde à la moins profonde : (profondeur, id, sorte, visible)."""
    return [(int(depth), int(cid), dtype, visible == 't') for depth, cid, dtype, visible in query(base, """
        WITH RECURSIVE arete AS (
            SELECT entree_id AS mere, sortie_id AS fille FROM transformation
            UNION SELECT entree_id, sortie_id FROM conversion),
        profondeur AS (
            SELECT c.id, 0 AS n FROM chronique c WHERE c.dtype IN ('calculee', 'convertie')
            UNION ALL SELECT a.fille, p.n + 1 FROM profondeur p JOIN arete a ON a.mere = p.id WHERE p.n < 12)
        SELECT max(p.n), c.id, c.dtype, c.estvisible FROM profondeur p JOIN chronique c ON c.id = p.id
         GROUP BY c.id, c.dtype, c.estvisible ORDER BY 1 DESC, 2""")]


def run_level(items, user, jobs, results):
    """Une profondeur de calculs, en parallèle : ces chroniques ne dépendent pas les unes des autres."""
    def one(item):
        depth, cid, dtype, _ = item
        command = 'bdoh:compute:chronique' if dtype == 'calculee' else 'bdoh:convert:chronique'
        return item, *console(command, str(cid), user)

    with concurrent.futures.ThreadPoolExecutor(jobs) as pool:
        for item, code, seconds in pool.map(one, items):
            results[item[1]] = {'item': item, 'code': code, 'secondes': seconds}


def fingerprint_of(base, server=SERVER):
    """L'empreinte d'une base : {(table, chronique): (lignes, empreinte)}."""
    return {(row[0], int(row[1])): (row[2], row[3])
            for sql in compare.FINGERPRINTS.values() for row in query(base, sql, server)}


def row_differences(base, other, cid):
    """Une chronique ligne à ligne, dans les deux bases, en comptes seulement : aucune valeur ne sort d'ici."""
    def rows(where):
        return {r[0]: r[1:] for r in query(where, f"""
            SELECT date, encode(float8send(valeur), 'hex'), encode(float8send(minimum), 'hex'),
                   encode(float8send(maximum), 'hex'), qualite_id, estcalculee
              FROM mesure WHERE chronique_id = {cid}""")}

    a, b = rows(base), rows(other)
    common = a.keys() & b.keys()
    differ = lambda *fields: sum(any(a[d][k] != b[d][k] for k in fields) for d in common)
    return {'seulement avant': len(a.keys() - b.keys()), 'seulement après': len(b.keys() - a.keys()),
            'valeurs': differ(0), 'bornes': differ(1, 2), 'qualités': differ(3), 'marque calculée': differ(4)}


def under_postgresql_11(base, cid, dtype):
    """L'empreinte d'une chronique calculée, recalculée par les fonctions de PostgreSQL 11 dans une
    transaction annulée : (code, lignes, empreinte). Les conversions passent par le PHP : pas ici."""
    if dtype != 'calculee':
        return None
    sql = compare.FINGERPRINTS['mesure'].replace('GROUP BY', f'WHERE chronique_id = {cid} GROUP BY')
    out = subprocess.run(['docker', 'exec', '-i', compare.CASES_SERVER, 'psql', '-U', 'bdoh', '-d', base, '-At', '-F', '\t',
                          '-v', 'ON_ERROR_STOP=1'],
                         input=f"BEGIN;\nSELECT bdoh_compute_chronique({cid});\n{sql};\nROLLBACK;\n", capture_output=True, text=True)
    lines = [line for line in out.stdout.splitlines() if line not in ('BEGIN', 'ROLLBACK')]
    if len(lines) < 1:
        return ('erreur', '', '')
    fields = lines[1].split('\t') if len(lines) > 1 else ['', '', '', '']
    return (lines[0], fields[2], fields[3])


def main():
    parser = argparse.ArgumentParser(description=__doc__.split('\n')[0])
    parser.add_argument('--base', default='bdoh')
    parser.add_argument('--jobs', type=int, default=4)
    parser.add_argument('--only-calculs', action='store_true')
    parser.add_argument('--only-taux', action='store_true', help='sur la copie déjà recalculée')
    args = parser.parse_args()

    out = compare.OUT / f"{datetime.datetime.now():%Y-%m-%d_%H%M%S}_recompute"
    out.mkdir(parents=True)
    report = [f"Base : {args.base}", *compare.provenance(), '']

    def say(line):
        """Une ligne du rapport, écrite tout de suite : une passe dure des heures, et peut s'arrêter."""
        report.append(line)
        print(line, flush=True)
        (out / 'rapport.txt').write_text('\n'.join(report) + '\n')

    results = {}
    if not args.only_taux:
        start = time.monotonic()
        compare.copy_base(args.base, COPY)
        say(f'Copie de {args.base} : {time.monotonic() - start:.0f} s')
        mark = int(query(COPY, 'SELECT max(id) FROM mesure')[0][0])
        items = plan(COPY)  # aucune sur OTHU, dont toutes les chroniques sont continues
        user = query(COPY, "SELECT id FROM utilisateur WHERE roleadmin = 'FCT' ORDER BY id LIMIT 1")[0][0] if items else None
        start = time.monotonic()
        for depth in sorted({i[0] for i in items}, reverse=True):
            run_level([i for i in items if i[0] == depth], user, args.jobs, results)
            say(f'Profondeur {depth} : {sum(i[0] == depth for i in items)} chroniques, {time.monotonic() - start:.0f} s depuis le début')
        codes = collections.Counter((r['item'][2], r['code']) for r in results.values())
        say('Codes de sortie, par sorte : ' + ', '.join(f'{d} {c} : {n}' for (d, c), n in sorted(codes.items())))
        # Le contrôle positif : une mesure calculée d'identifiant ancien n'a pas été recalculée
        old = {int(r[0]): int(r[1]) for r in query(COPY, f"""
            SELECT chronique_id, count(*) FROM mesure
             WHERE estcalculee AND id <= {mark}
               AND chronique_id IN (SELECT id FROM chronique WHERE dtype IN ('calculee', 'convertie'))
             GROUP BY 1""")}
        say(f"Chroniques qui gardent des mesures calculées d'avant le recalcul : {len(old)},"
            f" dont {sum(results.get(c, {}).get('code') == 0 for c in old)} au code 0")

        before, after = fingerprint_of(args.base), fingerprint_of(COPY)
        differing = {key for key in before.keys() | after.keys() if key[0] == 'mesure' and before.get(key) != after.get(key)}
        say(f"Mesures : {len({k for k in before if k[0] == 'mesure'})} chroniques, {len(differing)} qui diffèrent.")
        kinds, rows = collections.Counter(), collections.Counter()
        with open(out / 'visibles.txt', 'w') as detail:
            for _, cid in sorted(differing):
                r = results.get(cid)
                if r is None:  # pas recalculée, et pourtant changée
                    kinds['changée sans avoir été recalculée'] += 1
                    continue
                _, _, dtype, visible = r['item']
                counts = row_differences(args.base, COPY, cid)
                rows.update(counts)
                old_side = under_postgresql_11(args.base, cid, dtype)
                same_as_11 = old_side is not None and (old_side[1], old_side[2]) == after.get(('mesure', cid))
                verdict = ('conversion, sans ancien' if old_side is None else
                           'histoire : PostgreSQL 11 donne pareil' if same_as_11 else 'À EXAMINER : PostgreSQL 11 donne autrement')
                kinds[(dtype, 'visible' if visible else 'cachée', r['code'], verdict)] += 1
                if visible:
                    detail.write(f"{cid}\t{dtype}\t{r['code']}\t{verdict}\t{counts}\n")
                    detail.flush()
        for kind, n in sorted(kinds.items(), key=str):
            say(f'  {kind} : {n}')
        say(f'  Lignes de ces chroniques : {dict(rows)}')

    if not args.only_calculs:
        ids = [int(r[0]) for r in query(COPY, "SELECT id FROM chronique WHERE dtype <> 'discontinue' AND nbmesures > 0 ORDER BY id")]
        start = time.monotonic()
        with concurrent.futures.ThreadPoolExecutor(args.jobs) as pool:
            taux = list(pool.map(lambda cid: console('bdoh:compute:filling_rates', str(cid))[0], ids))
        say(f"Taux de remplissage : {len(ids)} chroniques, {time.monotonic() - start:.0f} s, codes {dict(collections.Counter(taux))}")
        before, after = fingerprint_of(args.base), fingerprint_of(COPY)
        measures_changed = {k[1] for k in before.keys() | after.keys() if k[0] == 'mesure' and before.get(k) != after.get(k)}
        rates = {k[1] for k in before.keys() | after.keys() if k[0] == 'tauxremplissage' and before.get(k) != after.get(k)}
        say(f"Taux : {len({k for k in before if k[0] == 'tauxremplissage'})} chroniques, {len(rates)} qui diffèrent,"
            f" dont {len(rates - measures_changed)} sans que leurs mesures aient changé.")
        visible = {int(r[0]) for r in query(COPY, 'SELECT id FROM chronique WHERE estvisible')}
        with open(out / 'visibles.txt', 'a') as detail:
            for cid in sorted((rates - measures_changed) & visible):
                detail.write(f'{cid}\ttaux différents, mesures identiques\n')

    print(f'Rapport : {out}')


if __name__ == '__main__':
    sys.exit(main())
