"""Les écarts admis entre l'ancien BDOH et le nouveau, sous une forme que compare.py lit.

Un écart qu'aucune règle n'explique reste « différents », et fait échouer le mode. Une règle vise des cas (une
expression sur tout le nom du cas) et renvoie, par `why`, au titre de dev/differences.md qui dit pourquoi : un
test vérifie que ce titre existe. Elle admet :

- `lines` : des lignes de diff, signe compris. Un texte se compare tel quel, une expression (`RX`) doit couvrir
  toute la ligne. Toutes les lignes ajoutées ou retirées doivent en être.
- `masks` : (expression, remplacement), appliqués aux lignes des deux côtés avant de comparer, pour admettre un
  champ d'une ligne d'état et rien d'autre de cette ligne.
- `must` : des lignes de diff qui doivent s'y trouver, sans quoi l'écart n'est pas celui qu'on admet.
- `order` : les mêmes lignes dans un autre ordre. Sans cette règle, un autre ordre compte pour un écart.
- `rewrite` : une fonction qui réécrit les lignes de l'ancien comme le nouveau doit les montrer, avant tout le reste :
  pour un écart qui déplace des lignes, qu'une liste de lignes admettrait aussi perdues. Ce qu'elle ne reconnaît pas,
  elle le rend tel quel, et l'écart se voit.

Sans donnée : ni valeur de mesure, ni compte réel (les comptes cités sont ceux que créent les modes). Ce qui dépend
des identifiants d'une base ne s'écrit pas ici en dur : `qjo_rules`, `account_rules` et `admin_rules` reçoivent ceux
de la passe, si bien que les règles valent sur bdoh_small comme sur OTHU (dev/decisions.md, « Un cas sans objet se
dit à part »).
"""

import re
from dataclasses import dataclass

RX = re.compile
ROLE = r'\S+(?: technique)?'
OBS = r'/[A-Z][A-Z0-9-]*'


@dataclass(frozen=True)
class Rule:
    mode: str
    case: str
    why: str
    lines: tuple = ()
    masks: tuple = ()
    must: tuple = ()
    order: bool = False
    rewrite: object = None


def replaced(mode, case, why, status, *lines):
    """Une page que le nouveau remplace par une autre réponse : tout ce que montrait l'ancien part, le statut
    est bien celui-là, et le nouveau n'ajoute que ces lignes."""
    return Rule(mode, case, why, lines=(RX(r'-.*'), f'+statut {status}', *lines), must=(f'+statut {status}',))


OPEN = 'Ouverture des données'
LINK = 'Connexion par lien, sans mot de passe'
CONTACT = 'Adresse de contact'
HOMES = 'Accueils autour de la carte'
GRAPH = "Graphique d'une chronique"
OLD_TERMS = "Pour pouvoir télécharger des données il faudra, une fois connecté, faire des demandes d'accès spécifiques sur les observatoires qui vous intéressent via le formulaire de demande d'accès aux données ou contacter un gestionnaire"
NEW_TERMS = "Les données ouvertes se téléchargent sans compte. Pour accéder aux données fermées, il faudra, une fois connecté, faire des demandes d'accès spécifiques sur les observatoires qui vous intéressent via le formulaire de demande d'accès aux données ou contacter un gestionnaire"
RIGHTS_NOTICE = "Conformément aux articles 39 et 40 de la loi Informatique et Libertés modifiée par la loi n° 2004-801 du 6 août 2004 relative à la protection des personnes physiques à l'égard des traitements de données à caractère personnel, vous disposez d'un droit d'accès et de rectification des données vous concernant. Pour toute demande de rectification veuillez vous adresser à : "

# La page de connexion, que montre aussi l'inscription réussie
LOGIN_PAGE = (
    "+T Saisissez l'adresse e-mail de votre compte : BDOH vous y envoie un lien de connexion, valable 15 minutes. Il n'y a plus de mot de passe.",
    RX(rf'-F post {OBS}/login_check'), RX(rf'\+F post {OBS}/login'),
    '+I input hidden _csrf_token <jeton>',
    '-I input text _username', '-T Mot de passe', '-I input password _password', '-I input submit Se connecter',
    '+I input text email', '+I input submit Recevoir un lien de connexion',
    RX(rf'-A {OBS}/resetting/request'), '-T Mot de passe oublié',
    RX(rf'\+A {OBS}/user/new'), '+T Pas encore de compte ? Créer un compte',
)
# Le formulaire d'inscription
NEW_ACCOUNT = (
    f"-T Le compte que vous allez créer sera valable sur toute l'application BDOH. {OLD_TERMS}.",
    f"+T Le compte que vous allez créer sera valable sur toute l'application BDOH. {NEW_TERMS}.",
    "-T L'adresse e-mail sera votre identifiant de connexion.",
    "+T L'adresse e-mail sera votre identifiant de connexion : vous vous connecterez par un lien envoyé à cette adresse, sans mot de passe.",
    '-T Mot de passe', '-I input password irstea_bdohsecuritybundle_newutilisateurtype[password][main]',
    '-T Répétez le mot de passe', '-I input password irstea_bdohsecuritybundle_newutilisateurtype[password][repeat]',
    '-T Protection anti-robots', '-T Veuillez recopier les caractères suivants', '-G <image>',
    '-I input text irstea_bdohsecuritybundle_newutilisateurtype[captcha]',
)
# Le formulaire de modification du compte
EDIT_ACCOUNT = (
    "+T Changer d'adresse e-mail vous déconnecte : vous vous reconnecterez par un lien envoyé à la nouvelle adresse.",
    '-T Si vous desirez changer votre mot de passe remplissez les 3 champs suivants',
    '-T Mot de passe actuel', '-I input password irstea_bdohsecuritybundle_editutilisateurtype[oldPassword]',
    '-T Nouveau mot de passe', '-I input password irstea_bdohsecuritybundle_editutilisateurtype[password][main]',
    '-T Répétez le mot de passe', '-I input password irstea_bdohsecuritybundle_editutilisateurtype[password][repeat]',
)

