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

import json
import re
import urllib.parse
from dataclasses import dataclass

import reviewed_texts

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
MANAGERS = "Les gestionnaires d'un observatoire, dans un onglet de son accueil"
HOMES = 'Accueils autour de la carte'
GRAPH = "Graphique d'une chronique"
OLD_TERMS = "Pour pouvoir télécharger des données il faudra, une fois connecté, faire des demandes d'accès spécifiques sur les observatoires qui vous intéressent via le formulaire de demande d'accès aux données ou contacter un gestionnaire"
# Les chroniques en accès restreint, réservées aux membres de leur observatoire, ne se proposent plus au public (Lou, 2026-11-16)
NEW_TERMS = "Les données ouvertes se téléchargent sans compte. Les chroniques en accès restreint sont réservées aux membres de leur observatoire : si vous en êtes membre, vous en demanderez l'accès, une fois connecté, par le formulaire de demande d'accès de l'observatoire, ou auprès d'un de ses gestionnaires"
RIGHTS_NOTICE = "Conformément aux articles 39 et 40 de la loi Informatique et Libertés modifiée par la loi n° 2004-801 du 6 août 2004 relative à la protection des personnes physiques à l'égard des traitements de données à caractère personnel, vous disposez d'un droit d'accès et de rectification des données vous concernant. Pour toute demande de rectification veuillez vous adresser à : "

# L'information sur les données personnelles, à l'inscription et dans le courriel de bienvenue (RGPD, article 13)
PRIVACY = 'Données personnelles des comptes'
# Les chroniques réservées, dites pour ce qu'elles sont (dev/differences.md, « Ouverture des données », 19 novembre)
RESERVED = 'Ouverture des données'
TERMS = "Conditions d'utilisation communes"
# L'aide des deux champs des photos d'un observatoire (Lou, 2026-11-16)
PHOTOS = 'Photographies sous Licence Ouverte'
PHOTO_HELP = ("+T La photo est diffusée sous Licence Ouverte 2.0, comme les données. Ne la déposez que si vous en êtes "
              "l'auteur, ou avec son accord.")
# L'adresse des conditions d'un observatoire dans une ligne (« <site>\/DRAIX\/terms_of_use »), devenue celle de tout BDOH
TERMS_PAGE = re.compile(r'(<site>(\\?/))[A-Z0-9_-]+\2terms_of_use')
# Depuis le 19 novembre (Lou) : à quoi servent les informations d'un compte, sans point-virgule, et ni téléphone ni
# adresse à l'inscription
PRIVACY_NOTICE = ("INRAE (147 rue de l'Université, 75338 Paris Cedex 07) traite les informations de votre compte pour le faire "
                  "fonctionner : la connexion, vos favoris, le lien de vos exports, et vos demandes d'accès, que reçoivent les "
                  "gestionnaires de l'observatoire concerné. Vous pouvez accéder à ces informations, les faire rectifier ou "
                  "effacer, et vous opposer à leur traitement, comme le prévoient le règlement (UE) 2016/679 et la loi "
                  "Informatique et Libertés. Écrivez à bdoh.support@listes.inrae.fr, ou à la déléguée à la protection des "
                  "données d'INRAE, cil-dpo@inrae.fr. Vous pouvez aussi saisir la CNIL (www.cnil.fr).")
PRIVACY_FORM = (f"+T Données personnelles : {PRIVACY_NOTICE}",)
# Le téléphone et l'adresse quittent l'inscription et la page du compte (Lou, 2026-11-19)
CONTACT_FIELDS = ('-T N° de téléphone', '-T Adresse',
                  RX(r'-I (?:textarea|input text) irstea_bdohsecuritybundle_\w+\[(?:adresse|telephone)\](?: .*)?'))
# … et le formulaire d'un compte dans l'administration (Lou, 2026-11-23 : trop identifiants, sans bénéfice)
ADMIN_CONTACT_FIELDS = ('-T N° de téléphone', '-T Adresse',
                        RX(r'-I (?:textarea|input text) s<uniqid>\[(?:adresse|telephone)\](?: .*)?'))

# La page de la demande d'accès : son titre, et à qui la demande est réservée (Lou, 2026-11-16). Seul un compte sans
# droit la voit : celui que crée accounts ; les comptes de pages y sont renvoyés à la recherche.
RESERVED_REQUEST = (
    RX(r"-T BDOH - .* - Demande d'accès aux données"),
    RX(r"\+T BDOH - .* - Demande d'accès aux chroniques réservées à l'observatoire"),
    "-T Demande d'accès aux données", "+T Demande d'accès aux chroniques réservées à l'observatoire",
    "+T Les chroniques en accès restreint (cadenas) sont réservées aux membres de l'observatoire, pour son travail "
    "interne : cette demande est la leur. BDOH n'en assure pas la diffusion : si vous n'êtes pas membre de "
    "l'observatoire, adressez-vous aux partenaires qui la portent.",
    '+T Les chroniques ouvertes se téléchargent sans compte ni demande.',
)
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
    map.onMap | map.seeStation | map.sites | map.title | map.withoutPosition | map.zoomHint | overview.hint | overview.title | measure.export.busy | measure.export.licence | measure.export.status.download
    measure.export.status.expired | measure.export.status.failed | measure.export.status.finished(%days%)
    measure.export.status.running | measure.export.status.title | measure.export.status.waiting | measure.export.tooHeavy
    measure.export.status.keep(%days%) | measure.export.tooLarge | measure.export.values.read | measure.export.values.computed
    historique.withoutAccount | measure.export.account | measure.export.accountFull | consult.help.chronique.reserved
    reserved.subtitle | reserved.what | reserved.distribution | reserved.producer(%producer%) | reserved.station(%station%)
    reserved.managers(%link%) | reserved.member.login(%link%) | reserved.member.connected
    security.utilisateur.newMailUtilisateur.body.openOnly
    measure.export.tooMany | security.besoin.members | security.besoin.open | security.login_check.info(%email%) | security.login_check.submit
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
CHANGED_KEYS = """accueilMessage | askDataAccess | consult.help.chronique.notVisible | measure.export.help-content | measure.export.job_queued(%job_id%) | measure.export.underway
    security.besoin.create.title | security.login_form.connectTo | security.utilisateur.create.info.isTheFirstStep
    security.utilisateur.create.info.yourIdentifier | security.utilisateur.create.isCompleted
    security.utilisateur.newMailUtilisateur.body.step2 | security.utilisateur.newMailUtilisateur.body.step4
    utilisateur.newAccountMail.body"""


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

