#!/usr/bin/env python3
"""Compare deux BDOH : mêmes requêtes, sorties comparées.

    dev/compare/compare.py pages   [--old SIDE] [--new SIDE] [--base BASE] [--only TEXTE]
    dev/compare/compare.py exports [--old SIDE] [--new SIDE] [--base BASE] [--only TEXTE]
    dev/compare/compare.py state   [--base BASE]
    dev/compare/compare.py queue   [--base BASE]
    dev/compare/compare.py imports [--base BASE] [--only TEXTE]

SIDE nomme une instance (voir SIDES). Les cas sont tirés de la base BASE du serveur de référence,
par des règles écrites ici : le dépôt ne contient ni données ni codes de chroniques. Le rapport et
les écarts vont dans ../bdoh_reference/compare/, hors du dépôt.

« state » mène les mêmes calculs (recalcul, conversion, taux de remplissage, puis la file de tâches)
avec l'ancien code sur PostgreSQL 17 et avec le nouveau, chacun sur sa copie de BASE, et compare
l'état des deux copies. « queue » est l'essai de la file de tâches (audit des risques, constat 3).

Les pages se comparent par ce qu'elles montrent (texte, liens, formulaires, attributs data-*), pas
par leur balisage ; les nombres, à un arrondi près (règle « Arrondis à la limite » de
dev/differences.md), compté à part pour être relu. Bibliothèque standard seulement.
"""

import argparse
import concurrent.futures
import contextlib
import datetime
import difflib
import hashlib
import html.parser
import http.cookiejar
import io
import json
import re
import subprocess
import sys
import urllib.error
import urllib.parse
import urllib.request
import zipfile
from dataclasses import dataclass
from pathlib import Path

HERE = Path(__file__).resolve().parent
OUT = HERE.parent.parent.parent / 'bdoh_reference' / 'compare'
PASSWORD = 'bdoh-test'  # mot de passe de tous les comptes des bases anonymisées


@dataclass
class Side:
    url: str
    console: list  # commande qui lance la console Symfony de l'instance


SIDES = {
    # La console tourne dans le conteneur des pages : celui de la file de tâches se relance toutes
    # les heures (--max-runtime, comme en production), ce qui couperait une commande en cours.
    'reference': Side('http://172.30.25.10',
                      ['docker', 'exec', 'bdoh-reference-app', 'php', 'app/console']),
    # Le nouveau BDOH (service app de compose.yaml), sur PostgreSQL 17.
    'new': Side('http://172.30.26.10', ['docker', 'exec', 'bdoh-app', 'php', 'bin/console']),
    # L'ancien code sur PostgreSQL 17 (service reference-app-17 de compose.yaml).
    'reference-17': Side('http://172.30.26.17',
                         ['docker', 'exec', 'bdoh-reference-app-17', 'php', 'app/console']),
}


# --- Base : tirage des cas -----------------------------------------------------------------------

CASES_SERVER = 'bdoh-reference-db'  # serveur où tirer les cas (option --server)


def query(base, sql, server=None):
    """Lignes d'une requête sur une base du serveur des cas (ou d'un autre), en listes de champs."""
    out = subprocess.run(
        ['docker', 'exec', '-i', server or CASES_SERVER, 'psql', '-U', 'bdoh', '-d', base,
         '-v', 'ON_ERROR_STOP=1', '-At', '-F', '\t', '-c', sql],
        check=True, capture_output=True, text=True).stdout
    return [line.split('\t') for line in out.splitlines()]


# Observatoire de chaque chronique, par sa station et le premier site de la station.
CHRONIQUES = """
    SELECT DISTINCT ON (c.id) c.id, c.code, c.dtype, c.estvisible, s.code AS station, o.slug
      FROM chronique c
      JOIN station s ON s.id = c.station_id
      JOIN stations_sites ss ON ss.station_id = s.id
      JOIN siteexperimental se ON se.id = ss.site_id
      JOIN observatoire o ON o.id = se.observatoire_id
     ORDER BY c.id, se.observatoire_id"""


def profiles(base):
    """Comptes de connexion par profil : le premier compte actif de chaque sorte."""
    active = "(u.finacces IS NULL OR u.finacces > now())"
    found = {'anonyme': None}
    for name, where in [
        ('administrateur', f"u.roleadmin = 'FCT' AND {active}"),
        ('gestionnaire', f"{active} AND EXISTS (SELECT 1 FROM role r WHERE r.utilisateur_id = u.id"
                         " AND r.valeur = 'gestionnaire' AND r.observatoire_id IS NOT NULL)"),
        ('utilisateur', f"{active} AND u.roleadmin IS NULL AND NOT EXISTS (SELECT 1 FROM role r"
                        " WHERE r.utilisateur_id = u.id AND r.valeur <> 'utilisateur')"
                        " AND EXISTS (SELECT 1 FROM role r WHERE r.utilisateur_id = u.id)"),
    ]:
        rows = query(base, f"SELECT u.email FROM utilisateur u WHERE {where} ORDER BY u.id LIMIT 1")
        if rows:
            found[name] = rows[0][0]
    return found


