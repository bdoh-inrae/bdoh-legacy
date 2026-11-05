#!/usr/bin/env python3
"""Compare deux BDOH : mêmes requêtes, sorties comparées.

    dev/compare/compare.py pages   [--old SIDE] [--new SIDE] [--base BASE] [--only TEXTE]
    dev/compare/compare.py exports [--old SIDE] [--new SIDE] [--base BASE] [--only TEXTE]
    dev/compare/compare.py state   [--base BASE] [--inject-sql FICHIER]
    dev/compare/compare.py queue   [--base BASE]
    dev/compare/compare.py imports [--base BASE] [--only TEXTE]
    dev/compare/compare.py accounts [--base BASE]
    dev/compare/compare.py admin   [--base BASE] [--only TEXTE]
    dev/compare/compare.py theia   [--base BASE] [--only TEXTE]
    dev/compare/compare.py selftest [--base BASE] [--only TEXTE]
    dev/compare/compare.py fingerprints EMPREINTE_A EMPREINTE_B

SIDE nomme une instance (voir SIDES). Les cas sont tirés de la base BASE du serveur de référence,
par des règles écrites ici : le dépôt ne contient ni données ni codes de chroniques. Le rapport et
les écarts vont dans ../bdoh_reference/compare/, hors du dépôt.

« state » mène les mêmes calculs (recalcul, conversion, taux de remplissage, puis la file de tâches)
avec l'ancien code sur PostgreSQL 17 et avec le nouveau, chacun sur sa copie de BASE, et compare
l'état des deux copies. « queue » est l'essai de la file de tâches (audit des risques, constat 3).

Les pages se comparent par ce qu'elles montrent (texte, liens, formulaires, attributs data-*), pas
par leur balisage ; les nombres décimaux, à un arrondi près (règle « Arrondis à la limite » de
dev/differences.md), compté à part pour être relu. Un écart admis (dev/compare/expected.py) se compte à
part aussi ; un autre fait échouer le mode, de même qu'un autre ordre hors des listes que ce registre nomme.
Le rapport dit ce qui a été comparé, et le code de sortie vaut 1 s'il reste un cas différent. « selftest » éprouve l'outil : des erreurs inventées sur une
copie de base, qu'il doit toutes voir. « fingerprints » compare, en comptes, deux empreintes écrites par
make fingerprint. Bibliothèque standard seulement.
"""

import argparse
import concurrent.futures
import contextlib
import datetime
import decimal
import difflib
import hashlib
import html
import html.parser
import http.cookiejar
import io
import json
import os
import re
import socketserver
import subprocess
import sys
import threading
import time
import urllib.error
import urllib.parse
import urllib.request
import zipfile
from dataclasses import dataclass
from pathlib import Path

sys.dont_write_bytecode = True
import expected  # noqa: E402  (dev/compare/expected.py)
import invented  # noqa: E402  (dev/compare/invented.py)

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
    done = subprocess.run(
        ['docker', 'exec', '-i', server or CASES_SERVER, 'psql', '-U', 'bdoh', '-d', base,
         '-v', 'ON_ERROR_STOP=1', '-At', '-F', '\t', '-c', sql], capture_output=True, text=True)
    if done.returncode:
        # La première ligne de l'erreur seulement : la suite (DETAIL) peut citer une ligne de la base.
        raise RuntimeError(f"psql sur {base} : {(done.stderr.splitlines() or ['sans message'])[0]}\n{sql[:300]}")
    return [line.split('\t') for line in done.stdout.splitlines()]


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
    # Les configurations incomplètes relevées par l'inspection des bases, qui doivent s'afficher : la
    # première chronique calculée sans première transformation, et la page des transformations qui
    # la décrit ; la première chronique convertie sans conversion
    for slug, station, code, cid, dtype in query(base, f"""
            SELECT DISTINCT ON (c.dtype) c.slug, c.station, c.code, c.id, c.dtype FROM ({CHRONIQUES}) c
              JOIN chronique ch ON ch.id = c.id
             WHERE (c.dtype = 'calculee' AND ch.premiereentree_id IS NULL)
                OR (c.dtype = 'convertie' AND ch.conversion_id IS NULL)
             ORDER BY c.dtype, c.id"""):
        paths.append(f'/{slug}/{station}/{code}')
        if dtype == 'calculee':
            paths.append(f'/{slug}/admin/get-chronique-calculee?id={cid}')
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
        # Le format Vigilance n'était proposé nulle part dans l'ancien et n'avait pas de classe : il échouait,
        # et le nouveau ne l'accepte plus (dev/differences.md).
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
    # « Opérateurs des filtres sur une entité liée »)
    (re.compile(r'^T Est égal à$'), 'T est égal à'),
    (re.compile(r'^T Ne contient pas$'), "T n'est pas égal à"),
    # Le bouton qui retire l'entité choisie d'un champ ModelListType : un lien vide sous Sonata 3, un
    # bouton sous Sonata 4
    (re.compile(r'^A$'), 'I button button'),
    # La page du choix du type d'une chronique à créer : Sonata 3.23 n'en traduisait pas les libellés
    # (dev/differences.md, « Administration sur Sonata 4 »)
    (re.compile(r"^T Sélectionnez le type d'objet$"), 'T title_select_subclass'),
    (re.compile(r"^T Aucun type d'objet disponible$"), 'T no_subclass_available'),
    (re.compile(r'(?<![0-9A-Za-z])s[0-9a-f]{13}(?![0-9a-f])'), 's<uniqid>'),  # identifiants de formulaires de Sonata
    (re.compile(r'data:image/[\w+]+;base64,[\w+/=]+'), '<image>'),  # captcha
    (re.compile(r'(data-target=#[a-z]+-)\d+'), r'\1<n>'),  # fenêtres numérotées au hasard
    # Le jeton CSRF des pages des couleurs et de la conversion, nom et valeur tirés au hasard
    (re.compile(r'^I input hidden CSRFToken[0-9a-f]{16} [0-9a-f]{128}$'), 'I input hidden CSRFToken<jeton>'),
    # Les barèmes d'un calcul dans l'historique, repliés sous un identifiant tiré au hasard (mt_rand)
    (re.compile(r'(?<![\w])\d+_(first|second)Bareme_CollapseId'), r'<n>_\1_CollapseId'),
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
    l'ordre de Sonata 3), sans le mode de liste par défaut que Sonata 4 ajoute aux liens de tri, ni
    son mode de choix dans les listes d'entités liées ; dans le choix du nombre de lignes par page,
    sans le tri, que Sonata 3 y omettait quand il était celui par défaut."""
    pairs = urllib.parse.parse_qsl(query, keep_blank_values=True)
    # Le lien vers la liste d'une entité liée (champ ModelListType) : Sonata 4 y ajoute le mode de choix
    choosing = any(k == 'pcode' for k, _ in pairs)
    pairs = [(k, v) for k, v in pairs if (k, v) != ('_list_mode', 'list')
             and not (per_page_option and k == 'filter[_sort_by]') and not (choosing and (k, v) == ('select', '1'))]
    return urllib.parse.urlencode(sorted(pairs))


# Les mentions légales, réécrites pour INRAE, et le lien vers le code source (dev/differences.md)
LEGAL_NOTICE = re.compile(r"<p style='text-align: center;'>\s*(?:Institut national|INRAE).*?</p>", re.S)
# Les boutons et la fenêtre d'export suivent le droit de télécharger, que le nouveau ouvre à qui n'a pas de compte
# (dev/decisions.md, « Ouverture des données »). Là où l'ancien ne les montrait pas, ils sortent de la comparaison
# (strip_export) ; là où il les montre, le formulaire se compare, sauf les conditions d'utilisation, qui deviennent
# la licence pour une chronique visible, et le texte d'aide. open_data_checks vérifie ce que ces masques retirent.
EXPORT_WIDGETS = re.compile(r'(?:<!-- The modal for Terms Of Uses -->.*?)?<!-- Link which triggers the modal for export -->.*?'
                            r'<!-- END_The modal for export -->|<button class="graph-export-button.*?</button>', re.S)
EXPORT_TERMS = re.compile(r'<!-- Link which triggers the modal for Terms of Uses -->.*?<!-- Submit and close buttons -->', re.S)
EXPORT_HELP = re.compile(r'<div class="export-help-content">.*?</div>', re.S)
EXPORT_ARCHIVE = re.compile(r'--write-to\s+\S+\.zip\s*')  # le chemin de l'archive, dans les pages de suivi des tâches
EXPORT_MARK = b'<!-- Link which triggers the modal for export -->'


def page_lines(site, status, headers, body, strip_export=False):
    """Ce que montre une réponse, en lignes ; l'adresse de l'instance devient <site>."""
    lines = [f'statut {status}']
    if 300 <= status < 400:
        lines.append(f"redirection {headers.get('Location', '')}")
    kind = headers.get('Content-Type', '').split(';')[0]
    lines.append(f'type {kind}')
    if kind == 'text/html':
        parser = Extract()
        text = LEGAL_NOTICE.sub('', body.decode('utf-8', 'replace'))
        text = EXPORT_WIDGETS.sub('', text) if strip_export else text
        parser.feed(EXPORT_ARCHIVE.sub('', EXPORT_HELP.sub('', EXPORT_TERMS.sub('', text))))
        parser.flush()
        # Un script et une feuille que l'administration de l'ancien chargeait pour rien (dev/differences.md)
        lines += [line for line in parser.lines if not re.match(r'(S|L stylesheet) /assets/(locales/|admin/measure-import\.css)', line)]
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
        # Les fenêtres cachées des champs d'entités liées, que Sonata 3 écrivait même sans bouton pour les
        # ouvrir : leur bouton de fermeture ne compte pas
        closing = ['I button button', 'D button data-dismiss=modal', 'T ×']
        lines = [line for k, line in enumerate(lines)
                 if not any(lines[k - i:k - i + 3] == closing for i in range(3) if k - i >= 0)]
    # Symfony 7 ne code pas les paramètres d'adresse comme Symfony 2 (%20 ou +, %2C ou virgule…) :
    # même adresse, comparée sous une forme canonique (dev/differences.md).
    lines = [QUERY.sub(lambda m: m.group(1) + canonical_query(
                 m.group(2), line.startswith('O ') and 'filter%5B_per_page%5D' in line), line)
             if line[:2] in ('A ', 'F ', 'D ', 'S ', 'L ', 'G ', 'O ') else line
             for line in lines]
    return [line.replace(site, '<site>') for line in lines]


# --- Comparaison ---------------------------------------------------------------------------------

def recent_days():
    """Le jour où l'on est et la veille. Les dates du moment s'écrivent en UTC ou à l'heure de Paris selon
    le côté (dev/differences.md, « Deux fuseaux horaires »), et une passe peut franchir minuit. Aucune date
    d'une base anonymisée n'est aussi récente."""
    today = datetime.date.today()
    return [today, today - datetime.timedelta(days=1)]


def moment_mask():
    """Les dates du moment, en ISO (arguments d'une tâche) et en français (descriptions de l'historique)."""
    return re.compile('|'.join([*(f'{d}T[\\d:]+' for d in recent_days()),
                                *(f'{d:%d/%m/%Y} [\\d:]+' for d in recent_days())]))


def export_line(line):
    """Une ligne d'un fichier d'export, sans ce qui dit quand il a été fait : la date de son entête, et le
    jour de la ligne DEB du format Qtvar (date('Ymd')). Un autre jour que les derniers reste un écart."""
    line = re.sub(r'^(Export du|Export of) .*? (au format|in format)', r'\1 <date> \2', line)
    if line.startswith('DEB;'):
        for day in recent_days():
            line = line.replace(f'{day:%Y%m%d}', '<jour>')
    return line


# Une date ou une heure, à laisser telle quelle ; sinon un nombre, pris entier, pas au milieu d'un mot
TOKEN = re.compile(r'(?P<moment>\d{4}-\d\d-\d\d(?:[T ]\d\d:\d\d(?::\d\d(?:\.\d+)?)?)?'
                   r'|\d\d/\d\d/\d{4}(?: \d\d:\d\d(?::\d\d)?)?|\d\d:\d\d(?::\d\d(?:\.\d+)?)?)'
                   r'|(?<![\w.])(?P<number>-?\d+(?:[.,]\d+)?(?:[eE][-+]?\d+)?)(?![\w])')


def close_numbers(x, y):
    """Deux nombres écrits qui peuvent être le même, arrondi autrement (règle « Arrondis à la limite » de
    dev/differences.md). Un entier ne s'arrondit pas : c'est un compteur, un identifiant, un code."""
    if x == y:
        return True
    if ',' in x and ',' in y:  # une virgule ne se tolère que face à un point : l'ancien écrivait 3,34
        return False
    a, b = decimal.Decimal(x.replace(',', '.')), decimal.Decimal(y.replace(',', '.'))
    if a == b:  # 3,34 et 3.34 ; -0.000 et 0.000 ; 1.50 et 1.5
        return True
    if ',' in x + y or not any(c in x + y for c in '.eE'):
        return False
    exponent_x, exponent_y = a.as_tuple().exponent, b.as_tuple().exponent
    if exponent_x == exponent_y:  # mêmes décimales : une unité du dernier chiffre
        return abs(a - b) <= decimal.Decimal(1).scaleb(exponent_x)
    # Autres décimales : le même nombre, écrit à 15 chiffres par PostgreSQL 11, à 17 par PostgreSQL 17
    return abs(a - b) <= max(abs(a), abs(b)) * decimal.Decimal('1e-14')


def same_but_rounding(a, b):
    """Deux lignes qui ne diffèrent que par des nombres arrondis autrement. Les dates, les heures et les
    entiers doivent être identiques."""
    if TOKEN.sub('#', a) != TOKEN.sub('#', b):
        return False
    for x, y in zip(TOKEN.finditer(a), TOKEN.finditer(b)):
        if x.group('moment') or y.group('moment'):
            if x.group(0) != y.group(0):
                return False
        elif not close_numbers(x.group('number'), y.group('number')):
            return False
    return True


def compare(old, new, exact=False):
    """Écarts entre deux suites de lignes : (lignes de diff, paires de lignes à un arrondi près,
    ordre seul).

    Deux suites de même longueur se comparent ligne à ligne ; le diff complet, coûteux sur une
    longue page, ne sert que s'il reste un écart. « Ordre seul » : les mêmes lignes, ou les mêmes
    éléments d'une liste, dans un autre ordre (listes que l'ancien ne triait pas). exact : sans
    tolérance d'arrondi, pour ce qu'on lit en base sous un même PostgreSQL."""
    same_but_rounding = (lambda a, b: False) if exact else globals()['same_but_rounding']
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


def classify(old, new, exact=False):
    """Le verdict sur deux suites de lignes : (catégorie du rapport, lignes de diff, paires arrondies)."""
    diff, rounding, order_only = compare(old, new, exact)
    if diff:
        return ('même contenu, autre ordre' if order_only else 'différents'), diff, rounding
    return ('à un arrondi près' if rounding else 'identiques'), diff, rounding


def admitted(rules, old, new, exact=False):
    """Ces règles du registre expliquent-elles tout l'écart entre deux suites de lignes ?"""
    masks = [(re.compile(pattern), replacement) for rule in rules for pattern, replacement in rule.masks]

    def masked(lines):
        for pattern, replacement in masks:
            lines = [pattern.sub(replacement, line) for line in lines]
        return lines

    def found(wanted, line):
        return wanted == line if isinstance(wanted, str) else wanted.fullmatch(line) is not None

    diff = compare(masked(old), masked(new), exact)[0]
    changed = [line for line in diff[2:] if line[0] in '+-']  # ni les deux lignes de tête, ni le contexte
    allowed = [wanted for rule in rules for wanted in rule.lines]
    return (all(any(found(wanted, line) for wanted in allowed) for line in changed)
            and all(any(found(wanted, line) for line in changed) for rule in rules for wanted in rule.must))


def registered(rules, name, key, old, new, exact=False):
    """Ce que le registre fait d'un écart : (catégorie du rapport, règles qui l'expliquent). Un autre ordre
    n'est admis que pour une liste qu'il nomme."""
    rules = [rule for rule in rules if re.fullmatch(rule.case, name)]
    if key == 'même contenu, autre ordre':
        lists = [rule for rule in rules if rule.order]
        return (key, lists) if lists else ('différents', [])
    if key == 'différents' and rules and admitted(rules, old, new, exact):
        return 'écarts admis', rules
    return key, []


def run_text(*command):
    """La sortie d'une commande, ou rien si elle échoue."""
    r = subprocess.run(command, capture_output=True, text=True)
    return r.stdout.strip() if r.returncode == 0 else ''


def instance_base(side):
    """La base que lit réellement une instance, d'après sa propre console."""
    console = SIDES[side].console
    command = 'dbal:run-sql' if SIDES[side].login_link else 'doctrine:query:sql'
    found = re.search(r'\bbdoh\w*', run_text(*console, command, 'SELECT current_database() AS base', '--env=prod'))
    return found.group(0) if found else 'inconnue'


def check_instances(args):
    """Les deux instances comparées lisent-elles la base dont on a tiré les cas ? Pour les copies d'un
    mode (bdoh_state_old, bdoh_state_new), chacune la sienne."""
    for side in (args.old, args.new):
        base = instance_base(side)
        if base not in (args.base, args.base.replace('_old', '_new')):
            sys.exit(f"L'instance {side} lit la base {base}, pas {args.base} : dev/compare/use-base.sh {args.base}")


def provenance():
    """Ce qui a été comparé, pour que le rapport le prouve : la commande, le code, les versions."""
    root = str(HERE.parent.parent)
    changed = run_text('git', '-C', root, 'status', '--porcelain', '--', 'src', 'config', 'migrations', 'templates',
                       'dev/compare', 'dev/reference')
    servers = [f"{name} : {run_text('docker', 'exec', name, 'psql', '-U', 'postgres', '-Atc', 'SHOW server_version') or 'arrêté'}"
               for name in ('bdoh-reference-db', NEW_SERVER)]
    return [f"Commande : {' '.join(sys.argv[1:])}",
            f"Code : {run_text('git', '-C', root, 'rev-parse', '--short', 'HEAD')}"
            + (' et des fichiers modifiés' if changed else ''),
            f"PostgreSQL : {', '.join(servers)}",
            f"Fin : {datetime.datetime.now():%Y-%m-%d %H:%M:%S}"]


