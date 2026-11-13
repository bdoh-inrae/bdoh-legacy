#!/usr/bin/env python3
"""Prépare ce qu'on envoie aux gestionnaires pour les essais, observatoire par observatoire.

    make test-kit ARGS="--site https://… --deadline 'dimanche 15 novembre'"

Écrit dans ../bdoh_reference/test-kit/<OBSERVATOIRE>/ le tableur de ses chroniques et ses deux messages, à partir
des bases anonymisées, qui gardent les codes, les libellés et les stations. Aucune adresse n'y figure : elles
viennent de contacts.py, que Lou lance sur le dump brut. Tient aussi le fichier de suivi (dev/local/test-tracking.md),
créé une fois, jamais écrasé. Les constats propres à une station ou une chronique viennent de
dev/local/test-kit-notes.csv, hors de Git parce qu'il cite de vrais codes.
"""

import argparse
import csv
import datetime
import re
import subprocess
from pathlib import Path

from openpyxl import Workbook
from openpyxl.styles import Alignment, Font, PatternFill
from openpyxl.utils import get_column_letter

HERE = Path(__file__).resolve().parent
ROOT = HERE.parent.parent
OUT = ROOT.parent / 'bdoh_reference' / 'test-kit'
NOTES = ROOT / 'dev' / 'local' / 'test-kit-notes.csv'
TRACKING = ROOT / 'dev' / 'local' / 'test-tracking.md'
SERVER = 'bdoh-reference-db'
BASES = ['bdoh', 'bdoh_othu']
TO_FILL = '[à compléter]'

# Ce qui ne vaut que pour un observatoire : la fusion d'OTHU (dev/plans/2026-11-13_othu-merge.md), et les conditions
# d'utilisation (dev/audits/2026-11-13_terms-of-use.md)
SPECIAL = {
    'OTHU': (
        "OTHU rejoint BDOH : ce n'est plus un serveur à part, mais un observatoire de BDOH, au même site que les autres "
        "(data.othu.org y renverra). Vos données et vos droits sont repris tels quels ; un compte qui existait déjà dans "
        "BDOH sous la même adresse ne fait plus qu'un avec lui. Quelques libellés "
        "suivent désormais les listes communes de BDOH : « m3/s » au lieu de « m³/s », « m3 » au lieu de « m³ », "
        "« mm/j » au lieu de « mm/jour », « m3.m-3 » au lieu de « cm3/cm3 », et « Ensoleillement » devient « Puissance de "
        "rayonnement solaire ». Vos comptes d'administration deviennent gestionnaires d'OTHU ; les listes communes "
        "(unités, types de paramètres) passent alors par un administrateur de BDOH. Et vos exports instantanés, qui "
        "sortaient vides (un défaut de votre ancienne base), donnent désormais leurs valeurs.\n\n"
        "Trois questions : qui a besoin d'administrer OTHU ? À quelle fréquence ajoutez-vous des paramètres ou des "
        "unités ? Envoyez-vous vos fiches à Theia/OZCAR ? Et vos chroniques ouvertes viennent de l'INSA et de l'UCBL : "
        "votre « OK » vaut leur accord pour une diffusion sous Licence Ouverte."),
    'ORACLE': (
        "Vos conditions d'utilisation : le lien « Conditions d'utilisation » de votre accueil servait un PDF propre à "
        "ORACLE, en fait une copie des conditions de BDOH de 2014 (compte obligatoire, usage non commercial). Elles ne "
        "peuvent plus valoir pour des données ouvertes, et vos DOI déclarent déjà la Licence Ouverte : le lien mène "
        "désormais à la page commune à tous les observatoires. Si ORACLE a des conditions vraiment à lui, dites-le-moi."),
    'ARC-ISERE': (
        "Certaines de vos chroniques ouvertes viennent d'EDF, une entreprise : ouvertes, elles passent sous Licence "
        "Ouverte. EDF l'accepte-t-il ? Sinon, on les cache, comme celles qui le sont déjà."),
}

