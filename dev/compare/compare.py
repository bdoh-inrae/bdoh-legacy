#!/usr/bin/env python3
"""Compare deux BDOH : mêmes requêtes, sorties comparées.

    dev/compare/compare.py pages   [--old SIDE] [--new SIDE] [--base BASE] [--only TEXTE]
    dev/compare/compare.py exports [--old SIDE] [--new SIDE] [--base BASE] [--only TEXTE]
    dev/compare/compare.py state   [--base BASE]
    dev/compare/compare.py queue   [--base BASE]
    dev/compare/compare.py imports [--base BASE] [--only TEXTE]
    dev/compare/compare.py accounts [--base BASE]
    dev/compare/compare.py admin   [--base BASE] [--only TEXTE]

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
import html
import html.parser
import http.cookiejar
import io
import json
import re
import subprocess
import sys
import time
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
    login_link: bool = False  # connexion par un lien (nouveau BDOH), et non par mot de passe


SIDES = {
    # La console tourne dans le conteneur des pages : celui de la file de tâches se relance toutes
    # les heures (--max-runtime, comme en production), ce qui couperait une commande en cours.
    'reference': Side('http://172.30.25.10',
                      ['docker', 'exec', 'bdoh-reference-app', 'php', 'app/console']),
    # Le nouveau BDOH (service app de compose.yaml), sur PostgreSQL 17.
    'new': Side('http://172.30.26.10', ['docker', 'exec', 'bdoh-app', 'php', 'bin/console'], login_link=True),
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
        # Les pages des imports et des transformations (module 4)
        paths += [f'/{slug}/admin/{p}' for p in ['mesure/import', 'controle/import', 'shape/import',
                                                  'transformation']]
        # Les pages de comptes (module 5) ; les droits du premier compte qui a un rôle sur l'observatoire
        paths += [f'/{slug}/{p}' for p in ['user/new', 'user/edit', 'user/all', 'resetting/request',
                                           'resetting/check-email', 'besoin/new']]
        someone = query(base, f"""
            SELECT u.email FROM utilisateur u JOIN role r ON r.utilisateur_id = u.id
              JOIN observatoire o ON o.id = r.observatoire_id WHERE o.slug = '{slug}' ORDER BY u.id LIMIT 1""")
        paths += [f'/{slug}/user/right/{row[0]}' for row in someone]
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

    linked = {}  # seconde du dernier lien de connexion créé, par compte

    def __init__(self, side, email, slug, password=PASSWORD):
        self.side = side
        self.jar = jar = http.cookiejar.CookieJar()
        self.opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(jar), NoRedirect)
        if email and side.login_link:
            # Un lien créé par la console, comme celui du courriel. Deux liens d'un même compte créés
            # dans la même seconde sont le même lien, qui ne sert qu'une fois : on attend la suivante.
            while int(time.time()) == Client.linked.get(email):
                time.sleep(0.1)
            Client.linked[email] = int(time.time())
            r = subprocess.run([*side.console, 'security:users:login-link', email, slug, '--env=prod'],
                               capture_output=True, text=True)
            status, headers = self.follow_login_link(r.stdout.strip()) if r.returncode == 0 else (None, {})
        elif email:
            self.get(f'/{slug}/login')
            status, headers, _ = self.get(f'/{slug}/login_check', urllib.parse.urlencode(
                {'_username': email, '_password': password}).encode())
        if email and (status != 302 or 'login' in headers.get('Location', '')):
            raise RuntimeError(f'connexion refusée pour {email} sur {side.url}')

    def follow_login_link(self, url):
        """Ouvre un lien de connexion, puis envoie le bouton de la page qu'il ouvre ; rend la réponse."""
        parts = urllib.parse.urlsplit(html.unescape(url))
        path = f'{parts.path}?{parts.query}'
        form = FormFields(parts.path)
        form.feed(self.get(path)[2].decode('utf-8', 'replace'))
        status, headers, _ = self.get(parts.path, form.filled({}))
        return status, headers

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
    TOKEN = re.compile(r'(^|\[)_?(sonata_)?(csrf_)?token\]?$')
    # Le câblage du JavaScript de Sonata (Stimulus, arbre du menu d'AdminLTE 2.4, filtres des listes,
    # texte d'attente de select2, que l'option vide de la liste montre déjà), qui ne montre rien (dev/differences.md, « Administration sur Sonata 4 ») ; data-toggle vaut
    # advanced-filter dans Sonata 3, d'autres valeurs aussi dans Sonata 4
    WIRING = re.compile(r'data-(controller|action|widget|filter|placeholder|[\w-]+-(target|value|outlet|class|param))$')

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
        data = [f'{k}={v}' for k, v in sorted(a.items())
                if k.startswith('data-') and not self.WIRING.match(k) and (k, v) != ('data-toggle', 'advanced-filter')]
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
    (re.compile(r'(/bundles/[\w/.-]+)\?v=\d+'), r'\1'),  # version des fichiers des bundles
    # Les fichiers propres aux pages de l'administration : compilés dans assets/admin/ par l'ancien,
    # servis tels quels par le nouveau depuis le bundle (dev/decisions.md)
    (re.compile(r'^(S|L stylesheet) /bundles/irsteabdohadmin/crud/'), r'\1 /assets/admin/crud/'),
    # Sonata 4 nomme le bouton de suppression, que Sonata 3 ne nommait pas : son nom n'est pas lu
    (re.compile(r'^I button submit btn_delete$'), 'I button submit'),
    # Les opérateurs des filtres sur une entité liée, mal nommés par Sonata 3 (dev/differences.md,
    # « Filtre des listes mal nommé »)
    (re.compile(r'^T Est égal à$'), 'T est égal à'),
    (re.compile(r'^T Ne contient pas$'), "T n'est pas égal à"),
    (re.compile(r'(?<![0-9A-Za-z])s[0-9a-f]{13}(?![0-9a-f])'), 's<uniqid>'),  # identifiants de formulaires de Sonata
    (re.compile(r'data:image/[\w+]+;base64,[\w+/=]+'), '<image>'),  # captcha
    (re.compile(r'(data-target=#[a-z]+-)\d+'), r'\1<n>'),  # fenêtres numérotées au hasard
]


# Les fichiers communs de Sonata, que chaque page de l'administration charge : ceux de Sonata 3,
# compilés avec ceux de BDOH par l'ancien, ceux de Sonata 4 et ceux de BDOH à part dans le nouveau
# (dev/differences.md, « Administration sur Sonata 4 »).
SONATA_PAGE = re.compile(r'^S /(assets/admin/sonata\.js|bundles/sonataadmin/app\.js)$')
SONATA_ASSETS = re.compile(r'^((L stylesheet|S) /(assets/(admin/sonata|manifest|vendor)\.(css|js)$|'
                           r'bundles/(sonataadmin|sonataform|sonatacore|irsteabdohadmin/(sonata|lib))/)'
                           r'|J window\.SONATA_CONFIG = )')


# Les scripts des champs de Sonata (complétion automatique, fenêtres d'ajout et de choix d'une entité
# liée), propres à chaque version de Sonata
SONATA_AUTOCOMPLETE = re.compile(r'^J (jQuery\(function \(\$\) \{ (// Select2 v3 does not used same input as v4|'
                                 r'var autocompleteInput = )|var field_dialog_)')