# La relecture des textes (2026-11-19, dev/plans/2026-11-19_review.md) : chaque texte français corrigé, découpé en
# segments comme une page ou un courriel le montre (les marques %…%, les balises de bloc et les retours à la ligne
# coupent le texte, les formes d'un pluriel sont séparées par « | »), l'ancien segment devenant le nouveau où qu'il
# soit dans une ligne, en mots entiers. Un segment ne se réécrit pas partout s'il devient deux textes selon la clé, ou
# si un texte resté le même le contient aussi (`reviewed_texts.KEEP`) : il réécrirait ce texte-là. Les menus de
# l'historique, dont trois libellés sont dans ce cas, se réécrivent par leur ancre (`history_menu`). Un remplacement
# de trop se voit : la page de l'ancien réécrite ne ressemble plus à celle du nouveau.
REVIEW = 'Relecture des textes'


def review_segments(text):
    """Les segments d'un texte de catalogue, tels que les lignes d'une page ou d'un courriel les montrent."""
    text = re.sub(r'%[\w.]+%|\{\{[^}]*\}\}', '\0', text)
    text = re.sub(r'<(?:br|li|ul|ol|p|div|h\d)\b[^>]*>|</(?:li|ul|ol|p|div|h\d)>', '\0', text, flags=re.I)
    text = re.sub(r'<[^>]+>', '', text)
    parts = re.split(r'\0|\||\n', text)
    return [' '.join(re.sub(r'^(?:\{[^}]*\}|[\[\]][^\[\]]*[\[\]])', '', part).split()) for part in parts]


def review_pairs():
    """Les segments changés, du plus long au plus court : (avant, après). Un segment de moins de 4 caractères ne se
    réécrit pas : il se retrouverait partout."""
    targets = {}
    for _, _, before, after in reviewed_texts.CHANGED:
        old, new = review_segments(before), review_segments(after)
        if len(old) != len(new):
            continue
        for a, b in zip(old, new):
            if a and a != b:
                targets.setdefault(a, set()).add(b)
    return sorted(((a, next(iter(b))) for a, b in targets.items()
                   if len(b) == 1 and len(a) >= 4 and a not in reviewed_texts.KEEP), key=lambda p: -len(p[0]))


REVIEW_PAIRS = review_pairs()
REVIEW_NEW = dict(REVIEW_PAIRS)
# Tous les segments en une seule expression, les plus longs d'abord : une page a des milliers de lignes
REVIEW_ANY = re.compile('|'.join(re.escape(a) for a, _ in REVIEW_PAIRS))
JSON_STRING = re.compile(r'"(?:[^"\\]|\\.)*"')
# Les menus de l'historique : l'ancre d'une entrée (« A #adminMilieu »), puis son libellé
HISTORY_MENU = {key.rsplit('.', 1)[1]: (before, after) for domain, key, before, after in reviewed_texts.CHANGED
                if domain == 'messages' and key.startswith('historique.menu.')}


def review_text(text):
    """Un texte où chaque ancien segment, en mots entiers, devient le nouveau. Un retour à la ligne écrit en JSON
    (« \\n », dans l'état de la base) sépare deux mots."""
    def word(k):
        return 0 <= k < len(text) and (text[k].isalnum() or text[k] == '_')

    def replace(m):
        a, start, end = m.group(0), m.start(), m.end()
        glued = a[0].isalnum() and word(start - 1) and text[start - 2:start] != '\\n'
        return a if glued or (a[-1].isalnum() and word(end)) else REVIEW_NEW[a]
    return REVIEW_ANY.sub(replace, text)


def review_json(line):
    """Une ligne où le texte est écrit en JSON, caractères codés (« qualit\\u00e9es », dans les messages d'une page,
    data-notices) : chaque chaîne décodée, réécrite, puis recodée comme PHP l'écrit si elle a changé."""
    def replace(m):
        try:
            text = json.loads(m.group(0))
        except ValueError:
            return m.group(0)
        done = review_text(text) if isinstance(text, str) else text
        return m.group(0) if done == text else json.dumps(done).replace('/', '\\/')
    return JSON_STRING.sub(replace, line)


def history_menu(lines):
    """Le libellé d'une entrée du menu de l'historique, juste après son ancre (une ligne de bouton peut les séparer)."""
    out = list(lines)
    for k, line in enumerate(out):
        if line.startswith('A #') and line[3:] in HISTORY_MENU:
            before, after = HISTORY_MENU[line[3:]]
            for j in (k + 1, k + 2):
                if j < len(out) and out[j] == f'T {before}':
                    out[j] = f'T {after}'
                    break
    return out


def reviewed(lines):
    """L'ancien texte corrigé par la relecture, réécrit en le nouveau. Les lignes des traductions du JavaScript
    (`t.add(`) restent : la règle de leurs clés les admet, et un segment partagé y réécrirait une clé restée la même."""
    out = [line if line.startswith('t.add(') else review_json(line) if '\\u' in line else review_text(line)
           for line in lines]
    return history_menu(out)


def translation(sign, keys):
    """La ligne d'une de ces clés dans /translations/messages.js, quel qu'en soit le texte."""
    names = [re.escape(key.strip()) for key in re.split(r'\||\n', keys) if key.strip()]
    return RX(rf'{sign}t\.add\("(?:{"|".join(names)})", ".*", "messages", "\w+"\);')


HEADER = "En-tête, fil d'Ariane et pied de page"
SEARCH_NAME = 'La recherche avancée devenue « Recherche »'
CSV_FORMAT = 'Le format CSV'
# Le format CSV dans la fenêtre d'export, partout où va le format BDOH (dev/plans/2026-11-13_fair-exports.md)
CSV_OPTION = ('+O Csv selected', '+T CSV')  # sélectionné par défaut (Lou, 2026-11-16)
LANG_FLAG = re.compile(r'G /images/i18n/\w+\.png')
DATA_LINK = {'T Consultation des données': 'T Recherche', 'T See our data': 'T Search'}
SEARCH = {'T Recherche avancée': 'T Recherche', 'T Advanced search': 'T Search'}
MENU_BUTTON = ('+D button data-target=#header-bars data-toggle=collapse', '+I button button')
EDIT_TIP = re.compile(r'D li data-original-title=(.+) data-placement=bottom data-toggle=tooltip')
DROPDOWN = ['A #', 'D a data-toggle=dropdown']
CRUMB_HOME = ('T Accueil', 'T Home')
OBS_TITLE = re.compile(r'T «\s*(.+?)\s*» (Bienvenue|Welcome)')