# Les traductions pour le JavaScript : les clés retirées, ajoutées et changées, une à une
GONE_KEYS = """Bad credentials. | User account has expired. | consult.help.chronique.selectionOfDatesRange | Utilisateur.newPassword.mandatory | Utilisateur.oldPassword.mandatory
    Utilisateur.password.badCurrent | Utilisateur.password.badRepetition | captcha | captcha.info | captcha.security
    export.exportMail.body | newPassword | newPasswordRepeat | oldPassword | password | resetting.check_email
    resetting.email.message | resetting.email.subject | resetting.flash.success | resetting.isCompleted
    resetting.password_already_requested | resetting.request.email | resetting.request.invalid_email
    resetting.request.submit | resetting.reset.submit | security.login_form.lostPassword | security.login_form.password
    security.utilisateur.edit.info.changePassword | security.utilisateur.newAccountMail.body
    security.utilisateur.newAccountMail.title"""
NEW_KEYS = """Error.file.cantWrite(%file%) | dateMonthShort | oneMonth | admin.sendJson.missingCredentials | export.exportMail.body(%link%, %days%)
    legal.freeSoftware | legal.sourceCode | map.onMap | map.seeStation | map.sites | map.title | map.withoutPosition | map.zoomHint | overview.hint | overview.title | measure.export.busy | measure.export.licence | measure.export.status.download
    measure.export.status.expired | measure.export.status.failed | measure.export.status.finished(%days%)
    measure.export.status.running | measure.export.status.title | measure.export.status.waiting | measure.export.tooHeavy
    measure.export.tooMany | security.login_check.info(%email%) | security.login_check.submit
    security.login_form.createAccount | security.login_form.info | security.login_form.noAccount
    security.login_link.invalidForm | security.login_link.mail.body | security.login_link.mail.footer
    security.login_link.mail.hello | security.login_link.mail.notYou | security.login_link.mail.title
    security.login_link.sent(%email%) | security.tooManyRequests | security.utilisateur.edit.emailChanged(%email%)
    security.utilisateur.edit.info.changeEmail | security.utilisateur.newMailUtilisateur.body.confirm
    security.utilisateur.newMailUtilisateur.body.confirmLater | readLess | readMore
    favorite.menu | favorite.title | favorite.add | favorite.remove | favorite.intro | favorite.empty
    favorite.checkAll | favorite.export | favorite.labels.title | favorite.labels.hint | favorite.labels.none
    favorite.labels.name | favorite.labels.add | favorite.labels.remove | favorite.removeChecked
    favorite.done.labelAdded(%label%) | favorite.done.labelRemoved(%label%) | favorite.done.removed
    favorite.error.nothingChecked | favorite.error.noLabel | favorite.error.invalidForm
    favorite.exportModal.notAllowed | favorite.exportModal.askAccess | favorite.exportModal.withoutMeasures
    favorite.exportModal.nothing"""
CHANGED_KEYS = """accueilMessage | measure.export.help-content | measure.export.job_queued(%job_id%) | measure.export.underway
    security.besoin.create.title | security.login_form.connectTo | security.utilisateur.create.info.isTheFirstStep
    security.utilisateur.create.info.yourIdentifier | security.utilisateur.create.isCompleted
    security.utilisateur.newMailUtilisateur.body.step2 | security.utilisateur.newMailUtilisateur.body.step4
    technicalContact | utilisateur.newAccountMail.body"""


def NO_MANAGER_MAIL(lines):
    """Les courriels de l'ancien sans celui qui prévenait les gestionnaires d'une inscription (« nouvel utilisateur »),
    que le nouveau n'envoie plus (2026-11-12, Lou) : le reste doit être le même."""
    out, skip = [], False
    for line in lines:
        if line.startswith('Sujet : '):
            skip = line == 'Sujet : BDOH : nouvel utilisateur'
        if not skip:
            out.append(line)
    return out


