# Journal des modifications

Format : [Keep a Changelog](https://keepachangelog.com/fr/1.1.0/). Numéros de version : [SemVer](https://semver.org/lang/fr/).

## Non publié

### Ajouté

- Anonymisation des dumps de production (`make anonymize`) : chaque colonne de texte est classée, une colonne non classée arrête tout ; le rapport ne contient que des comptes, et aucun dump n'est produit en cas de fuite possible. Passée sur les bases de production et d'OTHU du 21 octobre 2026.
- Serveur de référence local, PostgreSQL 11 et PostGIS 2.5 comme la production, sur un réseau Docker interne (`make db`) ; base de travail réduite (`make db-small`).
- Application de référence : l'ancien BDOH au commit en production, installé comme le faisait le déploiement, sur les bases anonymisées, avec sa file de tâches, sans accès à l'extérieur (`make reference-app`, `reference-start`, `reference-stop`). Pages en UTC et file de tâches en `Europe/Paris`, comme en production.
- Outil de comparaison de deux BDOH (`dev/compare/compare.py`) : pages publiques et visualiseur par profil, exports en ligne de commande ; les cas sont tirés de la base par des règles, et les écarts écrits hors du dépôt.
- Essai de PostgreSQL 17 et PostGIS 3.5 (Debian 13) avec l'ancien code : la base complète s'y restaure sans erreur et donne les mêmes comptes d'intégrité ; une fois corrigé `EXTRACT` (ci-dessous), les exports sont identiques à ceux de PostgreSQL 11, et les pages aussi, à l'ordre des listes sans tri et au seizième chiffre près.
- Migrations du nouveau BDOH, à appliquer à la bascule : les compteurs d'identifiants en retard recalés, et `EXTRACT(EPOCH …)` ramené au type de PostgreSQL 11 dans les fonctions de calcul, sans quoi les exports en moyenne et en cumul échouent sous PostgreSQL 14 et plus.
- Contrôles d'intégrité d'une base BDOH, qui ne sortent que des comptes (`make inspect`, `dev/reference/inspect.sql`).
- Licence GNU AGPL v3.
- Nouveau BDOH sous Symfony 7.4 et PHP 8.4 (paquets de Debian 13), sur la structure de base de la production, sans conversion des données. Socle : entités, types et correspondance Doctrine, lus exactement comme l'ancien les lisait (comparaison des métadonnées et des entités chargées). Plan : `dev/plans/2026-10-23_socle.md`.
- File de tâches et exports du nouveau BDOH : recalculs, conversions, taux de remplissage, « vider une période », exports des quatre sortes aux formats BDOH et Qtvar, courriel de l'export, pages de suivi des tâches. Identiques à l'ancien sur les bases anonymisées, réduite et complète. Un exécuteur à nous sur les tables de JMS JobQueue garde une tâche à la fois par chronique (essai de l'audit passé). Plan : `dev/plans/2026-10-23_jobs-exports.md`.
- Imports du nouveau BDOH : mesures (formats BDOH, BDOH par plages, QJO, Qtvar), points de contrôle et leur export, barèmes et transformations, shapefiles. Identiques à l'ancien, réponses de chaque étape et état de la base, sur 49 cas tirés de la base et leurs variantes. Plan : `dev/plans/2026-10-25_imports.md`.
- Comptes, connexion et droits du nouveau BDOH : inscription, modification du compte, demandes d'accès, attribution des droits, liste des comptes, courriels aux gestionnaires, création d'un compte d'administration en ligne de commande ; identiques à l'ancien sur la base anonymisée, sauf la connexion (ci-dessous). Plan : `dev/plans/2026-10-26_comptes.md`.
- Connexion par un lien envoyé par courriel, sans mot de passe ni captcha : lien valable 15 minutes et une seule fois, session de 30 jours, adresse confirmée par la première connexion, formulaires qui envoient un courriel limités en fréquence. Le mot de passe oublié disparaît ; ses adresses renvoient à la page de connexion. La console crée un lien pour un compte dont le courriel n'arrive pas (`security:users:login-link`).
- Outil de comparaison : l'état de la base après les calculs, les exports demandés par le formulaire et la file de tâches (`compare.py state`, courriels lus dans Mailpit, `make mail-start`) ; l'essai de la file de tâches (`compare.py queue`) ; les imports étape par étape (`compare.py imports`) ; les parcours de comptes (`compare.py accounts`), avec un DNS de développement qui donne un MX aux adresses de test.

### Corrigé

Bogues de l'ancien BDOH, corrigés dans le nouveau (détail dans `dev/differences.md`) :

- Des corrections de sécurité, décrites à la mise en service du nouveau BDOH.
- Erreurs internes sur des pages publiques : le choix de la langue, une station ou une chronique inconnues.
- Un fichier joint impossible à supprimer arrêtait l'enregistrement d'un observatoire sur une erreur fatale.
- Une tâche ne s'annule ou ne se relance que si son état le permet ; une annulation et un démarrage simultanés ne donnent plus une tâche annulée qui tourne.
- Importer un barème sans fichier, ou d'un type refusé, affiche le message d'erreur au lieu d'une erreur interne.
- Créer un compte d'administration en ligne de commande ne donne tous les droits que si on le demande.