def header(lines):
    """L'en-tête d'une page de l'ancien, et le début de son fil d'Ariane, comme le nouveau doit les montrer
    (2026-11-12 et 2026-11-13, Lou) : « BDOH », qui mène au portail, à la place du logo de l'observatoire (dont le lien
    passe à côté du titre de son accueil) ; dans la barre du haut, ce qui tient à l'observatoire : son choix,
    l'observatoire de la page, sa « Recherche » (l'ancien « Consultation des données », à sa place), la langue puis
    l'aide, au bout (Lou, 2026-11-15) ; dans celle du dessous, le compte (`account_menu`) ; le fil commence par « BDOH » puis l'observatoire, au
    lieu de « Accueil », et toute page en a un. Les mêmes liens, déplacés : rien ne s'ajoute ni ne se perd qu'ici,
    sinon l'ouverture du menu du compte, et l'aide, qui mène à la page de tout BDOH, sur toutes les pages, là où
    l'ancien menait aux gestionnaires de l'observatoire, sur ses pages seulement (`HELP`, Lou, 2026-11-16)."""
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
            # « Bienvenue » laisse la place à ce qu'est la page, comme sur les autres (`titles`, Lou, 2026-11-16)
            kind = {'Bienvenue': 'Observatoire', 'Welcome': 'Observatory'}[m.group(2)]
            rest = [*rest[:title], *([f'A {site}'] if outward else []),
                    f'T {m.group(1)}{label} {kind}', *rest[title + 1:]]
    language = LANGUAGES.get(lines[lang + 3], 'FR')
    return [*lines[:start], 'A /', 'T BDOH', *lines[everything - 3:end], *(current or []),
            *([data[0], DATA_LINK[data[1]]] if data else []), *lang_switch(lines[lang:lang + 7]), *HELP[language],
            *account_menu(lines[lang + 7:everything - 3]), *crumb, *rest]


LANGUAGES = {'T Français': 'FR', 'T English': 'EN'}
# L'aide, au bout de la barre du haut de toutes les pages, portail compris : la page de tout BDOH (Lou, 2026-11-16)
HELP = {'FR': ['A /help', 'T Aide'], 'EN': ['A /help', 'T Help']}


def lang_switch(block):
    """Le choix de la langue de l'ancien, un menu au drapeau (« Français », puis le lien vers « English »), comme le
    nouveau le montre (Lou, 2026-11-15) : « FR EN », la langue de la page en texte, l'autre en lien, sans drapeau ;
    le lien vient d'abord, le texte des deux se lisant d'une seule ligne. Ce qu'elle ne reconnaît pas reste tel quel."""
    if len(block) != 7 or block[3] not in LANGUAGES or block[6] not in LANGUAGES or LANGUAGES[block[3]] == LANGUAGES[block[6]]:
        return block
    return [block[4], 'T FR EN']


FOOTER_INRAE = ['A https://www.inrae.fr/', 'G /images/Logo_INRAE_REPUBLIQUE.png Irstea']
UPLOADED = re.compile(r'G /uploads/\S+(?: .+)?')


# La ligne des liens de tout BDOH, au pied de chaque page, selon la langue de la page (Lou, 2026-11-15 et 16)
SOURCE = 'https://github.com/bdoh-inrae/bdoh-legacy'
FOOTER_LINKS = {
    'fr': ['A /help', 'T Aide et contact', 'A /terms_of_use', "T Conditions d'utilisation", 'A /legal', 'T Mentions légales',
           'A /legal#accessibilite', 'T Accessibilité : non conforme', f'A {SOURCE}', 'T Code source (AGPL-3.0)'],
    'en': ['A /help', 'T Help and contact', 'A /terms_of_use', 'T Terms of use', 'A /legal', 'T Legal notice',
           'A /legal#accessibilite', 'T Accessibility: not compliant', f'A {SOURCE}', 'T Source code (AGPL-3.0)'],
}

PRODUCERS = 'I select s<uniqid>[producteur]'
OPTION = re.compile(r'O (\d+)(?: selected)?')


def producers(lines):
    """La liste des producteurs du formulaire d'une chronique, dans l'ordre des identifiants, où le nouveau la trie
    (2026-11-23) : l'ancien la rend dans l'ordre des lignes de la table, qu'une mise à jour change ("Listes sans
    tri"). Chaque option, son identifiant puis son nom s'il en a un ; l'option vide reste en tête."""
    out, i = [], 0
    while i < len(lines):
        out.append(lines[i])
        i += 1
        if lines[i - 1] != PRODUCERS:
            continue
        head, options = [], []
        while i < len(lines) and (lines[i] == 'O' or OPTION.fullmatch(lines[i])):
            option = [lines[i]]
            i += 1
            if i < len(lines) and lines[i].startswith('T '):
                option.append(lines[i])
                i += 1
            (options if option[0] != 'O' else head).append(option)
        options.sort(key=lambda option: int(OPTION.fullmatch(option[0]).group(1)))
        out += [line for option in head + options for line in option]
    return out


def footer(lines):
    """Le pied de page de l'ancien comme le nouveau le montre (Lou, 2026-11-16) : la ligne des liens de tout BDOH
    (l'aide, les conditions d'utilisation, les mentions légales, la mention d'accessibilité, le code source), puis le
    bloc Marianne et INRAE. Les logos des partenaires de l'observatoire, qui suivaient le bloc (une image de
    /uploads/, avec son lien s'il en a un), passent à l'onglet « Partenaires » de son accueil, où HOMES les admet ; le
    paragraphe des mentions légales, que compare.py retire des deux côtés (LEGAL_NOTICE), passe sur sa page, /legal.
    La langue de la page se lit au lien de l'autre langue, dans l'en-tête de l'ancien comme une fois réécrit
    (`lang_switch`)."""
    language = 'en' if 'A /select_lang/fr' in lines else 'fr'
    for k in range(len(lines) - 1):
        if lines[k:k + 2] == FOOTER_INRAE:
            end = k + 2
            while True:
                if end < len(lines) and UPLOADED.fullmatch(lines[end]):
                    end += 1
                elif end + 1 < len(lines) and re.fullmatch(r'A \S+', lines[end]) and UPLOADED.fullmatch(lines[end + 1]):
                    end += 2
                else:
                    break
            return [*lines[:k], *FOOTER_LINKS[language], *FOOTER_INRAE, *lines[end:]]
    return lines


def per_page(rewrite):
    """Une réécriture de page, appliquée à chaque page d'un journal qui en mêle plusieurs (les étapes de `state`), où
    chacune commence par sa ligne « statut » (`page_lines`) : header() ne réécrit que le premier en-tête qu'il trouve."""
    def apply(lines):
        cuts = [i for i, line in enumerate(lines) if line.startswith('statut ')] + [len(lines)]
        out = list(lines[:cuts[0]])
        for begin, end in zip(cuts, cuts[1:]):
            out += rewrite(lines[begin:end])
        return out
    return apply


