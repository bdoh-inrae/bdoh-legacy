# Feuille de route

Porter BDOH vers Symfony 7.4 (version à support long, corrigée jusqu'en novembre 2029) et PHP 8.4, en gardant son comportement, ses adresses et ses données. Méthode et repères : `CLAUDE.md`.

Code de référence : commit `523cf8e32` de l'ancien dépôt (version 5.0.3, février 2025), celui qui tourne en production. L'instance OTHU tourne sur le même code applicatif ; seules la page des mentions légales et la configuration de déploiement diffèrent.

## En cours

- **Base de référence anonymisée**, tirée du dump de production du 21 octobre 2026 : `make anonymize` (`dev/anonymize/`). Le script est écrit d'après la structure de la base de production (138 tables) et essayé sur une base de cette structure remplie de lignes inventées.
  - [ ] Lou le lance sur le dump et transmet le rapport, fait de comptes.
  - [ ] Claude contrôle le dump anonymisé avant de s'en servir.
  - [ ] Ensuite, la base d'OTHU, de la même façon ; une colonne qui n'existe que là arrêtera le script, à classer.
  - La base `bdoh-messenger`, à part, n'est pas reprise.

## À faire

- **Faire tourner la référence.** L'ancien BDOH en local (`../bdoh/`, commit `523cf8e32`) sur la base anonymisée.
- **Base de travail réduite**, tirée de la base anonymisée : toutes les métadonnées, tous les comptes et toutes les chroniques, mais les mesures sur une fenêtre de temps commune, à choisir d'après les tailles du rapport d'anonymisation. Elle sert à itérer vite ; la base complète sert aux comparaisons étendues, à la fin de chaque module.
- **Outil de comparaison.** Il joue les mêmes requêtes sur l'ancien et le nouveau BDOH et compare les sorties : pages publiques et visualiseur, exports (chaque format, chaque pas de temps), imports (fichiers de `dev/exemples/` de l'ancien dépôt), droits d'accès selon le profil. Les sorties de l'ancien sont enregistrées dans `../bdoh_reference/`.
- **PostgreSQL 11 vers la version de Debian 13**, avec l'ancien code d'abord, PostGIS compris.
- **Portage, module par module**, chacun validé par la comparaison avant le suivant :
  1. socle : squelette Symfony 7.4, entités (correspondance Doctrine passée du YAML aux attributs), fonctions SQL de `app/sql/` reprises telles quelles ;
  2. consultation publique et visualiseur ;
  3. exports, la file de tâches (`jms/job-queue-bundle`) remplacée par Symfony Messenger ;
  4. imports : mesures, barèmes, points de contrôle, shapefiles ;
  5. comptes, connexion et droits ;
  6. administration (Sonata Admin 3 vers 4), le plus gros morceau ;
  7. envoi des fiches vers Theia/OZCAR, courriels.
- **Déploiement** sur une VM neuve sous Debian 13, qui fournit PHP 8.4 et le corrige : déploiement de test, reprise des données, vérifications par des gestionnaires d'observatoire, bascule. La VM est à demander à l'équipe infra.

## À décider

- **Nom et forge** du dépôt.