KINDS = {'continue': 'Continue', 'discontinue': 'Discontinue', 'calculee': 'Calculée', 'convertie': 'Convertie'}
CLEAN = "regexp_replace(coalesce({}, ''), '\\s+', ' ', 'g')"

CHRONIQUES = f"""
    SELECT DISTINCT ON (ch.id) o.slug, {CLEAN.format('st.code')}, {CLEAN.format('st.nom')}, {CLEAN.format('ch.code')},
           {CLEAN.format('ch.libelle')}, {CLEAN.format('tp.nom')}, {CLEAN.format('u.libelle')}, {CLEAN.format('pa.nom')},
           {CLEAN.format('d.titre')}, {CLEAN.format('doi.identifiant')}, ch.dtype, coalesce(ch.nbmesures, 0),
           coalesce(ch.datedebutmesures::date::text, ''), coalesce(ch.datefinmesures::date::text, ''), ch.estvisible
      FROM chronique ch
      JOIN station st ON st.id = ch.station_id
      JOIN stations_sites ss ON ss.station_id = st.id
      JOIN siteexperimental si ON si.id = ss.site_id
      JOIN observatoire o ON o.id = si.observatoire_id
      LEFT JOIN typeparametre tp ON tp.id = ch.parametre_id
      LEFT JOIN unite u ON u.id = ch.unite_id
      LEFT JOIN partenaire pa ON pa.id = ch.producteur_id
      LEFT JOIN dataset d ON d.id = ch.dataset_id
      LEFT JOIN doi ON doi.id = d.doi_id
     ORDER BY ch.id, si.id"""

OBSERVATORIES = f"SELECT slug, {CLEAN.format('nom')}, theiacode IS NOT NULL FROM observatoire ORDER BY slug"

# Une station sans coordonnées ; celles d'un jeu de données partent vers Theia en (0, 0)
NO_COORDINATES = f"""
    SELECT DISTINCT o.slug, {CLEAN.format('st.code')}, {CLEAN.format('st.nom')},
           EXISTS (SELECT 1 FROM chronique ch WHERE ch.station_id = st.id AND ch.dataset_id IS NOT NULL)
      FROM station st
      JOIN stations_sites ss ON ss.station_id = st.id
      JOIN siteexperimental si ON si.id = ss.site_id
      JOIN observatoire o ON o.id = si.observatoire_id
     WHERE st.latitude IS NULL OR st.longitude IS NULL"""


def query(base, sql):
    done = subprocess.run(['docker', 'exec', '-i', SERVER, 'psql', '-U', 'postgres', '-d', base, '-v', 'ON_ERROR_STOP=1', '-At', '-F', '\t',
                           '-c', f'SET search_path TO bdoh, public; {sql}'], check=True, capture_output=True, text=True)
    return [line.split('\t') for line in done.stdout.splitlines() if line != 'SET']


def read_notes():
    """Les constats écrits à la main, par observatoire : (station, chronique, constat, que faire)."""
    notes = {}
    if NOTES.exists():
        with NOTES.open(newline='') as file:
            for row in csv.reader(file, delimiter=';'):
                if row and not row[0].startswith('#'):
                    notes.setdefault(row[0], []).append(tuple(row[1:5]))
    return notes


# --- Le tableur ------------------------------------------------------------------------------------------

BASE_FONT = Font(name='Arial', size=10)
BOLD = Font(name='Arial', size=10, bold=True)
TITLE = Font(name='Arial', size=13, bold=True)
HEAD_FILL = PatternFill('solid', fgColor='DDDDDD')
FILL_IN = PatternFill('solid', fgColor='FFF2A8')
CLOSED = Font(name='Arial', size=10, bold=True, color='B00020')
OPEN = Font(name='Arial', size=10, color='1B6E2E')


def table(sheet, top, headers, widths):
    for column, (header, width) in enumerate(zip(headers, widths), start=1):
        cell = sheet.cell(row=top, column=column, value=header)
        cell.font, cell.fill = BOLD, HEAD_FILL
        cell.alignment = Alignment(vertical='center', wrap_text=True)
        sheet.column_dimensions[get_column_letter(column)].width = width
    sheet.freeze_panes = sheet.cell(row=top + 1, column=1)