def account_menu(lines):
    """Le compte d'un connecté, dans l'ancien : son nom, lien vers sa fiche avec la bulle « Modifier votre compte »,
    la déconnexion, puis le tableau de bord et les menus Administration et Gestion des données, ou la demande d'accès.
    Dans le nouveau (Lou, 2026-11-13) : le tableau de bord en tête du menu Administration, puis le nom, qui ouvre le
    menu du compte : le modifier (le texte de la bulle), se déconnecter. Les favoris s'y ajoutent, admis à part
    (FAVORITES). Sans compte, rien ne change."""
    tip = EDIT_TIP.fullmatch(lines[0]) if lines else None
    if not (tip and len(lines) >= 5 and re.fullmatch(rf'A {OBS}/user/edit', lines[1]) and lines[2].startswith('T ')
            and re.fullmatch(rf'A {OBS}/logout', lines[3]) and lines[4].startswith('T ')):
        return lines
    edit, name, logout, rest = lines[1], lines[2], lines[3:5], lines[5:]
    if len(rest) >= 5 and re.fullmatch(rf'A {OBS}/admin/dashboard', rest[0]) and rest[2:4] == DROPDOWN:
        rest = [*rest[2:5], *rest[:2], *rest[5:]]
    return [*rest, *DROPDOWN, name, edit, f'T {tip.group(1)}', *logout]


def doi_links(lines):
    """Un DOI enregistré avec son résolveur (« https://doi.org/10.… ») : l'ancien le préfixait d'un second
    (« https://dx.doi.org/https://doi.org/10.… »), dans le lien comme dans son texte ; le nouveau ne le double plus
    (passe juridique du 13 novembre, dev/differences.md, « Conditions d'utilisation communes »)."""
    return [re.sub(r'https?://dx\.doi\.org/(https?://(?:dx\.)?doi\.org/)', r'\1', line) for line in lines]


def search_renamed(lines):
    """« Recherche avancée » devient « Recherche » partout où la page la nomme : le bouton de l'accueil d'un
    observatoire, le titre, le fil d'Ariane et le titre de l'onglet de la page elle-même (Lou, 2026-11-13)."""
    def renamed(line):
        if line in SEARCH:
            return SEARCH[line]
        for before, after in SEARCH.items():
            if line.startswith('T ') and line.endswith(' - ' + before[2:]):
                return line[:-len(before) + 2] + after[2:]
        return line
    return [renamed(line) for line in lines]


TITLES = "Titres des pages d'une station, d'une chronique, d'un jeu de données"
CITATIONS = 'Blocs de citation'
# Le titre de l'ancien, « Station « nom » », le nom entre guillemets (français ou anglais) et en italique
TITLE = re.compile(r'T (Station|Chronique|Time series|Jeu\(x\) de données|Dataset) [«“] (.+) [»”]')


def titles(lines):
    """Le titre d'une station, d'une chronique, d'un jeu de données (Lou, 2026-11-16) : le nom seul, sans guillemets,
    puis ce qu'est la page, en petit, comme « Observatoire » sur l'accueil d'un observatoire (`header`) ; « Jeu de
    données » au singulier, comme le titre de l'onglet du navigateur. Les deux restent dans le même titre (h1), d'où une
    seule ligne."""
    def rewritten(line):
        m = TITLE.fullmatch(line)
        if not m:
            return line
        kind = 'Jeu de données' if m.group(1) == 'Jeu(x) de données' else m.group(1)
        return f'T {m.group(2)} {kind}'
    return [rewritten(line) for line in lines]


FAVORITES = 'Favoris et étiquettes'
# Les statistiques d'usage (Lou, 2026-11-16) : l'entrée du menu de l'administration, sous l'historique, pour qui le voit ;
# le reste se vérifie à part (« nouveau seul : statistiques » dans state)
STATISTICS = "Statistiques d'usage"
STATISTICS_MENU = (RX(rf'\+A {OBS}/statistics'), '+T Statistiques', '+T Statistics')
# Leurs textes, dans les traductions pour le JavaScript (/translations/messages.js), qui portent tout le catalogue
STATISTICS_KEYS = """statistics.allBdoh | statistics.bySeries | statistics.byYear | statistics.csv | statistics.csvBySeries
    statistics.intro | statistics.menu | statistics.notes | statistics.seeAll | statistics.seeObservatory(%name%) | statistics.title
    statistics.columns.accounts_created | statistics.columns.anonymous_requests | statistics.columns.checkpoint_imports
    statistics.columns.computations | statistics.columns.downloaded_values | statistics.columns.downloading_accounts
    statistics.columns.downloads | statistics.columns.imported_values | statistics.columns.imports
    statistics.columns.observatory | statistics.columns.series | statistics.columns.series_created
    statistics.columns.station | statistics.columns.stations_created | statistics.columns.views | statistics.columns.year
    usage.title | usage.views | usage.viewsSince(%date%) | usage.downloads | usage.downloadsSince(%year%) | usage.help"""
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
        account = ('+A #', '+D a data-toggle=dropdown', f'+T {name}', '+A /logout', '+T Déconnexion')
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



DESCRIPTIONS = 'Descriptions mises en forme'


def description_rules(rendered):
    """Les descriptions mises en forme d'un observatoire et d'un site, sur les accueils (relecture du 19 novembre) :
    l'ancien écrivait chaque retour à la ligne de leur code en saut de ligne, le nouveau les écrit telles quelles.
    `run_pages` les ajoute d'après la base de la passe, chaque description rendue des deux façons par `page_lines`
    (ancien, nouveau) ; la réécriture remplace, dans l'ancien, la suite de lignes de l'une par celle de l'autre. Une
    description que l'ancien montrait déjà comme le nouveau (sans retour à la ligne) ne donne rien."""
    pairs = sorted(((tuple(old), tuple(new)) for old, new in rendered if old and old != new), key=lambda p: -len(p[0]))
    if not pairs:
        return []
    starting = {}
    for old, new in pairs:
        starting.setdefault(old[0], []).append((old, new))

    def rewrite(lines):
        out, k = [], 0
        while k < len(lines):
            found = next(((old, new) for old, new in starting.get(lines[k], ()) if tuple(lines[k:k + len(old)]) == old), None)
            if found:
                out += found[1]
                k += len(found[0])
            else:
                out.append(lines[k])
                k += 1
        return out
    return [Rule('pages', rf'{ROLE} (?!/INCONNU/){OBS}/(?:home)?', DESCRIPTIONS, rewrite=rewrite)]


MERGE = "Fusion d'OTHU dans BDOH"


