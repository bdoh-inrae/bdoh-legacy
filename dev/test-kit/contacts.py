#!/usr/bin/env python3
"""Les contacts de chaque observatoire, à lancer par Lou sur un dump brut, production puis OTHU.

    make test-contacts SOURCE=chemin/du.dump

Lit le dump sans le restaurer : pg_restore, dans un conteneur sans réseau, n'en sort que les tables utiles. Écrit
dans ../bdoh_reference/private-test-contacts/ un fichier par observatoire, séparant INRAE et hors INRAE, et
allowed-recipients.env, les adresses à laisser passer pendant les essais (dev/plans/2026-10-26_deployment.md,
« Courriels sur le dump brut »). Claude ne lit pas ce dossier : ne sortent à l'écran et dans contacts-counts.csv que des comptes.
"""

import csv
import datetime
import os
import re
import subprocess
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent.parent
OUT = ROOT.parent / 'bdoh_reference' / 'private-test-contacts'
COUNTS = ROOT.parent / 'bdoh_reference' / 'test-kit' / 'contacts-counts.csv'
IMAGE = 'postgis/postgis:11-2.5'  # la version de la production
TABLES = ['utilisateur', 'role', 'observatoire', 'siteexperimental', 'personnetheia', 'observatoires_data_manager']
INRAE = re.compile(r'@(?:[\w.-]+\.)?(?:inrae|inra|irstea|cemagref)\.fr$', re.I)
OLD_DOMAIN = re.compile(r'@(?:[\w.-]+\.)?(?:inra|irstea|cemagref)\.fr$', re.I)
# Une adresse, en ASCII : une fiche Theia ou un observatoire en saisit en texte libre (« a@x.fr; b@y.fr »), et ce qui
# n'en est pas une rendrait illisible le .env où va allowed-recipients.env
ADDRESS = re.compile(r'[A-Za-z0-9._+-]+@[A-Za-z0-9-]+(?:\.[A-Za-z0-9-]+)+')


def parse_copy(lines):
    """Les blocs COPY d'une sortie de pg_restore : {table: [{colonne: valeur ou None}]}."""
    tables, name, columns = {}, None, []
    for line in lines:
        line = line.rstrip('\n')
        if name is None:
            found = re.match(r'COPY (?:\w+\.)?"?(\w+)"? \((.*)\) FROM stdin;', line)
            if found:
                name, columns = found[1], [column.strip().strip('"') for column in found[2].split(',')]
                tables[name] = []
        elif line == '\\.':
            name = None
        else:
            tables[name].append({column: None if value == '\\N' else value for column, value in zip(columns, line.split('\t'))})
    return tables


def contacts(tables, today):
    """{slug: {adresse en minuscules: [nom, [ce qu'elle est]]}} : qui écrire pour chaque observatoire. Un compte dont
    l'accès a pris fin n'en est pas."""
    users = {row['id']: row for row in tables['utilisateur']}
    people = {row['id']: row for row in tables['personnetheia']}
    sites = {row['id']: row['observatoire_id'] for row in tables['siteexperimental']}
    slugs = {row['id']: row['slug'] for row in tables['observatoire']}
    found = {slug: {} for slug in slugs.values()}
    unreadable = [0]  # les champs sans aucune adresse lisible : seulement comptés

    def add(observatory, field, name, what):
        if observatory not in slugs or not field:
            return
        emails = ADDRESS.findall(field)
        if not emails:
            unreadable[0] += 1
        for email in emails:
            entry = found[slugs[observatory]].setdefault(email.lower(), [name, []])
            entry[0] = entry[0] or name  # l'adresse de contact de l'observatoire n'a pas de nom
            if what not in entry[1]:
                entry[1].append(what)

    for row in tables['observatoire']:
        add(row['id'], row.get('email'), '', 'contact de l’observatoire')
        leader = people.get(row.get('projectleader_id'))
        if leader:
            add(row['id'], leader['email'], f"{leader['prenom']} {leader['nom']}", 'responsable')
    for row in tables['observatoires_data_manager']:
        person = people.get(row['personnetheia_id'])
        if person:
            add(row['observatoire_id'], person['email'], f"{person['prenom']} {person['nom']}", 'gestionnaire de données (Theia)')
    for row in tables['role']:
        user = users.get(row['utilisateur_id'])
        if row['valeur'] != 'gestionnaire' or not user or (user.get('finacces') and user['finacces'][:10] <= today):
            continue
        name = f"{user['prenom']} {user['nom']}"
        if row['dtype'] == 'observatoire':
            add(row['observatoire_id'], user['email'], name, 'gestionnaire')
        elif row['dtype'] == 'site':
            add(sites.get(row['site_id']), user['email'], name, 'gestionnaire d’un site')
    if unreadable[0]:
        print(f'{unreadable[0]} champs d’adresse sans adresse lisible, écartés')
    return found


