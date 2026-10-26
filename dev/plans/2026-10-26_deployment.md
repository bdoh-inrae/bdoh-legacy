# Mise en service

État : à faire, après le portage ; ouvert le 2026-10-26 pour réunir ce que la mise en service demande, relevé module après module. Point « Déploiement » de `ROADMAP.md`.

Chaque tâche renvoie à l'endroit où elle s'explique. Un module qui découvre une tâche de mise en service l'ajoute ici, au moment où il la découvre.

## Avant : relevés et décisions

- **Proxy inverse** devant BDOH en production ? Si oui, son adresse dans `SYMFONY_TRUSTED_PROXIES` : sans elle, toutes les requêtes ont l'adresse IP du proxy, et les limites de fréquence valent pour tout le site (`dev/decisions.md`, « Connexion par lien »). À relever avec l'équipe infra (Lou).
- **Réglages PHP et langue** des anciens serveurs (`ROADMAP.md`, « Relevés ») : utiles tant que la référence doit leur ressembler.
- **Comportements de l'ancien à trancher** : les entrées « à trancher » de `dev/differences.md`.
- **Prévenir les utilisateurs et utilisatrices** que la connexion change : plus de mot de passe, un lien par courriel (`dev/differences.md`, « Connexion par lien »). Un message sur l'accueil, ou un courriel à tous les comptes actifs.

## La machine

- VM neuve sous Debian 13, sans Docker : PHP 8.4, Apache, PostgreSQL 17 et PostGIS 3 de Debian (`ROADMAP.md`, « Déploiement » ; `dev/decisions.md`, « Environnement sur les paquets de Debian 13 »). À demander à l'équipe infra.
- Mises à jour de sécurité automatiques (`unattended-upgrades`).
- HTTPS seul ; en-têtes `Strict-Transport-Security`, `X-Content-Type-Options: nosniff`, `Referrer-Policy` dans Apache (`ROADMAP.md`, « Modernisations »).
- `.env` de production (`.env.example`) : `APP_ENV=prod`, `APP_SECRET` aléatoire, `DATABASE_URL`, `DEFAULT_URI` (adresse publique), `MAILER_DSN` (serveur SMTP), `SYMFONY_TRUSTED_HOSTS` (nom du serveur, sinon un lien envoyé par courriel peut porter un nom d'hôte choisi par le visiteur : `dev/local/security.md`).
- Le serveur SMTP accepte `bdoh@inrae.fr` depuis la nouvelle machine (SPF, relais) : la connexion passe désormais par le courriel. Essayer l'envoi d'un lien vers une adresse INRAE et vers une adresse extérieure, et vérifier qu'une messagerie qui ouvre les liens pour les analyser ne les consomme pas (page du bouton, `check_post_only`).
- Dossiers inscriptibles par le serveur web : `var/` (cache, journaux, sessions, imports), `public/css/themes/` (thèmes compilés par `CssManager`), `public/uploads/`.
- File de tâches : le service systemd `config/bdoh-job-worker.service` (`KillMode=mixed`, `dev/differences.md`, « Tâche interrompue par un signal »).
- JavaScript et feuilles de style compilés sur place, et non plus copiés de la référence (`dev/plans/2026-10-23_consultation.md`, étape 6).

## La base

- Restaurer la base de production sous PostgreSQL 17 et PostGIS 3 ; la base complète s'y restaure sans erreur (`CHANGELOG.md`, essai de PostgreSQL 17). Aucune colonne raster (`dev/audits/2026-10-23_reference-db.md`).
- Migrations du nouveau BDOH, chacune lue, avec son retour arrière (`CLAUDE.md`) :
  - `Version20261023120000` : compteurs d'identifiants recalés (`dev/differences.md`, « Compteurs d'identifiants en retard ») ;
  - `Version20261023130000` : `EXTRACT(EPOCH …)` ramené au type de PostgreSQL 11, avant toute tâche (`dev/differences.md`, « EXTRACT sous PostgreSQL 14 et plus »).
- `inspect.sql` avant et après, sur les deux bases : mêmes comptes (`dev/audits/2026-10-23_reference-db.md`).
- OTHU : aligner ses trois fonctions SQL sur la production, après comparaison (`ROADMAP.md`, « Fonctions SQL d'OTHU »).

## La bascule

- Arrêter l'ancien exécuteur de tâches et attendre la fin de ses tâches avant de lancer le nouveau ; les tâches restées « en cours » deviendront « incomplètes », donc relançables (`dev/differences.md`, « Tâches restées en cours »).
- Brancher le nouveau code sur la base, lancer la file de tâches.
- Vérifications par des gestionnaires d'observatoire (`ROADMAP.md`, « Déploiement »).
- Les adresses publiques de l'ancien répondent toujours : DOI et portail Theia/OZCAR y renvoient (`CLAUDE.md`).

## Après

- Publier les corrections de sécurité dans `dev/differences.md` (`dev/local/security.md`).
- Effacer les hachages des anciens mots de passe, une fois la connexion par lien éprouvée (`ROADMAP.md`, « Modernisations »).

## Suivi

- 2026-10-26 : ouvert, avec ce que les modules 1 à 5 ont relevé.