# L'astuce de saisie des dates, qui passe de la page à la bulle des champs (2026-11-12)
DATE_TIP = ("-T Astuce : La date peut s'écrire en tapant seulement les chiffres (exemple : 130495 -> 13/04/1995). Si vous écrivez 1304 l'année en cours sera ajoutée automatiquement.",
            '-T Tip : You can write the date with the number(example : 130495 -> 13/04/1995). If you write 1304 the current year will be automatically add')

# La ligne d'état du graphique (2026-11-12, Lou) : l'aide du double-clic rejoint les aides sous la bande, que le
# JavaScript écrit, et l'avis du pas des cumuls tient en une phrase
STATUS_LINE = ('-T Pour revenir au niveau de zoom initial, double-cliquez sur le graphe.',
               "-T Pour changer le pas de temps de l'histogramme veuillez modifier les dates de début et fin sélectionnées ci-dessus.",
               '+T Le pas des cumuls suit la durée de la période.')

# L'avertissement de la limite de 5 ans, retiré avec elle (2026-11-12)
FIVE_YEARS = ("-T Attention : pour des raisons de performance vous ne pouvez sélectionner qu'un intervalle de dates de 5 ans maximum.",
              '-T Warning: due to performance limitations please select a maximum time span of 5 years')

def translation(sign, keys):
    """La ligne d'une de ces clés dans /translations/messages.js, quel qu'en soit le texte."""
    names = [re.escape(key.strip()) for key in re.split(r'\||\n', keys) if key.strip()]
    return RX(rf'{sign}t\.add\("(?:{"|".join(names)})", ".*", "messages", "\w+"\);')


HEADER = "En-tête, fil d'Ariane et pied de page"
LANG_FLAG = re.compile(r'G /images/i18n/\w+\.png')
DATA_LINK = {'T Consultation des données': 'T Recherche avancée', 'T See our data': 'T Advanced search'}
CRUMB_HOME = ('T Accueil', 'T Home')
OBS_TITLE = re.compile(r'T «\s*(.+?)\s*» (Bienvenue|Welcome)')


def header(lines):
    """L'en-tête d'une page de l'ancien, et le début de son fil d'Ariane, comme le nouveau doit les montrer
    (2026-11-12, Lou) : « BDOH », qui mène au portail, à la place du logo de l'observatoire (dont le lien passe à côté
    du titre de son accueil) ; dans la barre du haut, le choix d'un observatoire, l'observatoire de la page, l'aide et
    la langue ; dans celle du dessous, « Recherche avancée » (l'ancien « Consultation des données ») puis le compte ;
    le fil commence par « BDOH » puis l'observatoire, au lieu de « Accueil », et toute page en a un. Les mêmes liens,
    déplacés : rien ne s'ajoute ni ne se perd qu'ici."""
    def at(k, *wanted):
        return all(0 <= k + i < len(lines) and (w(lines[k + i]) if callable(w) else lines[k + i] == w)
                   for i, w in enumerate(wanted))
    is_flag = LANG_FLAG.fullmatch
    lang = next((k for k in range(len(lines)) if at(k, 'A #', 'D a data-toggle=dropdown', is_flag)), None)
    if lang is None or not at(lang + 3, lambda l: l.startswith('T '), lambda l: l.startswith('A /select_lang/'), is_flag,
                              lambda l: l.startswith('T ')):
        return lines
    # Avant la langue, de la fin vers le début : l'aide, la consultation des données, l'observatoire, puis le logo
    k, found = lang, {}
    for part, link, text in (('contact', rf'A {OBS}/contact', r'T .+'),
                             ('data', rf'A {OBS}/nos-donnees', '|'.join(map(re.escape, DATA_LINK))),
                             ('current', rf'A {OBS}/home', r'T .+')):
        if k >= 2 and re.fullmatch(link, lines[k - 2]) and re.fullmatch(text, lines[k - 1]):
            k -= 2
            found[part] = lines[k:k + 2]
    if k < 1 or not lines[k - 1].startswith('G '):
        return lines
    start = k - 2 if k >= 2 and lines[k - 2].startswith('A ') else k - 1
    site = lines[start][2:] if start == k - 2 else None
    # Après la langue : le compte, puis le choix d'un observatoire et la liste des observatoires
    everything = next((m for m in range(lang + 7, len(lines)) if lines[m] == 'A /?_observatoire=ALL'), None)
    if everything is None or not at(everything - 3, 'A #', 'D a data-toggle=dropdown'):
        return lines
    end = everything + 2
    while at(end, lambda l: re.fullmatch(rf'A {OBS}/home', l), lambda l: l.startswith('T ') and l not in CRUMB_HOME):
        end += 2
    current = found.get('current')
    if current and at(end, current[0], lambda l: l in CRUMB_HOME):
        crumb, after = ['A /', 'T BDOH', *current], end + 2
    elif current and at(end, lambda l: l in CRUMB_HOME):
        crumb, after = ['A /', 'T BDOH', current[1]], end + 1
    else:
        crumb, after = (['A /', 'T BDOH', *current] if current else ['T BDOH']), end
    data = found.get('data')
    rest = lines[after:]
    # Le titre de l'accueil d'un observatoire : sans guillemets, avec le lien vers son site
    if current:
        title = next((i for i, l in enumerate(rest) if (m := OBS_TITLE.fullmatch(l)) and f'T {m.group(1)}' == current[1]), None)
        if title is not None:
            m = OBS_TITLE.fullmatch(rest[title])
            outward = site and site != 'http://'
            label = " Site de l'observatoire" if outward else ''
            rest = [*rest[:title], *([f'A {site}'] if outward else []),
                    f'T {m.group(1)}{label} {m.group(2)}', *rest[title + 1:]]
    return [*lines[:start], 'A /', 'T BDOH', *lines[everything - 3:end], *(current or []), *found.get('contact', []),
            *lines[lang:lang + 7], *([data[0], DATA_LINK[data[1]]] if data else []), *lines[lang + 7:everything - 3],
            *crumb, *rest]