class Report:
    failed = False  # un cas différent dans ce lancement : le code de sortie le dit

    def __init__(self, kind, exact=False):
        self.dir = OUT / f"{datetime.datetime.now():%Y-%m-%d_%H%M%S}_{kind}"
        self.dir.mkdir(parents=True)
        self.counts = {'identiques': 0, 'à un arrondi près': 0, 'même contenu, autre ordre': 0, 'écarts admis': 0,
                       'différents': 0}
        self.summary = []
        self.exact = exact
        self.rules = [rule for rule in expected.RULES if rule.mode == kind]
        self.used = []

    def add(self, name, old, new):
        # L'état d'une base se compare sans tolérance : deux copies sous un même PostgreSQL n'ont aucune
        # raison de différer d'un bit (dev/decisions.md, « Nombres lus en base, réécrits en texte »)
        exact = self.exact or name.startswith('état ') or name in STATE_QUERIES or name in ACCOUNT_QUERIES \
            or name == 'autres tables'
        key, diff, rounding = classify(old, new, exact)
        key, rules = registered(self.rules, name, key, old, new, exact)
        self.used += rules
        self.counts[key] += 1
        if diff or rounding:
            # Écrits pour un système de fichiers, deux noms de cas peuvent se confondre (« +01:00 » et
            # « -01:00 ») : leur empreinte les sépare, et reste la même d'une passe à l'autre.
            slug = re.sub(r'[^\w.-]+', '_', name).strip('_')[:140]
            unique = hashlib.sha1(name.encode()).hexdigest()[:8]
            shown = [f'~ {a}\n~ {b}' for a, b in rounding[:20]]
            (self.dir / f'{slug}.{unique}.diff').write_text('\n'.join([name, *diff, *shown, '']))
            self.summary.append(f"{key:<26} {name}" + (f'  ({len(rounding)} arrondis)' if rounding else '')
                                + (f"  [{rules[0].why}]" if rules else ''))
        print('X' if key == 'différents' else '.', end='', flush=True)

    def close(self):
        # Les comptes d'abord, puis la provenance, puis les cas qui diffèrent. Une règle sans écart n'est pas une
        # erreur (--only, ou un écart qui a disparu), mais elle se relit : elle n'a peut-être plus lieu d'être.
        unused = [f'Règle sans écart : {rule.case}  [{rule.why}]' for rule in self.rules if rule not in self.used]
        text = '\n'.join([*(f'{n:>6} {k}' for k, n in self.counts.items()), '', *provenance(), '', *self.summary, *unused, ''])
        (self.dir / 'rapport.txt').write_text(text)
        Report.failed = Report.failed or self.counts['différents'] > 0
        print('\n' + text + f'Rapport : {self.dir}')


def run_pages(args):
    check_instances(args)
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
                responses = [client.get(path) for client in pair]
                strip = EXPORT_MARK not in responses[0][2]
                lines = [page_lines(client.side.url, *response, strip_export=strip) for client, response in zip(pair, responses)]
                # Les conditions disent INRAE et non plus Irstea (dev/differences.md, « Ouverture des données ») :
                # leur statut et leur type seulement.
                return [page[:2] for page in lines] if path.endswith(('/conditions', '/terms_of_use')) else lines

            for path, lines in zip(paths, pool.map(fetch, paths)):
                report.add(f'{profile} {path}', *lines)
    report.close()


def run_exports(args):
    check_instances(args)
    old, new = SIDES[args.old], SIDES[args.new]

    def run(side, case):
        r = subprocess.run([*side.console, *case, '--env=prod'], capture_output=True, text=True)
        lines = [f'code de sortie {r.returncode}', *r.stdout.splitlines(), *r.stderr.splitlines()]
        # Le moment de l'export et le dossier temporaire changent à chaque fois.
        return [export_line(line) for line in lines if not line.startswith('# Printing ')]

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
    et une sans fille ; les taux de remplissage d'une chronique continue ; enfin le recalcul des
    configurations incomplètes relevées par l'inspection des bases, qui doit être refusé proprement :
    une chronique calculée sans première transformation, une convertie sans conversion."""
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
        ['bdoh:compute:chronique', first("""SELECT min(id) FROM chronique
                                             WHERE dtype = 'calculee' AND premiereentree_id IS NULL"""), user],
        ['bdoh:convert:chronique', first("""SELECT min(c.id) FROM chronique c WHERE c.dtype = 'convertie'
             AND NOT EXISTS (SELECT 1 FROM conversion cv WHERE cv.sortie_id = c.id)"""), user],
    ]


# L'empreinte au bit près d'une base, table par table : dev/reference/fingerprint.sql
FINGERPRINTS = {table: sql for sql, table in re.findall(
    r"^(SELECT '(\w+)', .*?);$", (HERE.parent / 'reference' / 'fingerprint.sql').read_text(), re.M | re.S)}

# Ce que les calculs écrivent, sans les dates du moment (mise à jour, création et exécution des
# tâches) ni la trace d'erreur des tâches, que BDOH ne lit pas (dev/differences.md) ; les mesures, les
# plages, les points de contrôle et les taux, en une empreinte par chronique, au bit près.
STATE_QUERIES = {
    **FINGERPRINTS,
    'chronique': """
        SELECT ((to_jsonb(c) - 'miseajour')
                || jsonb_build_object('miseajour récente', c.miseajour > now() - interval '1 hour'))::text
          FROM chronique c ORDER BY c.id""",
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
    'station': "SELECT (to_jsonb(s) - 'point')::text, ST_AsEWKT(point) FROM station s ORDER BY id",
    'transformation': "SELECT * FROM transformation ORDER BY id",
    'jeubareme': "SELECT (to_jsonb(j) - 'datecreation')::text FROM jeubareme j ORDER BY j.id",
    'baremejeubareme': "SELECT * FROM baremejeubareme ORDER BY id",
    'bassin': "SELECT (to_jsonb(b) - 'perimetre')::text, md5(ST_AsEWKT(perimetre)) FROM bassin b ORDER BY id",
    'courseau': "SELECT (to_jsonb(c) - 'trace')::text, md5(ST_AsEWKT(trace)) FROM courseau c ORDER BY id",
}

# Ce qu'une empreinte de table laisse de côté, dans toutes les tables : les dates du moment et ce qui
# est tiré au hasard ; ces colonnes se comparent, quand elles comptent, par les requêtes nommées.
MOMENT_COLUMNS = ['miseajour', 'datecreation', 'derniereconnection', 'password', 'salt', 'confirmationtoken',
                  'theiapassword', 'executed_at', 'lastrunat']


def other_tables_lines(base, known):
    """Toutes les tables que les requêtes nommées (known) ne lisent pas, une ligne chacune : son nombre
    de lignes et son empreinte ; et le nombre de tables du schéma temp, que les imports remplissent. Une
    écriture dans une table qu'on n'attendait pas se voit ainsi. L'empreinte est une somme, sans tri ni texte
    assemblé : assembler celle de 257 millions de mesures dépasse la mémoire de PostgreSQL."""
    tables = [row[0] for row in query(base, """SELECT table_name FROM information_schema.tables
                                                WHERE table_schema = 'bdoh' AND table_type = 'BASE TABLE'
                                                ORDER BY 1""", NEW_SERVER) if row[0] not in known]
    keys = ''.join(f" - '{column}'" for column in MOMENT_COLUMNS)
    lines = []
    for table in tables:
        if table in FINGERPRINTS:  # les mesures et leurs voisines : plus vite en binaire, et au bit près
            rows = query(base, FINGERPRINTS[table], NEW_SERVER)
            lines.append(f'{table}\t{sum(int(r[2]) for r in rows)}\t{sum(int(r[3]) for r in rows)}')
            continue
        row = query(base, f"""SELECT count(*), coalesce(sum(('x' || substr(md5((to_jsonb(t){keys})::text), 1, 15))::bit(60)::bigint), 0)
                                FROM bdoh.{table} t""", NEW_SERVER)[0]
        lines.append(f'{table}\t' + '\t'.join(row))
    temp = query(base, "SELECT count(*) FROM information_schema.tables WHERE table_schema = 'temp'", NEW_SERVER)[0][0]
    return [*lines, f'schéma temp\t{temp} tables']


SINK_HOST, SINK_PORT = '172.30.26.1', 2525  # smtp_sink(), sur la passerelle du réseau de compose.yaml
MAILPIT = 'http://172.30.26.25:8025'  # service mailpit de compose.yaml (make mail-start)


EXPORT_LINK = re.compile(r'Votre export de chronique est prêt : \S*/mesure/export/\d+/([0-9a-f]{32})/file\r?\nCe lien reste valable \d+ jours\.')
OLD_EXPORT_BODY = 'Veuillez trouver en pièce jointe votre export de chronique.'
EXPORT_FILES = lambda token: (HERE.parent.parent / 'var' / 'exports' / f'{token}.zip').read_bytes()


LICENCE_FILE = re.compile(r'ConditionsGeneralesUtilisation|Licence-Ouverte|Open-Licence')


def archive_lines(name, kind, body):
    """Une archive d'export : son nom et son type, puis ses fichiers."""
    lines = [f'Pièce jointe : {name} ({kind})']
    with zipfile.ZipFile(io.BytesIO(body)) as z:
        for inner in z.namelist():
            # Les fichiers de licence changent selon les chroniques : open_data_checks les vérifie
            if inner.endswith('.pdf') and LICENCE_FILE.search(inner):
                continue
            content = z.read(inner)
            if inner.endswith('.txt'):
                lines += [f'== {inner}', *map(export_line, content.decode('utf-8', 'replace').splitlines())]
            else:
                lines.append(f'== {inner} {hashlib.sha256(content).hexdigest()[:16]}')
    return lines


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
        # L'ancien joint l'archive, le nouveau donne un lien (dev/differences.md, « Ouverture des données ») : l'archive
        # du lien se lit comme une pièce jointe, et c'est son contenu qui se compare.
        link = EXPORT_LINK.search(m['Text'])
        text = EXPORT_LINK.sub(OLD_EXPORT_BODY, m['Text'])
        lines += [f"Sujet : {m['Subject']}", f"De : {address(m['From'])}",
                  'À : ' + ', '.join(sorted(map(address, m['To']))),
                  *(['Cc : ' + ', '.join(sorted(map(address, m['Cc'])))] if m.get('Cc') else []),
                  *text.rstrip().splitlines()]
        if link:
            lines += archive_lines('export.zip', 'application/zip', EXPORT_FILES(link.group(1)))
        if m['HTML']:
            lines += page_lines(MAILPIT, 200, {'Content-Type': 'text/html'}, m['HTML'].encode())[2:]
        for part in m['Attachments']:
            lines += archive_lines(part['FileName'], part['ContentType'], get(f"/api/v1/message/{summary['ID']}/part/{part['PartID']}"))
    return [line for lines in (messages if ordered else sorted(messages)) for line in lines]


NEW_URL = 'postgresql://bdoh@db:5432/{}?serverVersion=17&charset=utf8'
ENV_LOCAL = HERE.parent.parent / '.env.local'  # la base du nouveau BDOH, comme l'écrit use-base.sh


def copy_base(template, base):
    """Une copie fraîche d'une base, sur le serveur PostgreSQL 17."""
    for sql in [f'DROP DATABASE IF EXISTS {base} WITH (FORCE)', f'CREATE DATABASE {base} TEMPLATE {template} OWNER bdoh']:
        subprocess.run(['docker', 'exec', NEW_SERVER, 'psql', '-U', 'postgres', '-v', 'ON_ERROR_STOP=1',
                        '-qc', sql], check=True)


def configure_old_17(text):
    """Écrit les paramètres de l'ancien code sur PostgreSQL 17, vide son cache et recharge Apache. Sans ce
    rechargement, le cache d'opcodes de PHP 7.1 garde les fichiers de tous les caches vidés, et Apache finit par
    planter à chaque requête ou presque (une cinquantaine de vidages, en jouant toutes les fautes de mutants.py)."""
    OLD_17_PARAMETERS.write_text(text)
    for command in [['cache:clear', '--no-warmup'], ['cache:warmup']]:
        subprocess.run([*SIDES['reference-17'].console, *command, '--env=prod'], check=True, capture_output=True)
    subprocess.run(['docker', 'exec', 'bdoh-reference-app-17', 'apache2ctl', '-k', 'graceful'], check=True, capture_output=True)


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
    moment = moment_mask()
    offset = re.compile(r'(\dT\d\d:\d\d:\d\d|<maintenant>)[+-]\d\d:\d\d')
    # Le chemin de l'archive, qui n'existe que dans le nouveau (dev/differences.md, « Ouverture des données »)
    archive = re.compile(r'\\?"--write-to\\?",\s*\\?"[^"\\]*\\?",\s*')
    return {table: [archive.sub('', offset.sub(r'\1', moment.sub('<maintenant>', '\t'.join(row)))) if table == 'jms_jobs'
                    else moment.sub('<maintenant>', '\t'.join(row))
                    for row in query(base, STATE_QUERIES[table].format(historique=historique), NEW_SERVER)]
            for table in tables}


def inject_sql(base, path):
    """Une faute écrite en SQL, dans une copie : comme le ferait une migration fautive (mutants.py)."""
    subprocess.run(['docker', 'exec', '-i', NEW_SERVER, 'psql', '-U', 'bdoh', '-d', base, '-v', 'ON_ERROR_STOP=1', '-q'],
                   input=Path(path).read_text(), text=True, check=True, capture_output=True)


@contextlib.contextmanager
def refusing(base, operation, table, chronique):
    """Une écriture sur une chronique que la base refuse, le temps du bloc : la panne la plus simple à provoquer."""
    row = 'NEW' if operation == 'INSERT' else 'OLD'
    query(base, """CREATE FUNCTION essai_refus() RETURNS trigger LANGUAGE plpgsql AS
                   $$ BEGIN RAISE EXCEPTION 'refus de cet essai'; END $$""", NEW_SERVER)
    query(base, f"""CREATE TRIGGER essai_refus BEFORE {operation} ON {table} FOR EACH ROW
                    WHEN ({row}.chronique_id = {chronique}) EXECUTE FUNCTION essai_refus()""", NEW_SERVER)
    try:
        yield
    finally:
        query(base, f'DROP TRIGGER essai_refus ON {table}', NEW_SERVER)
        query(base, 'DROP FUNCTION essai_refus()', NEW_SERVER)


def failed_computation_checks(console, base, chronique, user):
    """Un calcul en échec laisse la chronique telle quelle, et sort sur son code (dev/differences.md, « Calcul
    en échec qui vide la chronique ») : une insertion refusée donne le code 16, un effacement refusé le 32.
    Un déclencheur fait échouer le calcul ; sans lui, le calcul rend de nouveau les mêmes mesures. Rien ne se
    lit des mesures qu'une empreinte."""
    def state():
        return [query(base, FINGERPRINTS['mesure'].replace('GROUP BY', f'WHERE chronique_id = {chronique} GROUP BY'), NEW_SERVER),
                query(base, f'SELECT nbmesures, datedebutmesures, datefinmesures FROM chronique WHERE id = {chronique}', NEW_SERVER)]

    def computed():
        return subprocess.run([*console, 'bdoh:compute:chronique', chronique, user, '--env=prod'], capture_output=True).returncode

    before, checks = state(), {}
    for name, operation, expected in [('insertion refusée', 'INSERT', 16), ('effacement refusé', 'DELETE', 32)]:
        with refusing(base, operation, 'mesure', chronique):
            code = computed()
        checks[f'nouveau seul : calcul en échec, {name}, chronique gardée'] = (
            ['conforme'] if code == expected and state() == before and before[0]
            else [f'code {code}, attendu {expected} ; chronique {"gardée" if state() == before else "changée"}'])
    code = computed()
    checks['nouveau seul : calcul refait après un échec, mêmes mesures'] = (
        ['conforme'] if code == 0 and state()[0] == before[0] else [f'code {code}'])
    return checks


def emptied_with_milliseconds_check(side, base, cid, page, manager, slug):
    """Vider l'intérieur d'une chronique met en lacune, même si sa dernière mesure a des millisecondes
    (dev/differences.md, « Vider une période, dernière mesure à millisecondes »)."""
    query(base, f"""UPDATE mesure SET date = date + interval '500 milliseconds' WHERE id =
                    (SELECT id FROM mesure WHERE chronique_id = {cid} ORDER BY date DESC LIMIT 1) RETURNING id""", NEW_SERVER)
    iso = """'YYYY-MM-DD"T"HH24:MI:SS"Z"'"""
    begin, end = query(base, f"""
        SELECT to_char(min(date), {iso}), to_char(max(date), {iso}) FROM (
            SELECT date FROM mesure WHERE chronique_id = {cid} ORDER BY date
            OFFSET (SELECT count(*) / 2 FROM mesure WHERE chronique_id = {cid}) LIMIT 5) t""", NEW_SERVER)[0]
    count = lambda: query(base, f'SELECT count(*) FROM mesure WHERE chronique_id = {cid}', NEW_SERVER)[0][0]
    before = count()
    status = Client(side, manager, slug).get(f'{page}/empty', urllib.parse.urlencode(
        {'beginDate': begin, 'endDate': end}).encode(), {'Referer': side.url + page})[0]
    gaps = query(base, f"""SELECT count(*) FROM mesure m JOIN qualite q ON q.id = m.qualite_id
                            WHERE m.chronique_id = {cid} AND q.type = 'gap'
                              AND m.date >= '{begin}' AND m.date <= '{end}'""", NEW_SERVER)[0][0]
    ok = status == 302 and count() == before and gaps != '0'
    return {'nouveau seul : vider une période, dernière mesure à millisecondes, en lacune':
            ['conforme'] if ok else [f'statut {status}, {before} mesures avant, {count()} après, {gaps} lacunes']}