def workbook(name, rows, notes, no_coordinates, path):
    book = Workbook()
    sheet = book.active
    sheet.title = 'Chroniques'
    sheet['A1'] = f'BDOH, {name} : chroniques ouvertes et fermées à la réouverture'
    sheet['A1'].font = TITLE
    sheet['A2'] = ('Ouverte : téléchargeable sans compte, sous Licence Ouverte Etalab. Fermée : sur demande d’accès, comme avant. '
                   'À remplir : seulement la colonne jaune, et seulement pour ce qui doit changer, '
                   'par exemple « à fermer, accord avec le producteur ».')
    sheet['A2'].font = BASE_FONT
    top = 4
    headers = ['Station', 'Nom de la station', 'Chronique', 'Libellé', 'Paramètre', 'Unité', 'Producteur', 'Jeu de données',
               'DOI', 'Sorte', 'Mesures', 'Début', 'Fin', 'État', 'À changer ?']
    widths = [16, 28, 12, 32, 22, 10, 22, 30, 22, 12, 11, 11, 11, 9, 30]
    table(sheet, top, headers, widths)
    sheet.cell(row=top, column=15).fill = FILL_IN
    for offset, row in enumerate(sorted(rows, key=lambda r: (r[1], r[3])), start=top + 1):
        (_, station, station_name, code, label, parameter, unit, producer, dataset, doi, kind, count, begin, end, visible) = row
        values = [station, station_name, code, label, parameter, unit, producer, dataset, doi, KINDS.get(kind, kind), int(count),
                  datetime.date.fromisoformat(begin) if begin else None, datetime.date.fromisoformat(end) if end else None,
                  'Ouverte' if visible == 't' else 'Fermée', None]
        for column, value in enumerate(values, start=1):
            cell = sheet.cell(row=offset, column=column, value=value)
            cell.font = BASE_FONT
        sheet.cell(row=offset, column=11).number_format = '#,##0'
        sheet.cell(row=offset, column=12).number_format = 'dd/mm/yyyy'
        sheet.cell(row=offset, column=13).number_format = 'dd/mm/yyyy'
        sheet.cell(row=offset, column=14).font = OPEN if visible == 't' else CLOSED
        sheet.cell(row=offset, column=15).fill = FILL_IN
    sheet.auto_filter.ref = f'A{top}:O{top + max(len(rows), 1)}'

    items = list(notes)
    for _, code, station_name, in_dataset in sorted(no_coordinates, key=lambda r: (r[3] != 't', r[1])):
        where = (f'Sans coordonnées, et dans un jeu de données : part vers Theia en (0, 0).' if in_dataset == 't'
                 else 'Sans coordonnées : absente des cartes.')
        items.append((code, '', where, 'Saisir ses coordonnées.'))
    if items:
        other = book.create_sheet('À regarder')
        other['A1'] = 'À corriger dans les données, quand vous aurez le temps'
        other['A1'].font = TITLE
        other['A2'] = 'Rien ici ne bloque la réouverture. Dans BDOH, le champ « latitude » porte le X et « longitude » le Y, en Lambert-93.'
        other['A2'].font = BASE_FONT
        table(other, 4, ['Station', 'Chronique', 'Constat', 'Que faire'], [18, 12, 80, 44])
        for offset, item in enumerate(items, start=5):
            for column, value in enumerate(item, start=1):
                cell = other.cell(row=offset, column=column, value=value or None)
                cell.font = BASE_FONT
                cell.alignment = Alignment(vertical='top', wrap_text=column >= 3)
        other.auto_filter.ref = f'A4:D{4 + len(items)}'
    book.save(path)
    return len(items)


# --- Les messages ----------------------------------------------------------------------------------------

def counts_sentence(name, opened, closed):
    if not opened:
        return f'Toutes les chroniques de {name} sont cachées ({closed}), donc rien ne s’ouvre. Si c’est bien ce que vous voulez, un « OK » suffit.'
    fermees = f'{closed} fermée{"s" if closed > 1 else ""}' if closed else 'aucune fermée'
    return (f'{name} : {opened} chroniques ouvertes, {fermees}. La seule question : une chronique ouverte est-elle sous accord avec '
            'un producteur ? Si oui, on la ferme. Un « OK » de l’un d’entre vous suffit, sinon dites-moi lesquelles changer. '
            'Sans réponse après une relance, elles s’ouvrent telles quelles.')


