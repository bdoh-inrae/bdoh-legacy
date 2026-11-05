#!/usr/bin/env python3
"""Essaie l'interface du nouveau BDOH dans un vrai navigateur (Playwright, Chromium sans fenêtre).

    make browser-check [ARGS="--only TEXTE"]

Chaque étape ouvre une page ou manipule un formulaire, vérifie ce qu'elle doit montrer, et relève les erreurs
JavaScript et les requêtes en échec. Les captures vont hors du dépôt, dans ../bdoh_reference/browser/.

Tout se joue sur une copie de la base réduite.
"""

import argparse
import datetime
import re
import subprocess
import sys
import zipfile
from pathlib import Path

from playwright.sync_api import TimeoutError as PlaywrightTimeout
from playwright.sync_api import sync_playwright

sys.path.insert(0, str(Path(__file__).resolve().parent.parent / 'compare'))
import compare as c  # noqa: E402

TEMPLATE, BASE = 'bdoh_small', 'bdoh_browser'
SLUG = 'REAL-COLLOBRIER'
SITE = c.SIDES['new'].url
OUT = c.OUT.parent / 'browser' / datetime.datetime.now().strftime('%Y-%m-%d_%H%M%S')

# Ce que le développement n'a pas : sans rapport avec le code (dev/browser/README.md)
# (les fichiers joints des bases anonymisées ne sont pas copiés : logos et photos répondent 404)
IGNORED = re.compile(r'livereload|favicon|/uploads/|the server responded with a status of 404|net::ERR_(CONNECTION|NAME|INTERNET|ADDRESS|TIMED|TUNNEL|SOCKET)')


def one(sql):
    rows = c.query(BASE, sql, c.NEW_SERVER)
    return rows[0][0] if rows else None


class Session:
    """Un navigateur, connecté ou non, et le relevé de ses étapes."""

    def __init__(self, browser, label, only, results):
        self.context = browser.new_context(viewport={'width': 1400, 'height': 1000}, locale='fr-FR')
        self.page = self.context.new_page()
        self.label, self.only, self.results, self.problems = label, only, results, []
        self.page.on('console', lambda m: self.problem(f'console : {m.text}') if m.type == 'error' else None)
        self.page.on('pageerror', lambda e: self.problem(f'JavaScript : {e}'))
        self.page.on('response', lambda r: self.problem(f'{r.status} {r.url}') if r.status >= 400 else None)
        self.page.on('requestfailed', lambda r: self.problem(f'échec {r.url} {r.failure}'))

    def problem(self, text):
        if not IGNORED.search(text):
            self.problems.append(text)

    def step(self, name, path, check=None):
        """Ouvre path s'il est donné, joue check(page), garde une capture et le relevé."""
        title = f'{self.label} : {name}'
        if self.only not in title:
            return
        self.problems = []
        failure = None
        try:
            if path:
                self.page.goto(SITE + path, wait_until='networkidle', timeout=120000)
            if check:
                check(self.page)
        except (PlaywrightTimeout, AssertionError, RuntimeError) as e:
            failure = str(e).split('\n')[0]
        number = len(self.results) + 1
        self.page.screenshot(path=OUT / f"{number:02d}-{re.sub(r'[^a-zA-Z0-9]+', '-', title)[:70]}.png", full_page=True)
        self.results.append((title, failure, list(self.problems)))
        state = 'échec' if failure else 'erreurs' if self.problems else 'ok'
        print(f'{number:3d} {state:8s} {title}')
        for line in ([failure] if failure else []) + self.problems[:6]:
            print(f'      {line[:170]}')


def visible(page, selector, timeout=20000):
    page.locator(selector).first.wait_for(state='visible', timeout=timeout)


def chart_step(page):
    visible(page, '#fillingRates')
    page.locator('.set-min').first.click()
    page.locator('.set-max').nth(1).click()
    page.locator('a.submit.validated').first.click()
    visible(page, 'canvas', timeout=60000)
    page.wait_for_load_state('networkidle')