def open_data_checks(side, console, runner, base, checks):
    """L'ouverture des données (dev/decisions.md, « Ouverture des données ») : ce qui s'ouvre et ce qui reste fermé.
    Sur une copie à elle, avec Mailpit ; rien ne se lit des valeurs d'une chronique cachée, seulement des statuts.
    checks se remplit au fil de l'eau, pour qu'un arrêt ne perde pas les résultats précédents."""
    def check(name, ok, detail=''):
        checks[f'nouveau seul : ouverture, {name}'] = ['conforme' if ok else f'non conforme : {detail}']

    def chronique(where):
        return query(base, f"""SELECT c.id, c.slug, c.station, c.code FROM ({CHRONIQUES}) c JOIN chronique ch ON ch.id = c.id
                                WHERE c.dtype = 'continue' AND ch.nbmesures > 0 AND {where} ORDER BY ch.nbmesures, c.id LIMIT 1""", NEW_SERVER)[0]

    visible, slug, station, code = chronique('c.estvisible')
    other = query(base, f"""SELECT c.id FROM ({CHRONIQUES}) c WHERE c.slug = '{slug}' AND c.estvisible AND c.id <> {visible}
                             AND c.dtype = 'continue' ORDER BY c.id LIMIT 1""", NEW_SERVER)[0][0]
    hidden, hidden_slug, hidden_station, hidden_code = chronique('NOT c.estvisible')
    role = lambda obs, value: query(base, f"""
        SELECT u.email FROM utilisateur u JOIN role r ON r.utilisateur_id = u.id JOIN observatoire o ON o.id = r.observatoire_id
         WHERE r.valeur = '{value}' AND r.dtype = 'observatoire' AND o.slug = '{obs}' AND (u.finacces IS NULL OR u.finacces > now())
         ORDER BY u.id LIMIT 1""", NEW_SERVER)[0][0]
    stranger = query(base, """SELECT u.email FROM utilisateur u WHERE u.roleadmin IS NULL AND (u.finacces IS NULL OR u.finacces > now())
                                AND NOT EXISTS (SELECT 1 FROM role r WHERE r.utilisateur_id = u.id) ORDER BY u.id LIMIT 1""", NEW_SERVER)[0][0]
    manager = Client(side, role(hidden_slug, 'gestionnaire'), hidden_slug)
    jobs = lambda: query(base, 'SELECT count(*) FROM jms_jobs', NEW_SERVER)[0][0]
    form = {'beginDate': '1800-01-01T00:00:00Z', 'endDate': '2030-01-01T00:00:00Z', 'exportFormat': 'Bdoh', 'exportType': 'identical',
            'instantStep': '60', 'meanCumulStep': '7', 'timezone': '1'}

    def export(client, where, ids, **extra):
        return client.get(f'/{where}/mesure/export', urllib.parse.urlencode({**form, 'chroniques[]': ids, **extra}, doseq=True).encode(),
                          {'Referer': f'{side.url}/{where}/'})

    limiter = lambda: subprocess.run([*console, 'cache:pool:clear', 'cache.rate_limiter', '--env=prod'], check=True, capture_output=True)
    limiter()

    # La fenêtre d'export, ce que EXPORT_WIDGETS retire de la comparaison
    anonymous = Client(side, None, slug)
    status, _, body = anonymous.get(f'/{slug}/{station}/{code}')
    check('fenêtre d’export d’une chronique visible, sans compte, avec la licence',
          status == 200 and EXPORT_MARK in body and b'export-licence' in body and b'name="termsOfUses"' not in body and b'graph-export-button' in body, f'statut {status}')
    status, _, body = manager.get(f'/{hidden_slug}/{hidden_station}/{hidden_code}')
    check('fenêtre d’export d’une chronique cachée, avec le droit, avec ses conditions',
          status == 200 and b'name="termsOfUses"' in body and b'export-licence' not in body, f'statut {status}')
    check('page d’une chronique cachée, compte sans droit', Client(side, stranger, hidden_slug).get(f'/{hidden_slug}/{hidden_station}/{hidden_code}')[0] == 403)

    # Sans compte : une chronique visible, par son lien
    logged = lambda: query(base, "SELECT count(*) FROM historique WHERE auteur_id IS NULL AND dtype ILIKE '%export%'", NEW_SERVER)[0][0]
    logged_before = logged()
    status, headers, _ = export(anonymous, slug, visible)
    found = re.search(r'/mesure/export/(\d+)/([0-9a-f]{32})$', headers.get('Location', ''))
    check('chronique visible, demandée sans compte', status == 302 and found, f"statut {status}, {headers.get('Location')}")
    job, token = found.groups()
    page = f'/{slug}/mesure/export/{job}/{token}'
    status, headers, body = anonymous.get(page)
    check('page de suivi, en attente, qui se recharge', status == 200 and headers.get('Refresh'), f'statut {status}')
    check('archive pas encore prête', anonymous.get(f'{page}/file')[0] == 404)
    drain(console, runner, base)
    status, headers, body = anonymous.get(page)
    check('page de suivi, prête', status == 200 and not headers.get('Refresh') and f'{token}/file'.encode() in body, f'statut {status}')
    status, headers, archive = anonymous.get(f'{page}/file')
    check('archive sans compte', status == 200 and archive[:2] == b'PK' and 'export.zip' in headers.get('Content-Disposition', ''), f'statut {status}')
    names = lambda body: zipfile.ZipFile(io.BytesIO(body)).namelist()
    has = lambda body, text: any(text in name for name in names(body))
    check('chronique visible, Licence Ouverte française et anglaise, sans les conditions',
          has(archive, 'Licence-Ouverte-2.0-fr.pdf') and has(archive, 'Open-Licence-2.0-en.pdf') and not has(archive, 'Conditions'), str(names(archive)))
    check('export sans compte noté dans l’historique', int(logged()) > int(logged_before), f'{logged_before} puis {logged()}')

    # Le même fichier qu'avec un compte, dont le lien arrive par courriel
    mails = lambda: json.loads(urllib.request.urlopen(MAILPIT + '/api/v1/messages', timeout=60).read())['messages']
    last_link = lambda: re.search(r'(/[\w-]+/mesure/export/\d+/[0-9a-f]{32}/file)', json.loads(urllib.request.urlopen(
        f"{MAILPIT}/api/v1/message/{mails()[0]['ID']}", timeout=60).read())['Text'])
    owner = Client(side, role(slug, 'gestionnaire'), slug)
    before = len(mails())
    export(owner, slug, visible)
    drain(console, runner, base)
    check('courriel d’un export avec compte, sans pièce jointe', len(mails()) == before + 1 and not mails()[0]['Attachments'],
          f'{before} courriels, puis {len(mails())}')
    link = last_link()
    same = link and owner.get(link.group(1))[2]
    check('même archive avec ou sans compte', link and archive_lines('', '', same) == archive_lines('', '', archive))
    check('lien d’une chronique visible, ouvert à tous', link and Client(side, None, slug).get(link.group(1))[0] == 200)

    # Une chronique cachée reste fermée
    before = jobs()
    check('chronique cachée, sans compte', export(Client(side, None, hidden_slug), hidden_slug, hidden, termsOfUses='1')[0] == 403)
    check('chronique cachée, compte sans droit', export(Client(side, stranger, hidden_slug), hidden_slug, hidden, termsOfUses='1')[0] == 403)
    mixed = query(base, f"""SELECT c.id FROM ({CHRONIQUES}) c WHERE c.estvisible AND c.slug = '{hidden_slug}' AND c.dtype = 'continue' ORDER BY c.id LIMIT 1""", NEW_SERVER)
    if mixed:
        check('demande mêlée, sans compte', export(Client(side, None, hidden_slug), hidden_slug, [mixed[0][0], hidden], termsOfUses='1')[0] == 403)
    check('aucune tâche pour ces demandes', jobs() == before, f'{before} puis {jobs()}')
    check('chronique cachée, conditions non cochées', export(manager, hidden_slug, hidden)[0] == 400)
    check('valeurs d’une chronique cachée, sans compte', Client(side, None, hidden_slug).get(
        f'/{hidden_slug}/chronique/{hidden}/measures?beginDate=2024-01-01T00:00:00Z&endDate=2024-02-01T00:00:00Z')[0] == 403)

    # L'archive d'une chronique cachée redemande son droit à qui ouvre le lien
    export(manager, hidden_slug, hidden, termsOfUses='1')
    drain(console, runner, base)
    link = last_link()
    if link:
        path = link.group(1)
        status, headers, _ = Client(side, None, hidden_slug).get(path)
        check('archive d’une chronique cachée, sans compte', status == 302 and 'login' in headers.get('Location', ''), f'statut {status}')
        check('archive d’une chronique cachée, compte sans droit', Client(side, stranger, hidden_slug).get(path)[0] == 403)
        status, _, hidden_archive = manager.get(path)
        check('archive d’une chronique cachée, avec le droit', status == 200, f'statut {status}')
        check('chronique cachée, les conditions, sans la Licence Ouverte',
              status == 200 and has(hidden_archive, 'Conditions') and not has(hidden_archive, 'Licence') and not has(hidden_archive, 'Open-Licence'))
        if mixed:
            export(manager, hidden_slug, [mixed[0][0], hidden], termsOfUses='1')
            drain(console, runner, base)
            status, _, both = manager.get(last_link().group(1))
            check('archive mêlée, les trois fichiers et la liste', status == 200 and has(both, 'Licence-Ouverte') and has(both, 'Open-Licence')
                  and has(both, 'Conditions') and has(both, 'Licences.txt'))
        # Sa chronique a disparu entre-temps : le lien reste fermé
        hidden_job = re.search(r'/export/(\d+)/', path).group(1)
        query(base, f"""UPDATE jms_jobs SET args = regexp_replace(args::text, '\\m{hidden}\\M', '999999999')::json WHERE id = {hidden_job} RETURNING id""", NEW_SERVER)
        status = Client(side, None, hidden_slug).get(path)[0]
        check('archive d’une chronique supprimée, sans compte', status == 302, f'statut {status}')
    else:
        check('lien de l’archive d’une chronique cachée', False, 'pas de lien')

    # Un jeton faux
    check('jeton inconnu', anonymous.get(f'/{slug}/mesure/export/{job}/{"0" * 32}')[0] == 404
          and anonymous.get(f'/{slug}/mesure/export/{job}/{"0" * 32}/file')[0] == 404)

    # Les points de contrôle d'une chronique visible, comme avec un droit
    pointed = query(base, f"""SELECT c.id, c.slug FROM ({CHRONIQUES}) c JOIN chronique ch ON ch.id = c.id
        WHERE c.estvisible AND EXISTS (SELECT 1 FROM pointcontrole p WHERE p.chronique_id = c.id) ORDER BY ch.nbmesures, c.id LIMIT 1""", NEW_SERVER)[0]
    measures = f'/{pointed[1]}/chronique/{pointed[0]}/measures?beginDate=1800-01-01T00:00:00Z&endDate=2030-01-01T00:00:00Z'
    without, with_right = Client(side, None, pointed[1]).get(measures), Client(side, role(pointed[1], 'gestionnaire'), pointed[1]).get(measures)
    check('points de contrôle d’une chronique visible, comme avec un droit',
          without[0] == 200 and without[2] == with_right[2] and b'controlPoints' in without[2], f'statut {without[0]}')

    # Un lien échu : le prochain export retire l'archive
    stale = HERE.parent.parent / 'var' / 'exports' / f'{token}.zip'
    moment = time.time() - 8 * 86400
    os.utime(stale, (moment, moment))
    status, _, body = anonymous.get(page)
    check('lien échu, avant même le prochain export', status == 200 and 'expiré'.encode() in body and anonymous.get(f'{page}/file')[0] == 404, f'statut {status}')
    export(anonymous, slug, visible)
    drain(console, runner, base)
    check('archive échue retirée par le prochain export', not stale.exists())

    # La place est comptée : au-delà, les plus anciennes partent, même avant leur terme
    kept = sorted((HERE.parent.parent / 'var' / 'exports').glob('*.zip'))
    tight = [*console[:2], '-e', 'EXPORTS_MAX_BYTES=1', *console[2:]]
    export(anonymous, slug, visible)
    drain(tight, runner, base)
    check('place dépassée, les archives les plus anciennes retirées', kept and not any(path.exists() for path in kept), f'{len(kept)} archives avant')

    # Une archive qui ne s'écrit pas fait échouer la commande, et --send-to ne va pas sans tâche
    period = ['2024-01-01T00:00:00+00:00', '2024-02-01T00:00:00+00:00']
    done = subprocess.run([*console, 'bdoh:export:identical', *period, visible, '--write-to', '/dossier-absent/essai.zip', '--env=prod'], capture_output=True)
    check('archive impossible à écrire, commande en échec', done.returncode != 0, f'code {done.returncode}')
    done = subprocess.run([*console, 'bdoh:export:identical', *period, visible, '--write-to', '/tmp/essai.zip', '--send-to', role(slug, 'gestionnaire'), '--env=prod'], capture_output=True)
    check('--send-to sans tâche, refusé', done.returncode != 0, f'code {done.returncode}')

    # Le seuil : une chronique seule passe toujours, un ensemble lourd demande un compte
    subprocess.run(['docker', 'exec', '-i', NEW_SERVER, 'psql', '-U', 'bdoh', '-d', base, '-v', 'ON_ERROR_STOP=1', '-q'], check=True, capture_output=True, text=True, input=f"""
        INSERT INTO mesure (id, chronique_id, qualite_id, date, valeur, estcalculee)
        SELECT nextval('mesure_id_seq'), {visible}, (SELECT qualite_id FROM mesure WHERE chronique_id = {visible} LIMIT 1),
               timestamp '1800-01-01' + n * interval '1 minute', 1, false FROM generate_series(1, 5000100) n;
        UPDATE chronique SET nbmesures = nbmesures + 5000100 WHERE id = {visible};""")
    limiter()
    before = jobs()
    status, headers, _ = export(anonymous, slug, [visible, other])
    check('ensemble trop lourd, refusé sans compte', status == 302 and jobs() == before and '/mesure/export/' not in headers.get('Location', ''),
          f"statut {status}, {before} tâches puis {jobs()}")
    check('la même chronique seule, acceptée', export(anonymous, slug, visible)[0] == 302 and int(jobs()) == int(before) + 1, f'{jobs()} tâches')
    status = export(owner, slug, [visible, other], termsOfUses='1')[0]
    check('ensemble trop lourd, accepté avec un compte', status == 302 and int(jobs()) == int(before) + 2, f'statut {status}, {jobs()} tâches')

    # La limite de fréquence, par adresse IP et sans compte
    limiter()
    before = jobs()
    for _ in range(21):
        export(Client(side, None, slug), slug, visible)
    check('limite de fréquence sans compte', int(jobs()) == int(before) + 20, f'{before} tâches puis {jobs()}')

    # La file chargée : les 2 demandes du seuil et ces 20-là font 22 tâches en attente, sur 30 admises sans compte
    limiter()
    before = jobs()
    for _ in range(10):
        export(Client(side, None, slug), slug, visible)
    waiting = query(base, "SELECT count(*) FROM jms_jobs WHERE queue = 'job_queue_export' AND state IN ('new', 'pending')", NEW_SERVER)[0][0]
    check('file chargée, refusé sans compte', waiting == '30', f'{waiting} en attente')
    limiter()


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
    # Un export en moyenne d'une chronique discontinue échoue des deux côtés : la tâche à relancer
    discontinue, discontinue_slug = query(template, f"""
        SELECT c.id, c.slug FROM ({CHRONIQUES}) c JOIN chronique ch ON ch.id = c.id
         WHERE c.dtype = 'discontinue' AND ch.nbmesures > 0 ORDER BY c.id LIMIT 1""", NEW_SERVER)[0]
    checks = {}
    lab = invented.choose(lambda sql: query(template, sql, NEW_SERVER))
    def play(side, console, runner, base):
        """Les calculs, les exports demandés par le formulaire, une tâche annulée, une semaine de
        mesures vidée avec propagation, une tâche relancée, la file jusqu'au bout, le jeu de calcul
        inventé ; rend ce que chaque étape a répondu, et ce que le jeu inventé a écrit."""
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
        for fields, where in [(form, slug), (dict(form, exportType='mean'), slug),
                              (dict(form, exportType='mean', **{'chroniques[]': discontinue}), discontinue_slug),
                              (dict(form, exportFormat='Qtvar'), slug)]:
            status, headers, _ = post(f'/{where}/mesure/export', fields)
            log.append(f"export {fields['exportFormat']} {fields['exportType']} : {status} "
                       f"{headers.get('Location', '').replace(side.url, '')}")
        if side.login_link:
            # Le format Vigilance, que rien ne sait écrire, ne part plus en tâche (dev/differences.md)
            jobs = lambda: query(base, 'SELECT count(*) FROM jms_jobs', NEW_SERVER)[0][0]
            before = jobs()
            post(f'/{slug}/mesure/export', dict(form, exportFormat='Vigilance'))
            checks['nouveau seul : format Vigilance refusé, aucune tâche'] = (
                ['conforme'] if jobs() == before else [f'{before} tâches avant, {jobs()} après'])
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
        computed = invented.play(console, NEW_SERVER, base, lab, commands[0][2])
        drain(console, runner, base)  # les taux de remplissage que ces calculs demandent
        return log, computed

    sides, computed = {}, {}
    for name, base in [('ancien', 'bdoh_state_old'), ('nouveau', 'bdoh_state_new')]:
        copy_base(template, base)
        urllib.request.urlopen(urllib.request.Request(MAILPIT + '/api/v1/messages', method='DELETE'), timeout=60)
        if name == 'ancien' or args.same:  # --same : l'ancien deux fois, ce qui mesure le bruit de l'outil
            with old_17_on(base, mailer_transport='smtp', mailer_host='mailpit', mailer_port='1025') as console:
                log, computed[name] = play(SIDES['reference-17'], console, 'jms-job-queue:run', base)
        else:
            if args.inject_sql:
                inject_sql(base, args.inject_sql)
            with new_on(base, template, 'MAILER_DSN=smtp://mailpit:1025') as console:
                log, computed[name] = play(SIDES['new'], console, 'bdoh:jobs:run', base)
        sides[name] = {'étapes': log, 'courriels': mail_lines(), **state_lines(base, historique),
                       'autres tables': other_tables_lines(base, STATE_QUERIES),
                       **{f'état après le calcul inventé, {step}': lines for step, lines in computed[name].items()}}
        if name == 'nouveau' and not args.same:
            with new_on(base, template) as console:
                checks.update(failed_computation_checks(console, base, *commands[0][1:3]))
                checks.update(emptied_with_milliseconds_check(SIDES['new'], base, cid, f'/{slug}/{station}/{code}', manager, slug))
            # Sur une copie à elle : elle ajoute des tâches et des millions de mesures, que les pages de suivi comparées
            # plus bas ne doivent pas voir
            copy_base(template, 'bdoh_open_data')
            exports = HERE.parent.parent / 'var' / 'exports'
            try:
                with new_on('bdoh_open_data', template, 'MAILER_DSN=smtp://mailpit:1025') as console:
                    open_data_checks(SIDES['new'], console, 'bdoh:jobs:run', 'bdoh_open_data', checks)
            except Exception as e:  # un arrêt ne doit pas perdre le rapport : il se lit comme un écart
                checks['nouveau seul : ouverture, arrêt du cas'] = [f'{type(e).__name__} : {str(e)[:200]}']
            finally:
                # Rien ne reste d'une archive, dont une de chronique cachée
                for path in exports.glob('*.zip*'):
                    path.unlink()
                subprocess.run(['docker', 'exec', NEW_SERVER, 'psql', '-U', 'postgres', '-qc', 'DROP DATABASE IF EXISTS bdoh_open_data WITH (FORCE)'], capture_output=True)

    report = Report('state')
    for part in sides['ancien']:
        report.add(part, sides['ancien'][part], sides['nouveau'][part])
    for part, lines in checks.items():
        report.add(part, ['conforme'], lines)
    # Le jeu inventé contre ses résultats écrits à la main, et contre PostgreSQL 11 (sans la ligne des dates,
    # que le PHP écrit) : au bit près, ces fonctions ne font passer aucun nombre par du texte
    for step, lines in invented.expected().items():
        report.add(f'état écrit à la main, {step}', lines, computed['nouveau'][step])
    for step, lines in invented.under_postgresql_11(CASES_SERVER, template, lab).items():
        report.add(f'état sous PostgreSQL 11 et 17, {step}', lines, [computed['nouveau'][step][0], *computed['nouveau'][step][2:]])
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

def queue_overlaps(base):
    """Les tâches d'une même file menées en même temps, ce qui ne doit jamais arriver, en lignes ; et le
    nombre de paires de tâches menées de front, sans quoi l'essai ne prouve rien."""
    overlaps = query(base, """
        SELECT a.queue, a.id, b.id FROM jms_jobs a JOIN jms_jobs b
          ON b.queue = a.queue AND b.id > a.id
         AND b.startedat < a.closedat AND a.startedat < b.closedat""", NEW_SERVER)
    parallel = query(base, """
        SELECT count(*) FROM jms_jobs a JOIN jms_jobs b ON b.id > a.id
         AND b.startedat < a.closedat AND a.startedat < b.closedat""", NEW_SERVER)[0][0]
    return [' '.join(row) for row in overlaps], int(parallel)


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

    sides, overlaps, parallel = {}, {}, {}
    for name, base, jobs in [('deux à la fois', 'bdoh_queue_parallel', 2), ('une à une', 'bdoh_queue_serial', 1)]:
        copy_base(template, base)
        for chronique in [parent, child, parent]:
            enqueue(base, chronique)
        console = ['docker', 'exec', '-e', f'DATABASE_URL={NEW_URL.format(base)}', 'bdoh-app', 'php', 'bin/console']
        drain(console, 'bdoh:jobs:run', base, jobs=jobs, rounds=60)
        sides[name] = state_lines(base, 0, ['chronique', 'mesure', 'tauxremplissage'])
        # Les tâches, sans leur ordre, qui dépend de celui où elles finissent
        mask = moment_mask()
        sides[name]['tâches'] = sorted(mask.sub('<maintenant>', ' '.join(row)) for row in query(base, """
            SELECT command, args::text, state, exitcode FROM jms_jobs""", NEW_SERVER))
        overlaps[name], parallel[name] = queue_overlaps(base)
        print(f'{name} : {len(sides[name]["tâches"])} tâches, {parallel[name]} paires menées en même temps,'
              f' {len(overlaps[name])} dans une même file')

    report = Report('queue', exact=True)  # le nouveau contre lui-même
    for part in sides['une à une']:
        report.add(part, sides['une à une'][part], sides['deux à la fois'][part])
    report.add('même file en même temps, deux à la fois', [], overlaps['deux à la fois'])
    report.add('tâches menées de front, deux à la fois', ['oui'], ['oui' if parallel['deux à la fois'] else 'non'])

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
            overlaps[name], parallel[name] = queue_overlaps(base)
            print(f'{name} : {len(sides[name]["tâches"])} tâches, {parallel[name]} paires menées en même temps,'
                  f' {len(overlaps[name])} dans une même file')
        for part in sides['imports, une à une']:
            report.add(f'imports, {part}', sides['imports, une à une'][part], sides['imports, deux à la fois'][part])
        report.add('même file en même temps, imports, deux à la fois', [], overlaps['imports, deux à la fois'])
    report.close()


