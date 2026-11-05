"""Des parcours de bout en bout, comme les ferait quelqu'un : chaque étape clique, puis vérifie ce que l'écran montre,
et, quand c'est là que la vérité se trouve, ce que la base ou le courriel en ont gardé. check.py les joue."""

import re
import time
import zipfile

import compare as c


def visible(page, selector, timeout=20000):
    page.locator(selector).first.wait_for(state='visible', timeout=timeout)


def submit(page, scope='form'):
    """Envoie un formulaire et attend la page qui suit."""
    with page.expect_navigation(wait_until='networkidle', timeout=120000):
        page.locator(f'{scope} input[type=submit], {scope} button[type=submit]').first.click()


def notice(page):
    """Les messages affichés par toastr, ou le texte des alertes."""
    page.wait_for_timeout(300)
    toasts = page.locator('#toast-container').all_inner_texts()
    return ' '.join(toasts + page.locator('.alert').all_inner_texts())


class Trip:
    """Ce dont les parcours ont besoin : la base, la file de tâches, les courriels et de quoi ouvrir une session."""

    def __init__(self, base, site, slug, console, new_session, one, out):
        self.base, self.site, self.slug, self.console = base, site, slug, console
        self.new_session, self.one, self.out = new_session, one, out

    def rows(self, sql):
        return c.query(self.base, sql, c.NEW_SERVER)

    def drain(self):
        c.drain(self.console, 'bdoh:jobs:run', self.base)

    def follow_mailed_link(self, page, email):
        """Le lien du dernier courriel reçu par une adresse, puis le bouton de sa page, comme le demande la connexion."""
        link = re.sub(r'^https?://[^/]+', self.site, c.mailed_link(email))
        page.goto(link, wait_until='networkidle')
        submit(page)


# --- Un nouveau compte -----------------------------------------------------------------------------------

def new_account(trip):
    """S'inscrire, se tromper, recevoir son lien, se connecter, se reconnecter, demander un accès."""
    s = trip.new_session('nouveau compte')
    slug = trip.slug
    email = f'parcours-{int(time.time())}@example.invalid'
    field = lambda page, name: page.locator(f'[name$="[{name}]"]').first

    def fill_form(page, phone):
        field(page, 'email').fill(email)
        field(page, 'prenom').fill('Camille')
        field(page, 'nom').fill('Parcours')
        field(page, 'organisme').fill('INRAE')
        field(page, 'telephone').fill(phone)
        field(page, 'adresse').fill('Lyon')
        field(page, 'categorie').select_option(index=1)

    def refused_then_created(page):
        fill_form(page, '01')
        submit(page)
        assert 'user/new' in page.url and page.locator('.has-error, .help-block, .form-error').count() > 0, 'le téléphone trop court passe'
        fill_form(page, '0102030405')
        submit(page)
        assert '/login' in page.url, f'pas de renvoi à la connexion : {page.url}'
        assert 'e-mail vient de vous' in notice(page) or 'e-mail' in notice(page), f'pas de message : {notice(page)[:200]}'
        assert trip.one(f"SELECT count(*) FROM utilisateur WHERE email = '{email}'") == '1', 'compte absent de la base'

    s.step('inscription : refusée, corrigée, créée', f'/{slug}/user/new', refused_then_created)

    def first_link(page):
        trip.follow_mailed_link(page, email)
        visible(page, 'a[href*="/logout"]')
        assert trip.one(f"SELECT derniereconnection IS NOT NULL FROM utilisateur WHERE email = '{email}'") == 't', 'dernière connexion non notée'
        assert trip.one(f"SELECT confirmationtoken IS NULL OR confirmationtoken = '' FROM utilisateur WHERE email = '{email}'") == 't', 'adresse non confirmée'

    s.step('le lien du courriel connecte', None, first_link)

    def link_only_once(page):
        c.mail_messages()
        link = re.sub(r'^https?://[^/]+', trip.site, c.mailed_link(email))
        other = trip.new_session('nouveau compte, autre navigateur')
        other.page.goto(link, wait_until='networkidle')
        other.page.locator('form input[type=submit], form button[type=submit]').first.click()
        other.page.wait_for_load_state('networkidle')
        assert other.page.locator('a[href*="/logout"]').count() == 0, 'le lien a servi deux fois'
        other.context.close()

    s.step('le même lien ne sert pas deux fois', None, link_only_once)

    def edit_account(page):
        field(page, 'telephone').fill('0607080910')
        submit(page)
        assert trip.one(f"SELECT telephone FROM utilisateur WHERE email = '{email}'") == '0607080910', 'téléphone non enregistré'

    s.step('modifier son compte', f'/{slug}/user/edit', edit_account)

    def ask_access(page):
        before = trip.one(f"""SELECT count(*) FROM besoin b JOIN utilisateur u ON u.id = b.utilisateur_id WHERE u.email = '{email}'""")
        form = page.locator('form[action*="besoin"]')
        for name in ('parametres', 'stations'):
            form.locator(f'select[name*="[{name}]"]').first.select_option(index=0)
        form.locator('input[name$="[duree]"]').fill('12')
        form.locator('textarea[name$="[details]"]').fill('Parcours d’essai')
        page.locator('#checkboxbesoinid').check()
        submit(page, 'form[action*="besoin"]')
        after = trip.one(f"""SELECT count(*) FROM besoin b JOIN utilisateur u ON u.id = b.utilisateur_id WHERE u.email = '{email}'""")
        assert int(after) == int(before) + 1, f'demande non enregistrée : {notice(page)[:200]}'

    s.step('demander un accès aux données fermées', f'/{slug}/besoin/new', ask_access)

    def out_and_back(page):
        with page.expect_navigation(wait_until='networkidle'):
            page.locator('a[href*="/logout"]').first.click()
        assert page.locator('a[href*="/logout"]').count() == 0, 'toujours connecté'
        page.goto(f'{trip.site}/{slug}/login', wait_until='networkidle')
        page.locator('#email').fill(email.upper())  # une adresse saisie autrement retrouve son compte
        time.sleep(1.1)  # deux liens de la même seconde sont le même lien (dev/decisions.md)
        submit(page)
        assert email in notice(page).lower() or 'lien' in notice(page).lower(), f'pas de message : {notice(page)[:200]}'
        trip.follow_mailed_link(page, email)
        visible(page, 'a[href*="/logout"]')

    s.step('se déconnecter, redemander un lien, revenir', None, out_and_back)
    return email, s


