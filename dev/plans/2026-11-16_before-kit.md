# Avant l'envoi du kit : la 0.7.1

État : terminé le 2026-11-16 pour la 0.7.1, publiée et installée ; ses points 10 et 12, et l'étape 8, se poursuivent dans [2026-11-16_reopening.md](2026-11-16_reopening.md), qui suit l'avancée. Ce qui reste avant d'envoyer le kit des essais aux gestionnaires, convenu avec Lou dans la nuit du 15 au 16 novembre, point par point. Il prend la suite de « Reste à faire » de `dev/plans/2026-11-11_interface.md`, élargi : pages générales, citations, export, deux migrations de données. Le kit attend que ce soit fait proprement, quitte à partir un jour plus tard (Lou : « il faut faire les choses proprement, et je vais pas leur faire faire 10 tests »).

## Où on en est

- **Fait, sans passe encore** (16 novembre, Suivi) : étapes 1 à 4 de l'ordre de travail, et le message du kit (étape 6, sauf le second onglet des tableurs). Ce qui était « à revoir dans 4df998b » l'est : onglets sur la ligne du nom, bloc de citation, « ? » partout vers la page d'aide.
- **Passes faites** sur le code de ces étapes (étape 7, Suivi) : tous les modes et `make browser-check` passent.
- **Publié** : la 0.7.1, le 16 novembre, sur le code de ces passes, pour que Lou montre la dernière version en ligne.
- **Reste** : la passe de relecture du contenu et sa migration globale (point 10, à part, avec Lou), le point 12, puis l'étape de Lou (étape 8) : suivis désormais dans `dev/plans/2026-11-16_reopening.md`. Le délai et le message du kit sont à revoir après le point de Lou avec sa responsable (`ROADMAP.md`, « En cours »).
- Le poste a dans `public/uploads/` les images de la production, pour que Lou juge les accueils : tout sauf `CEREGE-logo.gif`, seul de l'état d'origine. Copies dans `../bdoh_reference/uploads/` et `bdoh-uploads-prod.tar.gz`. Mises de côté le temps de chaque passe d'`admin`, puis remises.

## Méthode

- Les passes à la fin, une fois les changements faits (`dev/decisions.md`, « Essayer une passe, et écrire à côté ») ; entre-temps, les tests de l'outil à chaque règle changée, `lint:twig`, `lint:container`, et des captures.
- Ce qui se voit se montre à Lou en captures avant d'aller plus loin ; elle tranche. Les captures se font sur le poste (base réduite, Playwright de `.python_env`), sans erreur de la console.
- Après un changement de gabarit ou de traduction : `cache:clear` ; de `public/` d'un bundle (`stations-map.js`, `homes.css`) : `assets:install` (les bundles sont copiés, pas liés) ; d'une feuille LESS : effacer `public/css/themes/*.css`.

## Les décisions (Lou, nuit du 15 au 16 novembre)

### 1. Ce qui vaut pour tout BDOH, ce qui tient à un observatoire

Deux niveaux, nettement séparés, sans insister au-delà : un observatoire a ses gestionnaires, un site web a ses responsables.
- **Tout BDOH**, sur toutes les pages, au « ? » de la barre et au pied de page : l'aide, le contact du support, les conditions d'utilisation, les mentions légales, l'accessibilité, le code source.
- **Un observatoire**, sur ses pages seulement : sa présentation, ses partenaires, ses gestionnaires, sa citation.

### 2. La page d'aide

- `/help`, sans observatoire dans l'adresse, comme `/terms_of_use` (`MainController`). Le « ? » y mène sur toutes les pages, portail compris : une page, pas de menu déroulant.
- Pour l'instant : la notice d'utilisation (`/user_manual`, le PDF) ; le contact du support, `bdoh.support@listes.inrae.fr`, pour le site et l'infrastructure ; une phrase qui renvoie les questions scientifiques aux observatoires, chez leurs gestionnaires (onglet « Gestionnaires » de leur accueil).
- Plus tard : une aide intégrée au site (`ROADMAP.md`).
- Comparaison : le lien de l'aide change d'adresse dans l'en-tête de toutes les pages et apparaît sur le portail (`expected.header`) ; la page, en « nouveau seul ».