FAVORITES = 'Favoris et étiquettes'
# L'entrée du menu du compte, sur toute page d'un compte connecté, et l'étoile d'une chronique, dans les pages de
# consultation (dev/plans/2026-11-12_favorites.md) ; le reste se vérifie à part (« nouveau seul : favoris » dans accounts)
FAVORITE_MENU = ('+A /favorites', '+T Mes favoris', '+T My favourites')
FAVORITE_STAR = ('+I button button', RX(
    rf'\+D button data-favorite={OBS}/favorites/\d+ data-favorite-on=[01] data-favorite-token=<jeton> '
    r'data-title-off=(?:Ajouter aux favoris|Add to favourites) data-title-on=(?:Retirer des favoris|Remove from favourites)'))

PORTAL = 'Connexion et inscription depuis le portail'
PORTAL_GUEST = ('+A /login', '+T Se connecter', '+T ou', '+A /user/new', '+T Créer un compte')


def portal_rules(names):
    """Le compte, dans la seconde barre du portail, que l'ancien n'y montrait pas : les liens de connexion et
    d'inscription, ou, pour un compte connecté, son nom et la déconnexion. `run_pages` les ajoute avec le nom de chaque
    compte de la passe ({profil: « Prénom Nom »}) : rien d'autre ne s'ajoute à son en-tête. La page d'erreur d'une
    adresse inconnue, sans observatoire, ne montre pas le compte, comme l'ancien."""
    rules = [Rule('pages', r'anonyme /', PORTAL, lines=PORTAL_GUEST, must=PORTAL_GUEST)]
    for profile, name in names.items():
        account = (f'+T {name}', '+A /logout', '+T Déconnexion')
        rules.append(Rule('pages', rf'{re.escape(profile)} /', PORTAL, lines=account, must=account))
    return rules


QJO_DAYS = "Jours manquants d'un fichier QJO"


def qjo_rules(chroniques):
    """L'état des chroniques où les imports QJO écrivent, et de leurs filles calculées : les cas « QJO » de `imports`
    y changent volontairement des mesures. `run_imports` les ajoute avec les identifiants de sa passe (la 9 et sa fille
    936 sur bdoh_small). Tous les imports de ce mode écrivent sur cette chronique : ces règles y cacheraient une vraie
    faute, sans « seules ces mesures diffèrent de l'ancien » et « chronique fille », du même mode, qui les comparent
    ligne à ligne."""
    ids = '|'.join(chroniques)
    return [
        Rule('imports', 'état mesure', QJO_DAYS, masks=((rf'^(mesure\t(?:{ids})\t)\d+\t\d+$', r'\1<QJO>'),)),
        Rule('imports', 'état tauxremplissage', QJO_DAYS, masks=((rf'^(tauxremplissage\t(?:{ids})\t\d+\t)\d+$', r'\1<QJO>'),)),
        Rule('imports', 'état chronique', QJO_DAYS, masks=((rf'^(\{{"id": (?:{ids}), .*"nbmesures": )\d+', r'\1<QJO>'),)),
    ]


def account_rules(created):
    """Les comptes que crée `accounts`, aux identifiants de la passe (800 à 802 sur bdoh_small, 20 à 22 sur OTHU) : le
    compte d'essai, puis les comptes d'administration créés en console avec « y » et « non ». `run_accounts` les ajoute.
    Le nouveau ne leur donne plus de mot de passe ; l'ancien donnait au dernier les droits de gestionnaire que seul
    « y » doit donner."""
    ids = '|'.join(created)
    return [
        Rule('accounts', 'utilisateur', LINK, masks=((rf'^(\{{"id": (?:{ids}), .*"mot de passe": )(?:true|false)', r'\1<lien>'),)),
        Rule('accounts', 'role', "Création d'un compte d'administration en ligne de commande", lines=(
            RX(r'-\{"dtype": "(?:chronique|site|observatoire)", "valeur": "gestionnaire", "site_id": (?:\d+|null), '
               rf'"chronique_id": (?:\d+|null), "utilisateur_id": {created[-1]}, "observatoire_id": (?:\d+|null)\}}'),)),
    ]