# --- Le droit sur une chronique cachée -------------------------------------------------------------------

def closed_series(trip, email, user):
    """Un gestionnaire donne un droit sur une chronique cachée, le compte l'exporte, le droit est retiré."""
    row = trip.rows("""SELECT o.slug, st.code, ch.code, ch.id FROM chronique ch JOIN station st ON st.id = ch.station_id
        JOIN stations_sites ss ON ss.station_id = st.id JOIN siteexperimental si ON si.id = ss.site_id
        JOIN observatoire o ON o.id = si.observatoire_id
        WHERE NOT ch.estvisible AND ch.dtype = 'continue' AND ch.nbmesures BETWEEN 1 AND 20000
        ORDER BY ch.nbmesures DESC, ch.id LIMIT 1""")
    if not row:
        return
    slug, station, code, cid = row[0]
    page_path = f'/{slug}/{station}/{code}'
    manager = trip.one(f"""SELECT u.email FROM utilisateur u JOIN role r ON r.utilisateur_id = u.id JOIN observatoire o ON o.id = r.observatoire_id
        WHERE o.slug = '{slug}' AND r.valeur = 'gestionnaire' AND u.roleadmin IS NULL AND (u.finacces IS NULL OR u.finacces > now()) ORDER BY u.id LIMIT 1""")
    boss = trip.new_session('gestionnaire de la chronique cachée')
    boss.step('connexion', None, lambda page: trip.follow_console_link(page, manager, slug))

    def refused(page):
        response = page.goto(trip.site + page_path, wait_until='networkidle')
        assert response.status == 403, f'statut {response.status}'
        user.problems.clear()

    user.step('sans droit, chronique cachée refusée', None, refused)

    def set_right(page, granted):
        select = page.locator('#form_utilisateurChronique')
        select.evaluate("""(e, a) => { for (const o of e.options) { if (o.value === a.id) { o.selected = a.granted } }
                                       e.dispatchEvent(new Event('change', {bubbles: true})) }""", {'id': cid, 'granted': granted})
        submit(page, 'form[action*="/right/"]')

    right_page = f'/{slug}/user/right/{email}'
    boss.step('donner le droit sur la chronique', right_page, lambda page: set_right(page, True))

    def export_with_terms(page):
        page.goto(trip.site + page_path, wait_until='networkidle')
        page.locator('[data-target^="#export-"]').first.click()
        visible(page, '.modal.in [name=exportFormat]')
        terms = page.locator('.modal.in [name=termsOfUses]')
        assert terms.count() == 1, 'pas de conditions à cocher'
        before = len(c.mail_messages())
        terms.check()
        page.locator('.modal.in .set-min').first.click()
        page.locator('.modal.in .set-max').nth(1).click()
        with page.expect_navigation(wait_until='networkidle', timeout=120000):
            page.locator('.modal.in input[type=submit]').click()
        trip.drain()
        found = None
        for _ in range(40):
            for summary in c.mail_messages():
                if any(to['Address'] == email for to in summary['To']) and 'xport' in summary['Subject']:
                    found = summary
                    break
            if found:
                break
            time.sleep(0.25)
        assert found, f'pas de courriel d’export ({before} avant)'
        message = c.last_mail(email)
        link = re.search(r'https?://[^\s"<]+/mesure/export/\d+/\w+/file', message['HTML'] or message['Text'])
        assert link, 'pas de lien dans le courriel'
        link = re.sub(r'^https?://[^/]+', trip.site, link[0])
        saved = trip.out / 'export-chronique-cachee.zip'
        with page.expect_download() as download:
            page.evaluate('url => { window.location = url }', link)
        download.value.save_as(saved)
        names = zipfile.ZipFile(saved).namelist()
        assert any('ConditionsGeneralesUtilisation' in n for n in names), f'pas de conditions : {names}'
        assert not any('Licence-Ouverte' in n for n in names), f'licence ouverte sur une chronique cachée : {names}'
        user.link = link
        user.problems.clear()

    user.step('avec le droit : la voir, cocher, exporter, recevoir le fichier', None, export_with_terms)
    boss.step('retirer le droit', right_page, lambda page: set_right(page, False))

    def refused_again(page):
        response = page.goto(trip.site + page_path, wait_until='networkidle')
        assert response.status == 403, f'statut {response.status}'
        assert user.link, 'le lien de l’archive n’a pas été reçu'
        response = page.request.get(user.link)
        assert response.status == 403, f'le lien de l’archive répond {response.status}'
        user.problems.clear()

    user.step('droit retiré : la chronique et le lien refusés', None, refused_again)
    boss.context.close()