def allowed_recipients(emails):
    """La valeur de MAILER_ALLOWED_RECIPIENTS : des expressions régulières sans antislash, que .env lirait autrement.
    La casse ne compte pas : une adresse de la base peut en avoir. Tout ce qui n'est ni lettre, ni chiffre, ni @ s'écrit
    entre crochets."""
    return ','.join('(?i)' + re.sub(r'[^A-Za-z0-9@]', lambda m: f'[{m[0]}]', email) for email in emails)


def write_files(found):
    os.umask(0o077)
    OUT.mkdir(parents=True, exist_ok=True)
    COUNTS.parent.mkdir(parents=True, exist_ok=True)
    known = {}
    if COUNTS.exists():
        with COUNTS.open(newline='') as file:
            known = {row[0]: row[1:] for row in csv.reader(file, delimiter=';')}
    for slug, people in sorted(found.items()):
        inside = sorted(email for email in people if INRAE.search(email))
        outside = sorted(email for email in people if not INRAE.search(email))
        detail = [f"{email} ; {people[email][0]} ; {', '.join(people[email][1])}"
                  + (' ; ancienne adresse' if OLD_DOMAIN.search(email) else '') for email in sorted(people)]
        (OUT / f'{slug}.txt').write_text('\n'.join([
            '# INRAE : message-inrae.txt', ', '.join(inside), '', '# Hors INRAE : message-hors-inrae.txt', ', '.join(outside), '', '# Détail', *detail, '']))
        known[slug] = [str(len(inside)), str(len(outside))]
    with COUNTS.open('w', newline='') as file:
        csv.writer(file, delimiter=';').writerows([slug, *values] for slug, values in sorted(known.items()))
    everyone = sorted({line.split(' ; ')[0] for path in OUT.glob('*.txt')
                       for line in path.read_text().split('# Détail\n', 1)[-1].splitlines() if line})
    (OUT / 'allowed-recipients.env').write_text(f'MAILER_ALLOWED_RECIPIENTS={allowed_recipients(everyone)}\n')
    return known


def main():
    if len(sys.argv) != 2 or not Path(sys.argv[1]).is_file():
        sys.exit(__doc__)
    dump = str(Path(sys.argv[1]).resolve())
    names = [arg for table in TABLES for arg in ('-t', table)]
    process = subprocess.Popen(['docker', 'run', '--rm', '--network', 'none', '--log-driver', 'none', '-v', f'{dump}:/in/source.dump:ro', IMAGE,
                                'pg_restore', '--data-only', '-n', 'bdoh', *names, '-f', '-', '/in/source.dump'],
                               stdout=subprocess.PIPE, text=True, errors='replace')
    tables = parse_copy(process.stdout)
    if process.wait() or any(table not in tables for table in TABLES):
        sys.exit(f'Lecture du dump incomplète : {sorted(tables)}')
    found = contacts(tables, datetime.date.today().isoformat())
    counts = write_files(found)
    print(f"{'Observatoire':<40} INRAE  Hors INRAE")
    for slug in sorted(found):
        print(f'{slug:<40} {counts[slug][0]:>5}  {counts[slug][1]:>10}')
    print(f'Adresses dans {OUT}')


if __name__ == '__main__':
    main()