def admin_rules(created, technical, stations_on_first_page):
    """Ce que l'administration admet selon la base, avec ses identifiants (`run_admin` les ajoute) : le compte qu'elle
    crée, sans mot de passe dans le nouveau (800 sur bdoh_small) ; pour une base qui a ses propres comptes techniques
    (OTHU), les liens de modification de ces comptes, que le nouveau ne montre plus à un administrateur ; et, quand la
    station créée tient sur la première page de la liste (32 stations, OTHU en a 25), son altitude, que l'ancien
    arrondissait à 3 décimales."""
    rules = [Rule('admin', 'état utilisateur', LINK,
                  masks=((rf'^(\{{"id": {created}, .*"mot de passe": )(?:true|false)', r'\1<lien>'),))]
    if technical:
        ids = '|'.join(technical)
        rules.append(Rule('admin', rf'administrateur {OBS}/admin/(?:utilisateur|personnetheia)/list'
                                   r'|(?:utilisateur|personnetheia) : (?:création|suppression)',
                          "Modifier le compte d'un autre, réservé aux administrateurs",
                          lines=(RX(rf'-A {OBS}/admin/utilisateur/(?:{ids})/edit(?:\?.*)?'),)))
    if stations_on_first_page:
        rules.append(Rule('admin', 'station : création', 'Seuils de validité et coordonnées arrondis',
                          lines=('-T 123.457', '+T 123.4567')))
    return rules


