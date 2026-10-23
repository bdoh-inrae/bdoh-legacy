# Consultation publique et visualiseur

État : en préparation, le 2026-10-23. Point 2 du portage dans `ROADMAP.md` ; suit le socle (`dev/plans/2026-10-23_socle.md`).

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
