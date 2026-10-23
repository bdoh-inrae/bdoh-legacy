# Feuille de route

Porter BDOH vers Symfony 7.4 (version à support long, corrigée jusqu'en novembre 2029) et PHP 8.4, en gardant son comportement, ses adresses et ses données. Méthode et repères : `CLAUDE.md`.

Code de référence : commit `523cf8e32` de l'ancien dépôt (version 5.0.3, février 2025), celui qui tourne en production. L'instance OTHU tourne sur le même code applicatif ; seules la page des mentions légales et la configuration de déploiement diffèrent.

## En cours

- **Base de référence anonymisée**, tirée du dump de production du 21 octobre 2026 : `make anonymize` (`dev/anonymize/`). Le script est écrit d'après la structure de la base de production (138 tables) et essayé sur une base de cette structure remplie de lignes inventées.
  - [x] Production : `../bdoh_reference/bdoh-205-2026-10-21-anon.dump` (23 octobre). Rapport sans fuite ni contrôle en échec ; contrôle par Claude : aucun courriel réel hors des mesures, numéros repérés tous faux positifs (géométries, dates, coordonnées). 257 millions de mesures, base de 35,7 Go restaurée.
  - [ ] La base d'OTHU, de la même façon ; une colonne qui n'existe que là arrêtera le script, à classer.
  - La base `bdoh-messenger`, à part, n'est pas reprise.

## À faire

- **Faire tourner la référence.** L'ancien BDOH en local (`../bdoh/`, commit `523cf8e32`) sur la base anonymisée.
- **Base de travail réduite**, tirée de la base anonymisée : toutes les métadonnées, tous les comptes et toutes les chroniques, mais les mesures sur une fenêtre de temps commune, à choisir d'après les tailles du rapport d'anonymisation. Elle sert à itérer vite ; la base complète sert aux comparaisons étendues, à la fin de chaque module.
- **Contrôles sur la base anonymisée** (audit du 23 octobre, « Ce que ça appelle ») : seuils de validité à plus de 3 décimales, absence de raster.
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
- **Déploiement** sur une VM neuve sous Debian 13, qui fournit PHP 8.4 et le corrige : déploiement de test, reprise des données, vérifications par des gestionnaires d'observatoire, bascule. La VM est à demander à l'équipe infra.

## À décider

- **Nom et forge** du dépôt.
- **Seuils de validité** des chroniques : l'ancien BDOH les arrondit à 3 décimales à chaque enregistrement (audit, constat 8). Recommandé : le nouveau garde la valeur saisie.

## À relever sur les serveurs (Lou)

- `date.timezone` de PHP pour Apache et pour la ligne de commande (audit, constat 5).