def public_steps(s, station, chronique):
    def footer(page):
        assert page.locator('a[href*="github.com/bdoh-inrae/bdoh-legacy"]').count() == 1, 'pas de lien vers le code source'
        assert 'GNU AGPL' in page.inner_text('body'), 'licence absente'

    s.step('accueil, licence et code source', f'/{SLUG}/', footer)

    def our_data(page):
        visible(page, '#main-table tbody tr')
        info = page.locator('.dataTables_info').first
        before = info.inner_text()
        page.locator('#main-table_filter input, .dataTables_filter input').first.fill(chronique)
        page.wait_for_timeout(800)
        assert info.inner_text() != before, f'la recherche ne filtre pas : {before}'
        page.locator('#main-table_filter input, .dataTables_filter input').first.fill('')

    s.step('nos données, recherche dans le tableau', f'/{SLUG}/nos-donnees', our_data)
    s.step('page de la station', f'/{SLUG}/station/{station}', lambda p: visible(p, 'text=Caractéristiques'))

    s.step('chronique, taux de remplissage et graphique', f'/{SLUG}/{station}/{chronique}', chart_step)

    def complex_viewer(page):
        page.locator('.set-min').first.click()
        page.locator('.set-max').nth(1).click()
        page.locator('a.submit.validated').first.click()
        visible(page, 'button.add-pane', timeout=60000)
        page.locator('button.add-pane').click()
        page.wait_for_load_state('networkidle')
        page.wait_for_timeout(1000)

    s.step('visualiseur, ajout d’un panneau', f'/{SLUG}/viewer', complex_viewer)

    def language(page):
        page.locator('.dropdown-toggle:has-text("Français")').first.click()
        with page.expect_navigation(wait_until='networkidle'):
            page.locator('a[href*="select_lang/en"]').first.click()
        visible(page, 'text=Specifications')
        page.goto(f'{SITE}/{SLUG}/station/{station}', wait_until='networkidle')
        visible(page, 'text=Specifications')

    s.step('passage en anglais depuis une page, qui tient à la suivante', f'/{SLUG}/station/{station}', language)
    s.step('retour en français', '/select_lang/fr', lambda p: visible(p, 'body'))


def open_export(page):
    """Ouvre la fenêtre d'export d'une chronique, dates comprises."""
    page.locator('[data-target^="#export-"], a[href^="#export-"]').first.click()
    visible(page, '.modal.in [name=exportFormat]')


def open_data_steps(s, station, chronique, console, hidden):
    """L'ouverture des données, vue sans compte : un export jusqu'au fichier, et une chronique cachée refusée."""
    chronique_page = f'/{SLUG}/{station}/{chronique}'

    def export_to_file(page):
        open_export(page)
        assert page.locator('.modal.in .export-licence').is_visible(), 'la licence n’est pas dite'
        assert page.locator('.modal.in [name=termsOfUses]').count() == 0, 'conditions à cocher sur une chronique visible'
        page.locator('.modal.in .start').fill('01/01/2024 00:00')
        page.locator('.modal.in .end').fill('01/02/2024 00:00')
        page.locator('.modal.in .end').press('Tab')
        with page.expect_navigation(wait_until='networkidle'):
            page.locator('.modal.in input[type=submit]').click()
        assert re.search(r'/mesure/export/\d+/\w+$', page.url), f'pas de page de suivi : {page.url}'
        c.drain(console, 'bdoh:jobs:run', BASE)
        page.reload(wait_until='networkidle')
        with page.expect_download() as download:
            page.locator('a.btn-info').click()
        saved = OUT / 'export-sans-compte.zip'
        download.value.save_as(saved)
        names = zipfile.ZipFile(saved).namelist()
        assert any('Licence-Ouverte' in name for name in names), f'pas de licence dans le zip : {names}'
        assert any(name.endswith('.txt') for name in names), f'pas de données dans le zip : {names}'

    s.step('export sans compte, jusqu’au fichier', chronique_page, export_to_file)

    if hidden:
        def refused(page):
            response = page.goto(f'{SITE}/{hidden[0]}/{hidden[1]}/{hidden[2]}', wait_until='networkidle')
            assert response.status == 403, f'statut {response.status}'
            s.problems.clear()  # ce refus est celui qu'on attend, pas une erreur de la page

        s.step('chronique cachée refusée', None, refused)


def hidden_export_step(s, hidden):
    """Un compte qui a le droit : la fenêtre d'export d'une chronique cachée garde ses conditions à cocher."""
    def terms(page):
        open_export(page)
        assert page.locator('.modal.in [name=termsOfUses]').count() == 1, 'pas de conditions à cocher'
        assert page.locator('.modal.in .export-licence').count() == 0, 'la licence ouverte sur une chronique cachée'

    s.step('chronique cachée, fenêtre d’export', f'/{hidden[0]}/{hidden[1]}/{hidden[2]}', terms)


def field_group(page, name):
    """Le bloc d'un champ de formulaire Sonata, par la fin de son identifiant."""
    return page.locator(f'[id^="sonata-ba-field-container-"][id$="_{name}"]')


def open_select(page, name, typed=None):
    """Ouvre la liste select2 d'un champ et rend le nombre de choix proposés."""
    field_group(page, name).locator('.select2-container').first.click()
    if typed:
        page.locator('.select2-container--open input.select2-search__field').first.fill(typed)
        page.wait_for_timeout(1500)  # la recherche de communes passe par le serveur
    results = page.locator('.select2-container--open .select2-results__option')
    results.first.wait_for(state='visible', timeout=20000)
    if 'select2-results__message' in (results.first.get_attribute('class') or ''):
        page.keyboard.press('Escape')
        return 0
    count = results.count()
    page.keyboard.press('Escape')
    return count