def fill(template, **values):
    text = (HERE / 'messages' / template).read_text()
    for key, value in values.items():
        text = text.replace('{' + key + '}', value)
    # Un paragraphe vide, parce qu'il ne concerne pas cet observatoire, ne laisse pas de trou
    return re.sub(r'\n{3,}', '\n\n', text)


def main():
    parser = argparse.ArgumentParser(description=__doc__.split('\n')[0])
    parser.add_argument('--site', default=TO_FILL, help='adresse du site d’essai de la production')
    parser.add_argument('--deadline', default=TO_FILL, help='date limite des réponses, en toutes lettres')
    args = parser.parse_args()

    notes = read_notes()
    summary = []
    for base in BASES:
        chroniques = query(base, CHRONIQUES)
        missing = query(base, NO_COORDINATES)
        for slug, name, theia in query(base, OBSERVATORIES):
            rows = [row for row in chroniques if row[0] == slug]
            folder = OUT / slug
            folder.mkdir(parents=True, exist_ok=True)
            opened = sum(row[14] == 't' for row in rows)
            closed = len(rows) - opened
            items = workbook(name, rows, notes.get(slug, []), [row for row in missing if row[0] == slug], folder / f'chroniques-{slug}.xlsx')
            attachment = f'Le second onglet du tableau liste {items} point{"s" if items > 1 else ""} à corriger dans vos données. Ça ne bloque rien'
            values = dict(
                observatoire=name, comptes=counts_sentence(name, opened, closed), date=args.deadline,
                site=args.site, special=SPECIAL.get(slug, ''),
                theia='Theia : vos fiches publiées ont des coordonnées décalées de 300 à 700 m, un bogue de l’ancien. C’est corrigé au prochain envoi.' if theia == 't' else '')
            (folder / 'message-inrae.txt').write_text(fill('inrae.txt', **values, onglet=f'{attachment}, à faire quand vous aurez le temps.' if items else ''))
            (folder / 'message-hors-inrae.txt').write_text(fill('outside.txt', **values, onglet=f'{attachment}, à faire après la réouverture, quand vous aurez le temps.' if items else ''))
            summary.append((slug, opened, closed, items))

    # Le fichier de suivi : une fois, puis il vit sa vie
    widest = max(len(row[0]) for row in summary)
    print(f"{'Observatoire':<{widest}}  Ouvertes  Fermées  À regarder")
    for slug, opened, closed, items in summary:
        print(f'{slug:<{widest}}  {opened:>8}  {closed:>7}  {items:>10}')
    print(f"{'Total':<{widest}}  {sum(r[1] for r in summary):>8}  {sum(r[2] for r in summary):>7}  {sum(r[3] for r in summary):>10}")
    print(f'Dans {OUT}')
    if not TRACKING.exists():
        lines = [
            '# Suivi des essais', '',
            'Une ligne par observatoire ; ignoré par Git, et sans aucune adresse (elles sont dans `../bdoh_reference/private-test-contacts/`, que Claude ne lit pas). '
            'Lou colle les réponses à Claude, qui tient ce tableau et range chaque retour : bogue, chronique à changer, ou décision pour Lou.', '',
            f"{'Observatoire':<{widest}}  Ouvertes  Fermées  Envoyé  Validé  Essais  Données  À suivre",
            f"{'-' * widest}  --------  -------  ------  ------  ------  -------  --------"]
        lines += [f'{slug:<{widest}}  {opened:>8}  {closed:>7}  -       -       -       -        -' for slug, opened, closed, _ in summary]
        lines += ['', '## Retours', '', 'Le plus récent en haut : date, observatoire, ce qui est dit, ce qu’on en fait.', '']
        TRACKING.write_text('\n'.join(lines))
        print(f'Suivi créé : {TRACKING}')


if __name__ == '__main__':
    main()