# --- Ce que fait un gestionnaire --------------------------------------------------------------------------

def manager_work(trip, manager_session, station, code, cid, unit, quality):
    """Un import jusqu'au bout, avec une erreur d'abord ; puis vider une période ; une station créée puis retirée."""
    s, slug, base = manager_session, trip.slug, f'/{trip.slug}/admin'
    header = f'Station;Fuseau;Chronique;Unite\n{station};UTC+01;{code};{unit}\nDateHeure;Valeur;Qualite\n'
    bad = trip.out / 'import-faux.txt'
    bad.write_text(header + f'01/03/2019 12:00:00;abc;{quality}\n')
    good = trip.out / 'import-bon.txt'
    good.write_text(header + ''.join(f'{day:02d}/03/2019 12:00:00;{day}.5;{quality}\n' for day in range(1, 6)))
    count = lambda: int(trip.one(f"SELECT count(*) FROM mesure WHERE chronique_id = {cid} AND date >= '2019-03-01' AND date < '2019-04-01'"))

    def upload(page, path):
        """Étapes 1 et 2 : le fichier et son format, puis la station et le fuseau qu'il annonce."""
        page.locator('select[name="file-format"]').select_option('Bdoh')
        page.locator('input[type=file]').first.set_input_files(str(path))
        page.locator('form[name=main] input[type=submit]').click()
        visible(page, 'form[action*="step3"] input[type=submit]', 60000)
        page.locator('form[action*="step3"] input[type=submit]').click()

    def refused_file(page):
        upload(page, bad)
        page.locator('text=abc').first.wait_for(state='visible', timeout=60000)
        assert count() == 0, 'des mesures écrites malgré l’erreur'

    s.step('import : un fichier faux dit sa ligne, et rien n’est écrit', f'{base}/mesure/import', refused_file)

    def full_import(page):
        upload(page, good)
        visible(page, 'form[action*="step4"] input[type=submit]', 120000)
        page.locator(f'input[type=radio][value=keepExisting][name="importTypes[{cid}]"]').first.check()
        page.locator('form[action*="step4"] input[type=submit]').first.click()
        visible(page, 'table.table.well', 120000)
        cells = page.locator('table.table.well tbody tr').first.locator('td').all_inner_texts()
        assert cells[1].strip() == '5', f'mesures insérées : {cells}'
        assert count() == 5, f'{count()} mesures en base'
        trip.drain()

    s.step('import : jusqu’au bout, cinq mesures écrites', f'{base}/mesure/import', full_import)

    def in_history(page):
        visible(page, '.nav-tabs')
        assert re.search(r'[Ii]mport', page.inner_text('body')), 'import absent de l’historique'

    s.step('l’import est dans l’historique', f'/{slug}/historique/recent', in_history)

    def empty_period(page):
        page.locator('[data-target^="#empty-"]').first.click()
        visible(page, '.modal.in .start, .modal.in form', 20000)
        page.locator('.modal.in .set-min').first.click()  # une borne de la chronique : ses mesures sont supprimées
        page.locator('.modal.in .end').fill('01/04/2019 00:00:00')
        page.locator('.modal.in .end').press('Tab')
        with page.expect_navigation(wait_until='networkidle', timeout=120000):
            page.locator('.modal.in input[type=submit]').click()
        trip.drain()
        assert int(trip.one(f"""SELECT count(*) FROM mesure WHERE chronique_id = {cid} AND date >= '2019-03-01' AND date < '2019-04-01'
                                AND valeur IS NOT NULL""")) == 0, 'les mesures sont toujours là'

    s.step('vider la période importée', f'/{slug}/{station}/{code}', empty_period)

    station_code = f'PARCOURS{int(time.time()) % 100000}'

    def create_station(page):
        page.locator('[name$="[code]"]').first.fill(station_code)
        page.locator('[name$="[nom]"]').first.fill('Station du parcours')
        page.locator('select[name$="[sites][]"]').first.evaluate(
            "e => { e.options[0].selected = true; e.dispatchEvent(new Event('change', {bubbles: true})) }")
        with page.expect_navigation(wait_until='networkidle', timeout=120000):
            page.locator('button[name=btn_create_and_list]').click()
        assert trip.one(f"SELECT count(*) FROM station WHERE code = '{station_code}'") == '1', f'station non créée : {notice(page)[:300]}'

    s.step('créer une station', f'{base}/station/create', create_station)

    def delete_station(page):
        station_id = trip.one(f"SELECT id FROM station WHERE code = '{station_code}'")
        page.goto(f'{trip.site}{base}/station/{station_id}/delete', wait_until='networkidle')
        with page.expect_navigation(wait_until='networkidle', timeout=120000):
            page.locator('form[action*="/delete"] button[type=submit], form[action*="/delete"] input[type=submit]').first.click()
        assert trip.one(f"SELECT count(*) FROM station WHERE code = '{station_code}'") == '0', f'station toujours là : {notice(page)[:300]}'
        assert page.url.split('?')[0].endswith('/station/list'), f'pas de retour à la liste : {page.url}'

    s.step('supprimer cette station', None, delete_station)