### 3. Le pied de page, les mentions légales, l'accessibilité

Sur le modèle de j2000.inrae.fr (relevé le 16 novembre : trois liens au pied, « Mentions légales », « Accessibilité : non conforme », « Code source (GPL-3.0) » ; la déclaration d'accessibilité est une section de la page des mentions légales, `#accessibilite`).
- **Pied de page** : une ligne de liens, la même partout : Aide et contact · Conditions d'utilisation · Mentions légales · Accessibilité : non conforme · Code source (AGPL-3.0) ; puis le bloc Marianne et INRAE. Les séparateurs en CSS, pour ne pas ajouter de texte que la comparaison lirait.
- **Mentions légales** : une page, `/legal`, qui reprend le paragraphe d'aujourd'hui (`Core/mentionLegale.html.twig` : éditeur, directeur de publication, responsable de la publication, hébergeur, code source), en sections comme j2000 : Éditeur, Publication, Hébergement, Données personnelles, Crédits, Accessibilité.
- **Données personnelles**, rien que ce qu'on sait : les comptes (ce que dit l'inscription) ne sont jamais supprimés (le champ « fin d'accès » n'a aucun effet : `Utilisateur::isAccountNonExpired` rend toujours vrai, comme dans l'ancien) ; journaux de l'application gardés 10 jours (`monolog.yaml`, `max_files: 10`, dont `security.log`) ; journaux d'Apache gardés 14 jours (`/etc/logrotate.d/apache2` de la VM : `daily`, `rotate 14`, relevé par Lou le 16 novembre) ; seuls cookies, la session et la connexion ; aucune mesure d'audience ; contact : la déléguée à la protection des données d'INRAE (`cil-dpo@inrae.fr`, comme j2000). La durée de conservation des comptes reste à fixer avec elle (`ROADMAP.md`, « Suites de la passe juridique ») : ne pas l'inventer.
- **Crédits** : code sous AGPL-3.0 (GitHub) ; polices Inter et Raleway (SIL OFL), icônes Material Symbols (Apache 2.0), Leaflet, fond de carte de l'IGN ; logos à leurs titulaires.
- **Accessibilité** : obligatoire, donc dans cette version, pas plus tard (Lou). « Ce site est non conforme au RGAA ; il n'a pas encore fait l'objet d'un audit. » Signaler une difficulté : le support. Sans réponse satisfaisante : le Défenseur des droits. Base : décret 2019-768, lu dans des sources secondaires le 16 novembre (marche-public.fr, sk-web.fr) ; vérifier le texte sur Légifrance en l'écrivant. L'audit et les corrections, chantier à part : `ROADMAP.md`.
- Comparaison : `compare.py` retire le paragraphe des mentions de chaque page (`LEGAL_NOTICE`) ; le pied de page de l'ancien se réécrit comme le nouveau (`expected.footer`, qui ajoute déjà le lien des conditions) ; les pages `/help` et `/legal` en « nouveau seul ».

### 4. L'accueil d'un observatoire

- **Onglets** Présentation, Partenaires, Gestionnaires : sur la ligne du nom, à droite, comme avant la 0.7.1 (`nav-pills pull-right`) ; le logo reste petit, à gauche du nom (4df998b). Le filet passe sous le logo et le nom, et s'arrête avant les onglets, comme dans la 0.7.0 (Lou, 16 novembre : un filet sous les onglets n'est pas « comme avant »).
- **Onglet « Gestionnaires »** (le mot de BDOH) : leurs noms pour tous, leur adresse pour qui est connecté, comme la page de contact de l'ancien, et on le dit : « Connectez-vous pour voir leur adresse. » Un formulaire de contact à la manière de Dataverse, sans montrer l'adresse : plus tard (`ROADMAP.md`, Modernisations).
- **`/{observatoire}/contact`** reste valide : il renvoie à l'accueil, onglet Gestionnaires ouvert (l'adresse porte l'onglet, un petit script l'ouvre). Le courriel de bienvenue, qui y mène (`askManager`), y mène toujours.
- La liste « Pour citer ces données… » (déjà dans l'ancien) et l'encart de 4df998b laissent la place au bloc de citation (point 5).
- Comparaison : l'onglet des gestionnaires se retire de la page avant de comparer, et une vérification « nouveau seul » le relit (les noms de la page de contact, les adresses selon la connexion) ; les pastilles, dans la règle des accueils.

### 5. Les blocs de citation

Comme Dataverse : un bloc par sorte d'objet, de même forme, en haut de sa page, sans ambiguïté sur ce qu'on cite. Une citation ne porte jamais d'adresse électronique, et les gestionnaires n'en sont pas les auteurs.
- **Observatoire**, sous la ligne du nom, au-dessus de la carte, court. Avec un DOI principal : la citation officielle (point 6), dont les auteurs sont ceux que l'observatoire a déclarés chez Recherche Data Gouv. Sans : « Observatoire X. BDOH, INRAE. https://…/X/home, données téléchargées le [date]. » (l'adresse de son accueil dans BDOH, pas son propre site).
- **Jeu de données**, en haut de sa page, même forme. Avec son propre DOI : la citation officielle. S'il emprunte le DOI de son observatoire, ou n'en a pas : sa citation composée par BDOH (titre, observatoire, adresse de sa page), puis « Fait partie de : » et la citation de l'observatoire.
- **Autres DOI**, sous le bloc de l'observatoire : ceux qu'il a enregistrés sans qu'ils soient son DOI principal ni celui d'un de ses jeux, chacun avec sa citation officielle.
- **Le README d'un export** reprend la citation de l'observatoire.
- La colonne des jeux de données, en bas de l'accueil, mène à leurs pages : pas de citations de jeux sur l'accueil.