def multipart(fields, files):
    """Le corps d'un formulaire multipart/form-data, et son type ; fields, un dictionnaire ou une liste
    de paires (un champ à plusieurs valeurs)."""
    boundary = '----bdoh-compare-' + hashlib.sha256(repr((fields, files)).encode()).hexdigest()[:16]
    parts = []
    for name, value in (fields.items() if isinstance(fields, dict) else fields):
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


def import_steps(client, site, case, after_upload=None):
    """Un import de mesures par ses étapes, comme le JavaScript les envoie ; rend les réponses. after_upload se
    joue entre le téléversement et la lecture du fichier."""
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
    if after_upload:
        after_upload()
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
        types = case.get('types') or {case['cid']: case['type']}
        response = post('step4', {**{f'importTypes[{c}]': kind for c, kind in types.items()}, 'propagate': 'propagate'})
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
    # Une station et une valeur écrites en HTML, que les messages d'erreur citent
    add('station écrite en HTML', bdoh(rows(24), st='<b>INCONNUE</b>'))
    html_value = rows(25)
    html_value[1][1] = '<i>abc</i>'
    add('valeur écrite en HTML', bdoh(html_value))
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
    # Jours manquants (dev/differences.md) : un trou de plus d'un mois, des lignes qui reviennent en arrière
    for name, dates in [('QJO, trou de plus d’un mois', ['20220301', '20220415']),
                        ('QJO, lignes dans le désordre', ['20210601', '20210520'])]:
        lines = ['DEC;.  6 13', f'950;{station};essai;0;0;0;0;essai;0;',
                 *[f'QJO;{station};{d};1.5; ;9;' for d in dates], 'FIN;EXP-HYDRO;1;']
        cases.append({'name': name, 'format': 'Qjo', 'content': ('\n'.join(lines) + '\n').encode(),
                      'type': 'keepExisting', 'cid': cid, 'slug': slug, 'station': station, 'chronique': code,
                      'timezone': '+1'})
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


def real_import_cases(base):
    """De vrais fichiers d'import, tels que les gestionnaires les écrivent (hors du dépôt, ../bdoh_reference/data-test-import,
    déposés par Lou) : (nom, sorte, cas). Ils peuvent porter des valeurs de chroniques cachées : on n'en lit que l'en-tête, et
    leurs rapports se lisent en comptes. Un barème de ce nom existe déjà en base : il s'importe sous un autre nom."""
    cases = []
    folder = OUT.parent / 'data-test-import'
    for path in sorted(folder.glob('*')) if folder.is_dir() else []:
        content = path.read_bytes()
        head = [re.split(r'\s*;\s*', line.strip()) for line in content.decode('utf-8-sig', 'replace').splitlines()[:3]]
        if head[0][0].lower() == 'nom':
            cases.append((path.name, 'barème', {'content': content.replace(head[1][0].encode(), f'{head[1][0]}_essai'.encode(), 1)}))
            continue
        codes = head[1][2::2]
        rows = query(base, f"""SELECT c.id, c.slug, c.code FROM ({CHRONIQUES}) c
                                WHERE c.station = '{head[1][0]}' AND c.code IN ({','.join(f"'{code}'" for code in codes)})""", NEW_SERVER)
        ids = [row[0] for row in rows]
        if path.name.lower().startswith('controle'):
            cases.append((path.name, 'contrôle', {'content': content, 'cid': ids[0], 'slug': rows[0][1]}))
        else:
            cases.append((path.name, 'mesures', {
                'name': path.name, 'format': 'BdohPlage' if head[2][0].lower() == 'debut' else 'Bdoh', 'content': content,
                'filename': 'import.txt', 'cid': ids[0], 'slug': rows[0][1], 'type': 'overwriteExisting',
                'types': {cid: 'overwriteExisting' for cid in ids}}))
    return cases


@contextlib.contextmanager
def small_tmp():
    """Un /tmp minuscule pour le nouveau, le temps du bloc : une panne que les imports doivent dire. Le conteneur
    d'une image sans droit de monter quoi que ce soit se recrée, et ses sessions partent avec lui : se connecter
    dans le bloc."""
    def app(*files):
        env = {**os.environ, 'HOST_UID': str(os.getuid()), 'HOST_GID': str(os.getgid()), 'REFERENCE_DIR': str(OUT.parent)}
        subprocess.run(['docker', 'compose', '-f', str(HERE.parent.parent / 'compose.yaml'), *files, 'up', '-d', '--no-deps', 'app'],
                       check=True, capture_output=True, env=env)
        for _ in range(120):
            try:
                Client(SIDES['new'], None, '').get('/')
                return
            except OSError:
                time.sleep(0.5)
        raise RuntimeError('le nouveau BDOH ne répond plus')

    app('-f', str(HERE / 'small-tmp.yaml'))
    try:
        yield
    finally:
        app()


def import_failure_checks(template, base, check):
    """Ce que seul le nouveau garantit d'un import, sur sa copie, une fois le reste comparé : tout ou rien quand la
    base refuse une écriture, deux archives de même nom qui ne se mêlent pas, le contrôle des points de contrôle,
    /tmp plein dit (dev/differences.md, « Import qui échoue en route », « Archives de même nom », « Points de
    contrôle »). Chaque panne est provoquée ; rien ne se lit des mesures que des comptes et des empreintes.
    check(nom, lignes) note chaque résultat dès qu'il est connu, pour qu'un arrêt ne perde pas les précédents."""
    cid, slug, controles = controle_cases(template)
    station, code, unite = query(template, f"""
        SELECT c.station, c.code, u.libelle FROM ({CHRONIQUES}) c JOIN chronique ch ON ch.id = c.id
          JOIN unite u ON u.id = ch.unite_id WHERE c.id = {cid}""", NEW_SERVER)[0]
    other, other_code, other_unite = query(template, f"""
        SELECT c.id, c.code, u.libelle FROM ({CHRONIQUES}) c JOIN chronique ch ON ch.id = c.id
          JOIN unite u ON u.id = ch.unite_id
         WHERE c.dtype = 'continue' AND c.station = '{station}' AND c.slug = '{slug}' AND c.id <> {cid}
         ORDER BY c.id LIMIT 1""", NEW_SERVER)[0]
    manager = query(template, f"""
        SELECT u.email FROM utilisateur u JOIN role r ON r.utilisateur_id = u.id
          JOIN observatoire o ON o.id = r.observatoire_id
         WHERE r.valeur = 'gestionnaire' AND o.slug = '{slug}' AND (u.finacces IS NULL OR u.finacces > now())
         ORDER BY u.id LIMIT 1""", NEW_SERVER)[0][0]
    site, client = SIDES['new'].url, Client(SIDES['new'], manager, slug)
    ajax = {'X-Requested-With': 'XMLHttpRequest'}
    prints = lambda table, *ids: query(base, FINGERPRINTS[table].replace(
        'GROUP BY', f"WHERE chronique_id IN ({','.join(ids)}) GROUP BY"), NEW_SERVER)
    jobs = lambda: query(base, 'SELECT count(*) FROM jms_jobs', NEW_SERVER)[0][0]
    rows = query(base, f"""
        SELECT to_char(m.date + interval '1 hour', 'DD/MM/YYYY HH24:MI:SS'), coalesce(m.valeur::text, '-9999'), q.code
          FROM mesure m JOIN qualite q ON q.id = m.qualite_id
         WHERE m.chronique_id = {cid} AND m.date >= '2024-01-01' AND m.date < '2024-01-15'
         ORDER BY m.date LIMIT 50""", NEW_SERVER)
    moved = lambda v: v if v == '-9999' else f'{float(v) + 0.5:g}'
    measures = lambda lines, **extra: {'format': 'Bdoh', 'content': ('\n'.join(lines) + '\n').encode(),
                                       'type': 'overwriteExisting', 'cid': cid, 'slug': slug, **extra}
    one = ['Station;Fuseau;Chronique;Unite', f'{station};UTC+01;{code};{unite}', 'DateHeure;Valeur;Qualite',
           *[f'{d};{moved(v)};{q}' for d, v, q in rows]]
    two = ['Station;Fuseau;Chronique;Unite;Chronique;Unite', f'{station};UTC+01;{code};{unite};{other_code};{other_unite}',
           'DateHeure;Valeur;Qualite;Valeur;Qualite', *[f'{d};{moved(v)};{q};{moved(v)};{q}' for d, v, q in rows]]

    def controle_import(content):
        body, kind = multipart({}, {'file': ('controle.txt', content)})
        response = client.get(f'/{slug}/admin/controle/import/step2', body, {**ajax, 'Content-Type': kind})
        lines = ['== étape 2', *step_lines(site, *response)]
        if response[0] == 200 and json.loads(response[2]).get('action') == 'step3':
            response = client.get(f'/{slug}/admin/controle/import/step3',
                                  urllib.parse.urlencode({'importType': 'import'}).encode(), ajax)
            lines += ['== étape 3', *step_lines(site, *response)]
        return lines

    def refused(label, run, table, refused_id, *watched):
        """La base refuse d'écrire sur une chronique : rien ne change, et l'import ne se dit pas réussi."""
        before = prints(table, *watched), jobs()
        with refusing(base, 'INSERT', table, refused_id):
            log = run()
        kept = (prints(table, *watched), jobs()) == before
        told = not any(line.endswith('"step4"') for line in log)
        check(label, ['conforme'] if kept and told and before[0]
              else [f'{len(before[0])} chroniques vues ; données {"gardées" if kept else "changées"}, réussite {"tue" if told else "annoncée"}'])

    refused('nouveau seul : écraser, insertion refusée, période gardée',
            lambda: import_steps(client, site, measures(one)), 'mesure', cid, cid)
    refused('nouveau seul : deux chroniques, la seconde refusée, la première gardée',
            lambda: import_steps(client, site, measures(two, types={cid: 'overwriteExisting', other: 'overwriteExisting'})),
            'mesure', other, cid, other)
    controle_import(controles[0][1])
    refused('nouveau seul : points de contrôle, insertion refusée, points gardés',
            lambda: controle_import(controles[1][1]), 'pointcontrole', cid, cid)

    # Deux archives dont le fichier intérieur porte le même nom : la seconde arrive entre deux étapes de la première
    def archive(content):
        buffer = io.BytesIO()
        with zipfile.ZipFile(buffer, 'w', zipfile.ZIP_DEFLATED) as z:
            z.writestr('mesures.txt', content)
        return buffer.getvalue()

    first, second = bdoh_import(base, cid, 1, 'overwriteExisting'), bdoh_import(base, cid, 2, 'overwriteExisting')
    period = lambda months: query(base, f"""
        SELECT date, valeur FROM mesure WHERE chronique_id = {cid}
           AND date >= timestamp '2024-01-01' + interval '{months} month' AND date < timestamp '2024-01-16' + interval '{months} month'
         ORDER BY date""", NEW_SERVER)
    expected = len(second['content'].decode().splitlines()) - 3
    other_period = period(2)

    def second_upload():
        body, kind = multipart({'file-format': 'Bdoh'}, {'file': ('mesures.zip', archive(second['content']))})
        Client(SIDES['new'], manager, slug).get(f'/{slug}/admin/mesure/import/step2', body, {**ajax, 'Content-Type': kind})

    import_steps(client, site, {**first, 'content': archive(first['content']), 'filename': 'mesures.zip'}, second_upload)
    written = len(period(1))
    check('nouveau seul : deux archives de même nom, chacune garde son fichier',
          ['conforme'] if written >= expected > 0 and period(2) == other_period
          else [f"{written} mesures écrites pour {expected} lues ; l'autre période {'gardée' if period(2) == other_period else 'changée'}"])

    # Points de contrôle : une date future refusée, une valeur écrite 0 acceptée
    head = controles[0][1].decode().splitlines()[:3]
    sample = controles[0][1].decode().splitlines()[3].split(';')
    controle_import(('\n'.join([*head, f'01/01/2042 00:00:00;1;{sample[2]}']) + '\n').encode())
    future = query(base, f'SELECT count(*) FROM pointcontrole WHERE chronique_id = {cid} AND date > now()', NEW_SERVER)[0][0]
    check('nouveau seul : point de contrôle daté dans le futur, refusé', ['conforme'] if future == '0' else [f'{future} points dans le futur'])
    controle_import(('\n'.join([*head, f'{sample[0]};0;{sample[2]}']) + '\n').encode())
    zero = query(base, f'SELECT count(*) FROM pointcontrole WHERE chronique_id = {cid} AND valeur = 0', NEW_SERVER)[0][0]
    check('nouveau seul : point de contrôle de valeur 0, accepté', ['conforme'] if zero == '1' else [f'{zero} points à zéro'])

    # /tmp plein pendant la lecture : une erreur, et rien d'écrit. Le fichier se comprime assez pour passer l'envoi.
    lines = [one[0], one[1], one[2], *[f'{day:02}/01/1990 {hour:02}:{minute:02}:00;1.5;{rows[0][2]}'
                                       for day in range(1, 29) for hour in range(24) for minute in range(60)]]
    count = lambda: query(base, f"SELECT count(*) FROM mesure WHERE chronique_id = {cid} AND date < '1991-01-01' AND date >= '1990-01-01'", NEW_SERVER)[0][0]
    before = count()
    with small_tmp():
        log = import_steps(Client(SIDES['new'], manager, slug), site,
                           {**measures(lines), 'content': archive(('\n'.join(lines) + '\n').encode()), 'filename': 'mesures.zip'})
    told = any('crire dans le fichier' in line for line in log)
    check('nouveau seul : /tmp plein, dit, et rien d’écrit',
          ['conforme'] if told and count() == before else [f'{before} mesures avant, {count()} après ; disque plein {"dit" if told else "tu"}', *log[-4:]])


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
        # De vrais fichiers : l'ancien et le nouveau, puis où chacun est arrivé, sans rien lire de leurs valeurs
        real_cases = real_import_cases(template) if args.only in 'fichiers réels' else []
        reached, touched = [], set()
        for title, kind, case in real_cases:
            where = case.get('slug') or real_cases[-1][2]['slug']
            logs = []
            for name in ('ancien', 'nouveau'):
                if kind == 'barème':
                    logs.append(play_bareme(name, where, case['content']))
                elif kind == 'contrôle':
                    logs.append(play_controle(name, case['cid'], where, case['content']))
                else:
                    logs.append(play(name, case))
            report.add(f'import fichier réel {title}', *logs)
            actions = [line for line in logs[1] if line.startswith('action : ')]
            reached.append((title, actions[-1] if actions else 'aucune étape'))
            if kind == 'mesures':
                touched |= set(case['types'])
        for name in sides:
            sides[name] = {**state_lines(bases[name], historique),
                           'autres tables': other_tables_lines(bases[name], STATE_QUERIES)}
    for table in sides['ancien']:
        report.add(f'état {table}', sides['ancien'][table], sides['nouveau'][table])
    # Ce fichier a deux plages qui se chevauchent (lignes 4 et 5) : BDOH doit le refuser, des deux côtés
    refused = {'mercure_discontinu.txt'}
    for title, action in reached:
        wanted = 'action : "errors"' if title in refused else 'action : "step4"'
        report.add(f'nouveau seul : fichier réel {title}, {"refusé" if title in refused else "importé jusqu’au bout"}', ['conforme'],
                   ['conforme' if action == wanted else f'non conforme : {action}'])
    for table in ('mesure', 'plage'):
        if touched:
            prints = [query(bases[name], FINGERPRINTS[table].replace('GROUP BY', f"WHERE chronique_id IN ({','.join(touched)}) GROUP BY"), NEW_SERVER)
                      for name in ('ancien', 'nouveau')]
            report.add(f'nouveau seul : fichiers réels, {table} écrites identiques à l’ancien', ['conforme'],
                       ['conforme' if prints[0] == prints[1] else f'non conforme : {len(prints[0])} et {len(prints[1])} chroniques'])
    # Ce que seul le nouveau fait aux formats QJO et Qtvar (dev/differences.md, « Zéro importé » et
    # « Jours manquants ») : chaque cas, les mesures attendues, et combien de mesures diffèrent de l'ancien
    hydro = {'QJO': (1, 'zéro écrit 0 gardé', "date >= '2025-09-09 12:00' AND date < '2025-09-10 12:00' AND valeur = 0", 1),
             'Qtvar': (2, 'zéro écrit 0 gardé', "date >= '2025-10-10 00:00' AND date < '2025-10-11 00:00' AND valeur = 0", 2),
             'QJO, trou de plus d’un mois': (31, 'trou de plus d’un mois comblé par des lacunes',
                                             "date >= '2022-03-01 12:00' AND date < '2022-04-14 12:00' AND valeur IS NULL", 44),
             'QJO, lignes dans le désordre': (11, 'lignes dans le désordre, aucune lacune ajoutée',
                                              "date >= '2021-05-19 00:00' AND date < '2021-06-20 00:00'", 2)}
    played = [c for c in cases if c['name'] in hydro]
    for case in played:
        _, label, where, expected = hydro[case['name']]
        found = int(query(bases['nouveau'], f"SELECT count(*) FROM mesure WHERE chronique_id = {case['cid']} AND {where}",
                          NEW_SERVER)[0][0])
        report.add(f"nouveau seul : {label}, {case['name']}", ['conforme'],
                   ['conforme' if found == expected else f'non conforme : {found} au lieu de {expected}'])
    if played:
        rows = lambda base, chronique: {tuple(row) for row in query(base, f"""SELECT date, valeur, minimum, maximum, qualite_id FROM mesure
                                                                               WHERE chronique_id = {chronique}""", NEW_SERVER)}
        changed = lambda chronique: {row[0] for row in rows(bases['ancien'], chronique) ^ rows(bases['nouveau'], chronique)}
        mother, expected = changed(played[0]['cid']), sum(hydro[c['name']][0] for c in played)
        report.add('nouveau seul : seules ces mesures diffèrent de l’ancien', ['conforme'],
                   ['conforme' if len(mother) == expected else f'non conforme : {len(mother)} au lieu de {expected}'])
        # Ses filles recalculées : le registre admet leur empreinte (dev/compare/expected.py), c'est ce contrôle qui
        # les protège. Une fille ne diffère qu'aux dates où sa mère diffère.
        for child, in query(bases['nouveau'], f"SELECT sortie_id FROM transformation WHERE entree_id = {played[0]['cid']}", NEW_SERVER):
            extra = changed(child) - mother
            report.add(f'nouveau seul : chronique fille {child}, ne diffère qu’aux dates où sa mère diffère', ['conforme'],
                       ['conforme' if not extra else f'non conforme : {len(extra)} dates de plus'])
    try:
        # --only pannes : ces vérifications seules, sur des copies neuves
        if not args.only or args.only == 'pannes':
            with new_on(bases['nouveau'], template):
                import_failure_checks(template, bases['nouveau'], lambda label, lines: report.add(label, ['conforme'], lines))
    finally:
        report.close()
    print(f'Les instances lisent de nouveau {template}. Les deux copies restent pour examen :'
          f' {bases["ancien"]} et {bases["nouveau"]}.')