def merge_rules(renamed, others, families, admins):
    """Ce que la fusion d'OTHU change à ses pages et à ses exports (`run_merge` les ajoute) :
    - les libellés des listes communes rapprochés d'un autre libellé de la production (renamed, ancien et nouveau),
      réécrits dans les lignes de l'ancienne base là où ils s'affichent seuls : une ligne, une valeur JSON, un paramètre
      d'adresse, suivis d'un code postal ou d'un compte de chroniques ; un texte libre qui les contient ne change pas ;
    - les autres observatoires (others : adresse et nom), que le choix d'un observatoire propose désormais ;
    - l'ordre des listes que le code trie par identifiant (séries d'un graphique par qualité, chroniques d'une station),
      et les identifiants que portent les pages, qui changent avec la fusion ;
    - les familles des paramètres qui en changent (families : leurs noms, d'avant et d'après), et les regroupements
      qui les suivent ;
    - les contacts de l'observatoire, où s'ajoutent ses anciens administrateurs (admins : prénom et nom), devenus ses
      gestionnaires ;
    - les exports instantanés, toujours vides dans OTHU, dont la table interp_qualite était vide : ils gagnent leurs
      valeurs, sans en perdre aucune ;
    - dans le format Qtvar, le code de l'unité « m³/s », écrit « M-S » comme celui des m/s, devenu « M3-S » avec le
      libellé « m3/s »."""
    patterns = []
    for old in sorted(renamed, key=len, reverse=True):
        new = renamed[old]
        patterns.append((re.compile(rf'(?<=^T ){re.escape(old)}(?=$| \(| ‒)|(?<=": "){re.escape(old)}(?=")'
                                    rf'|(?<=;){re.escape(old)}(?=;|$)'), new))
        patterns.append((re.compile(rf'(?<==){re.escape(urllib.parse.quote_plus(old))}(?=&|$)'), urllib.parse.quote_plus(new)))

    def relabel(lines):
        out = []
        for line in lines:
            for pattern, new in patterns:
                line = pattern.sub(new, line)
            out.append(line)
        return out

    # Les identifiants que portent les pages (sites de la carte, ancres des familles de paramètres, options d'une
    # liste, objets du visualiseur) changent avec la fusion : masqués, ce qu'ils désignent restant comparé, et les
    # données elles-mêmes vérifiées par l'empreinte de chaque chronique (merge.sql)
    ids = ((r'(data-map-site=|data-chronique-id=|#famille-|#family|#param-)\d+', r'\1<id>'), (r'("sites":\[)[\d,]*', r'\1<ids>'),
           (r'^O \d+( selected)?$', r'O <id>\1'), (r'^(\s*"(?:id|unitId)": )\d+', r'\1<id>'), (r'^(\s*")\d+(": \{)$', r'\1<id>\2'))
    regrouped = tuple(x for name in families for x in (
        RX(rf'[-+]T {re.escape(name)}(?:##.*)?'), RX(rf'[-+]A /[A-Z][A-Z0-9-]*/nos-donnees\?famille={re.escape(urllib.parse.quote_plus(name))}')))
    return [
        Rule('merge', '.*', MERGE, rewrite=relabel),
        Rule('merge', r'anonyme /.*', MERGE, order=True, masks=ids,
             lines=tuple(x for slug, name in others for x in (RX(rf'\+A /{re.escape(slug)}/(?:home)?'), f'+T {name}'))),
        Rule('merge', r'anonyme /.*', MERGE, lines=(*regrouped, RX(r'[-+]A #(?:famille-|family|param-).*'),
                                                    RX(r'[-+]D a data-toggle=collapse'))) if families else None,
        # Les administrateurs d'OTHU, devenus gestionnaires de l'observatoire, s'ajoutent à l'onglet de ses
        # gestionnaires, sur son accueil (Lou, 2026-11-16 ; la page de contact y renvoie)
        Rule('merge', r'anonyme /[A-Z][A-Z0-9-]*/', MERGE, lines=tuple(f'+T {" ".join(names)}' for names in admins)),
        Rule('merge', r'bdoh:export:instantaneous .*', MERGE, lines=(
            RX(r'\+\d\d/\d\d/\d{4} \d\d:\d\d:\d\d;.*'), RX(r'-\s*Aucune mesure exportée'), RX(r'\+\s*\d+ mesures exportées'))),
        Rule('merge', r'bdoh:export:.* --format qtvar .*', MERGE,
             lines=(RX(r'-919;.*;M-S;'), RX(r'\+919;.*;M3-S;'))),
    ]


