# Consultation publique et visualiseur

État : en cours, commencé le 2026-10-23. Point 2 du portage dans `ROADMAP.md` ; suit le socle (`dev/plans/2026-10-23_socle.md`).

But : les pages que l'on consulte sans administrer, servies par le nouveau BDOH à l'identique de l'ancien, pour chaque profil (anonyme, utilisateur, gestionnaire, administrateur) : `compare.py pages` sans écart hors de `dev/differences.md`.

## Ce que ça couvre

Les routes de `BdohConsultBundle` (accueil de tous les observatoires, page d'un observatoire, d'une station, d'une chronique, d'un jeu de données, recherche « nos données », visualiseur et ses données, contact, conditions d'utilisation), le manuel, le choix de la langue (`BdohInternationalisationBundle`), les données d'une chronique pour le visualiseur (`DataProvidingController`), les conditions à télécharger, les traductions pour le JavaScript (`/translations/…`).

Pour les comparer par profil, il faut se connecter : la connexion par formulaire et les voteurs de droits (`BdohSecurityBundle/Security`) viennent avec ce module. Les pages de comptes (création, modification, droits, mot de passe oublié) restent au module 5.

## Comment

1. **Services de `BdohBundle`** : gestionnaire de l'observatoire courant (écouteur de requête), `CssManager` (thèmes LESS compilés à la volée par less.php, version maintenue `wikimedia/less.php`), extension Twig, formateur de durées. Les identifiants de services de l'ancien sont gardés (`irstea_bdoh.manager.observatoire`…), déclarés dans le `Resources/config/services.yml` de chaque bundle, importé par `config/services.yaml`.
2. **Contrôleurs.** La classe de base de BDOH (`BdohBundle/Controller/Controller.php`) déclare les services que les contrôleurs demandent par `$this->get('…')` (abonnement aux services de Symfony) : les appels restent tels quels. Annotations de routes et de gabarits (`@Route`, `@Method`, `@Template`) passées en attributs PHP.
3. **Routage** : `config/routes.yaml` reprend `app/config/routing.yml` et `routing_observatory.yml`, dans le même ordre (la route « tout attraper » de BdohBundle reste la dernière).
4. **Gabarits** : noms `Bundle:Dossier:fichier` passés en `@Bundle/Dossier/fichier`, `transchoice` en `trans` avec `%count%`, `spaceless` en `apply spaceless`, `for … if` en filtre ; `app/Resources/views` dans `templates/`.
5. **Traductions** : celles des bundles restent dans leurs `Resources/translations`.
6. **JavaScript et feuilles de style.** Une seule chose change à la fois : d'abord, les fichiers compilés comme l'ancien les compilait (même `package.json`, même Encore, dans un conteneur Node 12 jetable), pour que la comparaison ne voie que le code PHP et Twig ; ensuite, à part, le passage à un Encore récent et au Node de Debian 13, vérifié dans un navigateur.
7. **Vérification** : `compare.py pages --old reference-17 --new new` (l'ancien code sur la même base PostgreSQL 17 que le nouveau), puis contre la référence sur PostgreSQL 11 ; les écarts restants tranchés un à un.

## Suivi

- 2026-10-23 : plan écrit.
- 2026-10-23 : pages servies par le nouveau BDOH, en environnement prod comme la référence.
  - Échafaudage temporaire, à retirer module par module : les routes des modules à venir (`config/routes_pending/`, mêmes noms, adresses et valeurs par défaut que l'ancien, réponse 501), pour que les pages portées y renvoient ; `PendingJobManager`, qui tient la place de la file de tâches (aucune tâche en cours) ; `BdohAdminBundle` déclaré pour ses seules traductions, que les gabarits de consultation emploient.
  - JavaScript et feuilles de style : pour l'instant, les fichiers compilés par l'installation de la référence, copiés dans `public/assets/` et `public/js/` ; les paquets JavaScript de l'ancien (`package.json`, `package-lock.json`) installés par `npm ci`, pour les sources LESS de Bootstrap 3 que `CssManager` compile.
  - Repris de l'ancien : la page d'erreur de BDOH (`templates/bundles/TwigBundle/`), ses traductions, celles de `app/Resources/translations` ; et, par un écouteur, la réponse JSON aux erreurs des requêtes JSON, que Symfony 7 n'a plus.
  - Profil anonyme : 216 pages identiques sur 250 avant la page de connexion ; restent le choix de la langue (l'erreur 500 de l'ancien, corrigée), les données des chroniques non visibles (réglé par l'écouteur JSON) et la page de connexion (portée).
  - Le filtre Twig `spaceless`, déprécié depuis Twig 3.12, reste dans les gabarits : à remplacer avant Twig 4.
- 2026-10-24 : consultation validée côté serveur. Sur la base réduite (1 000 cas, quatre profils) puis sur la base complète, contre l'ancien code sur PostgreSQL 17 comme contre la référence sur PostgreSQL 11 : toutes les pages sont identiques, à un arrondi près ou de même contenu dans un autre ordre (listes désormais triées), sauf le choix de la langue (erreur 500 de l'ancien, corrigée). L'outil de comparaison a appris en route : adresse de chaque instance, paramètres d'adresse sous forme canonique, traductions pour le JavaScript triées, pages qui ne diffèrent que par l'ordre rangées à part, choix de la langue dans une session à lui.
  - Reste au module : le JavaScript et les feuilles de style compilés ici et non plus copiés de la référence (étape 6 du plan, second temps) ; un coup d'œil dans un navigateur, visualiseur compris, par Lou.