# --- Comptes ---------------------------------------------------------------------------------------

CAPTCHA_BYPASS = 'comparaison'  # passe outre le captcha de l'ancien (install-app.sh)
LOGIN_LINK = re.compile(r'(<site>)?/[\w-]+/login_check\?[^\s"<]+')


@contextlib.contextmanager
def smtp_sink():
    """Un serveur SMTP réduit, le temps du bloc : rend la liste où s'ajoute chaque (destinataires réels) reçu. Mailpit
    ne les dit pas : il lit l'en-tête To, que le détournement laisse tel quel. Il écoute sur la passerelle du réseau."""
    received = []

    class Handler(socketserver.StreamRequestHandler):
        def handle(self):
            self.wfile.write(b'220 sink\r\n')
            recipients = []
            while line := self.rfile.readline():
                command = line.decode('utf-8', 'replace').strip().upper()
                if command.startswith('RCPT'):
                    recipients += re.findall(r'<([^>]+)>', line.decode('utf-8', 'replace'))
                if command == 'DATA':
                    self.wfile.write(b'354 go\r\n')
                    while self.rfile.readline().rstrip(b'\r\n') != b'.':
                        pass
                    received.append(recipients)
                    recipients = []
                self.wfile.write(b'221 bye\r\n' if command == 'QUIT' else b'250 ok\r\n')
                if command == 'QUIT':
                    return

    class Server(socketserver.ThreadingTCPServer):
        allow_reuse_address = True
        daemon_threads = True

    with Server((SINK_HOST, SINK_PORT), Handler) as server:
        threading.Thread(target=server.serve_forever, daemon=True).start()
        try:
            yield received
        finally:
            server.shutdown()


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
                   for table, sql in ACCOUNT_QUERIES.items()},
                'autres tables': other_tables_lines(base, ACCOUNT_QUERIES)}

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

        # Pendant les essais : tout part à l'adresse de détournement, et seule une adresse de la liste reçoit le sien
        # (dev/plans/2026-10-26_deployment.md). La liste, écrite comme make test-contacts, et en majuscules : la casse ne compte pas.
        kept, diverted = [row[0] for row in query(base, f"""SELECT email FROM utilisateur WHERE (finacces IS NULL OR finacces > now())
                                                             AND email <> '{manager}' ORDER BY id LIMIT 2""", NEW_SERVER)]
        allowed = f"(?i){kept.upper().replace('.', '[.]')},(?i)personne[.]absente@example[.]invalid"
        ENV_LOCAL.write_text(f'DATABASE_URL="{NEW_URL.format(base)}"\nMAILER_DSN=smtp://{SINK_HOST}:{SINK_PORT}\n'
                             f'MAILER_DIVERT_TO=essais@example.invalid\nMAILER_ALLOWED_RECIPIENTS={allowed}\n')
        with smtp_sink() as received:
            ask_link(down, kept)
            ask_link(down, diverted)
            for _ in range(40):
                if len(received) == 2:
                    break
                time.sleep(0.25)
        ENV_LOCAL.write_text(f'DATABASE_URL="{NEW_URL.format(base)}"\nMAILER_DSN=smtp://mailpit:1025\n')
        check('courriels détournés pendant les essais, sauf la liste',
              len(received) == 2 and sorted(received[0]) == sorted(['essais@example.invalid', kept])
              and received[1] == ['essais@example.invalid'], f'reçus : {received}')

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
# qui lit la table, quand elle a des colonnes que les deux côtés écrivent autrement (dates du moment,
# mots de passe) ; « also » : ses tables de liaison ; « more » : d'autres éditions, après « change ».
STATION_PICK = '''SELECT min(s.id) FROM station s JOIN stations_sites ss ON ss.station_id = s.id
                   JOIN siteexperimental se ON se.id = ss.site_id JOIN observatoire o ON o.id = se.observatoire_id
                  WHERE o.slug = '{slug}' '''

CHRONIQUE_PICK = "SELECT min(c.id) FROM (" + CHRONIQUES.replace('{', '{{').replace('}', '}}') + \
                 ") c WHERE c.slug = '{{slug}}' AND c.dtype LIKE '{dtype}'"

ADMINS = {
    'observatoire': {'table': 'observatoire', 'also': ['observatoires_data_manager'],
                     'pick': "SELECT id FROM observatoire WHERE slug = '{slug}'",
                     'change': {'[titreEn]': 'Observatory (test)'},
                     'more': [('lien sans protocole', {'[lien]': 'www.inrae.fr'})]},
    'siteexperimental': {'table': 'siteexperimental', 'pick': OBSERVATORY_PICK.format(table='siteexperimental'),
                         'change': {'[descriptionEn]': '<p>Site (test)</p>'}, 'create': {'[nom]': 'Site d’essai'}},
    'station': {'table': 'station', 'also': ['stations_sites'], 'pick': STATION_PICK,
                'change': {'[commentaireEn]': 'Station (test)'},
                'create': {'[nom]': 'Station d’essai', '[code]': 'ESSAI', '[sites]': FIRST, '[altitude]': '123.4567',
                           '[latitude]': '45.1', '[longitude]': '4.9'}},
    'chronique': {'table': 'chronique', 'state': STATE_QUERIES['chronique'], 'pick': CHRONIQUE_PICK.format(dtype='%')},
    'chroniquecontinue': {'table': 'chronique', 'state': STATE_QUERIES['chronique'],
                          'also': ['echantillonnagesortielicite_chronique', 'optionechantillonnagesortie_chronique'],
                          'pick': CHRONIQUE_PICK.format(dtype='continue'), 'change': {'[libelleEn]': 'Time series (test)'},
                          'create': {'[station]': FIRST, '[parametre]': FIRST, '[code]': 'ESSAI', '[libelle]': 'Essai',
                                     '[libelleEn]': 'Test', '[genealogie]': 'Essai', '[genealogieEn]': 'Test',
                                     '[milieu]': FIRST, '[unite]': FIRST, '[echantillonnage]': FIRST}},
    'chroniquediscontinue': {'table': 'chronique', 'state': STATE_QUERIES['chronique'],
                             'pick': CHRONIQUE_PICK.format(dtype='discontinue'),
                             'change': {'[libelleEn]': 'Time series (test)'}},
    'chroniquecalculee': {'table': 'chronique', 'state': STATE_QUERIES['chronique'],
                          'pick': CHRONIQUE_PICK.format(dtype='calculee'), 'change': {'[libelleEn]': 'Time series (test)'}},
    'chroniqueconvertie': {'table': 'chronique', 'state': STATE_QUERIES['chronique'],
                           'pick': CHRONIQUE_PICK.format(dtype='convertie'), 'change': {'[libelleEn]': 'Time series (test)'}},
    'dataset': {'table': 'dataset', 'pick': OBSERVATORY_PICK.format(table='dataset'),
                'also': ['topic_category_dataset', 'criteria_geology_dataset', 'criteria_climat_dataset',
                         'data_manager_dataset', 'principal_investigator_dataset', 'data_collector_dataset',
                         'project_member_dataset'],
                'change': {'[titreEn]': 'Dataset (test)'}},
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
                   'more': [('lien sans protocole', {'[lien]': 'www.inrae.fr'}),
                            ('lien en javascript', {'[lien]': 'javascript:alert(1)'})],
                   'create': {'[nom]': 'Partenaire d’essai', '[iso]': 'FR', '[estFinanceur]': '0',
                              '[typeFundings]': FIRST}},
    'utilisateur': {'table': 'utilisateur', 'state': ACCOUNT_QUERIES['utilisateur'], 'nodelete': True,
                    'change': {'[telephone]': '0102030405'},
                    'create': {'[email]': 'compte-admin@example.invalid', '[prenom]': 'Camille', '[nom]': 'Essai',
                               '[organisme]': 'INRAE', '[categorie]': FIRST, '[telephone]': '0102030405',
                               '[adresse]': '1 rue de l’Essai'}},
    'categorie': {'table': 'categorie'},
    'objectifrecherche': {'table': 'objectifrecherche'},
    'typetravaux': {'table': 'typetravaux'},
}


def admin_profiles(base):
    """Les comptes des pages de l'administration, chacun avec les observatoires où il est gestionnaire
    (ses rôles ne donnent de droits sur les données que là), du plus fourni au moins fourni : un
    administrateur fonctionnel, et un gestionnaire qui n'est que gestionnaire (celui de « pages » est
    aussi administrateur), sur un seul observatoire ; enfin un administrateur technique, à qui tout est
    permis, sur les observatoires du premier. Les bases de production n'en ont pas : run_admin donne ce
    rôle, dans ses deux copies, au deuxième administrateur fonctionnel."""
    active = "(u.finacces IS NULL OR u.finacces > now())"
    tables = ['bassin', 'courseau', 'doi', 'partenaire', 'siteexperimental', 'dataset', 'bareme']
    richness = ' + '.join([*(f'(SELECT count(*) FROM {t} WHERE observatoire_id = o.id)' for t in tables),
                           f"(SELECT count(*) FROM ({CHRONIQUES}) c WHERE c.slug = o.slug)"])
    admin, uid = query(base, f"""SELECT u.email, u.id FROM utilisateur u WHERE u.roleadmin = 'FCT' AND {active}
                                  ORDER BY u.id LIMIT 1""", NEW_SERVER)[0]
    slugs = [r[0] for r in query(base, f"""
        SELECT o.slug FROM observatoire o JOIN role r ON r.observatoire_id = o.id
         WHERE r.utilisateur_id = {uid} AND r.valeur = 'gestionnaire' ORDER BY {richness} DESC, o.id""", NEW_SERVER)]
    manager, slug = query(base, f"""
        SELECT u.email, o.slug FROM utilisateur u JOIN role r ON r.utilisateur_id = u.id
          JOIN observatoire o ON o.id = r.observatoire_id
         WHERE u.roleadmin IS NULL AND {active} AND r.valeur = 'gestionnaire' ORDER BY u.id, o.id LIMIT 1""",
                          NEW_SERVER)[0]
    tech, = query(base, f"""SELECT u.email FROM utilisateur u WHERE u.roleadmin = 'FCT' AND {active}
                             ORDER BY u.id OFFSET 1 LIMIT 1""", NEW_SERVER)[0]
    return {'administrateur': (admin, slugs), 'gestionnaire': (manager, [slug]),
            'administrateur technique': (tech, slugs)}


# Une station de plusieurs sites, et un compte actif qui ne gère que l'un d'eux (ni les autres, ni
# l'observatoire), avec l'observatoire de la page : le formulaire ne lui propose pas les autres sites.
SITE_MANAGER_OF_ONE = """
    SELECT ss.station_id, u.email, o.slug FROM stations_sites ss
      JOIN siteexperimental s ON s.id = ss.site_id JOIN observatoire o ON o.id = s.observatoire_id
      JOIN role r ON r.site_id = ss.site_id AND r.dtype = 'site' AND r.valeur = 'gestionnaire'
      JOIN utilisateur u ON u.id = r.utilisateur_id AND (u.finacces IS NULL OR u.finacces > now())
     WHERE ss.station_id IN (SELECT station_id FROM stations_sites GROUP BY 1 HAVING count(*) > 1)
       AND NOT EXISTS (SELECT 1 FROM role r2 WHERE r2.utilisateur_id = u.id AND r2.valeur = 'gestionnaire'
                         AND (r2.observatoire_id = o.id OR r2.site_id IN (
                              SELECT site_id FROM stations_sites WHERE station_id = ss.station_id AND site_id <> ss.site_id)))
     ORDER BY 1, 2 LIMIT 1"""


# Les contrôleurs à part de l'administration, sous le nom que --only reconnaît dans leurs adresses,
# et les tables qu'ils écrivent (lues par la requête de STATE_QUERIES ou d'OTHER_STATE).
OTHERS = {'colors': ['jeuqualite', 'qualite'],
          'conversion': ['chronique', 'conversion', 'jeuconversion', 'jms_jobs', 'jms_job_related_entities'],
          'fillingrate': ['jms_jobs', 'jms_job_related_entities'],
          'historique': [],
          # Pages d'autres modules, ouvertes ici pour chaque profil (voir GUARDED)
          'mesure': [], 'controle': [], 'transformation': [], 'shape': [], 'user': []}
# Les premières pages des contrôleurs gardés par une expression (#[IsGranted]) : l'ancien @Security
# l'évaluait sans les voteurs, et le super administrateur ne passait pas outre (SuperAdminVoter).
GUARDED = ['/admin/mesure/import', '/admin/controle/import', '/admin/transformation', '/admin/shape/import',
           '/user/all']
OTHER_STATE = {
    'jeuqualite': "SELECT to_jsonb(j)::text FROM jeuqualite j ORDER BY j.id",
    'qualite': "SELECT to_jsonb(q)::text FROM qualite q ORDER BY q.id",
    'conversion': "SELECT to_jsonb(c)::text FROM conversion c ORDER BY c.id",
    'jeuconversion': "SELECT (to_jsonb(j) - 'datecreation')::text FROM jeuconversion j ORDER BY j.id",
}
# Les chroniques converties qu'ouvre la page de la conversion : sans conversion (configuration
# incomplète relevée par l'inspection des bases), avec une chronique fille, sans fille.
CONVERTED = {
    'sans conversion': "NOT EXISTS (SELECT 1 FROM conversion cv WHERE cv.sortie_id = c.id)",
    'avec fille': """EXISTS (SELECT 1 FROM conversion cv WHERE cv.sortie_id = c.id)
                     AND EXISTS (SELECT 1 FROM transformation t WHERE t.entree_id = c.id)""",
    'sans fille': """EXISTS (SELECT 1 FROM conversion cv WHERE cv.sortie_id = c.id)
                     AND NOT EXISTS (SELECT 1 FROM transformation t WHERE t.entree_id = c.id)""",
}


def converted(base, rule):
    """La première chronique convertie qui suit la règle, avec son observatoire."""
    rows = query(base, f"SELECT c.id, c.slug FROM ({CHRONIQUES}) c WHERE c.dtype = 'convertie' AND {CONVERTED[rule]}"
                       " ORDER BY c.id LIMIT 1", NEW_SERVER)
    return rows[0] if rows else (None, None)


def other_pages(base, slugs):
    """Les pages des contrôleurs à part pour un compte dont slugs sont les observatoires, en (observatoire
    de la page, adresse sous l'observatoire) : les couleurs, sans jeu de qualité puis de chacun ; la
    conversion, sans chronique puis de chaque chronique de CONVERTED, ouverte dans son observatoire s'il
    est du compte, comme l'édition des chroniques à configuration incomplète ; l'historique de l'année la plus récente, des deux derniers mois, de l'année la plus
    ancienne et d'une année que la liste n'a pas."""
    slug = slugs[0]
    pages = [(slug, '/admin/colors')]
    pages += [(slug, f'/admin/colors/{j}') for j, in query(base, 'SELECT id FROM jeuqualite ORDER BY id', NEW_SERVER)]
    pages.append((slug, '/admin/conversion'))
    pages += [(slug, path) for path in GUARDED]
    for rule in CONVERTED:
        cid, cslug = converted(base, rule)
        if cid:
            pages.append((cslug if cslug in slugs else slug, f'/admin/conversion/{cid}'))
    # Le formulaire d'édition des configurations incomplètes (voir page_cases)
    for cid, cslug, dtype in query(base, f"""
            SELECT DISTINCT ON (c.dtype) c.id, c.slug, c.dtype FROM ({CHRONIQUES}) c JOIN chronique ch ON ch.id = c.id
             WHERE (c.dtype = 'calculee' AND ch.premiereentree_id IS NULL)
                OR (c.dtype = 'convertie' AND ch.conversion_id IS NULL) ORDER BY c.dtype, c.id""", NEW_SERVER):
        pages.append((cslug if cslug in slugs else slug, f'/admin/chronique{dtype}/{cid}/edit'))
    oldest = query(base, f"""SELECT coalesce(min(extract(year FROM h.date))::int, 2000) FROM historique h
                              JOIN observatoire o ON o.id = h.observatoire_id WHERE o.slug = '{slug}'""",
                   NEW_SERVER)[0][0]
    pages += [(slug, '/historique'), (slug, '/historique/recent'), (slug, f'/historique?year={oldest}'),
              (slug, '/historique?year=1899')]
    return pages


def today_lines(lines):
    """Les dates du jour dans une page de l'historique : celles des connexions de la comparaison elle-même,
    et la date de début d'un barème qui n'en a pas, que Twig remplace par celle du moment (filtre date
    d'une valeur nulle). Aucune date de la base n'est du jour."""
    days = {datetime.date.today(), datetime.datetime.now(datetime.timezone.utc).date()}
    marks = [d.strftime('%d/%m/%Y') for d in days] + [d.isoformat() for d in days]
    out, time_follows = [], False
    for line in lines:
        if time_follows and re.fullmatch(r'T \d\d:\d\d:\d\d', line):
            line = 'T <heure>'
        time_follows = False
        if any(mark in line for mark in marks):
            line = re.sub(r'\d\d:\d\d:\d\d', '<heure>', re.sub(r'data-order=\d+', 'data-order=<date>', line))
            for mark in marks:
                line = line.replace(mark, '<aujourd’hui>')
            time_follows = line == 'T <aujourd’hui>'
        out.append(line)
    return out


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


# Les fichiers joints que cite la base (logos, photos, conditions d'utilisation) : la base vient de la production, pas ses fichiers,
# et un formulaire qui cite un fichier absent est refusé (« Le fichier n'a pas été trouvé »), des deux
# côtés : l'enregistrement d'un observatoire ou d'un partenaire n'était pas joué.
WEB_ROOTS = [OUT.parent / 'app-17' / 'web', HERE.parent.parent / 'public']
ATTACHED_FILES = '''
    SELECT DISTINCT path FROM (SELECT unnest(ARRAY[pathlogo, pathphotoprincipale, pathphotosecondaire,
                                                  conditionsutilisation]) AS path
                                 FROM observatoire UNION ALL SELECT pathlogo FROM partenaire) p
     WHERE path LIKE 'uploads/%' AND path NOT LIKE '%..%' '''
PDF = b'%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\ntrailer << /Root 1 0 R >>\n%%EOF\n'
GIF = b'GIF89a\x01\x00\x01\x00\x80\x00\x00\x00\x00\x00\xff\xff\xff!\xf9\x04\x01\x00\x00\x00\x00,\x00\x00\x00\x00\x01\x00\x01\x00\x00\x02\x02D\x01\x00;'