def page_cases(base):
    """Les pages publiques et le visualiseur, pour chaque observatoire, quelques stations et chroniques."""
    observatoires = [r[0] for r in query(base, "SELECT slug FROM observatoire ORDER BY id")]
    paths = ['/', '/user_manual', '/select_lang/en', '/select_lang/fr', '/translations/messages.js']
    for slug in observatoires:
        paths += [f'/{slug}/{p}' for p in ['', 'home', 'nos-donnees', 'viewer', 'contact',
                                            'terms_of_use', 'conditions', 'login', 'jobs/']]
        paths.append(f'/{slug}/_STATION_CODE_/_CHRONIQUE_CODE_')
    # Des adresses qui ne mènent à rien : l'ancien répond 404
    first = observatoires[0]
    paths += ['/INCONNU/', f'/{first}/station/INCONNUE', f'/{first}/INCONNUE/INCONNUE',
              f'/{first}/dataset/00000000-0000-0000-0000-000000000000', f'/{first}/dataset/pas-un-uuid']
    stations = query(base, """
        SELECT o.slug, s.code FROM (
            SELECT s.id, s.code, se.observatoire_id,
                   row_number() OVER (PARTITION BY se.observatoire_id ORDER BY s.id) AS n
              FROM station s JOIN stations_sites ss ON ss.station_id = s.id
              JOIN siteexperimental se ON se.id = ss.site_id) s
          JOIN observatoire o ON o.id = s.observatoire_id
         WHERE s.n <= 2 ORDER BY o.id, s.id""")
    paths += [f'/{slug}/station/{code}' for slug, code in stations]
    # Par observatoire, de chaque sorte, la première chronique visible et la première non visible.
    chroniques = query(base, f"""
        SELECT slug, station, code, id FROM (
            SELECT *, row_number() OVER (PARTITION BY slug, dtype, estvisible ORDER BY id) AS n
              FROM ({CHRONIQUES}) c) c
         WHERE n = 1 ORDER BY slug, id""")
    for slug, station, code, cid in chroniques:
        paths.append(f'/{slug}/{station}/{code}')
        paths.append(f'/{slug}/chronique/{cid}/measures?beginDate=2024-03-01T00:00:00Z'
                     '&endDate=2024-04-01T00:00:00Z')
    datasets = query(base, """
        SELECT DISTINCT ON (d.id) o.slug, d.uuid FROM dataset d
          JOIN observatoire o ON o.id = d.observatoire_id ORDER BY d.id""")
    paths += [f'/{slug}/dataset/{uuid}' for slug, uuid in datasets]
    for slug in observatoires[:3]:
        paths.append(f'/{slug}/viewer/load?start=2024-03-01T00:00:00Z&end=2024-04-01T00:00:00Z')
    # Les tâches, sous l'observatoire de leur première entité liée ; les bases anonymisées n'en ont
    # pas, celles de « state » si.
    jobs = query(base, """
        SELECT DISTINCT ON (j.id) j.id, o.slug FROM jms_jobs j
          JOIN jms_job_related_entities r ON r.job_id = j.id
          JOIN observatoire o ON r.related_class LIKE '%Observatoire' AND r.related_id::json->>'id' = o.id::text
         ORDER BY j.id""")
    paths += [f'/{slug}/jobs/{job}' for job, slug in jobs]
    return paths


def export_cases(base):
    """Exports en ligne de commande : chaque sorte de chronique, chaque format, chaque pas de temps."""
    picked = query(base, f"""
        SELECT id, dtype FROM (
            SELECT c.id, c.dtype, row_number() OVER (PARTITION BY c.dtype ORDER BY c.id) AS n
              FROM ({CHRONIQUES}) c JOIN chronique ch ON ch.id = c.id
             WHERE ch.nbmesures > 0) c
         WHERE n <= 2
        UNION SELECT DISTINCT chronique_id, 'avant 1900' FROM mesure WHERE date < '1900-01-01'
        UNION (SELECT m.chronique_id, 'lacunes à -9999' FROM mesure m
                 JOIN qualite q ON q.id = m.qualite_id
                WHERE q.ordre = 100 AND m.valeur = -9999 LIMIT 1)
        ORDER BY 1""")
    cases = []
    for cid, kind in picked:
        if kind == 'avant 1900':
            period = ['1816-01-01T00:00:00+00:00', '1821-01-01T00:00:00+00:00']
        else:
            period = ['2024-01-01T00:00:00+00:00', '2025-01-01T00:00:00+00:00']
        # Le format Vigilance n'est proposé nulle part dans l'ancien (Modal/export.html.twig) et
        # n'a pas de classe : il échoue des deux côtés.
        formats = ['bdoh'] if kind == 'discontinue' else ['bdoh', 'qtvar']
        for fmt in formats:
            # Le fuseau entre apostrophes, comme JobManager le passe à la commande.
            for tz in ["'+01:00'", "'+00:00'", "'-05:00'"]:
                options = ['--format', fmt, '--timezone', tz]
                cases.append(['bdoh:export:identical', *period, cid, *options])
                # Une chronique discontinue ne s'exporte qu'à l'identique (Modal/export.html.twig) ;
                # les autres sortes échouent des deux côtés.
                if fmt != 'bdoh' or tz != "'+01:00'" or kind == 'discontinue':
                    continue
                cases.append(['bdoh:export:instantaneous', '60', *period, cid, *options])
                for step in ['5', '7', '8', '9']:  # 1 heure, 1 jour, 1 mois, 1 an
                    cases.append(['bdoh:export:mean', step, *period, cid, *options])
                    cases.append(['bdoh:export:cumulative', step, *period, cid, *options])
    return cases


# --- Pages : récupération et extraction ----------------------------------------------------------

class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, *args):
        return None


class Client:
    """Un navigateur sans JavaScript, connecté ou non, qui ne suit pas les redirections."""

    def __init__(self, side, email, slug):
        self.side = side
        jar = http.cookiejar.CookieJar()
        self.opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(jar), NoRedirect)
        if email:
            self.get(f'/{slug}/login')
            status, headers, _ = self.get(f'/{slug}/login_check', urllib.parse.urlencode(
                {'_username': email, '_password': PASSWORD}).encode())
            if status != 302 or 'login' in headers.get('Location', ''):
                raise RuntimeError(f'connexion refusée pour {email} sur {side.url}')

    def get(self, path, data=None, headers=None):
        request = urllib.request.Request(self.side.url + path, data, headers or {})
        try:
            with self.opener.open(request, timeout=300) as r:
                return r.status, r.headers, r.read()
        except urllib.error.HTTPError as e:
            return e.code, e.headers, e.read()