def calculations(trip, session_for):
    """Enregistrer une transformation recalcule la chronique ; une conversion se lance depuis sa page."""
    row = trip.rows("""SELECT o.slug, ch.id FROM chronique ch JOIN transformation t ON t.sortie_id = ch.id
        JOIN station st ON st.id = ch.station_id JOIN stations_sites ss ON ss.station_id = st.id
        JOIN siteexperimental si ON si.id = ss.site_id JOIN observatoire o ON o.id = si.observatoire_id
        WHERE ch.dtype = 'calculee' AND ch.estvisible AND ch.nbmesures BETWEEN 1 AND 20000 ORDER BY ch.nbmesures DESC, ch.id LIMIT 1""")
    if row:
        slug, cid = row[0]
        s = session_for(slug)

        def save_transformation(page):
            page.locator('#chroniqueCalculee').select_option(cid)
            visible(page, '#coefficient', 30000)
            page.wait_for_load_state('networkidle')
            page.locator('#coefficient').fill('2')
            page.locator('input[type=submit][value*="Sauvegarder"]').first.click()
            page.wait_for_load_state('networkidle')
            page.wait_for_timeout(1500)
            trip.drain()
            coefficient, computed = trip.rows(f"SELECT facteurmultiplicatif, nbmesures > 0 FROM chronique WHERE id = {cid}")[0]
            assert float(coefficient) == 2, f'coefficient {coefficient}, {notice(page)[:300]}'
            assert computed == 't', 'chronique vide après le calcul'
            assert trip.one(f"""SELECT count(*) FROM jms_jobs WHERE state = 'failed' AND command LIKE '%compute%' AND args::text LIKE '%{cid}%'""") == '0', 'calcul en échec'

        s.step('transformation : changer le coefficient, recalculer', f'/{slug}/admin/transformation', save_transformation)

    row = trip.rows("""SELECT o.slug, ch.id, ch.conversion_id FROM chronique ch JOIN station st ON st.id = ch.station_id
        JOIN stations_sites ss ON ss.station_id = st.id JOIN siteexperimental si ON si.id = ss.site_id
        JOIN observatoire o ON o.id = si.observatoire_id
        WHERE ch.dtype = 'convertie' AND ch.conversion_id IS NOT NULL ORDER BY ch.nbmesures, ch.id LIMIT 1""")
    if row:
        slug, cid, _ = row[0]
        s = session_for(slug)

        def show_conversion(page):
            page.locator('#chroniqueConvertieId').select_option(cid)
            page.wait_for_load_state('networkidle')
            visible(page, '#genealogieId', 30000)
            assert page.locator('#paramConversionId').input_value() != '', 'paramètre de conversion vide'

        s.step('conversion : choisir une chronique, voir son paramètre', f'/{slug}/admin/conversion', show_conversion)


