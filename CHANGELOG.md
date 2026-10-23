# Journal des modifications

Format : [Keep a Changelog](https://keepachangelog.com/fr/1.1.0/). Numéros de version : [SemVer](https://semver.org/lang/fr/).

## Non publié

### Ajouté

- Anonymisation des dumps de production (`make anonymize`) : chaque colonne de texte est classée, une colonne non classée arrête tout ; le rapport ne contient que des comptes, et aucun dump n'est produit en cas de fuite possible. Passée sur les bases de production et d'OTHU du 21 octobre 2026.
- Serveur de référence local, PostgreSQL 11 et PostGIS 2.5 comme la production, sur un réseau Docker interne (`make db`) ; base de travail réduite (`make db-small`).
- Application de référence : l'ancien BDOH au commit en production, installé comme le faisait le déploiement, sur les bases anonymisées, avec sa file de tâches, sans accès à l'extérieur (`make reference-app`, `reference-start`, `reference-stop`). Pages en UTC et file de tâches en `Europe/Paris`, comme en production.
- Outil de comparaison de deux BDOH (`dev/compare/compare.py`) : pages publiques et visualiseur par profil, exports en ligne de commande ; les cas sont tirés de la base par des règles, et les écarts écrits hors du dépôt.
- Contrôles d'intégrité d'une base BDOH, qui ne sortent que des comptes (`make inspect`, `dev/reference/inspect.sql`).
- Licence GNU AGPL v3.