class Extract(html.parser.HTMLParser):
    """Ce qu'une page montre, ligne à ligne : textes, liens, formulaires, attributs data-*."""

    BLOCKS = {'address', 'article', 'aside', 'blockquote', 'br', 'dd', 'div', 'dl', 'dt', 'fieldset',
              'footer', 'form', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'header', 'hr', 'label', 'legend',
              'li', 'nav', 'ol', 'option', 'p', 'pre', 'section', 'table', 'tbody', 'td', 'tfoot',
              'th', 'thead', 'title', 'tr', 'ul'}
    TOKEN = re.compile(r'(^|\[)_?(csrf_)?token\]?$')

    def __init__(self):
        super().__init__(convert_charrefs=True)
        self.lines, self.text, self.script, self.raw = [], [], None, None

    def add(self, *fields):
        self.lines.append(' '.join(f for f in fields if f))

    def flush(self):
        text = ' '.join(''.join(self.text).split())
        if text:
            self.add('T', text)
        self.text = []

    def handle_starttag(self, tag, attrs):
        a = {k: v or '' for k, v in attrs}
        if tag in self.BLOCKS:
            self.flush()
        if tag in ('script', 'style'):
            self.flush()
            self.raw = tag
            self.script = []
            if a.get('src'):
                self.add('S', a['src'])
        elif tag == 'a' and 'href' in a:
            self.add('A', a['href'])
        elif tag == 'form':
            self.add('F', a.get('method', 'get').lower(), a.get('action', ''))
        elif tag in ('input', 'button', 'select', 'textarea'):
            value = a.get('value', '')
            if self.TOKEN.search(a.get('name', '')) or a.get('type') == 'password':
                value = '<jeton>' if value else ''
            self.add('I', tag, a.get('type', ''), a.get('name', ''), value,
                     'checked' if 'checked' in a else '', 'disabled' if 'disabled' in a else '')
        elif tag == 'option':
            self.add('O', a.get('value', ''), 'selected' if 'selected' in a else '')
        elif tag == 'img':
            self.add('G', a.get('src', ''), a.get('alt', ''))
        elif tag == 'link':
            self.add('L', a.get('rel', ''), a.get('href', ''))
        data = [f'{k}={v}' for k, v in sorted(a.items()) if k.startswith('data-')]
        if data:
            self.add('D', tag, *data)

    def handle_endtag(self, tag):
        if tag == self.raw:
            code = ' '.join(''.join(self.script).split())
            if code and tag == 'script':
                self.add('J', code)
            self.raw = self.script = None
        elif tag in self.BLOCKS:
            self.flush()

    def handle_data(self, data):
        (self.script if self.raw else self.text).append(data)


QUERY = re.compile(r'(\S+?\?)([^\s#]*)')

NORMALIZE = [
    (re.compile(r'(/assets/[\w/.-]+?)\.[0-9a-f]{8,20}(\.\w+)'), r'\1\2'),  # empreintes des fichiers
    (re.compile(r'\bs[0-9a-f]{13}\b'), 's<uniqid>'),  # identifiants de formulaires de Sonata
    (re.compile(r'data:image/[\w+]+;base64,[\w+/=]+'), '<image>'),  # captcha
    (re.compile(r'(data-target=#[a-z]+-)\d+'), r'\1<n>'),  # fenêtres numérotées au hasard
]


def page_lines(site, status, headers, body):
    """Ce que montre une réponse, en lignes ; l'adresse de l'instance devient <site>."""
    lines = [f'statut {status}']
    if 300 <= status < 400:
        lines.append(f"redirection {headers.get('Location', '')}")
    kind = headers.get('Content-Type', '').split(';')[0]
    lines.append(f'type {kind}')
    if kind == 'text/html':
        parser = Extract()
        parser.feed(body.decode('utf-8', 'replace'))
        parser.flush()
        lines += parser.lines
    elif kind == 'application/json':
        try:
            lines += json.dumps(json.loads(body), indent=1, sort_keys=True, ensure_ascii=False).splitlines()
        except ValueError:
            lines += ['JSON illisible :', *body.decode('utf-8', 'replace').splitlines()]
    elif 'javascript' in kind:
        # Les traductions pour le JavaScript (/translations/…) : l'ordre des clés suit l'ordre de
        # chargement des catalogues, qui a changé ; triées, seules leurs valeurs comptent.
        lines += sorted(body.decode('utf-8', 'replace').splitlines())
    else:
        lines += body.decode('utf-8', 'replace').splitlines()
    for pattern, repl in NORMALIZE:
        lines = [pattern.sub(repl, line) for line in lines]
    # Symfony 7 ne code pas les paramètres d'adresse comme Symfony 2 (%20 ou +, %2C ou virgule…) :
    # même adresse, comparée sous une forme canonique (dev/differences.md).
    lines = [QUERY.sub(lambda m: m.group(1) + urllib.parse.urlencode(
                 urllib.parse.parse_qsl(m.group(2), keep_blank_values=True)), line)
             if line[:2] in ('A ', 'F ', 'D ', 'S ', 'L ', 'G ') else line
             for line in lines]
    return [line.replace(site, '<site>') for line in lines]


# --- Comparaison ---------------------------------------------------------------------------------

NUMBER = re.compile(r'-?\d+(?:[.,]\d+)?(?:[eE][-+]?\d+)?')


def same_but_rounding(a, b):
    """Deux lignes qui ne diffèrent que par des nombres écrits à une unité près du dernier chiffre,
    ou par -0 et 0 (règle « Arrondis à la limite »)."""
    if NUMBER.sub('#', a) != NUMBER.sub('#', b):
        return False
    for x, y in zip(NUMBER.findall(a), NUMBER.findall(b)):
        if x == y:
            continue
        if x.lstrip('-') == y.lstrip('-') and float(x.replace(',', '.')) == 0:
            continue
        decimals = len(x.split('.')[1]) if '.' in x else 0
        try:
            if abs(float(x.replace(',', '.')) - float(y.replace(',', '.'))) > 1.000001 * 10 ** -decimals:
                return False
        except ValueError:
            return False
    return True


def compare(old, new):
    """Écarts entre deux suites de lignes : (lignes de diff, paires de lignes à un arrondi près,
    ordre seul).

    Deux suites de même longueur se comparent ligne à ligne ; le diff complet, coûteux sur une
    longue page, ne sert que s'il reste un écart. « Ordre seul » : les mêmes lignes, ou les mêmes
    éléments d'une liste, dans un autre ordre (listes que l'ancien ne triait pas)."""
    rounding = []
    original, new = list(new), list(new)
    if len(old) == len(new):
        for k, (a, b) in enumerate(zip(old, new)):
            if a != b and same_but_rounding(a, b):
                new[k] = a
                rounding.append((a, b))
        if old == new:
            return [], rounding, False
    else:
        matcher = difflib.SequenceMatcher(None, old, new)
        for op, i1, i2, j1, j2 in matcher.get_opcodes():
            if op == 'replace' and i2 - i1 == j2 - j1:
                for k in range(i2 - i1):
                    if same_but_rounding(old[i1 + k], new[j1 + k]):
                        rounding.append((old[i1 + k], new[j1 + k]))
                        new[j1 + k] = old[i1 + k]
    # Le type de ligne (T, A…) reste en tête ; les éléments qui suivent se trient.
    key = lambda line: line[:2] + '##'.join(sorted(line[2:].split('##'))) if '##' in line else line
    sorted_old, sorted_new = sorted(map(key, old)), sorted(map(key, original))
    order_only = len(old) == len(new) and all(
        a == b or same_but_rounding(a, b) for a, b in zip(sorted_old, sorted_new))
    diff = list(difflib.unified_diff(old, new, 'ancien', 'nouveau', n=2, lineterm=''))
    return diff, rounding, order_only