def control_points(trip, manager_session, station, code, cid, unit, quality):
    """Des points de contrôle se déposent par la page d'import."""
    s, slug = manager_session, trip.slug
    file = trip.out / 'points.txt'
    file.write_text(f'Station;Fuseau;Chronique;Unite\n{station};UTC+01;{code};{unit}\nDateHeure;Valeur;Qualite\n01/03/2018 12:00:00;1.5;{quality}\n')

    def import_points(page):
        before = int(trip.one(f'SELECT count(*) FROM pointcontrole WHERE chronique_id = {cid}'))
        page.locator('input[type=file]').first.set_input_files(str(file))
        page.locator('form[action*="controle/import"] input[type=submit], form[name=main] input[type=submit]').first.click()
        page.wait_for_load_state('networkidle')
        page.wait_for_timeout(1500)
        for _ in range(2):  # l'étape suivante, si la page la propose
            button = page.locator('form[action*="step3"] input[type=submit]')
            if button.count() and button.first.is_visible():
                radio = page.locator('input[type=radio][value=import]')
                if radio.count():
                    radio.first.check()
                button.first.click()
                page.wait_for_load_state('networkidle')
                page.wait_for_timeout(1500)
        after = int(trip.one(f'SELECT count(*) FROM pointcontrole WHERE chronique_id = {cid}'))
        assert after > before, f'{before} points avant, {after} après : {page.inner_text("body")[:400]}'

    s.step('points de contrôle : importés', f'/{slug}/admin/controle/import', import_points)


def jobs_page(trip, admin_session):
    """Les pages de suivi des tâches se lisent, et une tâche se suit jusqu'à sa page."""
    s, slug = admin_session, trip.slug

    def read_jobs(page):
        visible(page, 'table tbody tr')
        page.locator('table tbody tr a[href*="/jobs/"]').first.click()
        page.wait_for_load_state('networkidle')
        assert re.search(r'/jobs/\d+', page.url), f'pas de page de tâche : {page.url}'

    s.step('tâches : la liste, puis une tâche', f'/{slug}/jobs/', read_jobs)