Comment c'est construit (code et base complète, 16 novembre) : chaque DOI appartient à un observatoire (`doi` : identifiant, description écrite à la main) ; un observatoire désigne un « DOI principal » (`doiprincipal_id`, ajouté pour Theia, qui veut un DOI par producteur ; aucune page ne l'affiche) ; un jeu pointe vers un DOI de son observatoire (`dataset.doi_id`). Les DOI `10.17180/obs.<nom>` sont ceux des observatoires : chez DataCite, des fiches de Recherche Data Gouv, une par observatoire. 20 jeux sur 28 ont un DOI ; OTHU n'en a aucun.

```
Observatoire       DOI de l'observatoire          principal   jeux qui l'empruntent   DOI propres à des jeux   autres DOI
Arc-Isère          OBS.ARC-ISERE                  oui         0                       2                        1 article (10.1002/hyp.14044), pris comme DOI d'un jeu
Draix              obs.draix                      oui         7 sur 7                 0
Lônes de l'Ain     OBS.LONES-AIN                  non         0                       0
OSR                OBS.OSR                        oui         0                       1
OHM-CV             OBS.OHM-CV.ARDECHE, .GARDONS   non         0                       0
ORACLE             obs.oracle                     non         1 sur 1                 0                        2 jeux hébergés hors de BDOH (10.15454/9PUYPN, M7OK9E)
Réal Collobrier    obs.real                       non         3 sur 3                 0
Ardières-Morcille  aucun                          -           -                       0                        1 article décrivant le site (10.1002/hyp.14384)
Yzeron             OBS.YZERON                     oui         0                       4
Guyane, Haute-Loire, TPG, OTHU : aucun DOI. Des identifiants sont écrits avec « https://doi.org/ », d'autres nus.
```

### 6. La citation officielle