class Report:
    def __init__(self, kind):
        self.dir = OUT / f"{datetime.datetime.now():%Y-%m-%d_%H%M%S}_{kind}"
        self.dir.mkdir(parents=True)
        self.counts = {'identiques': 0, 'à un arrondi près': 0, 'même contenu, autre ordre': 0, 'différents': 0}
        self.summary = []

    def add(self, name, old, new):
        diff, rounding, order_only = compare(old, new)
        if diff:
            key = 'même contenu, autre ordre' if order_only else 'différents'
        else:
            key = 'à un arrondi près' if rounding else 'identiques'
        self.counts[key] += 1
        if diff or rounding:
            slug = re.sub(r'[^\w.-]+', '_', name).strip('_')[:150]
            shown = [f'~ {a}\n~ {b}' for a, b in rounding[:20]]
            (self.dir / f'{slug}.diff').write_text('\n'.join([name, *diff, *shown, '']))
            self.summary.append(f"{key:<26} {name}" + (f'  ({len(rounding)} arrondis)' if rounding else ''))
        print('X' if key == 'différents' else '.', end='', flush=True)

    def close(self):
        text = '\n'.join([*(f'{n:>6} {k}' for k, n in self.counts.items()), '', *self.summary, ''])
        (self.dir / 'rapport.txt').write_text(text)
        print('\n' + text + f'Rapport : {self.dir}')


def run_pages(args):
    old, new = SIDES[args.old], SIDES[args.new]
    slug = query(args.base, "SELECT slug FROM observatoire ORDER BY id LIMIT 1")[0][0]
    paths = [p for p in page_cases(args.base) if args.only in p]
    report = Report('pages')
    with concurrent.futures.ThreadPoolExecutor(args.jobs) as pool:
        for profile, email in profiles(args.base).items():
            if args.profile and profile != args.profile:
                continue
            clients = Client(old, email, slug), Client(new, email, slug)

            def fetch(path):
                # Le choix de la langue change la session : il passe par une session à lui, sans
                # quoi les pages demandées en même temps changeraient de langue d'un côté seulement.
                pair = (Client(old, None, slug), Client(new, None, slug)) if path.startswith('/select_lang/') else clients
                return [page_lines(client.side.url, *client.get(path)) for client in pair]

            for path, lines in zip(paths, pool.map(fetch, paths)):
                report.add(f'{profile} {path}', *lines)
    report.close()


def run_exports(args):
    old, new = SIDES[args.old], SIDES[args.new]

    def run(side, case):
        r = subprocess.run([*side.console, *case, '--env=prod'], capture_output=True, text=True)
        lines = [f'code de sortie {r.returncode}', *r.stdout.splitlines(), *r.stderr.splitlines()]
        # Date de l'export et dossier temporaire changent à chaque fois.
        return [re.sub(r'^(Export du|Export of) .*? (au format|in format)', r'\1 <date> \2', line)
                for line in lines if not line.startswith('# Printing ')]

    cases = [c for c in export_cases(args.base) if args.only in ' '.join(c)]
    report = Report('exports')
    with concurrent.futures.ThreadPoolExecutor(args.jobs) as pool:
        both = lambda case: [run(old, case), run(new, case)]
        for case, outputs in zip(cases, pool.map(both, cases)):
            report.add(' '.join(case), *outputs)
    report.close()


# --- État de la base après les calculs ------------------------------------------------------------

NEW_SERVER = 'bdoh-db'  # PostgreSQL 17, où tournent l'ancien code (reference-17) et le nouveau
OLD_17_PARAMETERS = OUT.parent / 'app-17' / 'app' / 'config' / 'parameters.yml'


def state_commands(base):
    """Les calculs, choisis par des règles : une chaîne de trois chroniques calculées, recalculée
    depuis la première avec propagation ; une chronique convertie qui a une fille, avec propagation,
    et une sans fille ; les taux de remplissage d'une chronique continue."""
    first = lambda sql: query(base, sql, NEW_SERVER)[0][0]
    user = first("SELECT id FROM utilisateur WHERE roleadmin = 'FCT' ORDER BY id LIMIT 1")
    chain = first("""
        SELECT a.sortie_id FROM transformation a
          JOIN transformation b ON b.entree_id = a.sortie_id
          JOIN transformation c ON c.entree_id = b.sortie_id
          JOIN chronique e ON e.id = a.entree_id
         WHERE e.nbmesures > 0 ORDER BY a.sortie_id LIMIT 1""")
    converted = """
        SELECT cv.sortie_id FROM conversion cv JOIN chronique e ON e.id = cv.entree_id
         WHERE e.nbmesures > 0
           AND {} EXISTS (SELECT 1 FROM transformation t WHERE t.entree_id = cv.sortie_id)
         ORDER BY cv.sortie_id LIMIT 1"""
    continuous = first("SELECT id FROM chronique WHERE dtype = 'continue' AND nbmesures > 0 ORDER BY id LIMIT 1")
    return [
        ['bdoh:compute:chronique', chain, user, '--propagate'],
        ['bdoh:convert:chronique', first(converted.format('')), user, '--propagate'],
        ['bdoh:convert:chronique', first(converted.format('NOT')), user],
        ['bdoh:compute:filling_rates', continuous],
    ]


# Ce que les calculs écrivent, sans les dates du moment (mise à jour, création et exécution des
# tâches) ni la trace d'erreur des tâches, que BDOH ne lit pas (dev/differences.md) ; les mesures et
# les taux, en une empreinte par chronique, valeurs écrites au chiffre près.
STATE_QUERIES = {
    'chronique': """
        SELECT ((to_jsonb(c) - 'miseajour')
                || jsonb_build_object('miseajour récente', c.miseajour > now() - interval '1 hour'))::text
          FROM chronique c ORDER BY c.id""",
    'mesure': """
        SELECT chronique_id, count(*), md5(string_agg(
                   concat_ws(';', date, valeur, qualite_id, minimum, maximum, estcalculee), '|'
                   ORDER BY date, valeur))
          FROM mesure GROUP BY 1 ORDER BY 1""",
    'tauxremplissage': """
        SELECT chronique_id, count(*), md5(string_agg(concat_ws(';', debut, fin, taux, poids), '|'
                                                      ORDER BY debut, fin))
          FROM tauxremplissage GROUP BY 1 ORDER BY 1""",
    'historique': """
        SELECT (to_jsonb(h) - 'id' - 'date')::text FROM historique h WHERE h.id > {historique} ORDER BY h.id""",
    # La sortie d'erreur d'une tâche en échec : la console de Symfony 7 présente l'exception
    # autrement que celle de Symfony 2 (dev/differences.md) ; seul compte qu'elle en ait une.
    'jms_jobs': """
        SELECT ((to_jsonb(j) - 'createdat' - 'startedat' - 'checkedat' - 'executeafter' - 'closedat'
                 - 'workername' - 'runtime' - 'memoryusage' - 'memoryusagereal' - 'stacktrace')
                || CASE WHEN j.exitcode <> 0 AND j.erroroutput <> ''
                        THEN jsonb_build_object('erroroutput', '<erreur>') ELSE '{{}}' END)::text
          FROM jms_jobs j ORDER BY j.id""",
    'jms_job_related_entities': "SELECT * FROM jms_job_related_entities ORDER BY 1, 2, 3",
}


