# Feuille de route

Porter BDOH vers Symfony 7.4 (version à support long, corrigée jusqu'en novembre 2029) et PHP 8.4, en gardant son comportement, ses adresses et ses données. Méthode et repères : `CLAUDE.md`.

Code de référence : commit `523cf8e32` de l'ancien dépôt (version 5.0.3, février 2025), celui qui tourne en production. L'instance OTHU tourne sur le même code applicatif ; seules la page des mentions légales et la configuration de déploiement diffèrent.

## En cours

- **Faire tourner la référence** : l'ancien BDOH en local, au commit `523cf8e32`, sur les bases anonymisées.
  - Point de départ (23 octobre). Les dumps anonymisés de la production et d'OTHU sont dans `../bdoh_reference/`, avec leurs rapports. Le serveur de référence `bdoh-reference-db` (PostgreSQL 11, PostGIS 2.5, réseau Docker interne `bdoh-reference`, fichiers dans `../bdoh_reference/postgres/`) porte les deux bases, `bdoh` et `bdoh_othu` ; après un redémarrage du poste : `docker start bdoh-reference-db`. L'image `bdoh-app:latest` (ancien BDOH, PHP 7.1) est gardée pour l'application ; l'ancienne installation Docker de `../bdoh/` et sa base de préproduction ont été supprimées.
  - L'application de référence rejoint le réseau interne, sans accès à l'extérieur : aucun courriel ni envoi vers Theia ne peut partir. Le dossier `../bdoh/` n'est pas modifié ; ce qu'il faut pour la lancer vit ici, dans `dev/reference/`.
  - Les comptes ont tous le mot de passe `bdoh-test`.

## À faire

- **Base de travail réduite**, tirée de la base anonymisée : toutes les métadonnées, tous les comptes et toutes les chroniques, mais les mesures sur une fenêtre de temps commune, à choisir d'après les tailles du rapport d'anonymisation. Elle sert à itérer vite ; la base complète sert aux comparaisons étendues, à la fin de chaque module.
- **Outil de comparaison.** Il joue les mêmes requêtes sur l'ancien et le nouveau BDOH et compare les sorties : pages publiques et visualiseur, exports (chaque format, chaque pas de temps), imports, droits d'accès selon le profil ; et, après chaque écriture, l'état des tables touchées. Les nombres se comparent selon la règle de l'audit (constat 4). Les imports jouent les fichiers de `dev/exemples/` de l'ancien dépôt et des variantes (espaces, fins de ligne Windows, virgules, valeurs invalides, changement d'heure, fuseaux). Les sorties de l'ancien sont enregistrées dans `../bdoh_reference/`.
- **PostgreSQL 11 vers la version de Debian 13**, avec l'ancien code d'abord, PostGIS compris (procédure du raster, audit constat 7).
- **Portage, module par module**, chacun validé par la comparaison avant le suivant :
  1. socle : squelette Symfony 7.4, structure de la base reprise de la production, entités (correspondance Doctrine passée du YAML aux attributs, types propres à BDOH récrits), fuseau `Europe/Paris` fixé, pas de `setlocale(LC_ALL)` ;
  2. consultation publique et visualiseur ;
  3. file de tâches remplacée par Symfony Messenger, avec une tâche à la fois par chronique, éprouvée par l'essai de l'audit (constat 3) ; puis les exports ;
  4. imports : mesures, barèmes, points de contrôle, shapefiles ;
  5. comptes, connexion et droits ;
  6. administration (Sonata Admin 3 vers 4), le plus gros morceau ;
  7. envoi des fiches vers Theia/OZCAR, courriels.
- **Déploiement** sur une VM neuve sous Debian 13, sans Docker, avec le PHP 8.4 et le PostgreSQL de Debian, qui les corrige par ses mises à jour ordinaires (choix du 23 octobre ; le code reste compatible avec PHP 8.5, celui d'Ubuntu 26.04, que l'équipe infra propose par défaut) : déploiement de test, reprise des données, vérifications par des gestionnaires d'observatoire, bascule. La VM est à demander à l'équipe infra.

## À décider

- **Nom et forge** du dépôt.

## Plus tard, après la bascule

- **Lacunes à -9999 dans la base.** Les fonctions de calcul écrivent -9999 comme valeur des lacunes (3,9 millions de mesures de chroniques calculées), alors que les imports y mettent `NULL` : la base a deux conventions, et une valeur sentinelle qu'un calcul fait hors de BDOH peut prendre pour une vraie mesure. Passer à `NULL` partout touche neuf fonctions de calcul et ces mesures : à faire une fois le nouveau BDOH identique à l'ancien, comme chantier à part, vérifié par la comparaison. -9999 reste la valeur des lacunes dans les fichiers d'import et d'export, format public que les utilisateurs emploient.
- **Ouvrir le téléchargement des données publiques**, sans compte, en gardant fermées celles qui doivent l'être. Aujourd'hui, tout téléchargement demande un compte et un droit explicite sur l'observatoire, le site ou la chronique (`BdohRightVoter::canDownload`), même pour une chronique visible de tous, dont le visualiseur sert déjà les valeurs sans compte (`DataProvidingController`, droit `CONSULT_TIMESERIES`). Le modèle ne distingue rien d'autre que la visibilité : 2 244 chroniques visibles, 768 non visibles, et neuf chroniques non visibles appartiennent pourtant à un jeu de données publié. Principe retenu (Lou, 23 octobre) : science ouverte, donc ouvert par défaut et fermé sur demande. Le travail consiste à établir, avec les gestionnaires, ce qui doit rester fermé parce que les données viennent de partenaires privés (CNR, EDF) ou sont sensibles, puis à le marquer par un réglage explicite, par chronique ou par jeu de données, avant toute ouverture. La seule visibilité ne suffit pas à trancher, et les conditions d'utilisation de chaque observatoire restent à respecter (les 28 jeux de données renvoient à des conditions générales en PDF).
- **Critique des données, pour les gestionnaires.** L'inspection des bases relève des mesures valides hors des seuils de validité, et, sur OTHU, des lots de mesures au minimum et au maximum inversés ([dev/audits/2026-10-23_reference-db.md](dev/audits/2026-10-23_reference-db.md)). Hors du portage ; un retour aux gestionnaires d'observatoire est possible à partir de cette trace.