RULES = [
    # L'en-tête et le début du fil d'Ariane, sur toute page du site, quel que soit le mode qui la compare : header()
    # les réécrit dans l'ancien, et la page doit alors être la même, sauf ce que d'autres règles admettent
    *(Rule(mode, r'.*', HEADER, rewrite=header) for mode in ('pages', 'admin', 'imports', 'accounts')),
    *(Rule(mode, r'(?!anonyme ).*', FAVORITES, lines=FAVORITE_MENU) for mode in ('pages', 'admin', 'imports', 'accounts')),
    Rule('pages', r'(?!anonyme ).*', FAVORITES, lines=FAVORITE_STAR),

    # --- pages -------------------------------------------------------------------------------------
    # La carte des stations, sur l'accueil du portail et sur celui d'un observatoire ; son contenu se vérifie à part
    # (« nouveau seul : carte des stations » dans le même mode)
    # (« /INCONNU/ » est l'observatoire inventé du cas 404, qui n'a pas d'accueil)
    Rule('pages', rf'{ROLE} (?:(?!/INCONNU/){OBS}/(?:home)?|/)', 'Carte des stations', lines=(
        '+L stylesheet /bundles/irsteabdohconsult/vendor/leaflet/leaflet.css',
        '+S /bundles/irsteabdohconsult/vendor/leaflet/leaflet.js', '+S /bundles/irsteabdohconsult/js/stations-map.js',
        RX(r'\+T (?:Carte des stations|Map of the stations)'),
        RX(r'\+D div (?:data-fit-france= )?data-see-station=(?:Voir la station|See the station) data-stations=\[.*\]'
           r' data-zoom-hint=(?:Ctrl \+ molette, ou pincer, pour zoomer|Ctrl \+ wheel, or pinch, to zoom)'),
        RX(r'\+T (?:Stations sans position, absentes de la carte : |Stations without a position, missing from the map: )\d+\.')),
        must=(RX(r'\+T (?:Carte des stations|Map of the stations)'),)),
    # Les accueils organisés autour de la carte (2026-11-11). Le portail perd ses onglets au survol et les aperçus
    # cachés des observatoires, qui redisent leur page : tout ce qu'il montrait part, et il n'ajoute que la carte,
    # le titre « Observatoires » et une fiche par observatoire, que « nouveau seul : fiches des observatoires »
    # vérifie une à une, avec la présentation, qui doit rester. Un observatoire garde son contenu : s'y ajoutent les
    # boutons des sites sous la carte, et ses photos passent avant les listes.
    Rule('pages', rf'{ROLE} /', HOMES, lines=(
        RX(r'-.*'), '+L stylesheet /bundles/irsteabdohconsult/css/homes.css', RX(r'\+T Observato(?:ires|ries)'),
        RX(rf'\+A {OBS}/home'), RX(r'\+D a data-map-observatoire=.+'), RX(r'\+G /uploads/\S+'), RX(r'\+T .+')),
        must=('-S /assets/home.js', RX(r'\+D a data-map-observatoire=.+'))),
    Rule('pages', rf'{ROLE} (?!/INCONNU/){OBS}/(?:home)?', HOMES, lines=(
        '+L stylesheet /bundles/irsteabdohconsult/css/homes.css', '+S /bundles/irsteabdohconsult/js/read-more.js',
        RX(r'\+I button button(?: disabled)?'), RX(r'\+D button data-map-site=\d+'), RX(r'\+T Sites ?: .+'),
        RX(r'\+D li data-map-site=\d+'),
        RX(r'\+D div data-read-less=(?:Réduire|Show less) data-read-more=(?:Lire la suite|Read more)'),
        RX(r'[-+]D div data-interval=false data-ride=carousel'), RX(r'[-+]G /uploads/\S+ .+'), RX(r'[-+]A #photos'),
        RX(r'[-+]D a data-slide=(?:prev|next)')),
        must=('+S /bundles/irsteabdohconsult/js/read-more.js',)),
    # La bande de toute la chronique, sous son graphique : un élément de plus sur la page d'une chronique, que le
    # JavaScript remplit (« nouveau seul : bande de toute la chronique » vérifie sa route)
    Rule('pages', rf'{ROLE} {OBS}/[^/ ]+/[^/ ]+', GRAPH, lines=(
        RX(rf'\+D div data-hint=.+ data-title=.+ data-url={OBS}/chronique/\d+/overview'), *FIVE_YEARS, *DATE_TIP,
        *STATUS_LINE)),
    # La limite de 5 ans du choix des dates part avec son avertissement, sur la page d'une chronique et dans le visualiseur
    Rule('pages', rf'{ROLE} {OBS}/viewer', GRAPH, lines=(*FIVE_YEARS, *DATE_TIP), must=FIVE_YEARS[:1]),
    # L'astuce de saisie des dates passe dans la bulle des champs, sur la recherche avancée aussi
    Rule('pages', rf'{ROLE} {OBS}/nos-donnees', GRAPH, lines=DATE_TIP),
    Rule('pages', rf'{ROLE} {OBS}/contact', CONTACT, lines=(
        '-A mailto:bdoh.support@lists.irstea.fr', '+A mailto:bdoh.support@listes.inrae.fr',
        "-T Pour les demandes techniques concernant l'utilisation de BDOH veuillez contacter bdoh.support@lists.irstea.fr.",
        "+T Pour les demandes techniques concernant l'utilisation de BDOH veuillez contacter bdoh.support@listes.inrae.fr.")),
    # Une chronique visible montre ses points de contrôle à tous. Une cachée répond 403 sans droit, ce que vérifie
    # « nouveau seul : ouverture », de `state` : cette règle ne distingue pas les deux.
    Rule('pages', rf'(?:anonyme|utilisateur|utilisateur-chronique) {OBS}/[^/ ]+/[^/ ]+', OPEN, lines=(
        '+T Points de contrôle (?) : Aucun', RX(r'\+T Points de contrôle \(\?\) : \d+ points?'),
        RX(r'\+T (?:du|au) \d\d/\d\d/\d{4} \d\d:\d\d:\d\d UTC'))),
    Rule('pages', rf'{ROLE} {OBS}/login', LINK, lines=LOGIN_PAGE),
    Rule('pages', rf'{ROLE} {OBS}/user/new', OPEN, lines=NEW_ACCOUNT),
    Rule('pages', rf'{ROLE} {OBS}/user/edit', LINK, lines=EDIT_ACCOUNT),
    replaced('pages', rf'{ROLE} {OBS}/resetting/(?:request|check-email)', LINK, 301,
             RX(rf'\+redirection {OBS}/login'), RX(rf'\+T Redirecting to {OBS}/login\.?'), RX(rf'\+A {OBS}/login')),
    Rule('pages', rf'{ROLE} {OBS}/besoin/new', OPEN, lines=(
        RX(r"-T BDOH - .* - Demande d'accès aux données"), RX(r"\+T BDOH - .* - Demande d'accès aux données fermées"),
        "-T Demande d'accès aux données", "+T Demande d'accès aux données fermées")),
    replaced('pages', rf'{ROLE} /select_lang/(?:en|fr)', 'Erreurs 500 sur des pages publiques', 302,
             '+redirection /', '+T Redirecting to /', '+T Redirecting to /.', '+A /'),
    Rule('pages', rf'{ROLE} /', OPEN, lines=(
        RX(r"-T Ces données sont utilisées à des fins scientifiques .* Les données sont accessibles à tous gratuitement moyennant une inscription sur le site et le respect des conditions d'utilisation\."),
        RX(r"\+T Ces données sont utilisées à des fins scientifiques .*\.Les données ouvertes sont accessibles à tous, gratuitement et sans inscription, sous Licence Ouverte Etalab ; les données fermées le sont sur demande d'accès\."))),
    Rule('pages', rf'{ROLE} /translations/messages\.js', OPEN,
         lines=(translation('-', GONE_KEYS), translation(r'\+', NEW_KEYS), translation('[-+]', CHANGED_KEYS))),
    # Les clés de l'en-tête : « Consultation des données » devenu « Recherche avancée », le titre du portail sans
    # guillemets, le lien vers le site d'un observatoire
    Rule('pages', rf'{ROLE} /translations/messages\.js', HEADER, lines=(
        translation('-', 'dataAccess'), translation('[-+]', 'home.title'), translation(r'\+', 'observatoryWebsite'))),
    # Les textes de la ligne d'état du graphique
    Rule('pages', rf'{ROLE} /translations/messages\.js', GRAPH, lines=(
        translation('-', 'consult.help.chronique.double-clic-unzoom'),
        translation('[-+]', 'consult.help.chronique.time-step-depends-on-span'))),
    # Le courriel de bienvenue d'un compte créé depuis le portail
    Rule('pages', rf'{ROLE} /translations/messages\.js', PORTAL,
         lines=(translation(r'\+', 'security.utilisateur.newMailUtilisateur.body.portal'),)),
    Rule('pages', rf'{ROLE} {OBS}/dataset/[^/ ]+', 'Listes sans tri', order=True),
    Rule('pages', rf'{ROLE} {OBS}/nos-donnees', 'Listes sans tri', order=True),

    # --- exports -----------------------------------------------------------------------------------
    # Une chronique visible porte la licence ouverte, à la place des conditions. Une cachée, identique, n'a pas d'écart.
    Rule('exports', r'bdoh:export:.*', OPEN, lines=(
        RX(r'-# Ignoring (?:ORACLE-)?ConditionsGeneralesUtilisation\.pdf'),
        '+# Ignoring Licence-Ouverte-2.0-fr.pdf', '+# Ignoring Open-Licence-2.0-en.pdf', '+'),
        must=('+# Ignoring Licence-Ouverte-2.0-fr.pdf', '+# Ignoring Open-Licence-2.0-en.pdf')),

    # --- imports -----------------------------------------------------------------------------------
    Rule('imports', 'import station écrite en HTML', 'Corrections de sécurité', lines=(
        '-T Le code « INCONNUE » ne correspond à aucune station de cet observatoire',
        '+T Le code « <b>INCONNUE</b> » ne correspond à aucune station de cet observatoire')),
    Rule('imports', 'import valeur écrite en HTML', 'Corrections de sécurité', lines=(
        RX(r'-T Ligne 5 \(date = « [^»]* », valeur = « abc »\) : la valeur n.est pas de type numérique'),
        RX(r'\+T Ligne 5 \(date = « [^»]* », valeur = « <i>abc</i> »\) : la valeur n.est pas de type numérique'))),
    Rule('imports', 'import QJO, trou de plus d’un mois', QJO_DAYS, lines=('-T 15', '+T 46')),
    Rule('imports', 'import QJO, lignes dans le désordre', QJO_DAYS, lines=(
        '-T Fin : 11/06/2021 23:00:00', '+T Fin : 31/05/2021 23:00:00', '-T 13', '+T 2')),
    replaced('imports', 'import barème, sans fichier', "Import d'un barème sans fichier", 200,
             '+action : "errors"', '+view :', '+T Format « Barème » :', '+T Erreurs :', "+T Le fichier n'a pu être chargé",
             '+T Nouvel import', '+T Fermer', '+A javascript:void(0);', '+D button data-dismiss=modal', '+I button button'),
    replaced('imports', 'import barème, fichier PDF', "Import d'un barème sans fichier", 200,
             '+action : "errors"', '+view :', '+T Format « Barème » :', '+T Erreurs :',
             '+T Le type MIME du fichier (« application/pdf ») est invalide',
             '+T Nouvel import', '+T Fermer', '+A javascript:void(0);', '+D button data-dismiss=modal', '+I button button'),
    # L'état des chroniques des imports QJO : qjo_rules, avec les identifiants de la passe.
    # Les deux lignes de l'historique des imports QJO qui changent : leur fin, et leur nombre de mesures
    Rule('imports', 'état historique', QJO_DAYS, masks=(
        (r'^(?=.*"debut": "(?:2022-02-28|2021-05-19)T23:00:00")(.*"nombremesures": )\d+', r'\1<QJO>'),
        (r'^(?=.*"debut": "(?:2022-02-28|2021-05-19)T23:00:00")(.*"fin": )"[^"]+"', r'\1<QJO>'),
        (r'^(?=.*"debut": "(?:2022-02-28|2021-05-19)T23:00:00")(.*Mesures : )\d+', r'\1<QJO>'),
        (r'^(?=.*"debut": "(?:2022-02-28|2021-05-19)T23:00:00")(.*Fin : )\d\d/\d\d/\d{4} \d\d:\d\d:\d\d', r'\1<QJO>'),
    )),

    # --- accounts ----------------------------------------------------------------------------------
    Rule('accounts', 'inscription, (?:téléphone trop court|adresse sans MX|adresse mal formée|adresse déjà prise|nom manquant)',
         OPEN, lines=NEW_ACCOUNT),
    Rule('accounts', 'inscription, valide', LINK, lines=(
        *LOGIN_PAGE,
        '-D div data-notices={"success":["Votre compte BDOH a bien \\u00e9t\\u00e9 cr\\u00e9\\u00e9. Vous pouvez d\\u00e9sormais vous connecter."]}',
        '+D div data-notices={"success":["Votre compte BDOH a bien \\u00e9t\\u00e9 cr\\u00e9\\u00e9. Un e-mail vient de vous \\u00eatre envoy\\u00e9 : suivez le lien qu\'il contient pour confirmer votre adresse et vous connecter."]}')),
    Rule('accounts', 'modification du compte, téléphone trop court', LINK, lines=EDIT_ACCOUNT),
    Rule('accounts', 'courriels', LINK, rewrite=NO_MANAGER_MAIL),
    Rule('accounts', 'courriels', LINK, lines=(
        f'-{OLD_TERMS} :', f'-T {OLD_TERMS} :', f'+{NEW_TERMS} :', f'+T {NEW_TERMS} :',
        '+Pour confirmer votre adresse et vous connecter, suivez ce lien, valable 15 minutes et une seule fois : <lien de connexion>',
        '+T Pour confirmer votre adresse et vous connecter, suivez ce lien, valable 15 minutes et une seule fois : <lien de connexion>',
        '+Passé ce délai, demandez un nouveau lien depuis la page de connexion de BDOH.',
        '+T Passé ce délai, demandez un nouveau lien depuis la page de connexion de BDOH.',
        '+A <lien de connexion>', '+',
        f'-{RIGHTS_NOTICE}bdoh.support@lists.irstea.fr.', f'-T {RIGHTS_NOTICE}bdoh.support@lists.irstea.fr.',
        f'+{RIGHTS_NOTICE}bdoh.support@listes.inrae.fr.', f'+T {RIGHTS_NOTICE}bdoh.support@listes.inrae.fr.')),
    # L'état des comptes que crée le mode : account_rules, avec les identifiants de la passe.

    # --- admin -------------------------------------------------------------------------------------
    # L'astuce de saisie des dates passe dans la bulle des champs, sur la page des barèmes aussi
    Rule('admin', rf'{ROLE} {OBS}/admin/transformation', GRAPH, lines=DATE_TIP),
    Rule('admin', rf'{ROLE} {OBS}/admin/utilisateur/export\?format=csv', "Export des listes de l'administration",
         lines=(RX(r'-statut (?:403|500)'), '+statut 404'), must=('+statut 404',)),
    Rule('admin', rf'gestionnaire {OBS}/admin/personnetheia/list', "Modifier le compte d'un autre, réservé aux administrateurs",
         lines=(RX(rf'-A {OBS}/admin/utilisateur/\d+/edit\?.*'),)),
    Rule('admin', rf'gestionnaire {OBS}/admin/utilisateur/list', "Modifier le compte d'un autre, réservé aux administrateurs",
         lines=(RX(rf'-A {OBS}/admin/utilisateur/\d+/edit'),)),
    replaced('admin', rf'gestionnaire {OBS}/admin/utilisateur/\d+/edit', "Modifier le compte d'un autre, réservé aux administrateurs", 403,
             RX(r'\+(?!statut ).*')),
    Rule('admin', 'taux de remplissage : chronique inconnue', "Taux de remplissage d'une chronique inconnue",
         lines=('-statut 500', '+statut 404'), must=('+statut 404',)),
    Rule('admin', 'courriels', LINK, lines=(
        "-Votre identifiant est l'adresse e-mail à laquelle vous recevez ce message et le mot de passe est «\xa0password\xa0», nous vous invitons à le changer dès votre première connexion.",
        "+Votre identifiant est l'adresse e-mail à laquelle vous recevez ce message. BDOH n'a pas de mot de passe\xa0: pour vous connecter, saisissez cette adresse sur la page de connexion, et BDOH vous enverra un lien de connexion.")),
    # L'état du compte qu'elle crée, et ce qui dépend de la base : admin_rules.
    # Le logo d'un partenaire porte aussi le nom de son observatoire ; « nouveau seul : logos de deux partenaires de
    # même nom » vérifie qu'il y est
    Rule('admin', r'fichiers joints : (?:partenaire créé avec un logo|logo remplacé par une image d’un autre type|logo retiré'
         r'|partenaire supprimé avec son logo)', 'Logos de partenaires de même nom',
         masks=((r'uploads/[A-Z0-9-]+_(?=[A-Z0-9-]+-logo\.)', 'uploads/'),)),
]