MAILPIT = 'http://172.30.26.25:8025'  # service mailpit de compose.yaml (make mail-start)


def mail_lines():
    """Les courriels retenus par Mailpit, du plus ancien au plus récent, pièces jointes dépliées."""
    get = lambda path: urllib.request.urlopen(MAILPIT + path, timeout=60).read()
    lines = []
    for summary in sorted(json.loads(get('/api/v1/messages'))['messages'], key=lambda m: m['Created']):
        m = json.loads(get(f"/api/v1/message/{summary['ID']}"))
        address = lambda a: f"{a['Name']} <{a['Address']}>"
        # Le texte sans ses fins de ligne finales, que Swiftmailer ajoute en l'encodant
        lines += [f"Sujet : {m['Subject']}", f"De : {address(m['From'])}",
                  'À : ' + ', '.join(map(address, m['To'])), *m['Text'].rstrip().splitlines()]
        for part in m['Attachments']:
            lines.append(f"Pièce jointe : {part['FileName']} ({part['ContentType']})")
            with zipfile.ZipFile(io.BytesIO(get(f"/api/v1/message/{summary['ID']}/part/{part['PartID']}"))) as z:
                for name in z.namelist():
                    content = z.read(name)
                    if name.endswith('.txt'):
                        lines += [f'== {name}', *(re.sub(r'^(Export du|Export of) .*? (au format|in format)',
                                                         r'\1 <date> \2', line)
                                                  for line in content.decode('utf-8', 'replace').splitlines())]
                    else:
                        lines.append(f'== {name} {hashlib.sha256(content).hexdigest()[:16]}')
    return lines


NEW_URL = 'postgresql://bdoh@db:5432/{}?serverVersion=17&charset=utf8'
ENV_LOCAL = HERE.parent.parent / '.env.local'  # la base du nouveau BDOH, comme l'écrit use-base.sh


def copy_base(template, base):
    """Une copie fraîche d'une base, sur le serveur PostgreSQL 17."""
    for sql in [f'DROP DATABASE IF EXISTS {base} WITH (FORCE)', f'CREATE DATABASE {base} TEMPLATE {template}']:
        subprocess.run(['docker', 'exec', NEW_SERVER, 'psql', '-U', 'postgres', '-v', 'ON_ERROR_STOP=1',
                        '-qc', sql], check=True)


def configure_old_17(text):
    """Écrit les paramètres de l'ancien code sur PostgreSQL 17, et vide son cache."""
    OLD_17_PARAMETERS.write_text(text)
    for command in [['cache:clear', '--no-warmup'], ['cache:warmup']]:
        subprocess.run([*SIDES['reference-17'].console, *command, '--env=prod'], check=True, capture_output=True)


@contextlib.contextmanager
def old_17_on(base, **params):
    """L'ancien code sur PostgreSQL 17 branché sur une base (et d'autres paramètres), le temps du bloc."""
    original = OLD_17_PARAMETERS.read_text()
    edited = original
    for key, value in {'database_name': base, **params}.items():
        edited = re.sub(rf'^(    {key}: *).*$', rf'\g<1>{value}', edited, flags=re.M)
    configure_old_17(edited)
    try:
        yield SIDES['reference-17'].console
    finally:
        configure_old_17(original)


@contextlib.contextmanager
def new_on(base, template, *env):
    """Le nouveau BDOH branché sur une base le temps du bloc, ses pages par .env.local, sa console par
    l'environnement ; il lit ensuite de nouveau la base template."""
    ENV_LOCAL.write_text(f'DATABASE_URL="{NEW_URL.format(base)}"\n')
    try:
        yield ['docker', 'exec', '-e', f'DATABASE_URL={NEW_URL.format(base)}',
               *[x for e in env for x in ('-e', e)], 'bdoh-app', 'php', 'bin/console']
    finally:
        ENV_LOCAL.write_text(f'DATABASE_URL="{NEW_URL.format(template)}"\n')


def drain(console, runner, base, jobs=1, rounds=30):
    """La file de tâches, jusqu'à ce qu'elle soit vide ; par défaut une tâche à la fois, parce que
    l'ordre où elles finissent décide de celui des tâches qu'elles créent."""
    for _ in range(rounds):
        if query(base, "SELECT count(*) FROM jms_jobs WHERE state IN ('new', 'pending', 'running')",
                 NEW_SERVER)[0][0] == '0':
            return
        subprocess.run([*console, runner, '--max-runtime=10', f'--max-concurrent-jobs={jobs}', '--env=prod'],
                       capture_output=True)


def state_lines(base, historique, tables=STATE_QUERIES):
    """L'état d'une copie, table par table ; les dates du moment, dans les arguments des taux de
    remplissage d'une chronique vide, sont normalisées."""
    days = '|'.join(str(datetime.date.today() - datetime.timedelta(days=n)) for n in (0, 1))
    return {table: [re.sub(f'(?:{days})T[\\d:]+', '<maintenant>', '\t'.join(row))
                    for row in query(base, STATE_QUERIES[table].format(historique=historique), NEW_SERVER)]
            for table in tables}


