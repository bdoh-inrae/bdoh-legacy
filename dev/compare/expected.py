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
    security.utilisateur.edit.info.changePassword"""
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
    security.utilisateur.newMailUtilisateur.body.confirmLater | readLess | readMore"""
CHANGED_KEYS = """accueilMessage | measure.export.help-content | measure.export.job_queued(%job_id%) | measure.export.underway
    security.besoin.create.title | security.login_form.connectTo | security.utilisateur.create.info.isTheFirstStep
    security.utilisateur.create.info.yourIdentifier | security.utilisateur.create.isCompleted
    security.utilisateur.newMailUtilisateur.body.step2 | security.utilisateur.newMailUtilisateur.body.step4
    technicalContact | utilisateur.newAccountMail.body"""


# L'avertissement de la limite de 5 ans, retiré avec elle (2026-11-12)
FIVE_YEARS = ("-T Attention : pour des raisons de performance vous ne pouvez sélectionner qu'un intervalle de dates de 5 ans maximum.",
              '-T Warning: due to performance limitations please select a maximum time span of 5 years')

def translation(sign, keys):
    """La ligne d'une de ces clés dans /translations/messages.js, quel qu'en soit le texte."""
    names = [re.escape(key.strip()) for key in re.split(r'\||\n', keys) if key.strip()]
    return RX(rf'{sign}t\.add\("(?:{"|".join(names)})", ".*", "messages", "\w+"\);')


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
    # --- pages -------------------------------------------------------------------------------------
    # La carte des stations, sur l'accueil du portail et sur celui d'un observatoire ; son contenu se vérifie à part
    # (« nouveau seul : carte des stations » dans le même mode)
    Rule('pages', rf'{ROLE} (?:{OBS}/(?:home)?|/)', 'Carte des stations', lines=(
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
    Rule('pages', rf'{ROLE} {OBS}/(?:home)?', HOMES, lines=(
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
        RX(rf'\+D div data-hint=.+ data-title=.+ data-url={OBS}/chronique/\d+/overview'), *FIVE_YEARS)),
    # La limite de 5 ans du choix des dates part avec son avertissement, sur la page d'une chronique et dans le visualiseur
    Rule('pages', rf'{ROLE} {OBS}/viewer', GRAPH, lines=FIVE_YEARS, must=FIVE_YEARS[:1]),
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