RULES = [
    # L'en-tête et le début du fil d'Ariane, sur toute page du site, quel que soit le mode qui la compare : header()
    # les réécrit dans l'ancien, et la page doit alors être la même, sauf ce que d'autres règles admettent
    # (theia aussi, pour la page du contrôle des fiches, qu'il n'avait pas rejouée depuis le 11 novembre)
    *(Rule(mode, r'.*', HEADER, rewrite=header) for mode in ('pages', 'admin', 'imports', 'accounts', 'theia')),
    *(Rule(mode, r'.*', HEADER, rewrite=footer) for mode in ('pages', 'admin', 'imports', 'accounts', 'theia')),
    # La liste des producteurs du formulaire d'une chronique, triée par le nouveau (2026-11-23)
    Rule('admin', r'.*', 'Listes sans tri', rewrite=producers),
    # Le bouton de menu, qui replie les deux barres sur un téléphone (Lou, 2026-11-23), sur toutes les pages
    *(Rule(mode, r'.*', HEADER, lines=MENU_BUTTON)
      for mode in ('pages', 'admin', 'imports', 'accounts', 'theia', 'state')),
    # Les textes corrigés par la relecture, sur toute page et tout courriel de ces modes (2026-11-19 ; pas theia, dont les
    # pages ne montrent aucun texte relu : « Règle sans écart » le 19 novembre, sur bdoh)
    *(Rule(mode, r'.*', REVIEW, rewrite=reviewed) for mode in ('pages', 'admin', 'imports', 'accounts', 'state')),
    Rule('pages', rf'{ROLE} /translations/messages\.js', REVIEW, lines=(
        translation('[-+]', ' | '.join(k for d, k, _, _ in reviewed_texts.CHANGED if d == 'messages')),
        translation(r'\+', ' | '.join(k for d, k, _ in reviewed_texts.ADDED if d == 'messages')))),
    # (les pages des autres modes ne nomment pas la recherche : « Règle sans écart » le 13 et le 15 novembre)
    Rule('pages', r'.*', SEARCH_NAME, rewrite=search_renamed),
    Rule('pages', r'.*', TITLES, rewrite=titles),
    Rule('pages', r'.*', TERMS, rewrite=doi_links),
    *(Rule(mode, r'(?!anonyme ).*', FAVORITES, lines=FAVORITE_MENU) for mode in ('pages', 'admin', 'accounts')),
    Rule('theia', r'contrôle .*', FAVORITES, lines=FAVORITE_MENU),
    *(Rule(mode, r'(?!anonyme ).*', STATISTICS, lines=STATISTICS_MENU) for mode in ('pages', 'admin', 'accounts', 'imports', 'state')),
    Rule('theia', r'contrôle .*', STATISTICS, lines=STATISTICS_MENU),
    Rule('pages', r'(?!anonyme ).*', FAVORITES, lines=FAVORITE_STAR),
    # Les mêmes, dans les étapes de state, qui montrent les pages d'une chronique après qu'on l'a vidée sur une période
    Rule('state', 'étapes', HEADER, rewrite=per_page(header)),
    Rule('state', 'étapes', HEADER, rewrite=per_page(footer)),
    Rule('state', 'étapes', SEARCH_NAME, rewrite=search_renamed),
    Rule('state', 'étapes', TITLES, rewrite=titles),
    Rule('state', 'étapes', FAVORITES, lines=(*FAVORITE_MENU, *FAVORITE_STAR)),
    Rule('state', 'étapes', CSV_FORMAT, lines=CSV_OPTION),
    Rule('state', 'étapes', GRAPH, lines=(
        RX(rf'\+D div data-hint=.+ data-title=.+ data-url={OBS}/chronique/\d+/overview'), *FIVE_YEARS, *DATE_TIP,
        *STATUS_LINE)),
    # L'export en moyenne d'une chronique discontinue, que state demande par le formulaire : l'ancien s'arrête sur une
    # erreur fatale (255), le nouveau sur le code 1 ; un export qui réussit (0) d'un côté seulement reste un écart
    Rule('state', 'jms_jobs', 'Export en moyenne d’une chronique discontinue', masks=(
        (r'("command": "bdoh:export:mean", "exitcode": )(?:1|255)\b', r'\1<1 ou 255>'),)),

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
        RX(rf'\+A {OBS}/home'), RX(r'\+D a data-map-observatoire=.+'), RX(r'\+G /uploads/\S+'), RX(r'\+T .+'),
        # les pastilles des observatoires sous la carte (Lou, 2026-11-15)
        RX(r'\+I button button(?: disabled)?'), RX(r'\+D button data-map-observatoire=.+')),
        must=('-S /assets/home.js', RX(r'\+D a data-map-observatoire=.+'))),
    Rule('pages', rf'{ROLE} (?!/INCONNU/){OBS}/(?:home)?', HOMES, lines=(
        '+L stylesheet /bundles/irsteabdohconsult/css/homes.css', '+S /bundles/irsteabdohconsult/js/read-more.js',
        RX(r'\+I button button(?: disabled)?'), RX(r'\+D button data-map-site=\d+'), RX(r'\+T Sites ?: .+'),
        # le chevron des sites qui ont des chroniques, vers la recherche de leurs données (Lou, 2026-11-25)
        RX(rf'\+A {OBS}/nos-donnees\?site=.+'),
        RX(r'\+D li data-map-site=\d+'),
        RX(r'\+D div data-read-less=(?:Réduire|Show less) data-read-more=(?:Lire la suite|Read more)'),
        RX(r'[-+]D div data-interval=false data-ride=carousel'), RX(r'[-+]G /uploads/\S+ .+'), RX(r'[-+]A #photos'),
        RX(r'[-+]D a data-slide=(?:prev|next)'),
        # le logo de l'observatoire à côté de son nom, et ceux des partenaires dans leur onglet, sans texte : le nom est
        # à côté (Lou, 2026-11-15)
        RX(r'\+G /uploads/\S+'),
        # l'onglet des conditions d'utilisation, passées au pied de toutes les pages (Lou, 2026-11-15 ; expected.footer)
        RX(rf'-A {OBS}/terms_of_use'), RX(r"-T (?:Conditions d'utilisation|Terms of use)"),
        # celui des gestionnaires (Lou, 2026-11-16), dont le contenu sort de la comparaison des pages (MANAGERS_TAB) et
        # que « nouveau seul : gestionnaires » compare à la page de contact de l'ancien
        '+A #managers', RX(r'\+T (?:Gestionnaires|Managers)'), '+D a data-toggle=pill',
        '+S /bundles/irsteabdohconsult/js/home-tabs.js',
        # la liste « Pour citer ces données… » de la présentation, que remplace le bloc de citation, hors de la
        # comparaison des pages (CITATION_BLOCK) et lu par « nouveau seul : citations » (Lou, 2026-11-16)
        RX(r'-T (?:Pour citer ces données vous pouvez vous référer (?:au DOI suivant|aux DOI suivants) :'
           r'|To cite these data you may refer to the following DOIs?:)'),
        RX(r'-A https?://(?:dx\.)?doi\.org/\S+'), RX(r'-T .*https?://(?:dx\.)?doi\.org/\S+')),
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
    # La page de contact d'un observatoire renvoie à l'onglet de ses gestionnaires, sur son accueil ; la notice et le
    # support sont sur la page d'aide de tout BDOH (Lou, 2026-11-16)
    replaced('pages', rf'{ROLE} {OBS}/contact', MANAGERS, 301, RX(rf'\+redirection {OBS}/#managers'),
             RX(rf'\+T Redirecting to {OBS}/#managers\.?'), RX(rf'\+A {OBS}/#managers')),
    # Une chronique visible montre ses points de contrôle à tous. Une cachée répond 403 sans droit, ce que vérifie
    # « nouveau seul : ouverture », de `state` : cette règle ne distingue pas les deux.
    Rule('pages', rf'(?:anonyme|utilisateur|utilisateur-chronique) {OBS}/[^/ ]+/[^/ ]+', OPEN, lines=(
        '+T Points de contrôle (?) : Aucun', RX(r'\+T Points de contrôle \(\?\) : \d+ points?'),
        RX(r'\+T (?:du|au) \d\d/\d\d/\d{4} \d\d:\d\d:\d\d UTC'))),
    Rule('pages', rf'{ROLE} {OBS}/login', LINK, lines=LOGIN_PAGE),
    Rule('pages', rf'{ROLE} {OBS}/user/new', OPEN, lines=NEW_ACCOUNT),
    Rule('pages', rf'{ROLE} {OBS}/user/new', PRIVACY, lines=PRIVACY_FORM, must=PRIVACY_FORM),
    Rule('pages', rf'{ROLE} {OBS}/user/(?:new|edit)', PRIVACY, lines=CONTACT_FIELDS),
    # Le formulaire réaffiché après une inscription refusée
    Rule('accounts', r'inscription, .*', PRIVACY, lines=CONTACT_FIELDS),
    Rule('admin', rf'(?:{ROLE} {OBS}/admin/utilisateur/(?:create|\d+/edit)|utilisateur : .*)', PRIVACY, lines=ADMIN_CONTACT_FIELDS),
    # L'adresse des conditions d'un observatoire, où l'ancien servait un PDF, renvoie à la page commune à tout BDOH
    # (Lou, 2026-11-15), que « nouveau seul : conditions d'utilisation » lit ; compare.py n'en compare que les deux
    # premières lignes, le statut et le type ou la redirection
    Rule('pages', rf'{ROLE} {OBS}/terms_of_use', TERMS,
         lines=('-statut 200', '-type application/octet-stream', '+statut 301', '+redirection /terms_of_use'),
         must=('+redirection /terms_of_use',)),
    # Les fiches envoyées à Theia citent cette page, et non plus l'adresse de l'observatoire (Mapper::mapDataConstraint) ;
    # la page du contrôle des fiches les cite dans une ligne (TERMS_PAGE)
    Rule('theia', r'contrôle .*', TERMS, rewrite=lambda lines: [TERMS_PAGE.sub(r'\1terms_of_use', line) for line in lines]),
    Rule('theia', r'.*', TERMS, lines=(RX(r'-\s*"urlDataPolicy": "<site>\\?/[A-Z0-9_-]+\\?/terms_of_use",?'),
                                       RX(r'\+\s*"urlDataPolicy": "<site>\\?/terms_of_use",?'))),
    Rule('pages', rf'{ROLE} {OBS}/dataset/[^/ ]+', TERMS, lines=("-T Data Constraint :", "+T Conditions d'accès et d'utilisation :")),
    Rule('pages', rf'{ROLE} /translations/messages\.js', TERMS, lines=(translation('[-+]', 'dataConstraint'),)),
    Rule('pages', rf'{ROLE} /translations/messages\.js', PRIVACY, lines=(
        translation(r'\+', 'security.utilisateur.create.info.personalData'),
        translation('[-+]', 'security.utilisateur.newMailUtilisateur.body.step3'))),
    Rule('pages', rf'{ROLE} {OBS}/user/edit', LINK, lines=EDIT_ACCOUNT),
    replaced('pages', rf'{ROLE} {OBS}/resetting/(?:request|check-email)', LINK, 301,
             RX(rf'\+redirection {OBS}/login'), RX(rf'\+T Redirecting to {OBS}/login\.?'), RX(rf'\+A {OBS}/login')),
    Rule('pages', rf'{ROLE} {OBS}/besoin/new', OPEN, lines=RESERVED_REQUEST),
    # Ce qui accompagne les chroniques réservées (Lou, 2026-11-19 ; reserved_checks vérifie chaque cas) : dans la liste
    # des chroniques d'une station ou d'un jeu de données, la mention en clair à la place de la bulle du cadenas
    Rule('pages', rf'{ROLE} {OBS}/(?:station|dataset)/[^/ ]+', RESERVED,
         lines=('-D i data-placement=top data-toggle=tooltip', "+T Réservée aux membres de l'observatoire")),
    # Le lien du menu du compte, renommé ; il quitte le menu d'un observatoire sans chronique réservée
    *(Rule(mode, r'.*', RESERVED,
           lines=('-T Demander un accès aux données', "+T Accès des membres de l'observatoire", RX(rf'-A {OBS}/besoin/new')))
      for mode in ('pages', 'accounts')),
    # Le courriel de bienvenue d'un observatoire sans chronique réservée ne dit que ceci, sans la demande d'accès ni le
    # contact des gestionnaires
    Rule('accounts', 'courriels', RESERVED, lines=(
        RX(rf'-(?:T )?Demander un accès aux données : <site>{OBS}/besoin/new'), RX(rf'-(?:T )?Contacter un gestionnaire : <site>{OBS}/contact'),
        RX(rf'-A <site>{OBS}/(?:besoin/new|contact)'),
        '+Les données ouvertes se téléchargent sans compte.', '+T Les données ouvertes se téléchargent sans compte.')),
    # La page d'une chronique réservée, pour qui n'y a pas droit : toujours refusée, elle dit ce qu'est la chronique
    # (sans ligne obligatoire : une telle règle s'imposerait à toutes les pages de chroniques)
    Rule('pages', rf'{ROLE} {OBS}/[A-Z0-9_-]+/[A-Z0-9_-]+', RESERVED, lines=(
        '-T Accès refusé', RX(r"-T Vous n'avez pas la permission d'accéder à cette information ou d'effectuer cette action\."),
        RX(r"\+T (?:BDOH - .* - )?\S+ / \S+ Chronique réservée à l'observatoire"),
        "+T Cette chronique est réservée aux membres de l'observatoire, pour son travail interne.",
        RX(r"\+T BDOH n'en assure pas la diffusion : pour l'obtenir, adressez-vous aux partenaires qui la portent\.(?: Son producteur : .+\.)?"),
        RX(r'\+T Ses métadonnées restent visibles sur la page de sa station, \S+\.'), RX(rf'\+A {OBS}/station/\S+'),
        "+T Pour en savoir plus sur cette chronique, vous pouvez écrire aux gestionnaires de l'observatoire.", RX(rf'\+A {OBS}/#managers'),
        "+T Membre de l'observatoire ? Connectez-vous, puis demandez-en l'accès depuis le menu de votre compte.", RX(rf'\+A {OBS}/login'),
        "+T Membre de l'observatoire ? Demandez-en l'accès depuis le menu de votre compte.")),
    replaced('pages', rf'{ROLE} /select_lang/(?:en|fr)', 'Erreurs 500 sur des pages publiques', 302,
             '+redirection /', '+T Redirecting to /', '+T Redirecting to /.', '+A /'),
    # L'adresse d'une chronique inconnue, sous une station inconnue ou connue : 404 au lieu de l'erreur interne
    Rule('pages', rf'{ROLE} {OBS}/(?:INCONNUE|_STATION_CODE_|[A-Z0-9_-]+)/(?:INCONNUE|_CHRONIQUE_CODE_)', 'Erreurs 500 sur des pages publiques',
         lines=('-statut 500', '+statut 404', '-T Kaboom !', '+T Pas trouvé !', RX(r'-T Le serveur a eu un gros souci ! Merci de réessayer .*'),
                "+T La page ou l'information que vous cherchez n'est pas à cet endroit."), must=('+statut 404',)),
    Rule('pages', rf'{ROLE} /', OPEN, lines=(
        RX(r"-T Ces données sont utilisées à des fins scientifiques .* Les données sont accessibles à tous gratuitement moyennant une inscription sur le site et le respect des conditions d'utilisation\."),
        RX(r"\+T Ces données sont utilisées à des fins scientifiques .*\.Les données ouvertes sont accessibles à tous, gratuitement et sans inscription, sous Licence Ouverte Etalab 2\.0 ; quelques chroniques, données intermédiaires d'un observatoire, sont réservées à ses membres\."))),
    Rule('pages', rf'{ROLE} /translations/messages\.js', OPEN,
         lines=(translation('-', GONE_KEYS), translation(r'\+', NEW_KEYS), translation('[-+]', CHANGED_KEYS))),
    Rule('pages', rf'{ROLE} /translations/messages\.js', STATISTICS, lines=(translation(r'\+', STATISTICS_KEYS),)),
    # Les clés de l'en-tête : « Consultation des données » devenu « Recherche avancée », le titre du portail sans
    # guillemets, le lien vers le site d'un observatoire
    Rule('pages', rf'{ROLE} /translations/messages\.js', HEADER, lines=(
        translation('-', 'dataAccess'), translation('[-+]', 'home.title'), translation(r'\+', 'observatoryWebsite'),
        # la ligne des liens du pied de page (Lou, 2026-11-16)
        translation(r'\+', 'footer.help | footer.legal | footer.accessibility | footer.sourceCode'))),
    Rule('pages', rf'{ROLE} /translations/messages\.js', SEARCH_NAME, lines=(translation('[-+]', 'advancedSearch'),)),
    # Les flèches des photos d'un observatoire, et le libellé des pastilles du portail (Lou, 2026-11-15)
    Rule('pages', rf'{ROLE} /translations/messages\.js', HOMES,
         lines=(translation(r'\+', 'photo.previous | photo.next | map.observatories | map.observatoryPage | map.siteSearch'),)),
    # Les blocs de citation (Lou, 2026-11-16)
    Rule('pages', rf'{ROLE} /translations/messages\.js', CITATIONS, lines=(
        translation(r'\+', 'cite.observatoryTitle | cite.datasetTitle | cite.partOf | cite.otherDois'),
        translation('-', 'citeWithDoi | citeWithDois'))),
    # « Bienvenue », après le nom d'un observatoire, laisse la place à « Observatoire » (Lou, 2026-11-16)
    Rule('pages', rf'{ROLE} /translations/messages\.js', TITLES, lines=(translation('-', 'welcome'),)),
    # L'onglet des gestionnaires ; les textes de la page de contact qui passent sur la page d'aide (Lou, 2026-11-16)
    Rule('pages', rf'{ROLE} /translations/messages\.js', MANAGERS,
         lines=(translation(r'\+', 'managers | managers.login(%link%)'), translation('-', 'userManual(%link%) | technicalContact'))),
    # Le format CSV : son nom, et l'aide de la fenêtre d'export qui le décrit
    Rule('pages', rf'{ROLE} /translations/messages\.js', CSV_FORMAT,
         lines=(translation(r'\+', 'export.Csv'), translation('[-+]', 'measure.export.help-content'))),
    # Son option dans la fenêtre d'export (d'une chronique, d'une station, d'un jeu de données, des favoris)
    # (accounts et admin ne comparent pas de fenêtre d'export : « Règle sans écart » le 13 et le 15 novembre)
    Rule('pages', r'.*', CSV_FORMAT, lines=CSV_OPTION),
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
    # « crée le » devenu « créé le », dans la ligne du calcul d'une chronique calculée (rapport, puis README)
    Rule('exports', r'bdoh:export:.*', REVIEW,
         rewrite=lambda lines: [review_text(line) if line.startswith('calcul ') else line for line in lines]),
    Rule('exports', r'bdoh:export:.*', OPEN, lines=(
        RX(r'-# Ignoring (?:ORACLE-)?ConditionsGeneralesUtilisation\.pdf'),
        '+# Ignoring Licence-Ouverte-2.0-fr.pdf', '+# Ignoring Open-Licence-2.0-en.pdf', '+'),
        must=('+# Ignoring Licence-Ouverte-2.0-fr.pdf', '+# Ignoring Open-Licence-2.0-en.pdf')),

    # --- imports -----------------------------------------------------------------------------------
    # Le symbole de chargement au lieu de l'image animée, dans les fenêtres d'attente des étapes de l'import (rien d'autre
    # ne change) : « Symbole de chargement » dans dev/differences.md
    Rule('imports', r'.*', 'Symbole de chargement', lines=('-G /images/loader.gif',)),
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
    Rule('accounts', 'inscription, (?:téléphone trop court|adresse sans MX|adresse mal formée|adresse déjà prise|nom manquant)',
         PRIVACY, lines=PRIVACY_FORM, must=PRIVACY_FORM),
    Rule('accounts', "demande d'accès, page", OPEN, lines=RESERVED_REQUEST, must=RESERVED_REQUEST[-1:]),
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
        '+A <lien de connexion>', '+')),
    # Les articles 39 et 40 de la loi Informatique et Libertés, abrogés en 2018, cèdent la place à l'information du RGPD
    Rule('accounts', 'courriels', PRIVACY, lines=(
        f'-{RIGHTS_NOTICE}bdoh.support@lists.irstea.fr.', f'-T {RIGHTS_NOTICE}bdoh.support@lists.irstea.fr.',
        f'+{PRIVACY_NOTICE}', f'+T {PRIVACY_NOTICE}')),
    # L'état des comptes que crée le mode : account_rules, avec les identifiants de la passe.

    # --- admin -------------------------------------------------------------------------------------
    # Le libellé de la contrainte d'accès d'un jeu de données, en français (dev/differences.md)
    Rule('admin', rf'{ROLE} {OBS}/admin/dataset/.*|dataset : .*', TERMS,
         lines=('-T Data Constraint', "+T Conditions d'accès et d'utilisation")),
    # L'astuce de saisie des dates passe dans la bulle des champs, sur la page des barèmes aussi
    Rule('admin', rf'{ROLE} {OBS}/admin/transformation', GRAPH, lines=DATE_TIP),
    # Sous chacun des deux champs des photos, le formulaire d'un observatoire dit leur licence
    # (les étapes qui renvoient ce formulaire aussi : modification, couleurs, fichiers joints d'un observatoire)
    Rule('admin', rf'{ROLE} {OBS}/admin/observatoire/(?:create|\d+/edit)|observatoire : .*|fichiers joints : .*observatoire.*',
         PHOTOS, lines=(PHOTO_HELP,)),
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