def run_state(args):
    template = args.base
    historique = query(template, 'SELECT coalesce(max(id), 0) FROM historique', NEW_SERVER)[0][0]
    commands = state_commands(template)
    email = query(template, "SELECT email FROM utilisateur WHERE roleadmin = 'FCT' ORDER BY id LIMIT 1", NEW_SERVER)[0][0]
    cid, slug, station, code = query(template, f"""
        SELECT c.id, c.slug, c.station, c.code FROM ({CHRONIQUES}) c JOIN chronique ch ON ch.id = c.id
         WHERE c.dtype = 'continue' AND ch.nbmesures > 0 ORDER BY c.id LIMIT 1""", NEW_SERVER)[0]
    # Vider des mesures revient aux gestionnaires de l'observatoire (BdohRightVoter::canRemoveData)
    manager = query(template, f"""
        SELECT u.email FROM utilisateur u JOIN role r ON r.utilisateur_id = u.id
          JOIN observatoire o ON o.id = r.observatoire_id
         WHERE r.valeur = 'gestionnaire' AND o.slug = '{slug}' AND (u.finacces IS NULL OR u.finacces > now())
         ORDER BY u.id LIMIT 1""", NEW_SERVER)[0][0]
    def play(side, console, runner, base):
        """Les calculs, les exports demandés par le formulaire, une tâche annulée, une semaine de
        mesures vidée avec propagation, une tâche relancée, la file jusqu'au bout ; rend ce que
        chaque étape a répondu."""
        log = []
        for command in commands:
            r = subprocess.run([*console, *command, '--env=prod'], capture_output=True, text=True)
            log += [' '.join(command), f'code de sortie {r.returncode}', *r.stdout.splitlines()]

        client = Client(side, email, slug)
        post = lambda path, fields: client.get(path, urllib.parse.urlencode(fields).encode(),
                                               {'Referer': f'{side.url}/{slug}/'})
        form = {'beginDate': '2024-01-01T00:00:00Z', 'endDate': '2024-02-01T00:00:00Z',
                'exportFormat': 'Bdoh', 'exportType': 'identical', 'instantStep': '60',
                'meanCumulStep': '7', 'timezone': '1', 'chroniques[]': cid, 'termsOfUses': '1'}
        for fields in [form, dict(form, exportType='mean'), dict(form, exportFormat='Vigilance'),
                       dict(form, exportFormat='Qtvar')]:
            status, headers, _ = post(f'/{slug}/mesure/export', fields)
            log.append(f"export {fields['exportFormat']} {fields['exportType']} : {status} "
                       f"{headers.get('Location', '').replace(side.url, '')}")
        last = query(base, "SELECT max(id) FROM jms_jobs", NEW_SERVER)[0][0]
        status, headers, _ = post(f'/{slug}/jobs/{last}', {'action': 'cancel'})
        log.append(f"annulation : {status} {headers.get('Location', '').replace(side.url, '')}")

        drain(console, runner, base)
        page = f'/{slug}/{station}/{code}'
        manager_client = Client(side, manager, slug)
        status, headers, _ = manager_client.get(f'{page}/empty', urllib.parse.urlencode(
            {'beginDate': '2024-01-05T00:00:00Z', 'endDate': '2024-01-12T00:00:00Z', 'propagate': 'propagate'}
        ).encode(), {'Referer': side.url + page})
        log.append(f"vider une période : {status} {headers.get('Location', '').replace(side.url, '')}")
        log += page_lines(side.url, *manager_client.get(page))  # avec le message qui le confirme
        failed = query(base, "SELECT min(id) FROM jms_jobs WHERE state = 'failed'", NEW_SERVER)[0][0]
        status, headers, _ = post(f'/{slug}/jobs/{failed}', {'action': 'retry'})
        log.append(f"relance : {status} {headers.get('Location', '').replace(side.url, '')}")
        drain(console, runner, base)
        return log

    sides = {}
    for name, base in [('ancien', 'bdoh_state_old'), ('nouveau', 'bdoh_state_new')]:
        copy_base(template, base)
        urllib.request.urlopen(urllib.request.Request(MAILPIT + '/api/v1/messages', method='DELETE'), timeout=60)
        if name == 'ancien':
            with old_17_on(base, mailer_transport='smtp', mailer_host='mailpit', mailer_port='1025') as console:
                log = play(SIDES['reference-17'], console, 'jms-job-queue:run', base)
        else:
            with new_on(base, template, 'MAILER_DSN=smtp://mailpit:1025') as console:
                log = play(SIDES['new'], console, 'bdoh:jobs:run', base)
        sides[name] = {'étapes': log, 'courriels': mail_lines(), **state_lines(base, historique)}

    report = Report('state')
    for part in sides['ancien']:
        report.add(part, sides['ancien'][part], sides['nouveau'][part])
    report.close()

    # Les mêmes dates dans les deux copies, pour en comparer les pages de suivi des tâches
    for base in ['bdoh_state_old', 'bdoh_state_new']:
        query(base, """
            UPDATE jms_jobs SET createdat = timestamp '2026-01-01' + id * interval '1 minute',
                   executeafter = timestamp '2026-01-01' + id * interval '1 minute',
                   startedat = CASE WHEN startedat IS NOT NULL THEN timestamp '2026-01-01' + id * interval '1 minute' END,
                   checkedat = CASE WHEN checkedat IS NOT NULL THEN timestamp '2026-01-01' + id * interval '1 minute' END,
                   closedat = CASE WHEN closedat IS NOT NULL THEN timestamp '2026-01-01' + id * interval '1 minute' END,
                   runtime = CASE WHEN runtime IS NOT NULL THEN 0 END""", NEW_SERVER)
    print(f'Les instances lisent de nouveau {template}. Les deux copies restent pour examen :'
          ' bdoh_state_old et bdoh_state_new ; pour comparer les pages des tâches, y brancher'
          ' reference-17 et new, puis :\n'
          '  dev/compare/compare.py pages --old reference-17 --new new --base bdoh_state_old'
          ' --server bdoh-db --only /jobs')

