#!/usr/bin/env python3
"""La couverture du nouveau BDOH, en PHP (PCOV) et en SQL (plpgsql_check), le temps d'une passe de compare.py.

    dev/coverage/coverage.py start     branche les deux mesures, sur un dossier vidé
    BDOH_COVERAGE=1 dev/compare/compare.py ...     les modes, avec cette variable
    dev/coverage/coverage.py report    relève ce qui reste, et écrit les listes
    dev/coverage/coverage.py stop      remet le conteneur et le rôle comme avant

Le but est une liste à lire, ce qui écrit d'abord, pas un pourcentage (dev/plans/2026-11-04_validation.md). Les
listes vont dans ../bdoh_reference/coverage/ : des chemins, des numéros de ligne, et le source du code.
"""

import json
import os
import re
import shutil
import subprocess
import sys
from collections import defaultdict
from pathlib import Path

HERE = Path(__file__).resolve().parent
ROOT = HERE.parent.parent
OUT = ROOT.parent / 'bdoh_reference' / 'coverage'
SERVER = 'bdoh-db'
# Ce qui écrit, dans un fichier de src/ : ce qui se lit d'abord
WRITES = re.compile(r'\b(INSERT|UPDATE|DELETE|TRUNCATE|COPY)\b|->persist\(|->flush\(|->remove\(|copyFrom|executeStatement'
                    r'|file_put_contents|fwrite\(|unlink\(|rename\(|->move\(|setState\(')


def compose(*args):
    env = {**os.environ, 'HOST_UID': str(os.getuid()), 'HOST_GID': str(os.getgid()), 'REFERENCE_DIR': str(ROOT.parent / 'bdoh_reference')}
    subprocess.run(['docker', 'compose', '-f', str(ROOT / 'compose.yaml'), *args], check=True, env=env)


def psql(sql, base='postgres'):
    return subprocess.run(['docker', 'exec', '-i', SERVER, 'psql', '-U', 'postgres', '-d', base, '-v', 'ON_ERROR_STOP=1', '-Atqc', sql],
                          check=True, capture_output=True, text=True).stdout


def start():
    subprocess.run(['docker', 'build', '-q', '-t', 'bdoh-app-coverage', str(HERE)], check=True, capture_output=True)
    shutil.rmtree(OUT, ignore_errors=True)
    (OUT / 'php').mkdir(parents=True)
    (OUT / 'sql').mkdir()
    compose('-f', str(HERE / 'coverage.yaml'), 'up', '-d', '--no-deps', '--wait', 'app')
    # Par rôle, et non par base : une copie de base ne garde pas ce réglage. Les profils déjà là restent, propres à
    # leurs bases : le relevé passe base par base, et ne lit que celles de la passe.
    psql('ALTER ROLE bdoh SET plpgsql_check.profiler = on')
    print(f'Couverture branchée. Lancer les modes avec BDOH_COVERAGE=1 ; les listes iront dans {OUT}.')


def stop():
    psql('ALTER ROLE bdoh RESET plpgsql_check.profiler')
    compose('up', '-d', '--no-deps', '--wait', 'app')


def bases():
    """Les bases dont le nouveau BDOH s'est servi : ni les copies de l'ancien, ni les autres."""
    names = psql("SELECT datname FROM pg_database WHERE datname LIKE 'bdoh%'").split()
    return [name for name in names if not name.endswith('_old')]


def report():
    sys.path.insert(0, str(ROOT / 'dev' / 'compare'))
    sys.dont_write_bytecode = True
    import compare

    for base in bases():
        compare.save_sql_coverage(base)
    php_report()
    sql_report()


def php_report():
    run, missed = defaultdict(set), defaultdict(set)
    for path in (OUT / 'php').glob('*.json'):
        for file, lines in json.loads(path.read_text()).items():
            run[file].update(lines['run'])
            missed[file].update(lines['missed'])
    prefix = '/var/www/bdoh/'
    tracked = subprocess.run(['git', '-C', str(ROOT), 'ls-files', 'src/*.php'], check=True, capture_output=True, text=True).stdout.split()
    rows, never = [], []
    for name in tracked:
        if '/vendor/' in name:
            continue
        source = (ROOT / name).read_text(errors='replace')
        writes = bool(WRITES.search(source))
        file = prefix + name
        if file not in run and file not in missed:
            never.append((writes, name))
            continue
        lines = sorted(missed[file] - run[file])
        if lines:
            rows.append((writes, len(lines), name, lines))
    rows.sort(key=lambda row: (not row[0], -row[1]))
    never.sort(key=lambda row: (not row[0], row[1]))
    out = [f'{sum(map(len, run.values()))} lignes exécutées, {sum(row[1] for row in rows)} vues sans être exécutées,'
           f' {len(never)} fichiers jamais chargés.', '', '# Fichiers jamais chargés (E : le fichier écrit)']
    out += [f"{'E' if writes else ' '} {name}" for writes, name in never]
    out += ['', '# Lignes jamais exécutées (E : le fichier écrit), les fichiers qui écrivent d\'abord']
    for writes, count, name, lines in rows:
        out.append(f"\n{'E' if writes else ' '} {name} : {count}")
        source = (ROOT / name).read_text(errors='replace').splitlines()
        out += [f'  {line:>5} {source[line - 1].strip()[:150]}' for line in lines if line <= len(source)]
    (OUT / 'php.txt').write_text('\n'.join(out) + '\n')
    print('\n'.join(out[:1]), f'{OUT / "php.txt"}')


def sql_report():
    statements = defaultdict(dict)
    for path in (OUT / 'sql').glob('*.tsv'):
        for line in path.read_text().splitlines():
            function, stmtid, lineno, executed, name = line.split('\t')
            key = (int(stmtid), lineno, name)
            statements[function][key] = statements[function].get(key, 0) + int(executed)
    # Le texte des fonctions, pour lire ce qui ne s'exécute pas : celui de la base réduite
    sources = json.loads(psql("""SELECT json_object_agg(p.proname || '(' || pg_get_function_identity_arguments(p.oid) || ')', p.prosrc)
                                   FROM pg_proc p JOIN pg_namespace n ON n.oid = p.pronamespace JOIN pg_language l ON l.oid = p.prolang
                                  WHERE n.nspname = 'bdoh' AND l.lanname = 'plpgsql'""", 'bdoh_small'))
    out, total, unrun = [], 0, 0
    for function in sorted(statements):
        rows = statements[function]
        missed = [key for key in sorted(rows) if not rows[key]]
        total += len(rows)
        unrun += len(missed)
        if not any(rows.values()):
            out.append(f'{function} : jamais appelée ({len(rows)} instructions)')
        elif missed:
            out.append(f'{function} : {len(missed)} instructions sur {len(rows)} jamais exécutées')
            source = sources.get(function, '').splitlines()
            out += [f"  {lineno:>5} {name} : {source[int(lineno) - 1].strip()[:130] if lineno.isdigit() and 0 < int(lineno) <= len(source) else ''}"
                    for _, lineno, name in missed]
        else:
            out.append(f'{function} : tout exécuté ({len(rows)} instructions)')
    text = [f'{unrun} instructions jamais exécutées sur {total}, dans {len(statements)} fonctions.', '', *out]
    (OUT / 'sql.txt').write_text('\n'.join(text) + '\n')
    print(text[0], f'{OUT / "sql.txt"}')


if __name__ == '__main__':
    {'start': start, 'stop': stop, 'report': report}[sys.argv[1]]()
