# Journal des modifications

Format : [Keep a Changelog](https://keepachangelog.com/fr/1.1.0/). Numéros de version : [SemVer](https://semver.org/lang/fr/).

## Non publié

### Modifié

- Le graphique d'une chronique n'a plus de limite de 5 ans, et son bouton « Exporter » n'est plus bloqué au-delà : on affiche toute une chronique. Au-delà de 5 ans, les cumuls s'additionnent par mois (« mm/mois »), et une période de plus de 50 000 mesures s'allège en une seule lecture, avec le même dessin, pics compris.
- Dans les listes de chroniques et de stations, toute la ligne mène à sa fiche, et le survol le montre.
- En haut à gauche, « BDOH » remplace le logo de l'observatoire et mène au portail ; le site de l'observatoire s'ouvre d'une icône à côté du titre de son accueil.
- La barre du haut porte le choix d'un observatoire, celui qu'on consulte, l'aide et la langue ; celle du dessous, « Recherche avancée » (l'ancien « Consultation des données ») et le compte.
- Le fil d'Ariane commence par « BDOH », puis l'observatoire, au lieu de « Accueil », et toute page en a un, le portail compris : le contenu commence toujours au même endroit.
- Les titres du portail et de l'accueil d'un observatoire perdent leurs guillemets.
- Le pied de page s'allège : un filet, les logos sur une ligne, puis les mentions légales en petit.
- L'avis d'un graphique allégé tient en une ligne grise, et l'astuce de saisie des dates passe dans la bulle des champs.

### Ajouté

- À côté de chaque champ de date, un bouton ouvre le calendrier du navigateur ; la saisie au clavier reste.
- La bande sous le graphique se cale d'un bouton sur une semaine, un, trois ou six mois, un ou cinq ans, ou toute la chronique.
- La comparaison avec l'ancien BDOH passe aussi sur la base d'OTHU, dans tous ses modes : un cas que la base ne permet pas de jouer y est nommé, et les écarts admis prennent les identifiants de la base comparée.

### Corrigé

- Sur la page des droits d'un compte, ses demandes d'accès s'affichent dans l'ordre où elles ont été faites : elles sortaient dans un ordre que changeait une mise à jour.

## 0.4.1 - 2026-11-11

Des retouches de l'interface, après le regard de Lou.

### Modifié

- L'accueil d'un observatoire revient à trois colonnes, sites, paramètres et jeux de données, tassées en haut, la recherche avancée gardée au-dessus.
- La carte des stations zoome par niveaux entiers : entre deux, son fond était flou.

### Corrigé

- Les paragraphes vides d'une description d'observatoire ne creusent plus de grands blancs.

## 0.4.0 - 2026-11-11

Une interface modernisée, sans changer les pages ni leurs adresses : les accueils organisés autour de la carte, un habillage sur la charte d'INRAE, et un graphique qu'on parcourt directement.

### Modifié

- L'accueil du portail montre la carte de toutes les stations en tête, la présentation, puis une fiche par observatoire ; survoler une fiche allume ses stations sur la carte. La liste dont le survol remplaçait tout le panneau disparaît.
- L'accueil d'un observatoire montre la carte de ses stations en bandeau, avec un bouton par site qui allume les siennes, puis la présentation, qui coule autour des photos, puis la recherche avancée en tête des listes : sites et jeux de données d'un côté, paramètres deux fois plus larges, sur deux colonnes, de l'autre, les longues descriptions repliées. Sans station placée, plus de carte vide.
- La carte des stations a un fond plus doux, le plan de l'IGN atténué, avec le réseau hydrographique à l'échelle d'un observatoire ; elle se zoome en pinçant ou avec Ctrl et la molette, la molette seule faisant défiler la page.
- L'habillage suit la charte d'INRAE : titres en Raleway, texte en Inter, plus grand ; toutes les couleurs d'un observatoire tirées de sa couleur principale et rendues lisibles au besoin, liens compris ; barres et blocs à plat ; icônes d'un seul trait ; logo d'INRAE plus grand. Les observatoires restés sur l'ancienne couleur par défaut, et le portail, prennent le bleu pétrole d'INRAE.
- Le graphique d'une chronique s'affiche dès l'ouverture, sur sa dernière année, sur fond blanc, ses courbes dans des couleurs plus douces de la même famille que celles des qualités. Les lacunes et les mesures invalides s'y dessinent en bandes pâles sur toute la hauteur, au lieu d'un trait rouge.