def run_queue(args):
    """L'essai de l'audit (constat 3) : trois recalculs rapprochés avec propagation, sur une
    chronique calculée, sur une chronique qui en dépend, puis de nouveau sur la première, menés par
    le nouvel exécuteur deux à la fois comme en production, puis l'un après l'autre, chacun sur sa
    copie de BASE. Attendu : le même état final, et jamais deux tâches d'une même file en même
    temps."""
    template = args.base
    first = lambda sql: query(template, sql, NEW_SERVER)[0]
    parent, child = first("""
        SELECT a.sortie_id, b.sortie_id FROM transformation a
          JOIN transformation b ON b.entree_id = a.sortie_id
          JOIN transformation c ON c.entree_id = b.sortie_id
          JOIN chronique e ON e.id = a.entree_id
         WHERE e.nbmesures > 0 ORDER BY a.sortie_id, b.sortie_id LIMIT 1""")
    user, = first("SELECT id FROM utilisateur WHERE roleadmin = 'FCT' ORDER BY id LIMIT 1")

    def enqueue(base, chronique):
        """Une tâche de recalcul, écrite comme JobManager::enqueueChronicleComputation l'écrit."""
        query(base, f"""
            WITH job AS (
                INSERT INTO jms_jobs (id, state, queue, priority, createdat, executeafter, command, args,
                                      maxruntime, maxretries)
                VALUES (nextval('jms_jobs_id_seq'), 'pending', 'job_queue_chronique_{chronique}', -5, now(),
                        now() - interval '1 second', 'bdoh:compute:chronique',
                        '["--propagate", "{chronique}", "{user}"]', 0, 0)
                RETURNING id)
            INSERT INTO jms_job_related_entities (job_id, related_class, related_id)
            SELECT job.id, x.class, x.id FROM job, (VALUES
                ('Irstea\\BdohDataBundle\\Entity\\ChroniqueCalculee', '{{"id":{chronique}}}'),
                ('Irstea\\BdohDataBundle\\Entity\\Observatoire', (
                    SELECT json_build_object('id', se.observatoire_id)::jsonb::text FROM chronique c
                      JOIN stations_sites ss ON ss.station_id = c.station_id
                      JOIN siteexperimental se ON se.id = ss.site_id WHERE c.id = {chronique} LIMIT 1))
            ) AS x(class, id)""", NEW_SERVER)

    sides = {}
    for name, base, jobs in [('deux à la fois', 'bdoh_queue_parallel', 2), ('une à une', 'bdoh_queue_serial', 1)]:
        copy_base(template, base)
        for chronique in [parent, child, parent]:
            enqueue(base, chronique)
        console = ['docker', 'exec', '-e', f'DATABASE_URL={NEW_URL.format(base)}', 'bdoh-app', 'php', 'bin/console']
        drain(console, 'bdoh:jobs:run', base, jobs=jobs, rounds=60)
        today = datetime.date.today().isoformat()
        sides[name] = state_lines(base, 0, ['chronique', 'mesure', 'tauxremplissage'])
        # Les tâches, sans leur ordre, qui dépend de celui où elles finissent
        sides[name]['tâches'] = sorted(' '.join(row) for row in query(base, """
            SELECT command, args::text, state, exitcode FROM jms_jobs""", NEW_SERVER))
        sides[name]['tâches'] = [re.sub(today + r'T[\d:]+', '<maintenant>', line) for line in sides[name]['tâches']]
        overlaps = query(base, """
            SELECT a.queue, a.id, b.id FROM jms_jobs a JOIN jms_jobs b
              ON b.queue = a.queue AND b.id > a.id
             AND b.startedat < a.closedat AND a.startedat < b.closedat""", NEW_SERVER)
        parallel = query(base, """
            SELECT count(*) FROM jms_jobs a JOIN jms_jobs b ON b.id > a.id
             AND b.startedat < a.closedat AND a.startedat < b.closedat""", NEW_SERVER)[0][0]
        print(f'{name} : {len(sides[name]["tâches"])} tâches, {parallel} paires menées en même temps,'
              f' {len(overlaps)} dans une même file {overlaps or ""}')

    report = Report('queue')
    for part in sides['une à une']:
        report.add(part, sides['une à une'][part], sides['deux à la fois'][part])
    report.close()


def multipart(fields, files):
    """Le corps d'un formulaire multipart/form-data, et son type."""
    boundary = '----bdoh-compare-' + hashlib.sha256(repr((fields, files)).encode()).hexdigest()[:16]
    parts = []
    for name, value in fields.items():
        parts.append(f'--{boundary}\r\nContent-Disposition: form-data; name="{name}"\r\n\r\n{value}\r\n'.encode())
    for name, (filename, content) in files.items():
        parts.append(f'--{boundary}\r\nContent-Disposition: form-data; name="{name}"; filename="{filename}"\r\n'
                     'Content-Type: text/plain\r\n\r\n'.encode() + content + b'\r\n')
    parts.append(f'--{boundary}--\r\n'.encode())
    return b''.join(parts), f'multipart/form-data; boundary={boundary}'


def step_lines(site, status, headers, body):
    """Une réponse d'étape d'import : du JSON dont les fragments HTML se lisent comme les pages."""
    if not headers.get('Content-Type', '').startswith('application/json'):
        return page_lines(site, status, headers, body)
    lines = [f'statut {status}']
    for key, value in sorted(json.loads(body).items()):
        if isinstance(value, str) and '<' in value:
            fragment = page_lines(site, 200, {'Content-Type': 'text/html'}, value.encode())[2:]
            lines += [f'{key} :', *fragment]
        else:
            lines.append(f'{key} : {json.dumps(value, ensure_ascii=False, sort_keys=True)}')
    return [line.replace(site, '<site>') for line in lines]