def manager_steps(s, oid, station, chronique_id, chronique_page, dataset, import_file):
    base = f'/{SLUG}/admin'

    def export_modal(page):
        page.locator('[data-target^="#export-"], a[href^="#export-"]').first.click()
        visible(page, 'select[name=exportFormat], [name=exportFormat]')
        formats = page.locator('[name=exportFormat]').first.evaluate('e => [...(e.options || [])].map(o => o.value)')
        assert 'Vigilance' not in formats, f'formats : {formats}'
        visible(page, '[name=timezone]')

    s.step('fenêtre d’export', chronique_page, export_modal)
    s.step('tableau de bord', f'{base}/dashboard', lambda p: visible(p, '.content'))
    s.step('liste des stations', f'{base}/station/list', lambda p: visible(p, 'table tbody tr'))

    def station_form(page):
        assert open_select(page, 'sites') > 0, 'aucun site proposé'
        assert open_select(page, 'commune', 'Coll') > 0, 'aucune commune trouvée'

    s.step('station : sites et recherche de commune', f'{base}/station/{station}/edit', station_form)

    def station_save(page):
        page.locator('button[name=btn_update_and_edit]').click()
        page.wait_for_load_state('networkidle')
        visible(page, '.alert-success, .toast-success, #toast-container')

    s.step('station : enregistrée', f'{base}/station/{station}/edit', station_save)
    s.step('station : création', f'{base}/station/create', lambda p: visible(p, 'form'))

    def chronique_form(page):
        assert open_select(page, 'parametre') > 0, 'aucun paramètre proposé'
        assert open_select(page, 'milieu') > 0, 'aucun milieu proposé'
        visible(page, 'text=Période couverte')

    s.step('chronique : listes de choix', f'{base}/chroniquecontinue/{chronique_id}/edit', chronique_form)

    def add_producer(page):
        group = field_group(page, 'producteur')
        group.locator('a.sonata-ba-action:has-text("Créer")').first.click()
        visible(page, '.modal.in form')
        page.wait_for_load_state('networkidle')

    s.step('chronique : créer un producteur (fenêtre)', f'{base}/chroniquecontinue/{chronique_id}/edit', add_producer)

    def pick_doi(page):
        group = field_group(page, 'doi')
        group.locator('a.sonata-ba-action[title="Liste"]').first.click()
        visible(page, '.modal.in table tbody tr')
        page.locator('.modal.in table tbody tr a').first.click()
        page.wait_for_load_state('networkidle')
        page.wait_for_timeout(800)
        assert group.locator('.inner-field-short-description').first.inner_text().strip(), 'description vide'

    s.step('jeu de données : choisir un DOI par sa liste', f'{base}/dataset/{dataset}/edit', pick_doi)
    s.step('observatoire : éditeur de texte', f'{base}/observatoire/{oid}/edit', lambda p: visible(p, '.trumbowyg-box'))

    def measure_import(page):
        page.locator('select[name="file-format"]').select_option('Bdoh')
        page.locator('input[type=file]').first.set_input_files(import_file)
        page.wait_for_load_state('networkidle')
        page.wait_for_timeout(1500)

    s.step('import de mesures : choix du fichier', f'{base}/mesure/import', measure_import)
    s.step('import de points de contrôle', f'{base}/controle/import', lambda p: visible(p, 'form'))
    s.step('import de shapefiles', f'{base}/shape/import', lambda p: visible(p, 'form'))
    s.step('transformations', f'{base}/transformation', lambda p: visible(p, 'select'))
    s.step('conversion', f'{base}/conversion', lambda p: visible(p, 'form'))

    def history(page):
        visible(page, '.nav-tabs')
        page.locator('.nav-tabs a.dropdown-toggle').first.click()
        page.locator('.nav-tabs a[href="#adminStation"]').click()
        page.wait_for_timeout(500)
        visible(page, '#adminStation.active')

    s.step('historique : onglets', f'/{SLUG}/historique/recent', history)


def admin_steps(s, hidden):
    s.step('couleurs (sélecteurs)', f'/{SLUG}/admin/colors', lambda p: visible(p, 'form'))
    if hidden:
        hidden_export_step(s, hidden)