### Ajouté

- Sous le graphique d'une chronique, une bande de toute la chronique, dont on glisse ou élargit la fenêtre pour choisir la période affichée ; Ctrl et la molette zooment sur le graphique.

## 0.3.5 - 2026-11-10

La carte des stations, pour que les gestionnaires l'essaient.

### Ajouté

- Une carte des stations, sur l'accueil du portail et sur celui de chaque observatoire, sur le plan de l'IGN : un point par station qui a une position, avec un lien vers sa page, et le nombre de stations sans position.

## 0.3.4 - 2026-11-10

Deux bogues de l'ancien BDOH corrigés, que les testeurs verront : les lacunes du graphique, et les logos de partenaires de même nom. Et des cas de plus dans la comparaison, pour l'administration.

### Corrigé

- Les lacunes du graphique d'une chronique et du visualiseur se dessinent à la hauteur voulue à chaque affichage : après un changement de période ou de chronique, elles pouvaient se dessiner à une autre hauteur, ou en pente (bogue de l'ancien BDOH).
- Deux partenaires de même nom dans deux observatoires ont chacun leur fichier de logo : ils partageaient le même, si bien que l'un remplaçait ou effaçait le logo de l'autre (bogue de l'ancien BDOH).
- L'image de mesure de couverture (`dev/coverage/`) installe PCOV pour PHP 8.5 : elle ne se construisait plus depuis la 0.3.3.

### Ajouté

- Un essai du navigateur de plus : les lacunes du graphique à la hauteur que demande le serveur, d'une période à l'autre.
- Les courriels d'une instance de la VM se règlent par une cible du Makefile (`make vm-mail`) : l'adresse de renvoi des essais, puis le relais, dont l'identifiant et le mot de passe se lisent au clavier.
- Des cas de plus dans la comparaison, pour l'administration : fichiers joints remplacés et retirés, thème d'un observatoire recompilé après un changement de couleurs, fiche d'une chronique qui a déjà des limites, bassin et cours d'eau supprimés ; et les shapefiles importés en gardant l'existant.

## 0.3.3 - 2026-11-10

Le développement sur les mêmes versions que la VM, et les commandes d'installation corrigées en installant la production. Le code de l'application est celui de la 0.3.1.

### Modifié

- L'application du développement tourne sur les paquets d'Ubuntu 26.04 (PHP 8.5, Node 22), ceux de la VM : tous les modes de la comparaison et les 62 étapes du navigateur y donnent les mêmes résultats que sous PHP 8.4.
- Installation sur la VM : le compte du mot de passe d'accès des essais s'appelle `test` ; `make vm-db` dit « aucune » quand l'empreinte des mesures n'a pas changé, et si les logos ont été déballés.

### Corrigé

- `make vm-mail-test` envoyait depuis `from@example.org`, l'expéditeur par défaut de Symfony, que le relais refuserait : il envoie depuis `bdoh@inrae.fr`, comme BDOH.

## 0.3.2 - 2026-11-10

L'installation sur la VM, par le Makefile, sur Ubuntu 26.04. Le code de l'application est celui de la 0.3.1.

### Ajouté

- L'installation et la mise à jour sur la VM par des cibles du Makefile, lancées dans un clone du dépôt (`make vm-…`, `dev/deploy/README.md`) : paquets d'Ubuntu 26.04, une instance par dossier, site, file de tâches et sauvegarde quotidienne. Une mise à jour passe à une version précise, arrête la file, sauvegarde la base avant ses migrations et la contrôle avant et après.

### Modifié

- La base du développement tourne sur les paquets d'Ubuntu 26.04 (PostgreSQL 18, PostGIS 3.6), ceux de la VM : les trois bases s'y restaurent identiques au bit près à celles de PostgreSQL 11.
- Le service de la file de tâches vise une instance (`config/deploy/bdoh-job-worker@.service`), et suit PostgreSQL 18.

## 0.3.1 - 2026-11-09

La 0.3.0, plus une régression corrigée, trouvée en élargissant les cas de la comparaison.

### Corrigé

- Vider une période d'une chronique discontinue la vide de nouveau, au lieu d'une erreur interne (régression de la 0.1.0).

### Ajouté

- Des cas de plus dans la comparaison, tirés de la mesure de couverture et de l'usage réel : droits sur un observatoire et ses sites, plages et chevauchements d'import, fichiers refusés, points de contrôle, transformations, pas d'export employés en production, visualiseur de chaque échantillonnage ; et 300 exports tirés au hasard, sans écart.

## 0.3.0 - 2026-11-06

Les données ouvertes, sur le portage éprouvé : une chronique visible se télécharge sans compte, sous Licence Ouverte, et la comparaison avec l'ancien BDOH n'admet plus que les écarts inscrits à son registre, avec lequel elle voit les 47 fautes remises dans le code. C'est l'état que verront les testeurs. Pas encore en service.

### Modifié

- Les données ouvertes s'ouvrent : une chronique visible se télécharge sans compte, sous Licence Ouverte Etalab, et ses points de contrôle se voient avec elle. Une chronique cachée reste fermée, sur demande de droit, comme avant.
- Un export se récupère par un lien, valable 7 jours, sur une page de suivi sans compte, par courriel avec un compte ; il n'y a plus de pièce jointe.
- Sans compte, un export porte sur 5 millions de mesures au plus, une adresse IP en demande 20 par heure au plus, et aucun n'est accepté quand 30 attendent déjà ; au-delà, il faut un compte. Les archives occupent 20 Go au plus (`EXPORTS_MAX_BYTES`) : les plus anciennes partent d'abord.
- Le zip d'un export porte la licence de ses chroniques : la Licence Ouverte 2.0, en français et en anglais, pour une chronique visible ; les conditions d'utilisation pour une chronique cachée, actualisées : INRAE, portée limitée à ces chroniques, RGPD.
- L'adresse de contact devient `bdoh.support@listes.inrae.fr`.

### Corrigé

- Après une suppression dans l'administration, on revient à la liste, et plus à une page « Pas trouvé » (régression de la 0.1.0).

### Ajouté

- Des parcours de bout en bout dans un vrai navigateur, comme les ferait quelqu'un, rejoués par `make browser-check` : inscription, lien reçu par courriel, demande d'accès, droit sur une chronique cachée, export jusqu'au fichier, import, suppression.
- Le registre des écarts admis avec l'ancien BDOH, sous une forme que la comparaison lit : tout autre écart fait échouer un mode (`dev/compare/expected.py`). Les 47 fautes remises dans le code sont toutes vues avec lui ; un nombre qui change de valeur, même au dernier chiffre, n'est jamais admis.
- Pendant les essais sur le vrai dump, les courriels peuvent être détournés, sauf vers une liste d'adresses (`MAILER_DIVERT_TO`, `MAILER_ALLOWED_RECIPIENTS`).
- Le kit des essais : message et tableur par observatoire, contacts tirés du dump brut, suivi (`dev/test-kit/`).
- La mesure de ce que la comparaison exécute, en PHP et en SQL (`dev/coverage/`).
- Les étapes de l'installation sur la VM, pour les deux instances, en texte, à éprouver sur place (`dev/deploy/README.md`).

### Sécurité

- Symfony passe à 7.4.23, pour les 16 avis publiés le 4 novembre 2026. D'après leur lecture et le code, BDOH n'y était pas exposé : ni connexion par programme, ni `methods` dans `access_control`, ni sérialiseur sur des données reçues, ni fragments en ligne, ni `private_ranges`.

## 0.2.0 - 2026-11-04

Le portage corrigé et éprouvé, avant l'ouverture des données : le comparateur a vu toutes les fautes remises dans le code, le recalcul de la base complète retrouve les mesures, et tous les modes passent sur les paquets de Debian 13 avec un rôle sans superuser.

### Corrigé

- Un import qui échoue en route ne laisse rien : l'ancien BDOH pouvait laisser une période vide, ou une partie de ses chroniques importée sans historique. De même pour les points de contrôle.
- Un disque plein pendant un import est dit, et rien n'est importé.
- Deux imports en archive zip menés en même temps ne se mêlent plus quand leur fichier intérieur porte le même nom.
- À l'import de points de contrôle, une date future est refusée et une valeur écrite `0` acceptée.
- Vider l'intérieur d'une chronique met bien ses mesures en lacune, même si sa première ou sa dernière mesure a des millisecondes.
- Une réponse illisible d'un serveur distant n'arrête plus la file de tâches.
- Un calcul de chronique qui échoue à l'insertion laisse ses mesures en place : l'ancien BDOH la laissait vide, avec des métadonnées qui en annonçaient toujours.
- Aux imports QJO et Qtvar, une valeur écrite `0` reste un zéro : l'ancien BDOH en faisait une lacune, sans message.
- À l'import QJO, un trou de plus d'un mois se comble entièrement par des lacunes, et des lignes qui reviennent en arrière n'en ajoutent plus.
- Une station créée avec ses coordonnées a désormais son point, et apparaît donc sur les cartes.
- Le choix de la langue tient pour un visiteur sans compte (régression de la 0.1.0).
- Plus d'erreur JavaScript sur les pages de l'administration (régression de la 0.1.0).
- Une suppression que la base refuse n'est plus notée dans l'historique.
- La page de l'historique ne tombe plus en erreur après la suppression d'un milieu, d'un critère, d'une contrainte d'accès ou d'un thème INSPIRE.
- L'emprise d'un jeu de données envoyée à Theia/OZCAR est la vraie, et non le point d'une de ses stations.
- Un envoi vers Theia/OZCAR redirigé est un échec, et non un envoi réussi sans contenu ; sans code ou sans mot de passe, un message remplace l'erreur brute de la tâche.
- Le format d'export Vigilance, que rien ne savait écrire, n'est plus accepté, et une adresse de l'administration qui répondait toujours par une erreur interne est retirée.
- Des corrections de sécurité, décrites à la mise en service du nouveau BDOH.

### Modifié

- Les fichiers PHP et JavaScript de `src/` portent deux lignes SPDX au lieu de l'entête « IRSTEA, tous droits réservés » de l'ancien : IRSTEA n'existe plus, et le code est sous AGPL (`LICENSE`).
- Toutes les dates produites par BDOH sont en UTC, comme celles des mesures ; la file de tâches écrivait à l'heure de Paris.
- Les mentions légales citent INRAE, et non plus Irstea.

### Ajouté

- La comparaison avec l'ancien BDOH est éprouvée : 29 fautes remises dans le code, toutes vues (`dev/compare/mutants.py`), et un petit jeu de calcul aux résultats écrits à la main, joué aussi sous PostgreSQL 11 (`dev/compare/invented.py`).
- Chaque page publique indique la licence et un lien vers le code source, comme le demande l'AGPL.
- `CITATION.cff` et `codemeta.json` : qui a fait BDOH, comment le citer.
- Essais de l'interface dans un vrai navigateur (`make browser-check`), en plus de la comparaison.
- Migration qui construit le point des stations qui ont des coordonnées sans point, à appliquer à la bascule.

## 0.1.0 - 2026-11-03

Le portage complet vers Symfony 7.4 et PHP 8.4, comparé module par module à l'ancien BDOH. Pas encore en service : cette version marque l'état à faire essayer aux gestionnaires d'observatoire.

### Ajouté

- Anonymisation des dumps de production (`make anonymize`) : chaque colonne de texte est classée, une colonne non classée arrête tout ; le rapport ne contient que des comptes, et aucun dump n'est produit en cas de fuite possible. Passée sur les bases de production et d'OTHU du 21 octobre 2026.
- Serveur de référence local, PostgreSQL 11 et PostGIS 2.5 comme la production, sur un réseau Docker interne (`make db`) ; base de travail réduite (`make db-small`).
- Application de référence : l'ancien BDOH au commit en production, installé comme le faisait le déploiement, sur les bases anonymisées, avec sa file de tâches, sans accès à l'extérieur (`make reference-app`, `reference-start`, `reference-stop`). Pages en UTC et file de tâches en `Europe/Paris`, comme en production.
- Outil de comparaison de deux BDOH (`dev/compare/compare.py`) : pages publiques et visualiseur par profil, exports en ligne de commande ; les cas sont tirés de la base par des règles, et les écarts écrits hors du dépôt.
- Essai de PostgreSQL 17 et PostGIS 3.5 (Debian 13) avec l'ancien code : la base complète s'y restaure sans erreur et donne les mêmes comptes d'intégrité ; une fois corrigé `EXTRACT` (ci-dessous), les exports sont identiques à ceux de PostgreSQL 11, et les pages aussi, à l'ordre des listes sans tri et au seizième chiffre près.
- Migrations du nouveau BDOH, à appliquer à la bascule : les compteurs d'identifiants en retard recalés, et `EXTRACT(EPOCH …)` ramené au type de PostgreSQL 11 dans les fonctions de calcul, sans quoi les exports en moyenne et en cumul échouent sous PostgreSQL 14 et plus.
- Contrôles d'intégrité d'une base BDOH, qui ne sortent que des comptes (`make inspect`, `dev/reference/inspect.sql`).
- Licence GNU AGPL v3.
- Contrôles à chaque envoi sur GitHub (`.github/workflows/checks.yaml`) : failles connues des dépendances, analyse statique, syntaxe de la configuration et des gabarits, cohérence des services.
- Nouveau BDOH sous Symfony 7.4 et PHP 8.4 (paquets de Debian 13), sur la structure de base de la production, sans conversion des données. Socle : entités, types et correspondance Doctrine, lus exactement comme l'ancien les lisait (comparaison des métadonnées et des entités chargées). Plan : `dev/plans/2026-10-23_socle.md`.
- File de tâches et exports du nouveau BDOH : recalculs, conversions, taux de remplissage, « vider une période », exports des quatre sortes aux formats BDOH et Qtvar, courriel de l'export, pages de suivi des tâches. Identiques à l'ancien sur les bases anonymisées, réduite et complète. Un exécuteur à nous sur les tables de JMS JobQueue garde une tâche à la fois par chronique (essai de l'audit passé). Plan : `dev/plans/2026-10-23_jobs-exports.md`.
- Imports du nouveau BDOH : mesures (formats BDOH, BDOH par plages, QJO, Qtvar), points de contrôle et leur export, barèmes et transformations, shapefiles. Identiques à l'ancien, réponses de chaque étape et état de la base, sur 49 cas tirés de la base et leurs variantes. Plan : `dev/plans/2026-10-25_imports.md`.
- Comptes, connexion et droits du nouveau BDOH : inscription, modification du compte, demandes d'accès, attribution des droits, liste des comptes, courriels aux gestionnaires, création d'un compte d'administration en ligne de commande ; identiques à l'ancien sur la base anonymisée, sauf la connexion (ci-dessous). Plan : `dev/plans/2026-10-26_comptes.md`.
- Connexion par un lien envoyé par courriel, sans mot de passe ni captcha : lien valable 15 minutes et une seule fois, session de 30 jours, adresse confirmée par la première connexion, formulaires qui envoient un courriel limités en fréquence. Le mot de passe oublié disparaît ; ses adresses renvoient à la page de connexion. La console crée un lien pour un compte dont le courriel n'arrive pas (`security:users:login-link`).
- Consultation publique et visualiseur du nouveau BDOH : pages, données des chroniques, manuel, choix de la langue ; identiques à l'ancien pour chaque profil, sur les bases réduite et complète. JavaScript et feuilles de style compilés dans le dépôt, avec les paquets de l'ancien et le Node de Debian 13 (`make assets`). Plan : `dev/plans/2026-10-23_consultation.md`.
- Administration du nouveau BDOH sur Sonata Admin 4 : les 34 administrations, les couleurs, la conversion, les taux de remplissage, l'historique ; mêmes listes, mêmes formulaires et même état de la base après chaque écriture que l'ancien, pour un administrateur, un gestionnaire et un administrateur technique. Plan : `dev/plans/2026-10-26_administration.md`.
- Envoi des fiches des observatoires vers Theia/OZCAR : format pivot, contrôle par le schéma de Theia, envoi par la file de tâches ; identiques à l'ancien, du JSON produit au contenu reçu, sur les bases réduite et complète. Adresses du portail réglables par l'environnement (`THEIA_SEND_URI`, `THEIA_SCHEMA_URI`). Plan : `dev/plans/2026-10-27_theia.md`.
- Les droits d'un compte suivent l'observatoire de la page consultée, et non celui où il s'est connecté.
- Outil de comparaison : l'administration (`compare.py admin`, avec des fichiers joints de remplacement), l'envoi vers Theia/OZCAR (`compare.py theia`, avec un récepteur de développement) ; l'état de la base après les calculs, les exports demandés par le formulaire et la file de tâches (`compare.py state`, courriels lus dans Mailpit, `make mail-start`) ; l'essai de la file de tâches (`compare.py queue`) ; les imports étape par étape (`compare.py imports`) ; les parcours de comptes (`compare.py accounts`), avec un DNS de développement qui donne un MX aux adresses de test.

### Corrigé

Bogues de l'ancien BDOH, corrigés dans le nouveau (détail dans `dev/differences.md`) :

- Des corrections de sécurité, décrites à la mise en service du nouveau BDOH.
- Erreurs internes sur des pages publiques : le choix de la langue, une station ou une chronique inconnues.
- Un fichier joint impossible à supprimer arrêtait l'enregistrement d'un observatoire sur une erreur fatale.
- Une tâche ne s'annule ou ne se relance que si son état le permet ; une annulation et un démarrage simultanés ne donnent plus une tâche annulée qui tourne.
- Importer un barème sans fichier, ou d'un type refusé, affiche le message d'erreur au lieu d'une erreur interne.
- Créer un compte d'administration en ligne de commande ne donne tous les droits que si on le demande.
- Les seuils de validité d'une chronique ne sont plus arrondis à trois décimales à chaque enregistrement.
- Demander le calcul des taux de remplissage d'une chronique inconnue répond 404 au lieu d'une erreur interne.
- Enregistrer une station rattachée à un site que le compte ne gère pas ne l'en détache plus.
- Modifier un observatoire depuis la page d'un autre n'efface plus ses gestionnaires de données ni son responsable : l'édition se fait depuis sa propre page.
- Le clonage d'une chronique vérifie qu'elle existe et qu'on peut la modifier, et n'échoue plus sur le choix d'un jeu de données.
- L'altitude et les coordonnées d'une station ne sont plus arrondies à trois décimales à chaque enregistrement.
- Les coordonnées envoyées à Theia/OZCAR sont demandées en WGS 84 : l'ancien, sous PostGIS 2.5, les décalait de 300 à 700 m.
- Un serveur de Theia qui ne répond pas ne fait plus échouer l'envoi sur une erreur fatale.

### Modifié

- Modifier le compte d'un autre est réservé aux administrateurs. Un gestionnaire garde la liste des comptes, leur création et l'attribution des droits de ses observatoires.

### Sécurité

- `symfony/mime` passe à 7.4.22 ([CVE-2026-45070](https://symfony.com/cve-2026-45070)). BDOH n'y était pas exposé : il ne construit aucun nom de paramètre d'en-tête de courriel à partir d'une saisie. `compare.py accounts`, `state`, `admin` et `imports` donnent les mêmes résultats qu'avant.