def import_cases(base):
    """Les imports à jouer, tirés de la base : les mesures de deux semaines de janvier 2024 d'une
    chronique continue, au format BDOH, et leurs variantes, chacune décalée sur un mois de 2025
    pour ne pas en recouvrir une autre ; deux sur la période existante, pour écraser ou garder."""
    cid, slug, station, code, unite = query(base, f"""
        SELECT c.id, c.slug, c.station, c.code, u.libelle FROM ({CHRONIQUES}) c
          JOIN chronique ch ON ch.id = c.id JOIN unite u ON u.id = ch.unite_id
         WHERE c.dtype = 'continue' AND EXISTS (SELECT 1 FROM mesure m WHERE m.chronique_id = c.id
               AND m.date >= '2024-01-01' AND m.date < '2024-01-15')
         ORDER BY c.id LIMIT 1""", NEW_SERVER)[0]

    def measures(months):
        return query(base, f"""
            SELECT to_char(m.date + interval '1 hour' + interval '{months} month', 'DD/MM/YYYY HH24:MI:SS'),
                   coalesce(m.valeur::text, '-9999'), q.code, coalesce(m.minimum::text, ''), coalesce(m.maximum::text, '')
              FROM mesure m JOIN qualite q ON q.id = m.qualite_id
             WHERE m.chronique_id = {cid} AND m.date >= '2024-01-01' AND m.date < '2024-01-15'
             ORDER BY m.date""", NEW_SERVER)

    def bdoh(rows, tz='UTC+01', unit=unite, st=station, minmax=False, eol='\n'):
        head = ['Station;Fuseau;Chronique;Unite', f'{st};{tz};{code};{unit}',
                'DateHeure;Valeur;Qualite' + (';Min;Max' if minmax else '')]
        body = [';'.join(r if minmax else r[:3]) for r in rows]
        return (eol.join(head + body) + eol).encode()

    rows = lambda months: [list(r) for r in measures(months)]
    cases = []
    add = lambda name, content, import_type='keepExisting': cases.append((name, content, import_type))
    add('nouvelle période', bdoh(rows(12)))
    add('fins de ligne Windows', bdoh(rows(13), eol='\r\n'))
    spaced = rows(14)
    add('espaces autour des champs', bdoh([[f' {x} ' for x in r] for r in spaced]))
    add('virgule décimale', bdoh([[r[0], r[1] if r[1] == '-9999' else f'{float(r[1]) + 0.5:g}'.replace('.', ','),
                                   *r[2:]] for r in rows(15)]))
    invalid = rows(16)
    invalid[1][1] = 'abc'
    add('valeur invalide', bdoh(invalid))
    duplicated = rows(17)
    add('date en double', bdoh([duplicated[0], *duplicated]))
    add('fuseau UTC-05', bdoh(rows(18), tz='UTC-05'))
    swapped = rows(19)
    for r in swapped:
        if r[1] != '-9999':
            r[3], r[4] = str(float(r[1]) + 1), str(float(r[1]) - 1)
    add('minimum au-dessus du maximum', bdoh(swapped, minmax=True))
    future = rows(20)
    future[-1][0] = '01/01/2030 00:00:00'
    add('date future', bdoh(future))
    unknown = rows(21)
    unknown[0][2] = 'zz'
    add('qualité inconnue', bdoh(unknown))
    add('mauvaise unité', bdoh(rows(22), unit='inconnue'))
    add('station inconnue', bdoh(rows(23), st='INCONNUE'))
    changed = [[r[0], str(float(r[1]) + 1) if r[1] != '-9999' else r[1], *r[2:]] for r in rows(0)]
    add('période existante, écraser', bdoh(changed), 'overwriteExisting')
    add('période existante, garder', bdoh(rows(0)), 'keepExisting')
    return cid, slug, cases


def run_imports(args):
    """Chaque import, étape par étape, par l'ancien code sur PostgreSQL 17 et par le nouveau, chacun
    sur sa copie de BASE, au nom d'un gestionnaire de l'observatoire ; puis la file de tâches, et
    l'état des deux copies."""
    template = args.base
    historique = query(template, 'SELECT coalesce(max(id), 0) FROM historique', NEW_SERVER)[0][0]
    cid, slug, cases = import_cases(template)
    cases = [c for c in cases if args.only in c[0]]
    manager = query(template, f"""
        SELECT u.email FROM utilisateur u JOIN role r ON r.utilisateur_id = u.id
          JOIN observatoire o ON o.id = r.observatoire_id
         WHERE r.valeur = 'gestionnaire' AND o.slug = '{slug}' AND (u.finacces IS NULL OR u.finacces > now())
         ORDER BY u.id LIMIT 1""", NEW_SERVER)[0][0]
    bases = {'ancien': 'bdoh_imports_old', 'nouveau': 'bdoh_imports_new'}
    for base in bases.values():
        copy_base(template, base)

    report = Report('imports')
    with old_17_on(bases['ancien']) as old_console, new_on(bases['nouveau'], template) as new_console:
        sides = {'ancien': (SIDES['reference-17'], old_console, 'jms-job-queue:run'),
                 'nouveau': (SIDES['new'], new_console, 'bdoh:jobs:run')}
        clients = {name: Client(side, manager, slug) for name, (side, _, _) in sides.items()}

        def play(name, case):
            side, console, runner = sides[name]
            client = clients[name]
            title, content, import_type = case
            ajax = {'X-Requested-With': 'XMLHttpRequest'}
            post = lambda step, fields: client.get(f'/{slug}/admin/mesure/import/{step}',
                                                   urllib.parse.urlencode(fields).encode(), ajax)
            log = []
            body, kind = multipart({'file-format': 'Bdoh'}, {'file': ('import.txt', content)})
            response = client.get(f'/{slug}/admin/mesure/import/step2', body, {**ajax, 'Content-Type': kind})
            log += ['== étape 2', *step_lines(side.url, *response)]
            data = json.loads(response[2]) if response[0] == 200 else {}
            if data.get('action') == 'step2':
                # Les chroniques lues dans l'en-tête, gardées toutes, comme le fait le JavaScript
                chroniques = re.findall(r'<li name="([^"]+)"', data['view'])
                response = post('step3', {'timezone': '', **{f'chroniques[{i}]': c for i, c in enumerate(chroniques)}})
                log += ['== étape 3', *step_lines(side.url, *response)]
                data = json.loads(response[2]) if response[0] == 200 else {}
            if data.get('action') == 'step3':
                response = post('step4', {f'importTypes[{cid}]': import_type, 'propagate': 'propagate'})
                log += ['== étape 4', *step_lines(side.url, *response)]
            drain(console, runner, bases[name])
            return log

        for case in cases:
            report.add(f'import {case[0]}', play('ancien', case), play('nouveau', case))
        for name in sides:
            sides[name] = state_lines(bases[name], historique)
    for table in sides['ancien']:
        report.add(f'état {table}', sides['ancien'][table], sides['nouveau'][table])
    report.close()
    print(f'Les instances lisent de nouveau {template}. Les deux copies restent pour examen :'
          f' {bases["ancien"]} et {bases["nouveau"]}.')


def main():
    global CASES_SERVER
    parser = argparse.ArgumentParser(description=__doc__.split('\n')[0])
    parser.add_argument('what', choices=['pages', 'exports', 'state', 'queue', 'imports'])
    parser.add_argument('--old', default='reference', choices=SIDES)
    parser.add_argument('--new', default='reference', choices=SIDES)
    parser.add_argument('--base', default='bdoh_small', help='base où tirer les cas')
    parser.add_argument('--server', default=CASES_SERVER, help='conteneur du serveur de cette base')
    parser.add_argument('--only', default='', help='ne garder que les cas qui contiennent ce texte')
    parser.add_argument('--jobs', type=int, default=6, help='requêtes menées en parallèle')
    parser.add_argument('--profile', help='un seul profil (anonyme, utilisateur, gestionnaire, administrateur)')
    args = parser.parse_args()
    CASES_SERVER = args.server
    {'pages': run_pages, 'exports': run_exports, 'state': run_state, 'queue': run_queue,
     'imports': run_imports}[args.what](args)


if __name__ == '__main__':
    sys.exit(main())