def main():
    parser = argparse.ArgumentParser(description=__doc__.split('\n')[0])
    parser.add_argument('--only', default='', help='ne garder que les étapes qui contiennent ce texte')
    args = parser.parse_args()

    OUT.mkdir(parents=True)
    c.copy_base(TEMPLATE, BASE)
    results = []
    with c.new_on(BASE, TEMPLATE) as console, sync_playwright() as playwright:
        oid = one(f"SELECT id FROM observatoire WHERE slug = '{SLUG}'")
        visible_chronique = f"""SELECT st.code, ch.code, ch.id FROM chronique ch JOIN station st ON st.id = ch.station_id
            JOIN stations_sites ss ON ss.station_id = st.id JOIN siteexperimental si ON si.id = ss.site_id
            WHERE si.observatoire_id = {oid} AND ch.estvisible AND ch.dtype = 'continue' AND ch.nbmesures > 0
            ORDER BY ch.nbmesures DESC LIMIT 1"""
        station_code, chronique_code, chronique_id = c.query(BASE, visible_chronique, c.NEW_SERVER)[0]
        station_id = one(f"SELECT id FROM station WHERE code = '{station_code}'")
        dataset = one(f'SELECT id FROM dataset WHERE observatoire_id = {oid} ORDER BY id LIMIT 1')
        manager = one(f"""SELECT u.email FROM utilisateur u JOIN role r ON r.utilisateur_id = u.id
            WHERE r.observatoire_id = {oid} AND r.valeur = 'gestionnaire' AND u.roleadmin IS NULL
              AND (u.finacces IS NULL OR u.finacces > now()) ORDER BY u.id LIMIT 1""")

        def signed_in(label, email):
            session = Session(browser, label, args.only, results)
            link = subprocess.run([*console, 'security:users:login-link', email, SLUG, '--env=prod'],
                                  capture_output=True, text=True, check=True).stdout.strip()
            session.page.goto(re.sub(r'^https?://[^/]+', SITE, link.replace('&amp;', '&')))
            session.page.locator('form button, form [type=submit]').first.click()
            session.page.wait_for_load_state('networkidle')
            return session

        # Un fichier d'import au format BDOH, pour une période sans mesures (2020)
        unite = one(f'SELECT u.libelle FROM unite u JOIN chronique ch ON ch.unite_id = u.id WHERE ch.id = {chronique_id}')
        quality = one(f"""SELECT q.code FROM mesure m JOIN qualite q ON q.id = m.qualite_id
                           WHERE m.chronique_id = {chronique_id} LIMIT 1""")
        import_file = OUT / 'import.txt'
        import_file.write_text('Station;Fuseau;Chronique;Unite\n' + f'{station_code};UTC+01;{chronique_code};{unite}\n'
                               + 'DateHeure;Valeur;Qualite\n' + ''.join(f'2020-03-0{d} 12:00:00;1.5;{quality}\n' for d in range(1, 6)))

        # Une chronique d'une autre sorte, pour son graphique : (observatoire, station, chronique) ; et une cachée
        other_kind = lambda where: (c.query(BASE, f"""SELECT o.slug, st.code, ch.code FROM chronique ch JOIN station st ON st.id = ch.station_id
            JOIN stations_sites ss ON ss.station_id = st.id JOIN siteexperimental si ON si.id = ss.site_id
            JOIN observatoire o ON o.id = si.observatoire_id
            WHERE {where} AND ch.nbmesures > 0 ORDER BY ch.nbmesures DESC, ch.id LIMIT 1""", c.NEW_SERVER) or [None])[0]
        hidden = other_kind('NOT ch.estvisible')

        browser = playwright.chromium.launch()
        anonymous = Session(browser, 'sans compte', args.only, results)
        public_steps(anonymous, station_code, chronique_code)
        open_data_steps(anonymous, station_code, chronique_code, console, hidden)
        for label, where in (('discontinue', "ch.estvisible AND ch.dtype = 'discontinue'"), ('calculée', "ch.estvisible AND ch.dtype = 'calculee'")):
            found = other_kind(where)
            if found:
                anonymous.step(f'chronique {label}, graphique', f'/{found[0]}/{found[1]}/{found[2]}', chart_step)
        managing = signed_in('gestionnaire', manager)
        chronique_page = f'/{SLUG}/{station_code}/{chronique_code}'
        public_steps(managing, station_code, chronique_code)
        manager_steps(managing, oid, station_id, chronique_id, chronique_page, dataset, str(import_file))
        admin = one("""SELECT email FROM utilisateur WHERE roleadmin = 'FCT'
                        AND (finacces IS NULL OR finacces > now()) ORDER BY id LIMIT 1""")
        admin_steps(signed_in('administrateur', admin), hidden)
        browser.close()

    failed = [r for r in results if r[1]]
    noisy = [r for r in results if r[2] and not r[1]]
    print(f'\n{len(results)} étapes : {len(results) - len(failed) - len(noisy)} sans problème, '
          f'{len(noisy)} avec des erreurs relevées, {len(failed)} en échec. Captures : {OUT}')
    return 1 if failed or noisy else 0


if __name__ == '__main__':
    sys.exit(main())