def canonical_query(query, per_page_option=False):
    """Les paramètres d'une adresse sous une forme canonique : triés (Sonata 4 ne les écrit pas dans
    l'ordre de Sonata 3), sans le mode de liste par défaut que Sonata 4 ajoute aux liens de tri ;
    dans le choix du nombre de lignes par page, sans le tri, que Sonata 3 y omettait quand il était
    celui par défaut."""
    pairs = urllib.parse.parse_qsl(query, keep_blank_values=True)
    pairs = [(k, v) for k, v in pairs if (k, v) != ('_list_mode', 'list')
             and not (per_page_option and k == 'filter[_sort_by]')]
    return urllib.parse.urlencode(sorted(pairs))


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
    if any(SONATA_PAGE.match(line) for line in lines):
        lines = [line for line in lines if not SONATA_ASSETS.match(line) and not SONATA_AUTOCOMPLETE.match(line)]
        # Le champ à complétion automatique : Sonata 3 y ajoutait un champ de texte sans nom, pour select2 3
        lines = [line for k, line in enumerate(lines)
                 if not (line == 'I input text' and lines[k + 1:k + 3] == ['I select', 'D select data-sonata-select2=false'])]
    # Symfony 7 ne code pas les paramètres d'adresse comme Symfony 2 (%20 ou +, %2C ou virgule…) :
    # même adresse, comparée sous une forme canonique (dev/differences.md).
    lines = [QUERY.sub(lambda m: m.group(1) + canonical_query(
                 m.group(2), line.startswith('O ') and 'filter%5B_per_page%5D' in line), line)
             if line[:2] in ('A ', 'F ', 'D ', 'S ', 'L ', 'G ', 'O ') else line
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
        SELECT (to_jsonb(h) - 'id' - 'date' - 'datecreation')::text FROM historique h WHERE h.id > {historique}
         ORDER BY h.id""",
    # La sortie d'erreur d'une tâche en échec : la console de Symfony 7 présente l'exception
    # autrement que celle de Symfony 2 (dev/differences.md) ; seul compte qu'elle en ait une.
    'jms_jobs': """
        SELECT ((to_jsonb(j) - 'createdat' - 'startedat' - 'checkedat' - 'executeafter' - 'closedat'
                 - 'workername' - 'runtime' - 'memoryusage' - 'memoryusagereal' - 'stacktrace')
                || CASE WHEN j.exitcode <> 0 AND j.erroroutput <> ''
                        THEN jsonb_build_object('erroroutput', '<erreur>') ELSE '{{}}' END)::text
          FROM jms_jobs j ORDER BY j.id""",
    'jms_job_related_entities': "SELECT * FROM jms_job_related_entities ORDER BY 1, 2, 3",
    'bareme': "SELECT (to_jsonb(b) - 'datecreation')::text FROM bareme b ORDER BY b.id",
    'station': "SELECT id, code, ST_AsEWKT(point), altitude FROM station ORDER BY id",
    'transformation': "SELECT * FROM transformation ORDER BY id",
    'jeubareme': "SELECT (to_jsonb(j) - 'datecreation')::text FROM jeubareme j ORDER BY j.id",
    'baremejeubareme': "SELECT * FROM baremejeubareme ORDER BY id",
    'bassin': "SELECT id, observatoire_id, nom, aire, md5(ST_AsEWKT(perimetre)), stationexutoire_id FROM bassin ORDER BY id",
    'courseau': "SELECT id, observatoire_id, nom, codehydro, classification, md5(ST_AsEWKT(trace)) FROM courseau ORDER BY id",
    'pointcontrole': """
        SELECT chronique_id, count(*), md5(string_agg(concat_ws(';', date, valeur, qualite_id, minimum, maximum), '|'
                                                      ORDER BY date))
          FROM pointcontrole GROUP BY 1 ORDER BY 1""",
}


MAILPIT = 'http://172.30.26.25:8025'  # service mailpit de compose.yaml (make mail-start)


def mail_lines(ordered=True):
    """Les courriels retenus par Mailpit, du plus ancien au plus récent (ou rangés par leur texte,
    quand leur ordre ne compte pas), pièces jointes dépliées."""
    get = lambda path: urllib.request.urlopen(MAILPIT + path, timeout=60).read()
    messages = []
    for summary in sorted(json.loads(get('/api/v1/messages'))['messages'], key=lambda m: m['Created']):
        m = json.loads(get(f"/api/v1/message/{summary['ID']}"))
        lines = []
        messages.append(lines)
        address = lambda a: f"{a['Name']} <{a['Address']}>"
        # Les destinataires triés : leur ordre suit celui d'une requête sans tri. Le texte sans ses fins
        # de ligne finales, que Swiftmailer ajoute en l'encodant ; un courriel en HTML, lu comme une page.
        lines += [f"Sujet : {m['Subject']}", f"De : {address(m['From'])}",
                  'À : ' + ', '.join(sorted(map(address, m['To']))),
                  *(['Cc : ' + ', '.join(sorted(map(address, m['Cc'])))] if m.get('Cc') else []),
                  *m['Text'].rstrip().splitlines()]
        if m['HTML']:
            lines += page_lines(MAILPIT, 200, {'Content-Type': 'text/html'}, m['HTML'].encode())[2:]
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
    return [line for lines in (messages if ordered else sorted(messages)) for line in lines]


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
    """Le nouveau BDOH branché sur une base (et d'autres variables) le temps du bloc, ses pages par
    .env.local, sa console par l'environnement ; il lit ensuite de nouveau la base template."""
    ENV_LOCAL.write_text(f'DATABASE_URL="{NEW_URL.format(base)}"\n' + ''.join(f'{e}\n' for e in env))
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
    """L'état d'une copie, table par table ; les dates du moment sont normalisées : dans les arguments
    des taux de remplissage d'une chronique vide, et dans les descriptions de l'historique (« Créé le
    26/10/2026 13:18:50 UTC »), que les deux côtés écrivent à une seconde d'écart."""
    recent = [datetime.date.today() - datetime.timedelta(days=n) for n in (0, 1)]
    moment = re.compile('|'.join([*(f'{d}T[\\d:]+' for d in recent),
                                  *(f'{d:%d/%m/%Y} [\\d:]+' for d in recent)]))
    return {table: [moment.sub('<maintenant>', '\t'.join(row))
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

    # Deux imports rapprochés, sur les deux chroniques mères d'une chronique calculée : chacun en
    # lance le recalcul par propagation. La file les mène deux à la fois après les deux imports, ou
    # une à une après chaque import.
    found = query(template, """
        SELECT t1.entree_id, t2.entree_id FROM transformation t1
          JOIN transformation t2 ON t2.sortie_id = t1.sortie_id AND t2.entree_id > t1.entree_id
          JOIN chronique a ON a.id = t1.entree_id JOIN chronique b ON b.id = t2.entree_id
         WHERE a.dtype = 'continue' AND b.dtype = 'continue'
           AND EXISTS (SELECT 1 FROM mesure m WHERE m.chronique_id = a.id AND m.date >= '2024-01-01' AND m.date < '2024-01-15')
           AND EXISTS (SELECT 1 FROM mesure m WHERE m.chronique_id = b.id AND m.date >= '2024-01-01' AND m.date < '2024-01-15')
         ORDER BY t1.sortie_id LIMIT 1""", NEW_SERVER)
    if found:
        imports = [bdoh_import(template, cid, 12) for cid in found[0]]
        manager = query(template, f"""
            SELECT u.email FROM utilisateur u JOIN role r ON r.utilisateur_id = u.id
              JOIN observatoire o ON o.id = r.observatoire_id
             WHERE r.valeur = 'gestionnaire' AND o.slug = '{imports[0]['slug']}'
               AND (u.finacces IS NULL OR u.finacces > now()) ORDER BY u.id LIMIT 1""", NEW_SERVER)[0][0]
        sides = {}
        for name, base, jobs in [('imports, deux à la fois', 'bdoh_queue_parallel', 2),
                                 ('imports, une à une', 'bdoh_queue_serial', 1)]:
            copy_base(template, base)
            with new_on(base, template) as console:
                client = Client(SIDES['new'], manager, imports[0]['slug'])
                for case in imports:
                    import_steps(client, SIDES['new'].url, case)
                    if jobs == 1:
                        drain(console, 'bdoh:jobs:run', base, jobs=1, rounds=60)
                drain(console, 'bdoh:jobs:run', base, jobs=jobs, rounds=60)
            sides[name] = state_lines(base, 0, ['chronique', 'mesure', 'tauxremplissage'])
            sides[name]['tâches'] = sorted(
                re.sub(r'\d{4}-\d\d-\d\dT[\d:]+', '<date>', ' '.join(row)) for row in query(base, """
                    SELECT command, args::text, state, exitcode FROM jms_jobs""", NEW_SERVER))
            overlaps = query(base, """
                SELECT a.queue, a.id, b.id FROM jms_jobs a JOIN jms_jobs b
                  ON b.queue = a.queue AND b.id > a.id
                 AND b.startedat < a.closedat AND a.startedat < b.closedat""", NEW_SERVER)
            print(f'{name} : {len(sides[name]["tâches"])} tâches, {len(overlaps)} dans une même file {overlaps or ""}')
        for part in sides['imports, une à une']:
            report.add(f'imports, {part}', sides['imports, une à une'][part], sides['imports, deux à la fois'][part])
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


def zip_lines(body):
    """Une archive zip, fichier par fichier : le texte des fichiers texte, l'empreinte des autres."""
    lines = []
    with zipfile.ZipFile(io.BytesIO(body)) as z:
        for name in sorted(z.namelist()):
            content = z.read(name)
            # Le dossier de l'archive porte la date de l'export
            shown = re.sub(r'_\d{4}-\d\d-\d\d_\d\d-\d\d-\d\d/', '_<date>/', name)
            if name.endswith(('.txt', '.csv')):
                lines += [f'== {shown}', *content.decode('utf-8', 'replace').splitlines()]
            else:
                lines.append(f'== {shown} {hashlib.sha256(content).hexdigest()[:16]}')
    return lines


def step_lines(site, status, headers, body):
    """Une réponse d'étape d'import : du JSON dont les fragments HTML se lisent comme les pages ; une
    archive zip dépliée."""
    if body[:2] == b'PK':
        disposition = re.sub(r'\d{4}-?\d\d-?\d\d[_T -]?\d\d[-:h]?\d\d[-:]?\d\d', '<date>',
                             headers.get('Content-Disposition', ''))
        return [f'statut {status}', f"type {headers.get('Content-Type', '')}", f'fichier {disposition}',
                *zip_lines(body)]
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


def import_steps(client, site, case):
    """Un import de mesures par ses étapes, comme le JavaScript les envoie ; rend les réponses."""
    slug = case['slug']
    ajax = {'X-Requested-With': 'XMLHttpRequest'}
    post = lambda step, fields: client.get(f'/{slug}/admin/mesure/import/{step}',
                                           urllib.parse.urlencode(fields).encode(), ajax)
    read = lambda response: json.loads(response[2]) if response[0] == 200 else {}
    log = []
    files = {'file': (case.get('filename', 'import.txt'), case['content'])} if case['content'] is not None else {}
    body, kind = multipart({'file-format': case['format']}, files)
    response = client.get(f'/{slug}/admin/mesure/import/step2', body, {**ajax, 'Content-Type': kind})
    log += ['== étape 2', *step_lines(site, *response)]
    data = read(response)
    if data.get('action') == 'step2.askStation':
        response = post('step2/next', {'station': case['station']})
        log += ['== station', *step_lines(site, *response)]
        data = read(response)
    if data.get('action') == 'step2':
        # Les chroniques lues dans l'en-tête, gardées toutes, comme le fait le JavaScript ; sinon
        # celle du cas, choisie dans la liste proposée.
        chroniques = re.findall(r'<li name="([^"]+)"', data['view']) or [case['chronique']]
        fields = {'timezone': case.get('timezone', ''), **{f'chroniques[{i}]': c for i, c in enumerate(chroniques)}}
        response = post('step3', fields)
        log += ['== étape 3', *step_lines(site, *response)]
        data = read(response)
    if data.get('action') == 'step3':
        response = post('step4', {f"importTypes[{case['cid']}]": case['type'], 'propagate': 'propagate'})
        log += ['== étape 4', *step_lines(site, *response)]
    return log


def bdoh_import(base, cid, months, import_type='keepExisting'):
    """Un import au format BDOH sur la chronique cid : ses mesures de deux semaines de janvier 2024,
    décalées de months mois."""
    slug, station, code, unite = query(base, f"""
        SELECT c.slug, c.station, c.code, u.libelle FROM ({CHRONIQUES}) c
          JOIN chronique ch ON ch.id = c.id JOIN unite u ON u.id = ch.unite_id WHERE c.id = {cid}""", NEW_SERVER)[0]
    rows = query(base, f"""
        SELECT to_char(m.date + interval '1 hour' + interval '{months} month', 'DD/MM/YYYY HH24:MI:SS'),
               coalesce(m.valeur::text, '-9999'), q.code
          FROM mesure m JOIN qualite q ON q.id = m.qualite_id
         WHERE m.chronique_id = {cid} AND m.date >= '2024-01-01' AND m.date < '2024-01-15'
         ORDER BY m.date""", NEW_SERVER)
    lines = ['Station;Fuseau;Chronique;Unite', f'{station};UTC+01;{code};{unite}', 'DateHeure;Valeur;Qualite',
             *[';'.join(r) for r in rows]]
    return {'name': f'chronique {cid}', 'format': 'Bdoh', 'content': ('\n'.join(lines) + '\n').encode(),
            'type': import_type, 'cid': cid, 'slug': slug}


def png_pixel():
    """Une image PNG valide d'un pixel, que libmagic reconnaît dans toutes ses versions."""
    import struct
    import zlib
    chunk = lambda kind, data: (struct.pack('>I', len(data)) + kind + data
                                + struct.pack('>I', zlib.crc32(kind + data) & 0xffffffff))
    return (b'\x89PNG\r\n\x1a\n' + chunk(b'IHDR', struct.pack('>IIBBBBB', 1, 1, 8, 2, 0, 0, 0))
            + chunk(b'IDAT', zlib.compress(b'\x00\xff\x00\x00')) + chunk(b'IEND', b''))


def import_cases(base):
    """Les imports à jouer, tirés de la base, un dictionnaire par cas : nom, format, contenu du
    fichier, chronique visée (son identifiant, et son code et sa station quand le fichier ne les
    donne pas), fuseau quand le fichier ne le donne pas, sorte d'import.

    Au format BDOH : les mesures de deux semaines de janvier 2024 d'une chronique continue, et leurs
    variantes, chacune décalée sur un mois de 2025 pour ne pas en recouvrir une autre ; deux sur la
    période existante, pour écraser ou garder. Au format BDOH par plages : les plages de janvier 2024
    d'une chronique discontinue, décalées d'un an. Aux formats QJO et Qtvar, sur la chronique
    continue : des jours et des instants de septembre et d'octobre 2025, avec des jours manquants,
    une valeur nulle et une valeur absente."""
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

    def bdoh(rows, tz='UTC+01', unit=unite, st=station, ch=code, minmax=False, eol='\n', dates='DateHeure'):
        head = ['Station;Fuseau;Chronique;Unite', f'{st};{tz};{ch};{unit}',
                f'{dates};Valeur;Qualite' + (';Min;Max' if minmax else '')]
        width = len(dates.split(';')) + (4 if minmax else 2)
        body = [';'.join(r[:width]) for r in rows]
        return (eol.join(head + body) + eol).encode()

    rows = lambda months: [list(r) for r in measures(months)]
    cases = []

    def add(name, content, import_type='keepExisting', **extra):
        cases.append({'name': name, 'format': 'Bdoh', 'content': content, 'type': import_type, 'cid': cid,
                      'slug': slug, **extra})


    add('nouvelle période', bdoh(rows(12)))
    add('fins de ligne Windows', bdoh(rows(13), eol='\r\n'))
    add('espaces autour des champs', bdoh([[f' {x} ' for x in r] for r in rows(14)]))
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
    # Refusés dès l'étape 2, avant que l'importeur existe
    add('format inconnu', bdoh(rows(12)), format='Inconnu')
    add('sans fichier', None)
    add('fichier PDF', b'%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\ntrailer << /Root 1 0 R >>\n%%EOF\n',
        filename='import.pdf')
    # (une archive zip vide n'y est pas : libmagic la classe autrement selon sa version, dev/differences.md)
    image_zip = io.BytesIO()
    with zipfile.ZipFile(image_zip, 'w') as z:
        z.writestr('image.png', png_pixel())
    add('archive zip sans texte', image_zip.getvalue(), filename='import.zip')
    changed = [[r[0], str(float(r[1]) + 1) if r[1] != '-9999' else r[1], *r[2:]] for r in rows(0)]
    add('période existante, écraser', bdoh(changed), 'overwriteExisting')
    add('période existante, garder', bdoh(rows(0)), 'keepExisting')

    # BDOH par plages, sur une chronique discontinue
    found = query(base, f"""
        SELECT c.id, c.slug, c.station, c.code, u.libelle FROM ({CHRONIQUES}) c
          JOIN chronique ch ON ch.id = c.id JOIN unite u ON u.id = ch.unite_id
         WHERE c.dtype = 'discontinue' AND EXISTS (SELECT 1 FROM plage p WHERE p.chronique_id = c.id
               AND p.debut >= '2024-01-01' AND p.debut < '2024-02-01')
           AND NOT EXISTS (SELECT 1 FROM plage p WHERE p.chronique_id = c.id AND p.fin >= '2025-01-01')
         ORDER BY c.id LIMIT 1""", NEW_SERVER)
    if found:
        pid, pslug, pstation, pcode, punite = found[0]
        ranges = [list(r) for r in query(base, f"""
            SELECT to_char(p.debut + interval '1 hour 1 year', 'DD/MM/YYYY HH24:MI:SS'),
                   to_char(p.fin + interval '1 hour 1 year', 'DD/MM/YYYY HH24:MI:SS'),
                   coalesce(p.valeur::text, '-9999'), q.code
              FROM plage p JOIN qualite q ON q.id = p.qualite_id
             WHERE p.chronique_id = {pid} AND p.debut >= '2024-01-01' AND p.debut < '2024-02-01'
             ORDER BY p.debut""", NEW_SERVER)]
        content = bdoh(ranges, unit=punite, st=pstation, ch=pcode, dates='Debut;Fin')
        cases.append({'name': 'plages', 'format': 'BdohPlage', 'content': content, 'type': 'keepExisting',
                      'cid': pid, 'slug': pslug})
        inverted = [[r[1], r[0], *r[2:]] for r in ranges]
        content = bdoh(inverted, unit=punite, st=pstation, ch=pcode, dates='Debut;Fin')
        cases.append({'name': 'plages, fin avant le début', 'format': 'BdohPlage', 'content': content,
                      'type': 'keepExisting', 'cid': pid, 'slug': pslug})

    # QJO et Qtvar : qualités du jeu Hydro2 ; la station et la chronique se choisissent à l'import
    hydro2 = ['0', '5', '8', '9']
    days = [d for d in range(1, 21) if d not in (6, 7, 13)]  # des jours manquants
    qjo = ['DEC;.  6 13', f'950;{station};essai;0;0;0;0;essai;0;']
    for k, d in enumerate(days):
        value = '' if d == 9 else '0' if d == 10 else f'{1.5 * k:g}'
        qjo.append(f'QJO;{station};202509{d:02d};{value}; ;{hydro2[k % 4] if value else " "};')
    qjo.append('FIN;EXP-HYDRO;1;')
    cases.append({'name': 'QJO', 'format': 'Qjo', 'content': ('\n'.join(qjo) + '\n').encode(),
                  'type': 'keepExisting', 'cid': cid, 'slug': slug, 'station': station, 'chronique': code,
                  'timezone': '+1'})
    qtvar = ['DEC;. 6 13', f'950;{station};essai;0;;;;']
    for k, d in enumerate(days):
        for hour in ('00:00', '12:30'):
            value = '' if (d, hour) == (9, '12:30') else '0' if d == 10 else f'{0.25 * k:g}'
            qtvar.append(f'920;1;{station};202510{d:02d};{hour};{value};{hydro2[k % 4]};{1 if d == 14 else 2};')
    qtvar.append('FIN;EXP-HYDRO;1;')
    cases.append({'name': 'Qtvar', 'format': 'Qtvar', 'content': ('\n'.join(qtvar) + '\n').encode(),
                  'type': 'keepExisting', 'cid': cid, 'slug': slug, 'station': station, 'chronique': code,
                  'timezone': '+0'})
    return cases


def controle_cases(base):
    """Les imports de points de contrôle : quelques mesures d'une chronique continue en janvier 2024,
    reprises en points de contrôle (valeur, qualité, minimum et maximum), puis sans bornes, avec un
    commentaire ; puis deux lignes refusées, l'une sans qualité, l'autre avec un champ de trop."""
    cid, slug, station, code, unite = query(base, f"""
        SELECT c.id, c.slug, c.station, c.code, u.libelle FROM ({CHRONIQUES}) c
          JOIN chronique ch ON ch.id = c.id JOIN unite u ON u.id = ch.unite_id
         WHERE c.dtype = 'continue' AND EXISTS (SELECT 1 FROM mesure m WHERE m.chronique_id = c.id
               AND m.date >= '2024-01-01' AND m.date < '2024-01-15')
         ORDER BY c.id LIMIT 1""", NEW_SERVER)[0]
    rows = query(base, f"""
        SELECT to_char(m.date + interval '1 hour', 'DD/MM/YYYY HH24:MI:SS'), coalesce(m.valeur, 0)::text, q.code
          FROM mesure m JOIN qualite q ON q.id = m.qualite_id
         WHERE m.chronique_id = {cid} AND m.date >= '2024-01-01' AND m.date < '2024-01-15'
         ORDER BY m.date LIMIT 10""", NEW_SERVER)
    head = ['Station;Fuseau;Chronique;Unite', f'{station};UTC+01;{code};{unite}', 'DateHeure;Valeur;Qualite;Min;Max']
    full = [f'{d};{v};{q};{float(v) - 0.5:g};{float(v) + 0.5:g}' for d, v, q in rows]
    short = [f'{d};{float(v) + 1:g};{q}' for d, v, q in rows[:5]]
    content = lambda lines: ('\n'.join(head + lines) + '\n').encode()
    return cid, slug, [('points de contrôle', content(full)),
                       ('points de contrôle, sans bornes', content(['# commentaire', *short])),
                       ('points de contrôle, sans qualité', content([f'{rows[0][0]};1'])),
                       ('points de contrôle, trop de champs', content([full[0] + ';de trop']))]


def bareme_cases(base, slug):
    """Les imports de barèmes, sous l'observatoire slug : une courbe de tarage, et trois refusées
    (unité inconnue, valeur non numérique, qualité inconnue)."""
    entree, sortie = [r[0] for r in query(base, "SELECT libelle FROM unite ORDER BY id LIMIT 2 OFFSET 2", NEW_SERVER)]
    quality, = query(base, f"""
        SELECT q.code FROM qualite q JOIN observatoire o ON o.jeu_id = q.jeu_id
         WHERE o.slug = '{slug}' AND q.code <> 'gap' ORDER BY q.id DESC LIMIT 1""", NEW_SERVER)[0]
    def bareme(nom, unit_in=entree, points=None):
        points = points or [(0, 0), (1, 1000), (2, 2500.5), (3.5, 4000)]
        lines = ['Nom ; Unite entree ; Unite sortie ; Commentaire', f'{nom} ; {unit_in} ; {sortie} ; Essai',
                 'X ; Y ; Qualite ; Min (optionnel) ; Max (optionnel)',
                 *[f'{x} ; {y} ; {q}' for x, y, *rest in points for q in [rest[0] if rest else quality]]]
        return ('\n'.join(lines) + '\n').encode()
    return [('barème', bareme('Courbe essai')),
            ('barème, unité inconnue', bareme('Courbe essai 2', unit_in='inconnue')),
            ('barème, valeur non numérique', bareme('Courbe essai 3', points=[(0, 0), (1, 'abc')])),
            ('barème, qualité inconnue', bareme('Courbe essai 4', points=[(0, 0, 'zz'), (1, 1)])),
            # L'ancien rend une erreur 500 sans fichier, ou avec un fichier d'un type refusé (dev/differences.md)
            ('barème, sans fichier', None),
            ('barème, fichier PDF', b'%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\ntrailer << /Root 1 0 R >>\n%%EOF\n')]


SHAPES = HERE.parent.parent / 'var' / 'compare-shapes'  # monté dans bdoh-app sous /var/www/bdoh/var


def shape_cases(base, slug):
    """Les imports de shapefiles, sous l'observatoire slug, fabriqués par pgsql2shp depuis la base :
    ses stations déplacées de dix mètres ; un bassin et un cours d'eau nouveaux autour d'une de ses
    stations ; une archive sans shapefile."""
    stations = f"""SELECT s.code, ST_Translate(s.point, 10, 10) AS geom FROM station s
                    JOIN stations_sites ss ON ss.station_id = s.id JOIN siteexperimental se ON se.id = ss.site_id
                    JOIN observatoire o ON o.id = se.observatoire_id
                   WHERE o.slug = '{slug}' AND s.point IS NOT NULL"""
    first = f"SELECT code, point FROM ({stations.replace('ST_Translate(s.point, 10, 10) AS geom', 's.code AS c, s.point')}) x LIMIT 1"
    queries = {
        'station': stations,
        'bassin': f"""SELECT 'Bassin essai'::varchar AS nom, code AS code_exu, ST_Buffer(point, 500, 4) AS geom
                        FROM ({first}) x""",
        'coursEau': f"""SELECT 'Cours essai'::varchar AS toponyme, 'Z0000000'::varchar AS code_hydro, 2 AS classifica,
                               ST_MakeLine(ST_Translate(point, -300, 0), ST_Translate(point, 300, 0)) AS geom
                          FROM ({first}) x""",
    }
    SHAPES.mkdir(parents=True, exist_ok=True)
    cases = []
    for target, sql in queries.items():
        name = f'{target.lower()}s'
        subprocess.run(['docker', 'exec', 'bdoh-app', 'pgsql2shp', '-f', f'/var/www/bdoh/var/compare-shapes/{name}',
                        '-h', 'db', '-u', 'bdoh', base, ' '.join(sql.split())], check=True, capture_output=True)
        archive = io.BytesIO()
        with zipfile.ZipFile(archive, 'w') as z:
            for ext in ('shp', 'shx', 'dbf', 'prj'):
                if (SHAPES / f'{name}.{ext}').exists():
                    z.write(SHAPES / f'{name}.{ext}', f'{name}.{ext}')
        cases.append((f'shapefile, {target}', target, f'{name}.zip', archive.getvalue()))
    empty = io.BytesIO()
    with zipfile.ZipFile(empty, 'w') as z:
        z.writestr('lisezmoi.txt', 'pas de shapefile')
    cases.append(('shapefile, archive sans shapefile', 'station', 'vide.zip', empty.getvalue()))
    return cases


def run_imports(args):
    """Chaque import, étape par étape, par l'ancien code sur PostgreSQL 17 et par le nouveau, chacun
    sur sa copie de BASE, au nom d'un gestionnaire de l'observatoire visé ; puis la file de tâches,
    et l'état des deux copies."""
    template = args.base
    historique = query(template, 'SELECT coalesce(max(id), 0) FROM historique', NEW_SERVER)[0][0]
    cases = [c for c in import_cases(template) if args.only in c['name']]
    bases = {'ancien': 'bdoh_imports_old', 'nouveau': 'bdoh_imports_new'}
    for base in bases.values():
        copy_base(template, base)

    report = Report('imports')
    with old_17_on(bases['ancien']) as old_console, new_on(bases['nouveau'], template) as new_console:
        sides = {'ancien': (SIDES['reference-17'], old_console, 'jms-job-queue:run'),
                 'nouveau': (SIDES['new'], new_console, 'bdoh:jobs:run')}
        clients = {}

        def client_for(name, slug):
            """Un navigateur par côté et par observatoire, connecté au nom d'un de ses gestionnaires."""
            if (name, slug) not in clients:
                manager = query(template, f"""
                    SELECT u.email FROM utilisateur u JOIN role r ON r.utilisateur_id = u.id
                      JOIN observatoire o ON o.id = r.observatoire_id
                     WHERE r.valeur = 'gestionnaire' AND o.slug = '{slug}'
                       AND (u.finacces IS NULL OR u.finacces > now())
                     ORDER BY u.id LIMIT 1""", NEW_SERVER)[0][0]
                clients[name, slug] = Client(sides[name][0], manager, slug)
            return clients[name, slug]

        def play(name, case):
            side, console, runner = sides[name]
            log = import_steps(client_for(name, case['slug']), side.url, case)
            drain(console, runner, bases[name])
            return log

        for case in cases:
            report.add(f"import {case['name']}", play('ancien', case), play('nouveau', case))

        def play_controle(name, cid, slug, content):
            """Un import de points de contrôle (deux étapes), puis l'export des points de la chronique."""
            side = sides[name][0]
            client = client_for(name, slug)
            ajax = {'X-Requested-With': 'XMLHttpRequest'}
            body, kind = multipart({}, {'file': ('controle.txt', content)})
            response = client.get(f'/{slug}/admin/controle/import/step2', body, {**ajax, 'Content-Type': kind})
            log = ['== étape 2', *step_lines(side.url, *response)]
            if response[0] == 200 and json.loads(response[2]).get('action') == 'step3':
                response = client.get(f'/{slug}/admin/controle/import/step3',
                                      urllib.parse.urlencode({'importType': 'import'}).encode(), ajax)
                log += ['== étape 3', *step_lines(side.url, *response)]
            response = client.get(f'/{slug}/admin/controle/export',
                                  urllib.parse.urlencode({'chronique': cid, 'timezone': '+1'}).encode(),
                                  {'Referer': f'{side.url}/{slug}/admin/controle/import'})
            return log + ['== export', *page_lines(side.url, *response)]

        cid, slug, controles = controle_cases(template)
        if args.only in 'points de contrôle':
            for title, content in controles:
                report.add(f'import {title}', play_controle('ancien', cid, slug, content),
                           play_controle('nouveau', cid, slug, content))

        def play_bareme(name, slug, content):
            """Un import de barème, depuis la page des transformations (deux étapes)."""
            side = sides[name][0]
            client = client_for(name, slug)
            ajax = {'X-Requested-With': 'XMLHttpRequest'}
            body, kind = multipart({}, {'file': ('bareme.txt', content)} if content is not None else {})
            response = client.get(f'/{slug}/admin/transformation/import/bareme/step2', body,
                                  {**ajax, 'Content-Type': kind})
            log = ['== étape 2', *step_lines(side.url, *response)]
            if response[0] == 200 and json.loads(response[2]).get('action') == 'step3':
                response = client.get(f'/{slug}/admin/transformation/import/bareme/step3', b'', ajax)
                log += ['== étape 3', *step_lines(side.url, *response)]
            # La date de création du barème, celle du moment
            return [re.sub(r'\d\d/\d\d/\d{4} \d\d:\d\d(:\d\d)?', '<date>', line) for line in log]

        if args.only in 'barème':
            for title, content in bareme_cases(template, slug):
                report.add(f'import {title}', play_bareme('ancien', slug, content),
                           play_bareme('nouveau', slug, content))

        def play_shape(name, slug, target, filename, content):
            """Un import de shapefile (deux étapes), en écrasant l'existant."""
            side = sides[name][0]
            client = client_for(name, slug)
            ajax = {'X-Requested-With': 'XMLHttpRequest'}
            body, kind = multipart({'file-shape': target}, {'file': (filename, content)})
            body = body.replace(b'Content-Type: text/plain', b'Content-Type: application/zip')
            response = client.get(f'/{slug}/admin/shape/import/step2', body, {**ajax, 'Content-Type': kind})
            log = ['== étape 2', *step_lines(side.url, *response)]
            if response[0] == 200 and json.loads(response[2]).get('action') == 'step2':
                response = client.get(f'/{slug}/admin/shape/import/step3', urllib.parse.urlencode(
                    {'shapeImportType': 'overwriteExisting'}).encode(), ajax)
                log += ['== étape 3', *step_lines(side.url, *response)]
            # Le chemin du fichier téléversé, propre à chaque installation
            return [re.sub(r'/[\w/.-]*/imports/shape/[\w.-]*', '<fichier>', line) for line in log]

        def play_transformation(name, cid, slug):
            """La transformation d'une chronique calculée, lue comme la page la lit, puis enregistrée de
            nouveau avec un autre coefficient, une généalogie complétée et la propagation."""
            side, console, runner = sides[name]
            client = client_for(name, slug)
            ajax = {'X-Requested-With': 'XMLHttpRequest'}
            response = client.get(f'/{slug}/admin/get-chronique-calculee?id={cid}', None, ajax)
            log = ['== chronique calculée', *step_lines(side.url, *response)]
            current = json.loads(response[2])
            iso = lambda d: d.replace(' ', 'T') + '.000Z' if d else ''
            # Les exports de barèmes de la page : la liste, le jeu de barèmes de la première chronique
            # mère, puis ses barèmes choisis un à un
            response = client.get(f'/{slug}/admin/transformation/export/bareme/list', None, ajax)
            log += ['== liste des barèmes', *step_lines(side.url, *response)]
            jeu, = query(bases[name], f"""SELECT jeubaremeactuel_id FROM transformation WHERE sortie_id = {cid}
                                          ORDER BY id LIMIT 1""", NEW_SERVER)[0]
            child = f"{current.get('chroniqueMerePrincipale')}-fille"
            response = client.get(f'/{slug}/admin/transformation/export/jeu-bareme?' + urllib.parse.urlencode(
                {'child-chronicle-name': child, 'parent-chronicle-id': current.get('chroniqueMerePrincipale'),
                 'jeu-bareme-id': jeu}), b'', {'Referer': f'{side.url}/{slug}/admin/transformation'})
            log += ['== export du jeu de barèmes', *step_lines(side.url, *response)]
            info = '|'.join(f"{b['id']}_{current.get('chroniqueMerePrincipale')}_{b['begin']}_{b['end'] or ''}"
                            for b in current.get('baremesPrincipale', []))
            response = client.get(f'/{slug}/admin/transformation/export/bareme', urllib.parse.urlencode(
                {'child-chronicle-name': child, 'baremes-export-info': info}).encode(),
                {'Referer': f'{side.url}/{slug}/admin/transformation'})
            log += ['== export des barèmes', *step_lines(side.url, *response)]
            scales = lambda block: '|'.join(f"{b['id']}_{iso(b['begin'])}_{iso(b['end'])}"
                                            for b in current.get(f'baremes{block}', []))
            fields = {'chroniqueCalculee': cid, 'coefficient': '1.5',
                      'genealogie': (current['genealogie'] or '') + ' (essai)',
                      'genealogieEn': (current['genealogieEn'] or '') + ' (test)', 'propagate': 'propagate'}
            for block in ('Principale', 'Secondaire'):
                fields[f'selectChroniqueMere{block}'] = current.get(f'chroniqueMere{block}', '-1')
                fields[f'jeuBareme{block}'] = scales(block)
                fields[f'delaiPropagation{block}'] = current.get(f'delaiPropagation{block}') or 0
                fields[f'valueLimits{block}'] = current.get(f'valueLimitPolicy{block}') or ''
                fields[f'limitPlaceholder{block}'] = current.get(f'valueLimitPlaceholder{block}') or ''
            response = client.get(f'/{slug}/admin/transformation/save', urllib.parse.urlencode(fields).encode(), ajax)
            log += ['== enregistrement', *step_lines(side.url, *response)]
            drain(console, runner, bases[name])
            return [re.sub(r'\d\d/\d\d/\d{4} \d\d:\d\d(:\d\d)?', '<date>', line) for line in log]

        if args.only in 'transformation':
            cid, slug = query(template, f"""
                SELECT c.id, c.slug FROM ({CHRONIQUES}) c JOIN transformation t ON t.sortie_id = c.id
                  JOIN transformation fille ON fille.entree_id = c.id
                 WHERE c.dtype = 'calculee' ORDER BY c.id LIMIT 1""", NEW_SERVER)[0]
            report.add('transformation enregistrée', play_transformation('ancien', cid, slug),
                       play_transformation('nouveau', cid, slug))

        if args.only in 'shapefile':
            for title, target, filename, content in shape_cases(template, slug):
                report.add(f'import {title}', play_shape('ancien', slug, target, filename, content),
                           play_shape('nouveau', slug, target, filename, content))
        for name in sides:
            sides[name] = state_lines(bases[name], historique)
    for table in sides['ancien']:
        report.add(f'état {table}', sides['ancien'][table], sides['nouveau'][table])
    report.close()
    print(f'Les instances lisent de nouveau {template}. Les deux copies restent pour examen :'
          f' {bases["ancien"]} et {bases["nouveau"]}.')


# --- Comptes ---------------------------------------------------------------------------------------

CAPTCHA_BYPASS = 'comparaison'  # passe outre le captcha de l'ancien (install-app.sh)
LOGIN_LINK = re.compile(r'(<site>)?/[\w-]+/login_check\?[^\s"<]+')


def mail_messages():
    """Les courriels retenus par Mailpit, résumés, du plus récent au plus ancien."""
    return json.loads(urllib.request.urlopen(MAILPIT + '/api/v1/messages', timeout=60).read())['messages']


def last_mail(email, wait=10):
    """Le dernier courriel reçu par une adresse ; le nouveau les envoie après la réponse
    (DeferredMailer) : on l'attend quelques secondes."""
    for _ in range(wait * 4):
        for summary in mail_messages():
            if any(to['Address'] == email for to in summary['To']):
                return json.loads(urllib.request.urlopen(f"{MAILPIT}/api/v1/message/{summary['ID']}",
                                                         timeout=60).read())
        time.sleep(0.25)
    raise RuntimeError(f'aucun courriel pour {email}')


def mailed_link(email):
    """Le lien de connexion du dernier courriel reçu par une adresse."""
    m = last_mail(email)
    found = re.search(r'https?://[^\s"<]+/login_check\?[^\s"<]+', m['HTML'] or m['Text'])
    if not found:
        raise RuntimeError(f'aucun lien de connexion pour {email}')
    return html.unescape(found.group(0))
NEW_EMAIL = 'compte-essai@example.invalid'  # .invalid : le DNS de développement lui donne un MX


class FormFields(html.parser.HTMLParser):
    """Les champs d'un formulaire, tels qu'un navigateur les enverrait sans y toucher, et les options
    de ses listes."""

    def __init__(self, action):
        super().__init__(convert_charrefs=True)
        self.action, self.inside, self.select, self.textarea, self.url = action, False, None, None, None
        self.fields, self.options, self.names, self.buttons = [], {}, set(), []

    def handle_starttag(self, tag, attrs):
        a = {k: v or '' for k, v in attrs}
        if tag == 'form':
            self.inside = self.action in a.get('action', '')
            if self.inside:
                self.url = a.get('action', '')
        elif not self.inside or 'name' not in a and tag != 'option':
            return
        if 'name' in a and tag in ('input', 'select', 'textarea'):
            self.names.add(a['name'])
        if tag == 'button' and a.get('type', 'submit') == 'submit' and a.get('name'):
            self.buttons.append(a['name'])
        if tag == 'input' and a.get('type') not in ('submit', 'button', 'image', 'reset'):
            if a.get('type') not in ('checkbox', 'radio') or 'checked' in a:
                self.fields.append([a['name'], a.get('value', '' if a.get('type') != 'checkbox' else 'on')])
        elif tag == 'select':
            self.select = a['name']
            self.options[self.select] = []
        elif tag == 'option' and self.select:
            self.options[self.select].append(a.get('value', ''))
            if 'selected' in a:
                self.fields.append([self.select, a.get('value', '')])
        elif tag == 'textarea':
            self.textarea = [a['name'], '']
            self.fields.append(self.textarea)

    def handle_endtag(self, tag):
        if tag == 'form':
            self.inside = False
        elif tag == 'select':
            self.select = None
        elif tag == 'textarea':
            self.textarea = None

    def handle_data(self, data):
        if self.textarea:
            self.textarea[1] += data

    @staticmethod
    def match(name, key):
        """Le nom d'un champ se termine-t-il par key ([email], [dateFin][day]…), sans ses [] ?"""
        return name.removesuffix('[]').endswith(key)

    def filled(self, values):
        """Les champs, avec d'autres valeurs pour ceux dont le nom se termine par une clé : une
        liste donne toutes les valeurs du champ ; une clé sans champ dans le formulaire est ignorée
        (les mêmes valeurs servent à l'ancien et au nouveau, qui n'a ni mot de passe ni captcha)."""
        fields = [f for f in self.fields if not any(self.match(f[0], k) for k in values)]
        for key, value in values.items():
            for name in [n for n in sorted(self.names) if self.match(n, key)][:1]:
                fields += [[name, v] for v in (value if isinstance(value, list) else [value])]
        return urllib.parse.urlencode([tuple(f) for f in fields]).encode()

    def choices(self, key):
        """Les valeurs proposées par la liste dont le nom se termine par key, sans l'option vide."""
        return [v for n, values in self.options.items() if self.match(n, key) for v in values if v]


def form_of(client, path, action=None):
    """Le formulaire d'une page, lu après l'avoir demandée comme un navigateur."""
    parser = FormFields(action or path)
    parser.feed(client.get(path)[2].decode('utf-8', 'replace'))
    return parser


ACCOUNT_QUERIES = {
    # Ni le hachage du mot de passe ni le sel, tirés au hasard : seulement s'ils existent ; la date
    # de dernière connexion, seulement si elle est récente.
    'utilisateur': """
        SELECT ((to_jsonb(u) - 'password' - 'salt' - 'derniereconnection' - 'confirmationtoken')
                || jsonb_build_object('mot de passe', coalesce(u.password, '') <> '', 'sel', u.salt IS NOT NULL,
                                      'jeton', u.confirmationtoken IS NOT NULL,
                                      'connexion récente', u.derniereconnection > now() - interval '1 hour'))::text
          FROM utilisateur u ORDER BY u.id""",
    'role': """
        SELECT (to_jsonb(r) - 'id')::text FROM role r WHERE r.utilisateur_id > {utilisateur} ORDER BY 1""",
    'role des autres comptes': """
        SELECT count(*), md5(string_agg(to_jsonb(r)::text, '|' ORDER BY r.id)) FROM role r
         WHERE r.utilisateur_id <= {utilisateur}""",
    'besoin': "SELECT to_jsonb(b)::text FROM besoin b WHERE b.id > {besoin} ORDER BY b.id",
    'besoins_stations': "SELECT * FROM besoins_stations WHERE besoin_id > {besoin} ORDER BY 1, 2",
    'besoins_parametres': "SELECT * FROM besoins_parametres WHERE besoin_id > {besoin} ORDER BY 1, 2",
    'historique': STATE_QUERIES['historique'],
}


def run_accounts(args):
    """Les parcours de comptes, par l'ancien code sur PostgreSQL 17 et par le nouveau, chacun sur sa
    copie de BASE : inscription (et ses refus), modification de son compte, demande d'accès,
    attribution de droits par un gestionnaire, création de comptes d'administration en ligne de
    commande ; les réponses, les courriels et l'état des tables de comptes.

    Le nouveau se connecte par un lien envoyé par courriel, sans mot de passe ni captcha : le nouveau
    compte, par le lien de son courriel de bienvenue ; les écarts qui en découlent sont admis
    (dev/differences.md, « Connexion par lien »)."""
    template = args.base
    first = lambda sql: query(template, sql, NEW_SERVER)[0]
    marks = {'historique': first('SELECT coalesce(max(id), 0) FROM historique')[0],
             'utilisateur': first('SELECT max(id) FROM utilisateur')[0],
             'besoin': first('SELECT coalesce(max(id), 0) FROM besoin')[0]}
    # Un observatoire qui a des gestionnaires (ils reçoivent les courriels) et des stations
    slug, manager = first("""
        SELECT o.slug, u.email FROM observatoire o JOIN role r ON r.observatoire_id = o.id
          JOIN utilisateur u ON u.id = r.utilisateur_id
         WHERE r.valeur = 'gestionnaire' AND (u.finacces IS NULL OR u.finacces > now())
         ORDER BY o.id, u.id LIMIT 1""")
    taken = first('SELECT email FROM utilisateur ORDER BY id LIMIT 1')[0]

    def play(side, console):
        steps = {}

        def post(name, client, path, data, then=None):
            """Envoie un formulaire et garde la réponse, puis la page où elle renvoie."""
            response = client.get(path, data, {'Referer': side.url + path})
            steps[name] = page_lines(side.url, *response)
            location = response[1].get('Location', '') if 300 <= response[0] < 400 else ''
            if location:
                steps[name] += ['== page suivante', *page_lines(side.url, *client.get(location.replace(side.url, '')))]

        new = f'/{slug}/user/new'
        good = {'[email]': NEW_EMAIL, '[password][main]': 'essai-2026', '[password][repeat]': 'essai-2026',
                '[prenom]': 'Camille', '[nom]': 'Essai', '[organisme]': 'INRAE', '[telephone]': '0102030405',
                '[adresse]': '1 rue de l’Essai\n69000 Lyon', '[captcha]': CAPTCHA_BYPASS}
        anonymous = Client(side, None, slug)
        for name, changes in [
            ('téléphone trop court', {'[telephone]': '0102'}),
            ('adresse sans MX', {'[email]': 'compte-essai@pas-de-courrier.example'}),
            ('adresse mal formée', {'[email]': 'pas-une-adresse'}),
            ('adresse déjà prise', {'[email]': taken}),
            ('nom manquant', {'[nom]': ''}),
            ('valide', {}),
        ]:
            form = form_of(anonymous, new)
            categorie = form.choices('[categorie]')[:1]
            post(f'inscription, {name}', anonymous, new, form.filled({**good, '[categorie]': categorie, **changes}))

        def login(name, email, password):
            try:
                client = Client(side, email, slug, password)
                steps[name] = ['connexion acceptée']
                return client
            except RuntimeError:
                steps[name] = ['connexion refusée']

        if side.login_link:
            # Par le lien du courriel de bienvenue, qui confirme l'adresse
            user = Client(side, None, slug)
            status, headers = user.follow_login_link(mailed_link(NEW_EMAIL))
            accepted = status == 302 and 'login' not in headers.get('Location', '')
            steps['connexion du nouveau compte'] = ['connexion acceptée' if accepted else 'connexion refusée']
        else:
            user = login('connexion du nouveau compte', NEW_EMAIL, 'essai-2026')
        edit = f'/{slug}/user/edit'
        for name, changes in [('téléphone trop court', {'[telephone]': '01'}),
                              ('valide', {'[organisme]': 'INRAE, unité RiverLy', '[telephone]': '0607080910'})]:
            post(f'modification du compte, {name}', user, edit, form_of(user, edit).filled(changes))
        login('connexion après la modification', NEW_EMAIL, 'essai-2026')  # l'ancien : mot de passe inchangé

        need = f'/{slug}/besoin/new'
        for name, changes in [('détails manquants', {'[details]': ''}), ('valide', {})]:
            form = form_of(user, need)
            post(f"demande d'accès, {name}", user, need, form.filled({
                '[stations]': form.choices('[stations]')[:2], '[objectif]': form.choices('[objectif]')[:1],
                '[type]': form.choices('[type]')[:1], '[parametres]': form.choices('[parametres]')[:2],
                '[duree]': '6 mois', '[details]': 'Essai de la comparaison.', **changes}))

        boss = Client(side, manager, slug)
        steps['liste des comptes'] = page_lines(side.url, *boss.get(f'/{slug}/user/all'))
        right = f'/{slug}/user/right/{NEW_EMAIL}'
        form = form_of(boss, right)
        post('droits', boss, right, form.filled({
            **{n: '1' for n in form.names if 'besoinATraiter' in n},
            '[dateFin][day]': '30', '[dateFin][month]': '6', '[dateFin][year]': '2027',
            '[utilisateurChronique]': form.choices('[utilisateurChronique]')[:3]}))
        steps['droits, page relue'] = page_lines(side.url, *boss.get(right))

        for answer in ['y', 'non']:
            password = [] if side.login_link else ['admin-2026']  # le nouveau n'en demande plus
            r = subprocess.run([*console, 'security:users:create', 'Dominique', f'Admin-{answer}',
                                f'admin-{answer}@example.invalid', *password, 'FCT', answer, '-n', '--env=prod'],
                               capture_output=True, text=True)
            steps[f'commande security:users:create, {answer}'] = [f'code de sortie {r.returncode}',
                                                                  *r.stdout.splitlines()]
        return steps

    def snapshot(side, base):
        """Les courriels et les tables de comptes d'une copie, après les parcours. Deux courriels d'une
        même requête partent dans un ordre ou l'autre (l'ancien les met en file) ; le nouveau prévient
        les gestionnaires à la première connexion, et non à l'inscription."""
        mails = [LOGIN_LINK.sub('<lien de connexion>', line.replace(side.url, '<site>'))
                 for line in mail_lines(ordered=False)]
        return {'courriels': mails,
                **{table: ['\t'.join(row) for row in query(base, sql.format(**marks), NEW_SERVER)]
                   for table, sql in ACCOUNT_QUERIES.items()}}

    def new_only_checks(side, console, base):
        """Ce que seul le nouveau fait, sur sa copie, après la photo comparée à l'ancien : la relecture
        du module 5 (dev/plans/2026-10-26_comptes.md). Rend, pour chaque vérification, « conforme » ou
        ce qui ne l'est pas."""
        results = {}

        def check(name, ok, detail=''):
            results[f'nouveau seul : {name}'] = ['conforme' if ok else f'non conforme : {detail}']

        def ask_link(client, email, headers=None):
            """Demande un lien comme la page de connexion, jeton CSRF compris."""
            token = dict(form_of(client, f'/{slug}/login').fields).get('_csrf_token', '')
            return client.get(f'/{slug}/login', urllib.parse.urlencode({'email': email, '_csrf_token': token}).encode(),
                              headers)

        def notices(client):
            return next((l for l in page_lines(side.url, *client.get(f'/{slug}/login')) if 'data-notices' in l), '')

        # Le message de la page de connexion cite l'adresse saisie, échappée
        anonymous = Client(side, None, slug)
        ask_link(anonymous, '<b>x</b>@example.invalid')
        shown = notices(anonymous)
        check("message de la page de connexion échappé", '&lt;b&gt;' in shown and '<b>' not in shown, shown)

        # Un nom d'hôte que BDOH ne connaît pas : 400, et pas de lien à ce nom
        status = Client(side, None, slug).get(f'/{slug}/login', None, {'Host': 'piege.invalid'})[0]
        check("hôte inconnu refusé", status == 400, f'statut {status}')

        # Les liens sont limités par adresse et adresse IP : 3 par quart d'heure
        client = Client(side, None, slug)
        for _ in range(3):
            ask_link(client, NEW_EMAIL)
        ask_link(client, NEW_EMAIL)
        shown = notices(client)
        check("quatrième lien refusé", 'Trop de demandes' in shown, shown)

        # Le courriel de bienvenue échappe les noms (il part en HTML)
        signup = Client(side, None, slug)
        form = form_of(signup, f'/{slug}/user/new')
        signup.get(f'/{slug}/user/new', form.filled({
            '[email]': 'compte-html@example.invalid', '[prenom]': '<b>Camille</b>', '[nom]': 'Essai',
            '[organisme]': 'INRAE', '[telephone]': '0102030405', '[adresse]': 'Lyon',
            '[categorie]': form.choices('[categorie]')[:1]}))
        html_mail = last_mail('compte-html@example.invalid')['HTML']
        check("noms échappés dans le courriel de bienvenue",
              '&lt;b&gt;Camille' in html_mail and '<b>Camille' not in html_mail, html_mail[:200])

        # Une panne du serveur de courriel ne fait échouer ni l'inscription ni la demande de lien
        ENV_LOCAL.write_text(f'DATABASE_URL="{NEW_URL.format(base)}"\nMAILER_DSN=smtp://mailpit:1\n')
        down = Client(side, None, slug)
        form = form_of(down, f'/{slug}/user/new')
        status = down.get(f'/{slug}/user/new', form.filled({
            '[email]': 'compte-panne@example.invalid', '[prenom]': 'Camille', '[nom]': 'Panne',
            '[organisme]': 'INRAE', '[telephone]': '0102030405', '[adresse]': 'Lyon',
            '[categorie]': form.choices('[categorie]')[:1]}))[0]
        created = query(base, "SELECT count(*) FROM utilisateur WHERE email = 'compte-panne@example.invalid'",
                        NEW_SERVER)[0][0]
        link_status = ask_link(down, manager)[0]
        ENV_LOCAL.write_text(f'DATABASE_URL="{NEW_URL.format(base)}"\nMAILER_DSN=smtp://mailpit:1025\n')
        check("panne du courriel sans erreur", status == 302 and created == '1' and link_status == 302,
              f'inscription {status}, compte créé {created}, demande de lien {link_status}')

        # Changer d'adresse déconnecte, et un lien part vers la nouvelle
        user = Client(side, NEW_EMAIL, slug)
        changed = 'compte-essai-2@example.invalid'
        user.get(f'/{slug}/user/edit', form_of(user, f'/{slug}/user/edit').filled({'[email]': changed}))
        status, headers, _ = user.get(f'/{slug}/user/edit')
        try:
            link = mailed_link(changed)
        except RuntimeError:
            link = ''
        check("changer d'adresse déconnecte", status == 302 and 'login' in headers.get('Location', '') and link,
              f"statut {status} {headers.get('Location', '')}, lien {'envoyé' if link else 'absent'}")

        # Le cookie de 30 jours, seul, reconnecte et note la dernière connexion
        boss = Client(side, manager, slug)
        query(base, f"UPDATE utilisateur SET derniereconnection = '2000-01-01' WHERE email = '{manager}'", NEW_SERVER)
        for cookie in [c for c in boss.jar if c.name != 'REMEMBERME']:
            boss.jar.clear(cookie.domain, cookie.path, cookie.name)
        status = boss.get(f'/{slug}/user/edit')[0]
        recent = query(base, f"""SELECT derniereconnection > now() - interval '5 minutes' FROM utilisateur
                                 WHERE email = '{manager}'""", NEW_SERVER)[0][0]
        check("le cookie reconnecte et note la connexion", status == 200 and recent == 't',
              f'statut {status}, date récente {recent}')

        # Reconnecté par le cookie, une page refusée répond 403, sans renvoyer à la connexion
        plain = Client(side, NEW_EMAIL.replace('compte-essai', 'compte-html'), slug)
        for cookie in [c for c in plain.jar if c.name != 'REMEMBERME']:
            plain.jar.clear(cookie.domain, cookie.path, cookie.name)
        status = plain.get(f'/{slug}/admin/mesure/import')[0]
        check("page refusée par le cookie : 403", status == 403, f'statut {status}')

        # Les rôles suivent l'observatoire : gestionnaire ici, pas ailleurs (un gestionnaire qui n'administre
        # pas tous les observatoires)
        local, = query(base, f"""
            SELECT u.email FROM utilisateur u JOIN role r ON r.utilisateur_id = u.id
              JOIN observatoire o ON o.id = r.observatoire_id
             WHERE r.valeur = 'gestionnaire' AND o.slug = '{slug}' AND u.roleadmin IS NULL
               AND (u.finacces IS NULL OR u.finacces > now()) ORDER BY u.id LIMIT 1""", NEW_SERVER)[0]
        elsewhere, = query(base, f"""
            SELECT o.slug FROM observatoire o WHERE NOT EXISTS (
                SELECT 1 FROM role r JOIN utilisateur u ON u.id = r.utilisateur_id
                 WHERE u.email = '{local}' AND r.valeur = 'gestionnaire'
                   AND (r.observatoire_id = o.id OR r.site_id IN (SELECT id FROM siteexperimental
                                                                   WHERE observatoire_id = o.id)))
             ORDER BY o.id LIMIT 1""", NEW_SERVER)[0]
        boss = Client(side, local, slug)
        statuses = [boss.get(f'/{s}/admin/mesure/import')[0] for s in (slug, elsewhere, slug)]
        check("les rôles suivent l'observatoire", statuses == [200, 403, 200], f'statuts {statuses}')

        # Un compte créé en console ne prévient pas les gestionnaires à sa première connexion
        subprocess.run([*console, 'security:users:create', 'Dominique', 'Console', 'console@example.invalid',
                        'FCT', 'non', '-n', '--env=prod'], capture_output=True)
        urllib.request.urlopen(urllib.request.Request(MAILPIT + '/api/v1/messages', method='DELETE'), timeout=60)
        Client(side, 'console@example.invalid', slug)
        time.sleep(2)
        subjects = [m['Subject'] for m in mail_messages()]
        check("compte créé en console : pas de courriel aux gestionnaires", not subjects, f'courriels {subjects}')

        # Les droits ne s'appliquent pas avec un jeton CSRF invalide
        boss = Client(side, manager, slug)
        right = f'/{slug}/user/right/{changed}'
        form = form_of(boss, right)
        before = query(base, "SELECT count(*) FROM role", NEW_SERVER)[0][0]
        status = boss.get(right, form.filled({'[_token]': 'faux', '[utilisateurChronique]': form.choices('[utilisateurChronique]')[:5]}))[0]
        after = query(base, "SELECT count(*) FROM role", NEW_SERVER)[0][0]
        check("droits sans jeton CSRF valide : rien ne change", before == after and status == 200,
              f'rôles {before} puis {after}, statut {status}')

        # La commande refuse un observatoire inconnu
        r = subprocess.run([*console, 'security:users:login-link', manager, 'INCONNU', '--env=prod'], capture_output=True)
        check("commande : observatoire inconnu refusé", r.returncode != 0, f'code {r.returncode}')
        return results

    sides, checks = {}, {}
    for name, base in [('ancien', 'bdoh_accounts_old'), ('nouveau', 'bdoh_accounts_new')]:
        copy_base(template, base)
        urllib.request.urlopen(urllib.request.Request(MAILPIT + '/api/v1/messages', method='DELETE'), timeout=60)
        if name == 'ancien':
            side = SIDES['reference-17']
            with old_17_on(base, mailer_transport='smtp', mailer_host='mailpit', mailer_port='1025') as console:
                sides[name] = {**play(side, console), **snapshot(side, base)}
        else:
            side = SIDES['new']
            # Les limites de fréquence repartent de zéro : d'une comparaison à l'autre, elles compteraient
            subprocess.run([*side.console, 'cache:pool:clear', 'cache.rate_limiter'], check=True, capture_output=True)
            with new_on(base, template, 'MAILER_DSN=smtp://mailpit:1025') as console:
                sides[name] = {**play(side, console), **snapshot(side, base)}
                checks = new_only_checks(side, console, base)

    report = Report('accounts')
    for part in sides['ancien']:
        report.add(part, sides['ancien'][part], sides['nouveau'].get(part, []))
    for part, lines in checks.items():
        report.add(part, ['conforme'], lines)
    report.close()
    print(f'Les instances lisent de nouveau {template}. Les deux copies restent pour examen :'
          ' bdoh_accounts_old et bdoh_accounts_new.')


# --- Administration --------------------------------------------------------------------------------

FIRST = object()  # la première des valeurs que propose la liste du formulaire
OBSERVATORY_PICK = '''SELECT min(t.id) FROM {table} t JOIN observatoire o ON o.id = t.observatoire_id
                       WHERE o.slug = '{{slug}}' '''

# Les administrations portées, dans l'ordre du menu, sous le nom qui fait leurs adresses : la table
# qu'elles écrivent, les champs changés à l'édition et remplis à la création (la fin de leur nom,
# sans l'identifiant de formulaire de Sonata), et la requête qui choisit l'entité dont on ouvre les
# pages ({slug} : l'observatoire de la page), par défaut la première de la table. Sans « change »,
# l'édition n'est que renvoyée telle quelle ; sans « create », ni création ni suppression (routes
# retirées par l'administration) ; « nodelete » : création sans suppression ; « state » : la requête
# de ACCOUNT_QUERIES qui lit la table, quand elle a des colonnes que les deux côtés écrivent autrement.
STATION_PICK = '''SELECT min(s.id) FROM station s JOIN stations_sites ss ON ss.station_id = s.id
                   JOIN siteexperimental se ON se.id = ss.site_id JOIN observatoire o ON o.id = se.observatoire_id
                  WHERE o.slug = '{slug}' '''

ADMINS = {
    'siteexperimental': {'table': 'siteexperimental', 'pick': OBSERVATORY_PICK.format(table='siteexperimental'),
                         'change': {'[descriptionEn]': '<p>Site (test)</p>'}, 'create': {'[nom]': 'Site d’essai'}},
    'station': {'table': 'station', 'also': ['stations_sites'], 'pick': STATION_PICK,
                'change': {'[commentaireEn]': 'Station (test)'},
                'create': {'[nom]': 'Station d’essai', '[code]': 'ESSAI', '[sites]': FIRST, '[altitude]': '123.4567',
                           '[latitude]': '45.1', '[longitude]': '4.9'}},
    'bareme': {'table': 'bareme', 'pick': OBSERVATORY_PICK.format(table='bareme')},
    'jeuqualite': {'table': 'jeuqualite'},
    'echantillonnage': {'table': 'echantillonnage'},
    'pasechantillonnage': {'table': 'pasechantillonnage'},
    'familleparametres': {'table': 'familleparametres', 'change': {'[nomEn]': 'Family (test)'},
                          'create': {'[nom]': 'Famille d’essai', '[nomEn]': 'Test family', '[prefix]': 'ESS'}},
    'typeparametre': {'table': 'typeparametre', 'also': ['parametres_unites'], 'change': {'[nomEn]': 'Type (test)'},
                      'create': {'[nom]': 'Type d’essai', '[nomEn]': 'Test type', '[code]': 'ESSAI',
                                 '[familleParametres]': FIRST}},
    'unite': {'table': 'unite', 'change': {'[libelleEn]': 'unit (test)'},
              'create': {'[libelle]': 'unité d’essai', '[libelleEn]': 'test unit'}},
    'typefunding': {'table': 'typefunding', 'change': {'[nom]': 'Financeur (essai)'},
                    'create': {'[nom]': 'Financeur d’essai'}},
    'dataconstraint': {'table': 'dataconstraint', 'change': {'[nom]': 'Contrainte (essai)'},
                       'create': {'[nom]': 'Contrainte d’essai'}},
    'criteriaclimat': {'table': 'criteriaclimat', 'change': {'[nom]': 'Climat (essai)'},
                       'create': {'[nom]': 'Climat d’essai'}},
    'criteriageology': {'table': 'criteriageology', 'change': {'[nom]': 'Géologie (essai)'},
                        'create': {'[nom]': 'Géologie d’essai'}},
    'bassin': {'table': 'bassin', 'pick': OBSERVATORY_PICK.format(table='bassin'), 'change': {'[nom]': 'Bassin (essai)'}},
    'courseau': {'table': 'courseau', 'pick': OBSERVATORY_PICK.format(table='courseau'),
                 'change': {'[nom]': 'Cours d’eau (essai)'}},
    'commune': {'table': 'commune', 'change': {'[nom]': 'Commune (essai)'},
                'create': {'[nom]': 'Commune d’essai', '[codePostal]': '00000', '[codeInsee]': '00000'}},
    'doi': {'table': 'doi', 'pick': OBSERVATORY_PICK.format(table='doi'), 'change': {'[descriptionEn]': 'DOI (test)'},
            'create': {'[identifiant]': '10.0000/essai', '[description]': 'DOI d’essai', '[descriptionEn]': 'test DOI'}},
    'milieu': {'table': 'milieu', 'change': {'[nom]': 'Milieu (essai)'}, 'create': {'[nom]': 'Milieu d’essai'}},
    'topiccategory': {'table': 'topiccategory', 'change': {'[nom]': 'Catégorie (essai)'},
                      'create': {'[nom]': 'Catégorie d’essai'}},
    'inspiretheme': {'table': 'inspiretheme', 'change': {'[nom]': 'Thème (essai)'}, 'create': {'[nom]': 'Thème d’essai'}},
    'theiacategories': {'table': 'theiacategories', 'change': {'[nom]': 'Correspondance (essai)'},
                        'create': {'[milieu]': FIRST, '[familleParametre]': FIRST, '[nom]': 'Correspondance d’essai',
                                   '[uriOzcarTheia]': 'https://w3id.org/ozcar-theia/essai'}},
    'personnetheia': {'table': 'personnetheia', 'change': {'[prenom]': 'Camille'},
                      'create': {'[nom]': 'Essai', '[prenom]': 'Camille', '[email]': 'personne-essai@example.invalid',
                                 '[partenaire]': FIRST}},
    'partenaire': {'table': 'partenaire', 'pick': OBSERVATORY_PICK.format(table='partenaire'),
                   'change': {'[scanR]': 'essai'},
                   'create': {'[nom]': 'Partenaire d’essai', '[iso]': 'FR', '[estFinanceur]': '0',
                              '[typeFundings]': FIRST}},
    'utilisateur': {'table': 'utilisateur', 'state': 'utilisateur', 'nodelete': True,
                    'change': {'[telephone]': '0102030405'},
                    'create': {'[email]': 'compte-admin@example.invalid', '[prenom]': 'Camille', '[nom]': 'Essai',
                               '[organisme]': 'INRAE', '[categorie]': FIRST, '[telephone]': '0102030405',
                               '[adresse]': '1 rue de l’Essai'}},
    'categorie': {'table': 'categorie'},
    'objectifrecherche': {'table': 'objectifrecherche'},
    'typetravaux': {'table': 'typetravaux'},
}


def admin_profiles(base):
    """Les comptes des pages de l'administration : un administrateur fonctionnel, sur celui des
    observatoires qu'il gère qui a le plus d'entités à administrer (ce rôle ne donne de droits sur les
    données que là), et un gestionnaire qui n'est que gestionnaire (celui de « pages » est aussi
    administrateur), sur un observatoire où il a ce rôle."""
    active = "(u.finacces IS NULL OR u.finacces > now())"
    tables = ['bassin', 'courseau', 'doi', 'partenaire', 'siteexperimental', 'dataset', 'bareme']
    admin, uid = query(base, f"""SELECT u.email, u.id FROM utilisateur u WHERE u.roleadmin = 'FCT' AND {active}
                                  ORDER BY u.id LIMIT 1""", NEW_SERVER)[0]
    richest = query(base, f"""
        SELECT o.slug FROM observatoire o JOIN role r ON r.observatoire_id = o.id
         WHERE r.utilisateur_id = {uid} AND r.valeur = 'gestionnaire'
         ORDER BY {' + '.join(f'(SELECT count(*) FROM {t} WHERE observatoire_id = o.id)' for t in tables)} DESC, o.id
         LIMIT 1""", NEW_SERVER)[0][0]
    manager = query(base, f"""
        SELECT u.email, o.slug FROM utilisateur u JOIN role r ON r.utilisateur_id = u.id
          JOIN observatoire o ON o.id = r.observatoire_id
         WHERE u.roleadmin IS NULL AND {active} AND r.valeur = 'gestionnaire' ORDER BY u.id, o.id LIMIT 1""",
                    NEW_SERVER)[0]
    return {'administrateur': (admin, richest), 'gestionnaire': tuple(manager)}


def redirect_lines(site, status, headers):
    """Le statut d'une réponse et l'adresse où elle renvoie, sous la forme des pages. Sonata 4 renvoie
    à une liste avec ses filtres du moment, Sonata 3 sans eux (dev/differences.md, « Administration
    sur Sonata 4 ») : ils ne comptent pas."""
    location = headers.get('Location', '').replace(site, '<site>')
    for pattern, repl in NORMALIZE:
        location = pattern.sub(repl, location)
    location = QUERY.sub(lambda m: m.group(1) + canonical_query(m.group(2)), location)
    location = re.sub(r'(/list)\?(filter%5B\w+%5D=[^&]*&?)+$', r'\1', location)
    return [f'statut {status}', f'redirection {location}']


def run_admin(args):
    """L'administration (module 6), par l'ancien code sur PostgreSQL 17 et par le nouveau, chacun sur
    sa copie de BASE. Pour chaque administration portée (ADMINS), au nom d'un administrateur et d'un
    gestionnaire : la liste, la création, l'édition et la suppression de son entité, sans rien
    envoyer ; puis, au nom de l'administrateur, l'édition renvoyée telle quelle, une édition qui
    change un champ, une création et la suppression de ce qui a été créé, avec les pages où elles
    renvoient ; enfin l'état des tables écrites et l'historique."""
    template = args.base
    historique = query(template, 'SELECT coalesce(max(id), 0) FROM historique', NEW_SERVER)[0][0]
    admins = {name: spec for name, spec in ADMINS.items() if args.only in name}
    accounts = admin_profiles(template)
    bases = {'ancien': 'bdoh_admin_old', 'nouveau': 'bdoh_admin_new'}
    for base in bases.values():
        copy_base(template, base)

    def first(base, name, spec, slug):
        sql = spec.get('pick', f"SELECT min(id) FROM {spec['table']}").format(slug=slug)
        return query(base, sql, NEW_SERVER)[0][0]

    def play(side, base):
        parts = {}
        urllib.request.urlopen(urllib.request.Request(MAILPIT + '/api/v1/messages', method='DELETE'), timeout=60)
        for profile, (email, slug) in accounts.items():
            client = Client(side, email, slug)
            get = lambda path: page_lines(side.url, *client.get(path))
            parts[f'{profile} /{slug}/admin/dashboard'] = get(f'/{slug}/admin/dashboard')
            for name, spec in admins.items():
                root, cid = f'/{slug}/admin/{name}', first(base, name, spec, slug)
                paths = [f'{root}/list', f'{root}/create']
                if cid:
                    paths += [f'{root}/{cid}/edit', f'{root}/{cid}/delete']
                for path in paths:
                    parts[f'{profile} {path}'] = get(path)

        email, slug = accounts['administrateur']
        client = Client(side, email, slug)

        def send(path, action, values, button):
            """Un formulaire de la page, envoyé comme par un navigateur, et la page où il renvoie."""
            form = form_of(client, path, action)
            if form.url is None:
                return [f'pas de formulaire {action} : {path}']
            values = {k: (form.choices(k)[:1] or [''])[0] if v is FIRST else v for k, v in values.items()}
            if form.buttons and button not in form.buttons:
                button = form.buttons[0]
            body = form.filled(values) + b'&' + urllib.parse.urlencode({button: ''}).encode()
            status, headers, _ = client.get(form.url, body, {'Referer': side.url + path})
            lines = redirect_lines(side.url, status, headers)
            location = headers.get('Location', '').replace(side.url, '')
            return lines + (['== page suivante', *page_lines(side.url, *client.get(location))] if location else [])

        for name, spec in admins.items():
            root, cid = f'/{slug}/admin/{name}', first(base, name, spec, slug)
            if not cid:
                continue
            parts[f'{name} : édition renvoyée telle quelle'] = send(
                f'{root}/{cid}/edit', f'/{name}/{cid}/edit', {}, 'btn_update_and_edit')
            if 'change' in spec:
                parts[f'{name} : édition'] = send(f'{root}/{cid}/edit', f'/{name}/{cid}/edit', spec['change'],
                                                 'btn_update_and_edit')
            if 'create' in spec:
                parts[f'{name} : création'] = send(f'{root}/create', f'/{name}/create', spec['create'],
                                                  'btn_create_and_edit')
                if not spec.get('nodelete'):
                    created = query(base, f"SELECT max(id) FROM {spec['table']}", NEW_SERVER)[0][0]
                    parts[f'{name} : suppression'] = send(f'{root}/{created}/delete', f'/{name}/{created}/delete',
                                                         {}, 'btn_delete')
        time.sleep(2)  # les courriels du nouveau partent après la réponse (DeferredMailer)
        parts['courriels'] = [line.replace(side.url, '<site>') for line in mail_lines()]
        for name, spec in sorted(admins.items(), key=lambda item: item[1]['table']):
            sql = ACCOUNT_QUERIES[spec['state']] if 'state' in spec else \
                f"SELECT to_jsonb(t)::text FROM {spec['table']} t ORDER BY t.id"
            # Symfony 7 écrit les fins de ligne des champs de texte long en \n, Symfony 2 telles que le
            # navigateur les envoie, en \r\n (dev/differences.md, « Fins de ligne des textes longs »)
            parts[f"état {spec['table']}"] = ['\t'.join(row).replace('\\r\\n', '\\n')
                                             for row in query(base, sql, NEW_SERVER)]
        for table in sorted({t for spec in admins.values() for t in spec.get('also', [])}):
            parts[f'état {table}'] = ['\t'.join(row) for row in query(
                base, f"SELECT to_jsonb(t)::text FROM {table} t ORDER BY 1", NEW_SERVER)]
        parts['état historique'] = state_lines(base, historique, ['historique'])['historique']
        return parts

    sides = {}
    with old_17_on(bases['ancien'], mailer_transport='smtp', mailer_host='mailpit', mailer_port='1025'), \
            new_on(bases['nouveau'], template, 'MAILER_DSN=smtp://mailpit:1025'):
        sides['ancien'] = play(SIDES['reference-17'], bases['ancien'])
        sides['nouveau'] = play(SIDES['new'], bases['nouveau'])
    report = Report('admin')
    for part in sides['ancien']:
        report.add(part, sides['ancien'][part], sides['nouveau'].get(part, []))
    report.close()
    print(f'Les instances lisent de nouveau {template}. Les deux copies restent pour examen :'
          f' {bases["ancien"]} et {bases["nouveau"]}.')


def main():
    global CASES_SERVER
    parser = argparse.ArgumentParser(description=__doc__.split('\n')[0])
    parser.add_argument('what', choices=['pages', 'exports', 'state', 'queue', 'imports', 'accounts', 'admin'])
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
     'imports': run_imports, 'accounts': run_accounts, 'admin': run_admin}[args.what](args)


if __name__ == '__main__':
    sys.exit(main())