@contextlib.contextmanager
def attached_files(base):
    """Un fichier de remplacement (une image GIF d'un pixel, ou un PDF vide pour les conditions
    d'utilisation, dont le formulaire vérifie le type) pour chaque fichier joint de la base qui manque,
    dans les deux instances, le temps du bloc."""
    created = []
    for path, in query(base, ATTACHED_FILES, NEW_SERVER):
        for root in WEB_ROOTS:
            target = root / path
            if not target.exists():
                target.parent.mkdir(parents=True, exist_ok=True)
                target.write_bytes(PDF if path.endswith('.pdf') else GIF)
                created.append(target)
    try:
        yield
    finally:
        for target in created:
            target.unlink(missing_ok=True)
            with contextlib.suppress(OSError):  # le dossier, s'il est vide
                target.parent.rmdir()


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
    others = [name for name in OTHERS if args.only in name]
    accounts = admin_profiles(template)
    bases = {'ancien': 'bdoh_admin_old', 'nouveau': 'bdoh_admin_new'}
    for base in bases.values():
        copy_base(template, base)
        query(base, f"UPDATE utilisateur SET roleadmin = 'TECH' WHERE email = '{accounts['administrateur technique'][0]}'",
              NEW_SERVER)

    def first(base, spec, slugs):
        """L'observatoire de la page et l'entité à ouvrir : le premier des observatoires du compte qui en
        a une, sinon le premier, sans entité."""
        for slug in slugs:
            sql = spec.get('pick', f"SELECT min(id) FROM {spec['table']}").format(slug=slug)
            cid = query(base, sql, NEW_SERVER)[0][0]
            if cid:
                return slug, cid
        return slugs[0], ''

    def play(side, base):
        parts = {}
        urllib.request.urlopen(urllib.request.Request(MAILPIT + '/api/v1/messages', method='DELETE'), timeout=60)
        clients = {}

        def client_for(email, slug):
            """Un navigateur par compte et par observatoire : l'ancien fige les rôles de la session sur
            l'observatoire de la connexion."""
            if (email, slug) not in clients:
                clients[email, slug] = Client(side, email, slug)
            return clients[email, slug]

        for profile, (email, slugs) in accounts.items():
            client = client_for(email, slugs[0])
            parts[f'{profile} /{slugs[0]}/admin/dashboard'] = page_lines(side.url, *client.get(f'/{slugs[0]}/admin/dashboard'))
            # Pas d'export dans l'administration (Extension/NoExportExtension)
            if 'utilisateur' in admins:
                path = f'/{slugs[0]}/admin/utilisateur/export?format=csv'
                parts[f'{profile} {path}'] = redirect_lines(side.url, *client.get(path)[:2])
            for name, spec in admins.items():
                slug, cid = first(base, spec, slugs)
                client = client_for(email, slug)
                root = f'/{slug}/admin/{name}'
                paths = [f'{root}/list', f'{root}/create']
                if cid:
                    paths += [f'{root}/{cid}/edit', f'{root}/{cid}/delete']
                for path in paths:
                    parts[f'{profile} {path}'] = page_lines(side.url, *client.get(path))
            for slug, path in other_pages(base, slugs):
                if any(f'/{name}/' in path + '/' or f'/{name}?' in path for name in [*others, *admins]):
                    lines = page_lines(side.url, *client_for(email, slug).get(f'/{slug}{path}'))
                    parts[f'{profile} /{slug}{path}'] = today_lines(lines) if path.startswith('/historique') else lines

        email, slugs = accounts['administrateur']

        def send(path, action, values, button, token=True, who=None):
            """Un formulaire de la page, envoyé comme par un navigateur, et la page où il renvoie. Sans
            button, aucun bouton n'est envoyé (bouton sans nom) ; sans token, le formulaire part sans le
            jeton CSRF que les couleurs et la conversion tirent eux-mêmes ; who : un autre compte que
            l'administrateur."""
            client = client_for(who or email, path.split('/')[1])
            form = form_of(client, path, action)
            if form.url is None:
                return [f'pas de formulaire {action} : {path}']
            if not token:
                form.fields = [f for f in form.fields if not f[0].startswith('CSRFToken')]
            values = {k: (form.choices(k)[:1] or [''])[0] if v is FIRST else v for k, v in values.items()}
            body = form.filled(values)
            if button:
                if form.buttons and button not in form.buttons:
                    button = form.buttons[0]
                body += b'&' + urllib.parse.urlencode({button: ''}).encode()
            status, headers, _ = client.get(form.url, body, {'Referer': side.url + path})
            lines = redirect_lines(side.url, status, headers)
            location = headers.get('Location', '').replace(side.url, '')
            return lines + (['== page suivante', *page_lines(side.url, *client.get(location))] if location else [])

        for name, spec in admins.items():
            slug, cid = first(base, spec, slugs)
            root = f'/{slug}/admin/{name}'
            if not cid:
                continue
            parts[f'{name} : édition renvoyée telle quelle'] = send(
                f'{root}/{cid}/edit', f'/{name}/{cid}/edit', {}, 'btn_update_and_edit')
            if 'change' in spec:
                parts[f'{name} : édition'] = send(f'{root}/{cid}/edit', f'/{name}/{cid}/edit', spec['change'],
                                                 'btn_update_and_edit')
            for label, values in spec.get('more', []):
                parts[f'{name} : {label}'] = send(f'{root}/{cid}/edit', f'/{name}/{cid}/edit', values,
                                                 'btn_update_and_edit')
            if 'create' in spec:
                parts[f'{name} : création'] = send(f'{root}/create', f'/{name}/create', spec['create'],
                                                  'btn_create_and_edit')
                if not spec.get('nodelete'):
                    created = query(base, f"SELECT max(id) FROM {spec['table']}", NEW_SERVER)[0][0]
                    parts[f'{name} : suppression'] = send(f'{root}/{created}/delete', f'/{name}/{created}/delete',
                                                         {}, 'btn_delete')
        parts.update(other_writes(send, base, client_for(email, slugs[0]), slugs))
        time.sleep(2)  # les courriels du nouveau partent après la réponse (DeferredMailer)
        parts['courriels'] = [line.replace(side.url, '<site>') for line in mail_lines()]
        for name, spec in sorted(admins.items(), key=lambda item: item[1]['table']):
            sql = spec.get('state', f"SELECT to_jsonb(t)::text FROM {spec['table']} t ORDER BY t.id")
            # Symfony 7 écrit les fins de ligne des champs de texte long en \n, Symfony 2 telles que le
            # navigateur les envoie, en \r\n (dev/differences.md, « Fins de ligne des textes longs »)
            parts[f"état {spec['table']}"] = ['\t'.join(row).replace('\\r\\n', '\\n')
                                             for row in query(base, sql, NEW_SERVER)]
        for table in sorted({t for spec in admins.values() for t in spec.get('also', [])}):
            parts[f'état {table}'] = ['\t'.join(row) for row in query(
                base, f"SELECT to_jsonb(t)::text FROM {table} t ORDER BY 1", NEW_SERVER)]
        for table in sorted({t for name in others for t in OTHERS[name]} - {f[5:] for f in parts if f.startswith('état ')}):
            parts[f'état {table}'] = (['\t'.join(row) for row in query(base, OTHER_STATE[table], NEW_SERVER)]
                                      if table in OTHER_STATE else state_lines(base, historique, [table])[table])
        parts['état historique'] = state_lines(base, historique, ['historique'])['historique']
        parts['état des autres tables'] = other_tables_lines(base, {f[5:] for f in parts if f.startswith('état ')})
        if side.login_link:
            checks.update(new_only(send, base, client_for))
        return parts

    def other_writes(send, base, client, slugs):
        """Les écritures des contrôleurs à part, au nom de l'administrateur : les couleurs du premier jeu
        de qualité affectable, enregistrées telles quelles, changées (dont des valeurs refusées, que le
        contrôleur remplace par les valeurs par défaut), sans jeton CSRF, pour un simple changement de
        jeu, pour un jeu non affectable ; la conversion de chaque chronique de CONVERTED de ses
        observatoires ; le taux de remplissage d'une chronique continue, sans chronique, d'une chronique
        inconnue."""
        parts = {}
        slug = slugs[0]
        if 'colors' in others:
            jeu = query(base, 'SELECT min(id) FROM jeuqualite WHERE estaffectable', NEW_SERVER)[0][0]
            qualite = query(base, f"SELECT min(id) FROM qualite WHERE jeu_id = {jeu} AND code <> 'gap'", NEW_SERVER)[0][0]
            other = query(base, 'SELECT min(id) FROM jeuqualite WHERE NOT estaffectable', NEW_SERVER)[0][0]
            page, action = f'/{slug}/admin/colors/{jeu}', '/admin/colors'
            for label, values, token in [
                ('telles quelles', {}, True),
                ('changées', {f'colorId_{qualite}': '#12ab34', f'thicknessId_{qualite}': '3',
                              f'checkpointColorId_{jeu}': '#00FF00', f'discontinuousSizeId_{jeu}': '7',
                              f'checkpointShapeId_{jeu}': 'triangle', f'checkpointSizeId_{jeu}': '99'}, True),
                ('sans jeton CSRF', {f'colorId_{qualite}': '#000000'}, False),
                ('changement de jeu seulement', {'jeuQualiteChanged': 'jeuQualiteChanged',
                                                 f'colorId_{qualite}': '#000000'}, True),
                ('jeu non affectable', {'jeuQualiteId': str(other)}, True),
            ]:
                parts[f'couleurs : {label}'] = send(page, action, values, None, token)
        if 'conversion' in others:
            for rule, cases in {
                'sans conversion': [('création', {'chroniqueMereId': FIRST, 'paramConversion': '60'}, True)],
                'avec fille': [('telle quelle', {}, True)],
                'sans fille': [('telle quelle', {}, True),
                               ('changée', {'paramConversion': '1234', 'genealogie': 'Généalogie (essai)'}, True),
                               ('paramètre invalide', {'paramConversion': 'abc'}, True),
                               ('paramètre vide', {'paramConversion': ''}, True),
                               ('sans jeton CSRF', {'paramConversion': '30'}, False)],
            }.items():
                cid, cslug = converted(base, rule)
                if cslug in slugs:
                    for label, values, token in cases:
                        parts[f'conversion, {rule} : {label}'] = send(
                            f'/{cslug}/admin/conversion/{cid}', '/admin/conversion', values, None, token)
        if 'fillingrate' in others:
            cid = query(base, CHRONIQUE_PICK.format(dtype='continue').format(slug=slug), NEW_SERVER)[0][0]
            for label, params in [('chronique continue', f'?id={cid}'), ('sans chronique', ''),
                                  ('chronique inconnue', '?id=999999999')]:
                status, headers, _ = client.get(f'/{slug}/admin/fillingrate{params}')
                parts[f'taux de remplissage : {label}'] = redirect_lines(client.side.url, status, headers)
        return parts

    def account_rights(send, base, client_for):
        """Modifier le compte d'un autre est réservé aux administrateurs, et un compte technique à un
        compte technique ; un gestionnaire garde la liste, la création et la page des droits, sans
        pouvoir y poser de fin d'accès sur un compte d'administration (dev/differences.md)."""
        results = {}
        admin, (slug, *_) = accounts['administrateur']
        tech = accounts['administrateur technique'][0]
        manager, (manager_slug,) = accounts['gestionnaire']
        account = lambda email: query(base, f"SELECT id, coalesce(finacces::date::text, '') FROM utilisateur WHERE email = '{email}'",
                                      NEW_SERVER)[0]
        plain, plain_email = query(base, f"""SELECT id, email FROM utilisateur WHERE roleadmin IS NULL AND email <> '{manager}'
                                              AND (finacces IS NULL OR finacces > now()) ORDER BY id LIMIT 1""", NEW_SERVER)[0]
        admin_id, tech_id = account(admin)[0], account(tech)[0]

        def edit_status(who, who_slug, target):
            return client_for(who, who_slug).get(f'/{who_slug}/admin/utilisateur/{target}/edit')[0]

        def rights_end_date(who, who_slug, target_email):
            """Envoie la page des droits d'un compte avec une fin d'accès, le champ fût-il désactivé, et
            rend la date enregistrée."""
            client = client_for(who, who_slug)
            path = f'/{who_slug}/user/right/{target_email}'
            form = form_of(client, path)
            date = {'form[dateFin][day]': '30', 'form[dateFin][month]': '6', 'form[dateFin][year]': '2027'}
            kept = [(n, v) for n, v in form.fields if n not in date]
            client.get(path, urllib.parse.urlencode([*kept, *date.items()]).encode(), {'Referer': client.side.url + path})
            return account(target_email)[1]

        expected = {'gestionnaire sur un administrateur': (edit_status(manager, manager_slug, admin_id), 403),
                    'gestionnaire sur un compte sans rôle': (edit_status(manager, manager_slug, plain), 403),
                    'administrateur sur un compte sans rôle': (edit_status(admin, slug, plain), 200),
                    'administrateur sur un compte technique': (edit_status(admin, slug, tech_id), 403),
                    'compte technique sur un administrateur': (edit_status(tech, slug, admin_id), 200),
                    'compte technique sur le sien': (edit_status(tech, slug, tech_id), 200)}
        results['nouveau seul : modifier le compte d’un autre'] = (
            [f'{name} : statut {got}, attendu {want}' for name, (got, want) in expected.items() if got != want]
            or ['conforme'])

        # Un envoi fabriqué, sans passer par la page refusée
        path = f'/{manager_slug}/admin/utilisateur/{admin_id}/edit'
        status = client_for(manager, manager_slug).get(path, b'x=1', {'Referer': SIDES['new'].url + path})[0]
        email_after = query(base, f'SELECT email FROM utilisateur WHERE id = {admin_id}', NEW_SERVER)[0][0]
        results['nouveau seul : envoi du formulaire d’un compte par un gestionnaire, refusé'] = (
            ['conforme'] if status == 403 and email_after == admin else [f'statut {status}, adresse changée : {email_after != admin}'])

        # L'administrateur corrige une adresse
        new_email = 'adresse-corrigee@example.invalid'
        send(f'/{slug}/admin/utilisateur/{plain}/edit', f'/utilisateur/{plain}/edit', {'[email]': new_email}, 'btn_update_and_edit')
        got = query(base, f'SELECT email FROM utilisateur WHERE id = {plain}', NEW_SERVER)[0][0]
        results['nouveau seul : un administrateur corrige une adresse'] = (
            ['conforme'] if got == new_email else ['adresse inchangée'])

        # Le gestionnaire crée toujours un compte, et revient à la liste
        lines = send(f'/{manager_slug}/admin/utilisateur/create', '/utilisateur/create',
                     {'[email]': 'cree-par-gestionnaire@example.invalid', '[prenom]': 'Camille', '[nom]': 'Essai',
                      '[organisme]': 'INRAE', '[categorie]': FIRST, '[telephone]': '0102030405',
                      '[adresse]': '1 rue de l’Essai'}, 'btn_create_and_edit', who=manager)
        created = query(base, "SELECT count(*) FROM utilisateur WHERE email = 'cree-par-gestionnaire@example.invalid'", NEW_SERVER)[0][0]
        next_status = next((line for line in lines if line.startswith('statut ')), '')
        results['nouveau seul : un gestionnaire crée un compte'] = (
            ['conforme'] if created == '1' and lines[0].startswith('statut 302') and '/utilisateur/list' in ' '.join(lines[:3]) and next_status
            else [f'{created} compte, réponse : {lines[:3]}'])

        # La fin d'accès sur la page des droits
        results['nouveau seul : fin d’accès d’un compte d’administration, refusée à un gestionnaire'] = (
            ['conforme'] if rights_end_date(manager, manager_slug, admin) == '' else ['date enregistrée'])
        results['nouveau seul : fin d’accès d’un compte technique, refusée à un administrateur'] = (
            ['conforme'] if rights_end_date(admin, slug, tech) == '' else ['date enregistrée'])
        results['nouveau seul : fin d’accès d’un compte sans rôle, gardée au gestionnaire'] = (
            ['conforme'] if rights_end_date(manager, manager_slug, got) == '2027-06-30' else ['date non enregistrée'])
        return results

    def new_only(send, base, client_for):
        """Ce que seul le nouveau fait (dev/differences.md, dev/local/security.md) : chaque vérification
        rend « conforme » ou ce qui ne l'est pas. Après l'état des tables : ces écritures n'y comptent pas."""
        results = {}
        if 'utilisateur' in admins:
            results.update(account_rights(send, base, client_for))
        if 'partenaire' in admins:
            email, slugs = accounts['administrateur']
            slug, pid = first(base, admins['partenaire'], slugs)
            path, action = f'/{slug}/admin/partenaire/{pid}/edit', f'/partenaire/{pid}/edit'
            logo = lambda: query(base, f"SELECT coalesce(pathlogo, '') FROM partenaire WHERE id = {pid}", NEW_SERVER)[0][0]
            before = logo()
            send(path, action, {'[pathLogo][current]': '../../config/bundles.php'}, 'btn_update_and_edit')
            results['nouveau seul : chemin fabriqué du fichier enregistré, ignoré'] = (
                ['conforme'] if logo() == before else [f'chemin enregistré : {logo()}'])
            # Une image GIF valide, suivie de PHP, sous un nom en .php
            gif = GIF + b'<?php echo 1; ?>'
            client = client_for(email, slug)
            form = form_of(client, path, action)
            field = next(n for n in form.names if n.endswith('[pathLogo][new]'))
            body, kind = multipart([*[f for f in form.fields if f[0] != field], ('btn_update_and_edit', '')],
                                   {field: ('logo.php', gif)})
            status, _, _ = client.get(form.url, body, {'Content-Type': kind, 'Referer': client.side.url + path})
            results['nouveau seul : extension d’un fichier téléversé tirée de son contenu'] = (
                ['conforme'] if status == 302 and logo().endswith('.gif') else [f'statut {status}, chemin : {logo()}'])
        rows = query(base, f"""SELECT c.id, c.slug, c.station, c.code FROM ({CHRONIQUES}) c
                                 WHERE NOT c.estvisible ORDER BY c.id LIMIT 1""", NEW_SERVER)
        if rows:
            hid, hslug, hstation, hcode = rows[0]
            guest = Client(client_for(accounts['administrateur'][0], hslug).side, None, hslug)
            statuses = [guest.get(f'/{hslug}/{hstation}/{hcode}')[0],
                        guest.get(f'/{hslug}/chronique/{hid}/measures?beginDate=2000-01-01T00:00:00Z&endDate=2030-01-01T00:00:00Z')[0]]
            results['nouveau seul : chronique cachée, page et mesures refusées sans compte'] = (
                ['conforme'] if all(s in (401, 403) or s == 302 for s in statuses) else [f'statuts {statuses}'])
        # Un observatoire entièrement caché : sans compte, aucune chronique n'est annoncée avec des mesures
        rows = query(base, f"""SELECT c.slug FROM ({CHRONIQUES}) c GROUP BY 1
                                 HAVING count(*) FILTER (WHERE c.estvisible) = 0 ORDER BY 1 LIMIT 1""", NEW_SERVER)
        if rows:
            guest = Client(client_for(accounts['administrateur'][0], rows[0][0]).side, None, rows[0][0])
            status, _, body = guest.get(f'/{rows[0][0]}/viewer/load?start=2000-01-01T00:00:00Z&end=2030-01-01T00:00:00Z')
            results['nouveau seul : observatoire caché, aucune chronique annoncée avec des mesures'] = (
                ['conforme'] if status == 200 and b'"hasMeasures":true' not in body else [f'statut {status}'])
        if 'personnetheia' in admins:
            # La base refuse la suppression (responsable d'un observatoire) : l'historique n'en dit rien
            label = 'nouveau seul : suppression refusée par la base, historique muet'
            email, slugs = accounts['administrateur']
            rows = query(base, 'SELECT projectleader_id FROM observatoire WHERE projectleader_id IS NOT NULL ORDER BY 1 LIMIT 1', NEW_SERVER)
            if rows:
                pid = rows[0][0]
                removed = lambda: query(base, "SELECT count(*) FROM historique WHERE action = 'historique.actions.PersonneTheiaRemove'", NEW_SERVER)[0][0]
                before = removed()
                lines = send(f'/{slugs[0]}/admin/personnetheia/{pid}/delete', f'/personnetheia/{pid}/delete', {}, 'btn_delete')
                still = query(base, f'SELECT count(*) FROM personnetheia WHERE id = {pid}', NEW_SERVER)[0][0]
                results[label] = (['conforme'] if lines[0] == 'statut 302' and still == '1' and removed() == before
                                  else [lines[0], f'personne présente : {still}, suppressions notées : {before}, puis {removed()}'])
            else:
                results[label] = ['aucun cas dans la base']
        if 'utilisateur' in admins and 'station' in admins:
            # Les aides de Sonata demandent le droit du formulaire qui les appelle (dev/local/security.md)
            email, slugs = accounts['administrateur']
            manager, (mslug,) = accounts['gestionnaire']
            target = query(base, "SELECT id FROM utilisateur WHERE roleadmin = 'FCT' ORDER BY id LIMIT 1", NEW_SERVER)[0][0]
            code = 'irstea_bdoh_admin.admin.'
            helper = lambda who, slug, name, oid, field: client_for(who, slug).get(
                f'/{slug}/admin/core/get-form-field-element?' + urllib.parse.urlencode(
                    {'_sonata_admin': code + name, 'objectId': oid, 'uniqid': 'x', 'elementId': f'x_{field}'}),
                b'', {'X-Requested-With': 'XMLHttpRequest'})[0]
            short = lambda who, slug, name, oid: client_for(who, slug).get(
                f'/{slug}/admin/core/get-short-object-description.html?' + urllib.parse.urlencode(
                    {'_sonata_admin': code + name, 'objectId': oid}))[0]
            slug, sid = first(base, admins['station'], slugs)
            observed = {'champ du compte d’un autre, pour un gestionnaire': (helper(manager, mslug, 'utilisateur', target, 'email'), 403),
                        'champ d’un compte, pour un administrateur': (helper(email, slug, 'utilisateur', target, 'email'), 200),
                        'champ d’une station, pour un administrateur': (helper(email, slug, 'station', sid, 'nom'), 200),
                        'description courte, pour un administrateur': (short(email, slug, 'station', sid), 200)}
            # Ce que les formulaires d'un gestionnaire demandent : le bouton d'ajout d'un producteur, le choix d'un DOI
            own = query(base, f"""SELECT c.id FROM ({CHRONIQUES}) c WHERE c.slug = '{mslug}' AND c.dtype = 'continue'
                                    ORDER BY c.id LIMIT 1""", NEW_SERVER)
            doi = query(base, f"""SELECT d.id FROM doi d JOIN observatoire o ON o.id = d.observatoire_id
                                    WHERE o.slug = '{mslug}' ORDER BY d.id LIMIT 1""", NEW_SERVER)
            if own:
                observed['producteur d’une chronique, pour un gestionnaire'] = (helper(manager, mslug, 'chronique_continue', own[0][0], 'producteur'), 200)
            if doi:
                observed['description d’un DOI, pour un gestionnaire'] = (short(manager, mslug, 'doi', doi[0][0]), 200)
            rows = query(base, """SELECT u.email, o.slug FROM utilisateur u JOIN role r ON r.utilisateur_id = u.id
                                     JOIN siteexperimental s ON s.id = r.site_id JOIN observatoire o ON o.id = s.observatoire_id
                                    WHERE r.valeur = 'contributeur' AND u.roleadmin IS NULL
                                      AND (u.finacces IS NULL OR u.finacces > now()) ORDER BY u.id LIMIT 1""", NEW_SERVER)
            if rows:
                observed['champ du compte d’un autre, pour un contributeur'] = (helper(rows[0][0], rows[0][1], 'utilisateur', target, 'email'), 403)
            for name, (status, expected) in observed.items():
                results[f'nouveau seul : aides de Sonata, {name}'] = ['conforme'] if status == expected else [f'statut {status}']
        if 'station' in admins:
            slug, sid = first(base, admins['station'], accounts['administrateur'][1])
            # Un nom fait de HTML s'affiche échappé dans l'historique (dev/local/security.md)
            email = accounts['administrateur'][0]
            send(f'/{slug}/admin/station/{sid}/edit', f'/station/{sid}/edit', {'[nom]': 'Essai <b id="brut">gras</b>'},
                 'btn_update_and_edit')
            body = client_for(email, slug).get(f'/{slug}/historique/recent')[2].decode('utf-8', 'replace')
            results['nouveau seul : nom fait de HTML, échappé dans l’historique'] = (
                ['conforme'] if '<b id="brut">' not in body and 'id=&quot;brut&quot;' in body
                else ['écrit tel quel' if '<b id="brut">' in body else 'absent de la page'])
            home = client_for(email, slug).get(f'/{slug}/')[2].decode('utf-8', 'replace')
            results['nouveau seul : licence et lien vers le code source'] = (
                ['conforme'] if 'GNU AGPL' in home and 'github.com/bdoh-inrae/bdoh-legacy' in home else ['absents de la page'])
            # Le point d'une station créée (dev/differences.md, « Point d'une station créée »)
            send(f'/{slug}/admin/station/create', '/station/create',
                 dict(admins['station']['create'], **{'[code]': 'ESSAI2'}), 'btn_create_and_edit')
            value, = query(base, "SELECT coalesce(ST_AsText(point), '') FROM station WHERE code = 'ESSAI2'", NEW_SERVER)[0]
            results['nouveau seul : point calculé à la création d’une station'] = (
                ['conforme'] if value == 'POINT(45.1 4.9)' else [f'point : {value}'])
            send(f'/{slug}/admin/station/{sid}/edit', f'/station/{sid}/edit', {'[latitude]': '45,1234567'},
                 'btn_update_and_edit')
            value, = query(base, f'SELECT latitude FROM station WHERE id = {sid}', NEW_SERVER)[0]
            results['nouveau seul : latitude d’une station gardée à 7 décimales'] = (
                ['conforme'] if value == '45.1234567' else [f'valeur enregistrée : {value}'])
            # Une station de deux sites, enregistrée telle quelle par un gestionnaire de l'un seulement
            label = 'nouveau seul : sites d’une station que le compte ne gère pas, gardés'
            rows = query(base, SITE_MANAGER_OF_ONE, NEW_SERVER)
            if rows:
                sid, manager, mslug = rows[0]
                sites = lambda: query(base, f"SELECT string_agg(site_id::text, ',' ORDER BY site_id) FROM stations_sites"
                                            f" WHERE station_id = {sid}", NEW_SERVER)[0][0]
                before = sites()
                lines = send(f'/{mslug}/admin/station/{sid}/edit', f'/station/{sid}/edit', {}, 'btn_update_and_edit',
                             who=manager)
                results[label] = (['conforme'] if lines[0] == 'statut 302' and sites() == before
                                  else [lines[0], f'sites avant : {before}, après : {sites()}'])
            else:
                results[label] = ['aucun cas dans la base']
        if 'observatoire' in admins:
            # Un observatoire qui a des gestionnaires de données, ouvert depuis la page d'un autre : renvoyé
            # vers la sienne, où il s'enregistre tel quel
            label = 'nouveau seul : responsable et gestionnaires de données d’un observatoire modifié ailleurs, gardés'
            email, slugs = accounts['administrateur']
            rows = query(base, f"""SELECT o.id, o.slug FROM observatoire o WHERE o.slug <> '{slugs[0]}' AND EXISTS (
                                     SELECT 1 FROM observatoires_data_manager d WHERE d.observatoire_id = o.id)
                                   ORDER BY o.id LIMIT 1""", NEW_SERVER)
            if rows:
                oid, slug = rows[0]
                status, headers, _ = client_for(email, slugs[0]).get(f'/{slugs[0]}/admin/observatoire/{oid}/edit')
                moved = status == 302 and headers.get('Location', '').endswith(f'/{slug}/admin/observatoire/{oid}/edit')
                people = lambda: query(base, f"""SELECT o.projectleader_id, string_agg(d.personnetheia_id::text, ','
                                                  ORDER BY d.personnetheia_id) FROM observatoire o
                                                  LEFT JOIN observatoires_data_manager d ON d.observatoire_id = o.id
                                                 WHERE o.id = {oid} GROUP BY o.id""", NEW_SERVER)[0]
                before = people()
                lines = send(f'/{slug}/admin/observatoire/{oid}/edit', f'/observatoire/{oid}/edit', {},
                             'btn_update_and_edit')
                results[label] = (['conforme'] if moved and lines[0] == 'statut 302' and people() == before
                                  else [f'renvoi : {status} {headers.get("Location")}', lines[0],
                                        f'avant : {before}, après : {people()}'])
            else:
                results[label] = ['aucun cas dans la base']
        if 'chroniquecontinue' in admins:
            slug, cid = first(base, admins['chroniquecontinue'], accounts['administrateur'][1])
            status = client_for(accounts['administrateur'][0], slug).get(
                f'/{slug}/admin/chroniquecontinue/999999999/clone')[0]
            results['nouveau seul : clonage d’une chronique inconnue, 404'] = (
                ['conforme'] if status == 404 else [f'statut {status}'])
            # Le formulaire du clonage, envoyé à la création, sans choix du jeu de données (500 dans l'ancien
            # quand on en choisissait un)
            lines = send(f'/{slug}/admin/chroniquecontinue/{cid}/clone', '/chroniquecontinue/create',
                         {'[code]': 'CLONE'}, 'btn_create_and_edit')
            results['nouveau seul : clonage d’une chronique enregistré'] = (
                ['conforme'] if lines[0] == 'statut 302' else lines[:2])
            send(f'/{slug}/admin/chroniquecontinue/{cid}/edit', f'/chroniquecontinue/{cid}/edit',
                 {'[minimumValide]': '0,12345'}, 'btn_update_and_edit')
            value, = query(base, f'SELECT minimumvalide FROM chronique WHERE id = {cid}', NEW_SERVER)[0]
            results['nouveau seul : seuil de validité gardé à 5 décimales'] = (
                ['conforme'] if value == '0.12345' else [f'valeur enregistrée : {value}'])
        return results

    sides, checks = {}, {}
    with old_17_on(bases['ancien'], mailer_transport='smtp', mailer_host='mailpit', mailer_port='1025'), \
            new_on(bases['nouveau'], template, 'MAILER_DSN=smtp://mailpit:1025'), attached_files(template):
        sides['ancien'] = play(SIDES['reference-17'], bases['ancien'])
        sides['nouveau'] = play(SIDES['new'], bases['nouveau'])
    report = Report('admin')
    for part in sides['ancien']:
        report.add(part, sides['ancien'][part], sides['nouveau'].get(part, []))
    for part, lines in checks.items():
        report.add(part, ['conforme'], lines)
    report.close()
    print(f'Les instances lisent de nouveau {template}. Les deux copies restent pour examen :'
          f' {bases["ancien"]} et {bases["nouveau"]}.')