- Le serveur la demande à doi.org (`Accept: text/x-bibliography; style=apa; locale=fr-FR`, ou `en-US`), une fois par DOI, et la garde un mois (cache de Symfony) ; Guzzle est déjà là (envoi vers Theia). Si doi.org ne répond pas : la citation composée par BDOH. Le plus robuste et le plus simple (Lou : « on essaie le plus robuste et simple ») : le visiteur n'appelle aucun tiers, aucune colonne en base, le style se règle.
- doi.org et non l'API de DataCite : les articles sont enregistrés chez Crossref (`10.1002/hyp.14044` absent de DataCite, vérifié le 16 novembre), et doi.org répond pour les deux (documentation DataCite, « content resolver »).
- La réponse peut porter de petites balises (`<scp>` vu dans celle d'un article) : la réduire à du texte et de l'italique.
- Le réseau du développement n'a pas d'accès à l'extérieur : la comparaison passera par le repli. Prévoir de quoi éprouver l'autre chemin (une réponse servie par un faux serveur, ou une entrée de cache posée).

### 7. Les titres des pages

Station, chronique, jeu de données s'écrivaient `Type « nom »`, en italique (`Station/show`, `Chronique/show`, `Dataset/show`). Essayé d'abord : le type en petit, gris, au-dessus du nom ; refusé par Lou le 16 novembre, après l'avoir vu : il ressemble au fil d'Ariane et nuit à la lecture. Retenu (Lou) : le nom seul, puis ce qu'est la page en petit, à la place de « Bienvenue » sur l'accueil d'un observatoire : « Observatoire », « Station », « Chronique », « Jeu de données ». Et le même espace entre chaque titre et son filet, celui de l'accueil d'un observatoire, sur toutes les pages, aide, conditions et mentions légales comprises, qui prennent un titre sur filet.

### 8. La fenêtre d'export

- Par défaut : toute la période, de la première à la dernière mesure, et le CSV (BDOH et Qtvar restent au choix ; garder l'ordre des options, que la vérification des favoris de `accounts` lit).
- Vérifier chaque porte d'entrée : page d'une chronique et bouton de son graphique (qui pose sa période), page d'une station (une fenêtre pour toutes, une par chronique), page d'un jeu de données (de même), favoris : mêmes formats, types, pas et valeurs par défaut selon les chroniques.
- Soupçon à vérifier dans un navigateur : `export-modal.js` crée le widget au premier `show.bs.modal` de la page seulement (`$(document).one(…)`, hérité de l'ancien) ; sur une page qui a plusieurs fenêtres, la deuxième ouverte n'aurait ni sélecteur de dates ni validation. Essai : ouvrir l'export d'une chronique dans la liste d'une station, fermer, ouvrir celui d'une autre, cliquer sur le bouton de début. Si c'est le cas, corriger (`.on`, en ne créant le widget qu'une fois par fenêtre) et l'inscrire dans `dev/differences.md`.
- Les valeurs par défaut se posent dans le JavaScript (`setDateRange` à la création, que le bouton du graphique écrase ensuite) et l'option `selected` du CSV dans le gabarit, que `pages` verra (`CSV_OPTION`).

### 9. Le nom de l'archive

Pas de point dans un nom de fichier (Lou : il se lit comme une extension) ; ni `+`, qui devient une espace dans une adresse.
- Des champs `clé-valeur` séparés par `_` ; la clé est ce qui précède le premier `-`.
- La station et la chronique en deux champs, `sta-` et `chr-` (le code d'une chronique n'est pas un nom de variable : une station peut avoir deux chroniques de la même grandeur).
- Dans une valeur, rien que des lettres, des chiffres et `-` : le reste devient `-`, dont le `_` des 4 codes de station qui en ont un. Le nom n'a qu'à être lisible et sûr ; les codes exacts sont dans le README.
- Dates compactes (ISO 8601 de base).

```
BDOH-export_obs-DRAIX_sta-LAVAL_chr-Q_type-identical_format-csv_period-20200101-20241231_on-20261116.zip
BDOH-export_obs-DRAIX_n-12_type-mean_step-1day_format-csv_period-20200101-20241231_on-20261116.zip
```

Ce qui le lit : `ExportController::archiveName`, `compare.py` (`open_data_checks`, le nom de l'archive sans compte), `dev/browser/check.py` (`export_to_file`), l'entrée « Le nom de l'archive d'un export » de `dev/differences.md`.

### 10. La relecture du contenu, et une migration globale

Revu par Lou le 16 novembre : plutôt que deux migrations isolées, une passe à part de relecture du contenu de la base, avant l'envoi du kit. Les erreurs mineures qu'elle relève (forme, saisie évidente, les deux points ci-dessous) se corrigent dans une seule migration globale ; les erreurs plus importantes croisées en route partent aux gestionnaires avec le kit (second onglet de leur tableur), pour qu'ils les tranchent. Ce qui suit en est le point de départ.


La forme seulement, jamais le contenu. Chacune écrite à la main, essayée sur la base complète anonymisée, avec son retour arrière. Elles passent sur la VM avec la mise à jour vers la 0.7.1, qui sauvegarde la base avant (`/var/backups/bdoh-kept/`).
- **Les DOI principaux évidents** : Lônes de l'Ain (`10.17180/OBS.LONES-AIN`), ORACLE (`10.17180/obs.oracle`), Réal Collobrier (`10.17180/obs.real`), s'ils n'en ont pas déjà un ; le retour arrière les remet à vide. OHM-CV en a deux, un par bassin : la question part aux gestionnaires avec le kit.
- **Les descriptions des observatoires** (Lou : une passe dédiée aux défauts de forme laissés par la saisie). Mesuré sur la base complète le 16 novembre : seules les descriptions d'observatoire en ont (sites, stations et jeux de données sont propres). Ardières-Morcille : un copier-coller de Word, 165 000 caractères pour 16 000 de texte, 20 commentaires, environ 2 000 balises de Word. Lônes de l'Ain et TPG : un paragraphe vide chacun (un grand blanc). Réal Collobrier et Yzeron : un double saut de ligne chacun. Retirer les balises de Word, les paragraphes vides, les sauts doublés. La preuve : le texte de chaque description, balises retirées et espaces ramenées à une, identique avant et après. L'ancienne version reste dans une table, pour le retour arrière. Avant d'écrire la migration : montrer à Lou les cinq accueils avant et après, en captures. L'anonymisation garde la forme des textes (`anon.scramble` : balises, entités, espaces), si bien que la base anonymisée suffit à l'essayer.
- La passe de relecture elle-même, sur les autres tables (sites, stations, chroniques, jeux de données, DOI, partenaires) : ce qui est évident et sans risque pour le contenu va dans la migration globale, le reste au tableur des gestionnaires (`dev/local/data-issues.md`, pour ce qui cite des données).

### 11. Les données après les essais

Un « point zéro » de la base juste après l'installation de la 0.7.1, avant l'envoi du kit (une sauvegarde dans `/var/backups/bdoh-kept/`). Les gestionnaires font leurs corrections pendant les essais, et on les garde : la base des essais devient la production, comme le message du kit le dit. Le point zéro ne sert qu'en cas de dégât. La restauration s'éprouve à part, dans une base séparée, avant l'envoi (`dev/deploy/README.md`, section 4 ; les commandes, avec la comparaison d'`inspect.sql`, dans `dev/local/notes-for-lou.md`).

### 12. Les photographies sous Licence Ouverte

Décidé par Lou le 16 novembre (`dev/decisions.md`, « Ouverture des données »)  ; sa réalisation se suit dans `dev/plans/2026-11-16_reopening.md`, point 7.
- Sous les deux champs des photos du formulaire d'un observatoire (`Admin/Data/Observatoire.php`) : la photo est diffusée sous Licence Ouverte 2.0, comme les données ; ne la déposer que si on en est l'auteur, ou avec son accord.
- La page des conditions, en français et en anglais : les photographies sortent des exceptions de la licence (restent les logos et les coordonnées des personnes). Les mentions légales, « Crédits » : les logos à leurs titulaires, les photographies sous Licence Ouverte.
- Le message du kit : une ligne pour que les gestionnaires vérifient les photos de leur accueil (17 dans la base complète, deux au plus par observatoire).
- Comparaison : l'aide des deux champs, au registre du mode `admin` ; les pages des conditions et des mentions légales, dans leurs vérifications « nouveau seul ».

## Ordre de travail

1. Pages générales : `/help`, `/legal` avec l'accessibilité, pied de page, « ? » partout. Fait le 16 novembre (Suivi).
2. Accueil d'un observatoire (onglets, Gestionnaires, `/contact`) et titres des pages. Fait le 16 novembre (Suivi).
3. Blocs de citation et citation officielle. Fait le 16 novembre (Suivi).
4. Fenêtre d'export (valeurs par défaut, portes d'entrée) et nom de l'archive. Fait le 16 novembre (Suivi).
5. La passe de relecture du contenu, à part (Lou, 16 novembre) : une migration globale pour les erreurs mineures, après les captures des accueils montrées à Lou ; les erreurs plus importantes au second onglet des tableurs.
6. Le kit : son message (le CSV par défaut, l'aide au « ? », l'onglet Gestionnaires ; et, à proposer à Lou, que le site s'ouvre aussi sans VPN depuis le réseau filaire d'un bureau INRAE, d'après elle le 16 novembre, quand le message dit aujourd'hui « que sous le VPN ») et le second onglet des tableurs (OHM-CV : quel DOI principal ; ORACLE : ses deux autres DOI ; Arc-Isère : un article pris comme DOI d'un jeu ; Ardières-Morcille : un article seul). Message fait le 16 novembre, à relire par Lou ; le second onglet attend la relecture du contenu.
7. Retirer les images de la production de `public/uploads/`, puis toutes les passes (`pages`, `admin`, `accounts`, `state`, `exports`, `theia`, `imports` ; leurs commandes, et `make mail-start` d'abord : `dev/plans/2026-11-04_validation.md`, « Commandes »), `make browser-check`, `CHANGELOG.md`, la 0.7.1. Passes faites le 16 novembre sur le code des étapes 1 à 4 (Suivi), et la 0.7.1 publiée ; après la migration globale, rejouer les modes qu’elle touche, puis publier (`dev/plans/2026-11-16_reopening.md`).
8. Lou : `vm-update` vers la 0.7.1 (`NOM=prod` une dernière fois), puis vers la version suivante (`dev/plans/2026-11-16_reopening.md`) ; le `.env` au nom `bdoh.inrae.fr` (fait le 16 novembre : `dev/plans/2026-10-26_deployment.md`, Suivi) ; le point zéro, juste avant l'envoi du kit ; la restauration éprouvée à part, `make test-kit` avec `--site https://bdoh.inrae.fr`, l'envoi.

## Suivi

- 2026-11-16, matin, **étape 7, les passes sur le code des étapes 1 à 4** (5f19cec, puis l'outil corrigé) : `pages` 489/0/0/1096/0 (les cinq de plus qu'au 16 à 0 h 17 sont les nouveaux « nouveau seul » : aide, mentions légales, gestionnaires, citations par le repli et par le faux doi.org, conformes), `exports` 40/0/0/384/0, `state` 79/0/0/2/0, `queue` 11/0/0/0/0, `imports` 77/0/0/42/0, `accounts` 49/0/0/17/0, `theia` 19/0/0/19/0 sur `bdoh`, `admin` 448/0/0/230/0. Aucune régression de BDOH ; la première série a relevé des défauts de l'outil, corrigés : deux lignes de l'onglet des gestionnaires au registre (`data-toggle=pill`, `home-tabs.js`) ; le DOI de la citation du README compté deux fois (`report_facts` saute « Comment citer ») ; le contrôle du pied de page d'`admin`, qui cherchait l'ancien texte ; et `theia`, qui n'appliquait pas les réécritures de l'en-tête et du pied de page à la page du contrôle des fiches, ni celle de l'adresse des conditions dans sa ligne JSON (`TERMS_PAGE`) : il n'avait pas été rejoué depuis le 11 novembre, avant l'en-tête du 12. `make browser-check` : 78 étapes ; trois défauts de l'essai, corrigés et rejoués : la carte du portail mesurée dans la page (elle défilait au survol d'une pastille, depuis l'espace sous les titres), les fichiers de l'export des favoris attendus en `.txt` (le CSV est le format par défaut).
- 2026-11-16, **étape 4, fenêtre d'export et nom de l'archive** : toute la période et le CSV par défaut (`export-modal.js`, option `selected` du gabarit) ; le widget créé pour chaque fenêtre à sa première ouverture (`.on` et `instance`) au lieu de la première seule. Portes d'entrée : seules la page d'une chronique (et le bouton de son graphique) et les favoris ont une fenêtre d'export ; celles des pages d'une station et d'un jeu de données sont désactivées dans l'ancien depuis sa première mise en production (`{% if false and … %}`) et le restent, si bien que le défaut du widget était latent. Nom de l'archive : `ExportController::archiveName`, `compare.py` (`open_data_checks`) et `dev/browser/check.py` (`export_to_file`) suivent. `make browser-check` : l'étape des valeurs par défaut passe.
- 2026-11-16, **étape 3, blocs de citation** : `Irstea\BdohBundle\Doi\Citations` (citation officielle de doi.org, gardée un mois dans `cache.doi`, un échec une heure ; citations composées ; autres DOI), `CitationExtension` pour les gabarits, macro `citationBlock` sur l'accueil d'un observatoire (sous la ligne du nom) et la page d'un jeu de données (sous le titre) ; le README reprend la citation de l'observatoire ; la liste des DOI de la présentation et l'encart de 4df998b partent ; la page des conditions renvoie aux blocs. Un jeu « emprunte » le DOI de son observatoire s'il est son DOI principal ou un DOI `10.17180/obs.`. Outil : `CITATION_BLOCK`, « nouveau seul : citations, repli de BDOH » et « … officielles, faux doi.org » (`dev/compare/doi-resolver.php`, `DOI_RESOLVER`), conformes à vide sur `bdoh_small`. Revu avec Lou avant : « Bienvenue » devient « Observatoire », et le type suit le nom sur les autres pages (le surtitre gris, refusé) ; le même espace entre chaque titre et son filet partout. Les migrations deviennent une passe à part de relecture du contenu (point 10).
- 2026-11-16, **étape 2, accueil d'un observatoire et titres** : troisième onglet « Gestionnaires » (noms pour tous, adresse une fois connecté, sinon « Connectez-vous pour voir leur adresse ») ; `/contact` renvoie en 301 à `/{observatoire}/#managers`, que `home-tabs.js` ouvre, la vue revenant sur la ligne du nom ; les onglets sur la ligne du nom, le filet sous le logo et le nom seulement, comme dans la 0.7.0 : deux essais faisaient passer le filet sous les onglets, Lou les a refusés (« la ligne s'arrêtait aux onglets »). Titres : le type en petit au-dessus, le nom seul. Outil : l'onglet sort des pages comparées (`MANAGERS_TAB`) et « nouveau seul : gestionnaires, onglet de l'accueil » le compare à la page de contact de l'ancien, sans compte et en simple compte (conforme sur `bdoh_small`, contre `reference-17`, à vide) ; `expected.titles` ; la redirection de `/contact` ; `merge` voit les administrateurs d'OTHU dans l'onglet ; `make browser-check` : le pied de page, l'aide, l'onglet ouvert depuis `/contact` (4 étapes passées). Les textes de l'ancienne page de contact (notice, support) quittent les traductions.
- 2026-11-16, **étape 1, pages générales** : `/help` et `/legal` (avec la déclaration d'accessibilité), le « ? » vers `/help` partout, la ligne des liens au pied de page, puis le bloc Marianne et INRAE ; les mentions sous chaque page passent sur `/legal`. Vérifié sur Légifrance : loi n° 2005-102, article 47 (mention visible en page d'accueil, déclaration atteignable, signalement) ; décret n° 2019-768, article 6 (contenu de la déclaration) ; voies de recours et adresses du Défenseur des droits, du modèle d'accessibilite.numerique.gouv.fr. Philippe Mauguin est toujours président-directeur général d'INRAE (renouvelé en octobre 2024). Outil : `expected.header` (l'aide vers `/help`, sur le portail aussi), `expected.footer` (la ligne avant le bloc), les clés `footer.*` de `messages.js`, « nouveau seul : aide » et « nouveau seul : mentions légales et accessibilité » ; tests de l'outil, `lint:twig`, `lint:container`, captures en français et en anglais. Sans passe (méthode).
- 2026-11-16, nuit : ouvert. Les décisions ci-dessus, prises avec Lou point par point ; les mesures de la base complète et de DataCite faites pour elles. Le travail commencé dans la nuit sur l'onglet des gestionnaires, l'encart de citation fusionné, les valeurs par défaut de l'export et un nom d'archive à point a été retiré : il suivait des choix revus depuis.