# --- Envoi vers Theia/OZCAR (module 7) ------------------------------------------------------------

# Les schémas du format pivot (celui de Theia, et celui de GeoJSON qu'il cite), copiés du portail (pas
# d'accès à l'extérieur), hors du dépôt ; ils sont servis aux deux instances par le nouveau, à son adresse,
# avec le récepteur qui tient lieu de Theia.
THEIA_PORTAL = 'https://in-situ.theia-land.fr/json-schema/'
THEIA_SCHEMAS = OUT.parent / 'theia'
THEIA_RECEIVED = HERE.parent.parent / 'var' / 'theia-receiver'
THEIA_PASSWORD = 'theia-essai'


@contextlib.contextmanager
def theia_stand_in(served):
    """Les schémas (public/theia-schema/) et le récepteur dans public/ du nouveau, le temps du bloc."""
    public = HERE.parent.parent / 'public'
    (public / 'theia-schema').mkdir(exist_ok=True)
    for schema in THEIA_SCHEMAS.glob('*.json'):
        (public / 'theia-schema' / schema.name).write_text(
            schema.read_text().replace(THEIA_PORTAL, f'{served}/theia-schema/'))
    (public / 'theia-receiver.php').write_bytes((HERE / 'theia-receiver.php').read_bytes())
    try:
        yield
    finally:
        for schema in (public / 'theia-schema').glob('*.json'):
            schema.unlink()
        (public / 'theia-schema').rmdir()
        (public / 'theia-receiver.php').unlink(missing_ok=True)


def without_extents(value):
    """Un JSON du format pivot sans les coordonnées de l'emprise des jeux de données : le nouveau écrit
    l'emprise réelle, l'ancien le point d'une station (dev/differences.md, « Emprise d'un jeu de données »)."""
    if isinstance(value, list):
        return [without_extents(item) for item in value]
    if isinstance(value, dict):
        return {key: ('<emprise>' if key == 'spatialExtent' else without_extents(item)) for key, item in value.items()}
    return value


def extents_hold_points(data):
    """Les jeux de données d'une fiche dont l'emprise ne contient pas tous les points de ses chroniques ;
    et le nombre de jeux à plusieurs points, dont l'emprise doit avoir une largeur ou une hauteur."""
    wrong, wide = [], 0
    def walk(value):
        nonlocal wide
        if isinstance(value, list):
            for item in value:
                walk(item)
        elif isinstance(value, dict):
            metadata = value.get('metadata')
            if isinstance(metadata, dict) and 'spatialExtent' in metadata:
                ring = metadata['spatialExtent']['geometry']['coordinates'][0]
                xs, ys = [p[0] for p in ring], [p[1] for p in ring]
                # (0, 0) : une station sans point, que l'emprise ne compte pas (dev/differences.md)
                points = {tuple(o['featureOfInterest']['samplingFeature']['geometry']['coordinates'])
                          for o in value.get('observations', [])} - {(0, 0)}
                if any(not (min(xs) - 1e-9 <= x <= max(xs) + 1e-9 and min(ys) - 1e-9 <= y <= max(ys) + 1e-9) for x, y in points):
                    wrong.append(metadata.get('title') or '?')
                if len(points) > 1:
                    wide += 1
                    if min(xs) == max(xs) and min(ys) == max(ys):
                        wrong.append(metadata.get('title') or '?')
            for item in value.values():
                walk(item)
    walk(data)
    return wrong, wide


def theia_lines(text, *sites):
    """Un JSON du format pivot en lignes, lisibles ; l'adresse des instances devient <site>. Les fins de
    ligne des textes longs que le nouveau a enregistrés sont en \\n (dev/differences.md, « Fins de ligne
    des textes longs »)."""
    try:
        text = json.dumps(without_extents(json.loads(text)), indent=1, ensure_ascii=False)
    except ValueError:
        # Le JSON cité dans une ligne de page
        text = re.sub(r'"spatialExtent":\{"geometry":\{.*?\]\]\}', '"spatialExtent":{"geometry":"<emprise>"', text)
    text = text.replace('\\r\\n', '\\n')
    for site in sites:
        text = text.replace(site, '<site>')
    return text.splitlines()


def theia_received(*sites):
    """Ce que le récepteur a reçu, envoi par envoi, et le vide."""
    lines = []
    for record in sorted(THEIA_RECEIVED.glob('*.json')):
        sent = json.loads(record.read_text())
        lines += [f"{sent['method']} {sent['path']}", f"type {sent['content_type']}",
                  f"authentification {sent['authorization'][:16]}"]
        for name, content in sent['files'].items():
            lines += [f'fichier {name}', *theia_lines(content, *sites)]
        record.unlink()
    return lines or ['rien reçu']


def run_theia(args):
    """L'envoi des fiches vers Theia/OZCAR (module 7), par l'ancien code sur PostgreSQL 17 et par le
    nouveau, chacun sur sa copie de BASE. Pour chaque observatoire qui a un code Theia, au nom d'un de
    ses gestionnaires : le JSON téléchargé et la page de son contrôle ; puis un mot de passe Theia
    enregistré par le formulaire, l'envoi mené par la file et reçu par le récepteur, et un envoi refusé
    (401). L'état des tâches et de la mise à jour automatique. Sur la base réduite, les envois échouent au
    contrôle du schéma (chroniques sans mesures) : --base bdoh pour les jouer jusqu'au récepteur."""
    template = args.base
    cases = query(template, """
        SELECT DISTINCT ON (o.id) o.id, o.slug, u.email FROM observatoire o
          JOIN role r ON r.observatoire_id = o.id AND r.valeur = 'gestionnaire'
          JOIN utilisateur u ON u.id = r.utilisateur_id AND (u.finacces IS NULL OR u.finacces > now())
         WHERE o.theiacode IS NOT NULL ORDER BY o.id, u.id""", NEW_SERVER)
    cases = [c for c in cases if args.only in c[1]]
    bases = {'ancien': 'bdoh_theia_old', 'nouveau': 'bdoh_theia_new'}
    for base in bases.values():
        copy_base(template, base)
    served = SIDES['new'].url

    def play(side, console, runner, base):
        parts = {}
        # Les adresses du format pivot : celle de la page, ou celle de la console pour l'envoi
        # (router.request_context de l'ancien, DEFAULT_URI du nouveau)
        # et celle des schémas, servis par le nouveau, dont page_lines a déjà fait <site>
        sites = [f'{served}/theia-schema/', '<site>/theia-schema/', f'{served}/theia-receiver.php', side.url,
                 'http://bdoh.dvp', 'http://localhost']
        sites += [site.replace('/', '\\/') for site in sites]
        for oid, slug, email in cases:
            client = Client(side, email, slug)
            status, _, body = client.get(f'/{slug}/observatoire/{oid}/downloadJson')
            parts[f'JSON {slug}'] = [f'statut {status}', *theia_lines(body.decode('utf-8', 'replace'), *sites)]
            if side.login_link:
                wrong, wide = extents_hold_points(json.loads(body))
                checks[f'nouveau seul : emprise des jeux de données de {slug}'] = (
                    ['conforme'] if not wrong else [f'{len(wrong)} jeux sur {wide} à plusieurs points : emprise fausse'])
            lines = page_lines(side.url, *client.get(f'/{slug}/observatoire/{oid}/jsonValidator'))
            parts[f'contrôle {slug}'] = [theia_lines(line, *sites)[0] if line else line for line in lines]
            path = f'/{slug}/admin/observatoire/{oid}/edit'
            form = form_of(client, path, f'/observatoire/{oid}/edit')
            body = form.filled({'[theiaPassword]': THEIA_PASSWORD}) + b'&btn_update_and_edit='
            status = client.get(form.url, body, {'Referer': side.url + path})[0]
            parts[f'mot de passe Theia {slug}'] = [f'statut {status}']
            for label, refuse in [('envoi', False), ('envoi refusé', True)]:
                (THEIA_RECEIVED / 'refuse').unlink(missing_ok=True)
                if refuse:
                    (THEIA_RECEIVED / 'refuse').touch()
                parts[f'{label} {slug}'] = redirect_lines(side.url, *client.get(f'/{slug}/observatoire/{oid}/envoieJson')[:2])
                drain(console, runner, base)
                parts[f'{label} {slug}'] += ['== reçu', *theia_received(*sites)]
            (THEIA_RECEIVED / 'refuse').unlink(missing_ok=True)
        parts['tâches'] = [line for row in query(base, """
            SELECT state, args::text, coalesce(exitcode::text, ''), coalesce(output, '') FROM jms_jobs
             WHERE command = 'theia:send:json:data' ORDER BY id""", NEW_SERVER) for line in theia_lines('\t'.join(row), *sites)]
        parts['mise à jour automatique'] = ['\t'.join(row) for row in query(
            base, 'SELECT slug, miseajourautomatique FROM observatoire ORDER BY id', NEW_SERVER)]
        # L'observatoire change par la fiche enregistrée ; les tâches, par leurs dates et leur ordre
        parts['autres tables'] = other_tables_lines(base, {'observatoire', 'jms_jobs', 'jms_job_related_entities',
                                                           'historique'})
        if side.login_link and len(cases) > 1:
            # Les pages Theia d'un autre observatoire que celui de la page (dev/local/security.md)
            (oid, _, _), (_, slug, email) = cases[0], cases[1]
            client = Client(side, email, slug)
            statuses = [client.get(f'/{slug}/observatoire/{oid}/{action}')[0]
                        for action in ('downloadJson', 'jsonValidator', 'envoieJson')]
            checks['nouveau seul : pages Theia d’un autre observatoire, 404'] = (
                ['conforme'] if statuses == [404, 404, 404] else [f'statuts {statuses}'])
        # Une fiche qui passe le contrôle du schéma, seule à aller jusqu'à l'envoi
        sendable = [c for c in cases if query(base, f"""SELECT count(*) FROM jms_jobs j, observatoire o WHERE o.id = {c[0]}
                                                         AND j.command = 'theia:send:json:data' AND j.state = 'finished'
                                                         AND j.args::text LIKE '%' || o.theiacode || '%'""", NEW_SERVER)[0][0] != '0']
        if side.login_link and sendable:
            # Ce que seul le nouveau fait d'un envoi (dev/differences.md, « Envoi vers Theia/OZCAR ») ; la dernière
            # tâche : son état, son code de sortie, sa sortie, et la mise à jour automatique coupée
            oid, slug, email = sendable[0]
            client = Client(side, email, slug)
            set_auto = lambda: query(base, f'UPDATE observatoire SET miseajourautomatique = true WHERE id = {oid} RETURNING id', NEW_SERVER)
            last = lambda: query(base, f"""SELECT j.state, coalesce(j.exitcode::text, ''),
                                               translate(coalesce(j.output, ''), chr(10) || chr(9), '  '),
                                               (SELECT o.miseajourautomatique::text FROM observatoire o WHERE o.id = {oid})
                                          FROM jms_jobs j WHERE j.command = 'theia:send:json:data' ORDER BY j.id DESC LIMIT 1""",
                                 NEW_SERVER)[0]
            def failed_send(label, expected, run_console):
                set_auto()
                client.get(f'/{slug}/observatoire/{oid}/envoieJson')
                drain(run_console, runner, base)
                state, code, output, auto = last()
                ok = state == 'failed' and code == '1' and expected in output and auto == 'false'
                checks[label] = ['conforme'] if ok else [f'état {state}, code {code}, mise à jour {auto}', output[:300]]
            (THEIA_RECEIVED / 'redirige').touch()
            failed_send('nouveau seul : envoi redirigé, en échec', 'Réponse inattendue du serveur : 302', console)
            (THEIA_RECEIVED / 'redirige').unlink(missing_ok=True)
            # La sortie d'une tâche va en base : une réponse qui n'est pas en UTF-8 ne doit pas faire tomber l'exécuteur
            (THEIA_RECEIVED / 'latin1').touch()
            failed_send('nouveau seul : réponse en Latin-1, en échec, exécuteur debout', 'Body response : ', console)
            (THEIA_RECEIVED / 'latin1').unlink(missing_ok=True)
            # Un port fermé : la console de la file lit l'adresse d'envoi dans l'environnement
            dead = [x.replace(f'THEIA_SEND_URI={served}/theia-receiver.php/', 'THEIA_SEND_URI=http://172.30.26.10:9/') for x in console]
            failed_send('nouveau seul : serveur de Theia qui ne répond pas, en échec', 'Message : ', dead)
            # Sans mot de passe : un message, et aucune tâche
            jobs = lambda: query(base, "SELECT count(*) FROM jms_jobs WHERE command = 'theia:send:json:data'", NEW_SERVER)[0][0]
            query(base, f'UPDATE observatoire SET theiapassword = NULL WHERE id = {oid} RETURNING id', NEW_SERVER)
            before = jobs()
            status, headers, _ = client.get(f'/{slug}/observatoire/{oid}/envoieJson')
            ok = status == 302 and headers.get('Location', '').endswith(f'/{slug}/admin/observatoire/{oid}/edit') and jobs() == before
            checks['nouveau seul : envoi sans mot de passe, un message et aucune tâche'] = (
                ['conforme'] if ok else [f'statut {status}, {headers.get("Location")}, {before} tâches avant, {jobs()} après'])
        return parts

    THEIA_RECEIVED.mkdir(parents=True, exist_ok=True)
    for stale in THEIA_RECEIVED.glob('*'):
        stale.unlink()
    sides, checks = {}, {}
    with theia_stand_in(served), attached_files(template):
        with old_17_on(bases['ancien'], uri_theia_send_data=f"'{served}/theia-receiver.php/'",
                       uri_theia_ozcar_schema_json=f"'{served}/theia-schema/pivotSchemaDraft7.json'") as console:
            sides['ancien'] = play(SIDES['reference-17'], console, 'jms-job-queue:run', bases['ancien'])
        with new_on(bases['nouveau'], template, f'THEIA_SEND_URI={served}/theia-receiver.php/',
                    f'THEIA_SCHEMA_URI={served}/theia-schema/pivotSchemaDraft7.json') as console:
            sides['nouveau'] = play(SIDES['new'], console, 'bdoh:jobs:run', bases['nouveau'])
    report = Report('theia')
    for part in sides['ancien']:
        report.add(part, sides['ancien'][part], sides['nouveau'].get(part, []))
    for part, lines in checks.items():
        report.add(part, ['conforme'], lines)
    report.close()
    print(f'Les instances lisent de nouveau {template}. Les deux copies restent pour examen :'
          f' {bases["ancien"]} et {bases["nouveau"]}.')


# --- L'outil éprouvé ---------------------------------------------------------------------------------

# Des erreurs inventées, une par une, sur une copie de la base : (table comparée où elle doit se voir,
# nom, requête). Le comparateur doit les classer « différents », jamais « à un arrondi près ». Les premières
# lignes de la copie préparée portent les cas que les données n'ont pas forcément (selftest_rows).
SELFTEST_FIRST = {
    'mesure': "(SELECT min(id) FROM mesure WHERE valeur > 1 AND valeur * (1 + 2.3e-16) <> valeur)",
    'plage': "(SELECT min(id) FROM plage WHERE valeur > 1 AND valeur * (1 + 2.3e-16) <> valeur)",
    'pointcontrole': "(SELECT min(id) FROM pointcontrole WHERE valeur > 1 AND valeur * (1 + 2.3e-16) <> valeur)",
    'tauxremplissage': "(SELECT min(id) FROM tauxremplissage WHERE taux > 0.1 AND taux * (1 + 2.3e-16) <> taux)",
    'chronique': "(SELECT min(id) FROM chronique WHERE nbmesures > 0)",
    'bareme': "(SELECT min(id) FROM bareme WHERE valeurs[2][2] > 1 AND valeurs[2][2] * (1 + 2.3e-16) <> valeurs[2][2])",
}
SELFTEST_ROWS = {
    'une borne': "(SELECT min(id) FROM mesure WHERE valeur > 1)",
    'deux bornes': "(SELECT min(id) FROM mesure WHERE valeur > 1 AND id > (SELECT min(id) FROM mesure WHERE valeur > 1))",
    'lacune': "(SELECT max(id) FROM mesure WHERE valeur > 1)",
}
SELFTEST = [
    ('mesure', "un bit d'une valeur", f"UPDATE mesure SET valeur = valeur * (1 + 2.3e-16) WHERE id = {SELFTEST_FIRST['mesure']}"),
    ('mesure', 'une ligne en moins', f"DELETE FROM mesure WHERE id = {SELFTEST_FIRST['mesure']}"),
    ('mesure', 'une ligne en plus', f"""INSERT INTO mesure (id, qualite_id, chronique_id, date, valeur, minimum, maximum, estcalculee)
        SELECT nextval('mesure_id_seq'), qualite_id, chronique_id, date + interval '1 second', valeur, minimum, maximum, estcalculee
          FROM mesure WHERE id = {SELFTEST_FIRST['mesure']}"""),
    ('mesure', "une heure de décalage", """UPDATE mesure SET date = date + interval '1 hour'
        WHERE id = (SELECT id FROM mesure ORDER BY chronique_id, date DESC LIMIT 1)"""),
    ('mesure', 'minimum et maximum échangés, un NULL', f"UPDATE mesure SET minimum = maximum, maximum = minimum WHERE id = {SELFTEST_ROWS['une borne']}"),
    ('mesure', 'minimum et maximum échangés', f"UPDATE mesure SET minimum = maximum, maximum = minimum WHERE id = {SELFTEST_ROWS['deux bornes']}"),
    ('mesure', 'une autre qualité', f"""UPDATE mesure m SET qualite_id = (SELECT min(q.id) FROM qualite q WHERE q.id <> m.qualite_id)
        WHERE id = {SELFTEST_FIRST['mesure']}"""),
    ('mesure', 'marquée calculée', f"UPDATE mesure SET estcalculee = NOT estcalculee WHERE id = {SELFTEST_FIRST['mesure']}"),
    ('mesure', '-9999 devenu NULL', f"UPDATE mesure SET valeur = NULL WHERE id = {SELFTEST_ROWS['lacune']}"),
    ('plage', "un bit d'une valeur", f"UPDATE plage SET valeur = valeur * (1 + 2.3e-16) WHERE id = {SELFTEST_FIRST['plage']}"),
    ('plage', 'une seconde de plus à la fin', f"UPDATE plage SET fin = fin + interval '1 second' WHERE id = {SELFTEST_FIRST['plage']}"),
    ('plage', 'une ligne en moins', f"DELETE FROM plage WHERE id = {SELFTEST_FIRST['plage']}"),
    ('pointcontrole', "un bit d'une valeur", f"UPDATE pointcontrole SET valeur = valeur * (1 + 2.3e-16) WHERE id = {SELFTEST_FIRST['pointcontrole']}"),
    ('tauxremplissage', "un bit d'un taux", f"UPDATE tauxremplissage SET taux = taux * (1 + 2.3e-16) WHERE id = {SELFTEST_FIRST['tauxremplissage']}"),
    ('chronique', 'nombre de mesures, +1', f"UPDATE chronique SET nbmesures = nbmesures + 1 WHERE id = {SELFTEST_FIRST['chronique']}"),
    ('chronique', "date de début, une heure", f"UPDATE chronique SET datedebutmesures = datedebutmesures + interval '1 hour' WHERE id = {SELFTEST_FIRST['chronique']}"),
    ('station', "point déplacé d'un millimètre", "UPDATE station SET point = ST_Translate(point, 0.001, 0) WHERE id = (SELECT min(id) FROM station WHERE point IS NOT NULL)"),
    ('station', 'latitude', "UPDATE station SET latitude = latitude + 0.001 WHERE id = (SELECT min(id) FROM station WHERE latitude IS NOT NULL)"),
    ('bareme', "un bit d'un point d'une courbe", f"UPDATE bareme SET valeurs[2][2] = valeurs[2][2] * (1 + 2.3e-16) WHERE id = {SELFTEST_FIRST['bareme']}"),
    ('transformation', "une autre entrée", """UPDATE transformation t SET entree_id = (SELECT min(c.id) FROM chronique c WHERE c.id <> t.entree_id)
        WHERE id = (SELECT min(id) FROM transformation)"""),
    ('jeubareme', 'délai de propagation', "UPDATE jeubareme SET delaipropagation = coalesce(delaipropagation, 0) + 1 WHERE id = (SELECT min(id) FROM jeubareme)"),
    ('baremejeubareme', 'début de validité, une seconde', "UPDATE baremejeubareme SET debutvalidite = debutvalidite + interval '1 second' WHERE id = (SELECT min(id) FROM baremejeubareme)"),
    ('bassin', "périmètre déplacé d'un millimètre", "UPDATE bassin SET perimetre = ST_Translate(perimetre, 0.001, 0) WHERE id = (SELECT min(id) FROM bassin)"),
    ('courseau', "tracé déplacé d'un millimètre", "UPDATE courseau SET trace = ST_Translate(trace, 0.001, 0) WHERE id = (SELECT min(id) FROM courseau)"),
    ('historique', 'une entrée changée', "UPDATE historique SET description = coalesce(description, '') || ' essai' WHERE id = (SELECT max(id) FROM historique)"),
    ('jms_jobs', 'une tâche de plus', """INSERT INTO jms_jobs (id, state, queue, priority, createdat, executeafter, command, args, maxruntime, maxretries)
        VALUES (nextval('jms_jobs_id_seq'), 'pending', 'essai', 0, now(), now(), 'essai', '[]', 0, 0)"""),
    ('autres tables', 'une unité renommée', "UPDATE unite SET libelle = libelle || ' essai' WHERE id = (SELECT min(id) FROM unite)"),
    ('autres tables', 'un droit retiré', "DELETE FROM role WHERE id = (SELECT min(id) FROM role)"),
    ('autres tables', "le téléphone d'un compte", "UPDATE utilisateur SET telephone = '0102030405' WHERE id = (SELECT min(id) FROM utilisateur)"),
    ('autres tables', 'une table laissée dans temp', "CREATE TABLE temp.tmp_essai (a int)"),
]


def run_selftest(args):
    """Le comparateur voit-il les erreurs ? Chaque erreur de SELFTEST sur une copie fraîche de BASE, puis
    la comparaison de cette copie à la copie intacte, par les requêtes et le verdict des autres modes.
    Le rapport n'écrit que le nom des erreurs : aucune donnée, aucune ligne de la base."""
    prepared, altered = 'bdoh_selftest_base', 'bdoh_selftest'
    copy_base(args.base, prepared)
    # Des lignes qu'une base n'a pas forcément : une seule borne, deux bornes, une lacune à -9999
    query(prepared, f"UPDATE mesure SET minimum = NULL, maximum = valeur + 1 WHERE id = {SELFTEST_ROWS['une borne']}", NEW_SERVER)
    query(prepared, f"UPDATE mesure SET minimum = valeur - 1, maximum = valeur + 1 WHERE id = {SELFTEST_ROWS['deux bornes']}", NEW_SERVER)
    query(prepared, f"UPDATE mesure SET valeur = -9999 WHERE id = {SELFTEST_ROWS['lacune']}", NEW_SERVER)
    # La dernière entrée de l'historique compte dans ce que lit sa requête
    historique = int(query(prepared, 'SELECT max(id) FROM historique', NEW_SERVER)[0][0]) - 1

    def lines(base, part):
        if part == 'autres tables':
            return other_tables_lines(base, STATE_QUERIES)
        return state_lines(base, historique, [part])[part]

    report = Report('selftest')
    for part, name, sql in SELFTEST:
        if args.only not in name:
            continue
        copy_base(prepared, altered)
        tag = query(altered, sql, NEW_SERVER)
        if not tag or tag[0][0].split()[-1] == '0':
            report.add(f'{part} : {name}', ['vue'], ['sans effet : aucune ligne touchée, cas à revoir'])
            continue
        verdict = classify(lines(prepared, part), lines(altered, part), exact=True)[0]
        report.add(f'{part} : {name}', ['vue'], ['vue' if verdict == 'différents' else f'classée « {verdict} »'])
    report.close()
    query_postgres = ['docker', 'exec', NEW_SERVER, 'psql', '-U', 'postgres', '-qc']
    for base in (prepared, altered):
        subprocess.run([*query_postgres, f'DROP DATABASE IF EXISTS {base}'], check=True, capture_output=True)


def run_fingerprints(args):
    """Deux empreintes de make fingerprint, table par table, en comptes seulement : rien de ce qui nomme
    une chronique n'est écrit."""
    def load(path):
        return {tuple(fields[:2]): tuple(fields[2:]) for fields in (line.strip().split(';') for line in open(path))
                if len(fields) == 4}

    if len(args.files) != 2:
        sys.exit('Il faut deux fichiers : compare.py fingerprints A B')
    a, b = load(args.files[0]), load(args.files[1])
    for table in sorted({key[0] for key in {*a, *b}}):
        ka, kb = {k for k in a if k[0] == table}, {k for k in b if k[0] == table}
        same = sum(a[k] == b[k] for k in ka & kb)
        counts = sum(a[k][0] != b[k][0] for k in ka & kb)
        print(f"{table} : {len(ka)} et {len(kb)} chroniques, {same} identiques, {counts} de compte différent,"
              f" {len(ka & kb) - same - counts} de même compte et d'empreinte différente, {len(ka ^ kb)} d'un seul côté ;"
              f" {sum(int(a[k][0]) for k in ka)} et {sum(int(b[k][0]) for k in kb)} lignes")
        Report.failed = Report.failed or same != len(ka) or same != len(kb)


def main():
    global CASES_SERVER
    parser = argparse.ArgumentParser(description=__doc__.split('\n')[0])
    parser.add_argument('what', choices=['pages', 'exports', 'state', 'queue', 'imports', 'accounts', 'admin',
                                         'theia', 'fingerprints', 'selftest'])
    parser.add_argument('files', nargs='*', help='pour fingerprints : les deux empreintes à comparer')
    parser.add_argument('--old', default='reference', choices=SIDES)
    parser.add_argument('--new', default='reference', choices=SIDES)
    parser.add_argument('--base', default='bdoh_small', help='base où tirer les cas')
    parser.add_argument('--server', default=CASES_SERVER, help='conteneur du serveur de cette base')
    parser.add_argument('--only', default='', help='ne garder que les cas qui contiennent ce texte')
    parser.add_argument('--jobs', type=int, default=6, help='requêtes menées en parallèle')
    parser.add_argument('--profile', help='un seul profil (anonyme, utilisateur, gestionnaire, administrateur)')
    parser.add_argument('--same', action='store_true', help="state : l'ancien contre lui-même, pour mesurer le bruit")
    parser.add_argument('--inject-sql', metavar='FICHIER', help='state : du SQL à jouer sur la copie du nouveau (mutants.py)')
    args = parser.parse_args()
    if args.inject_sql and args.what != 'state':
        parser.error('--inject-sql ne vaut que pour state')
    CASES_SERVER = args.server
    {'pages': run_pages, 'exports': run_exports, 'state': run_state, 'queue': run_queue, 'imports': run_imports,
     'accounts': run_accounts, 'admin': run_admin, 'theia': run_theia,
     'fingerprints': run_fingerprints, 'selftest': run_selftest}[args.what](args)
    return 1 if Report.failed else 0


if __name__ == '__main__':
    sys.exit(main())
