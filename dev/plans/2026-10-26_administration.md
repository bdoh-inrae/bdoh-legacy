# Administration

État : en cours, commencé le 2026-10-26 ; suit les comptes (`dev/plans/2026-10-26_comptes.md`). Point 6 du portage dans `ROADMAP.md`.

But : l'administration servie par le nouveau BDOH, sur Sonata Admin 4, montrant les mêmes listes et les mêmes formulaires que l'ancien sur Sonata Admin 3, et laissant la base dans le même état après chaque enregistrement et chaque suppression.

## Ce que ça couvre

Relevé dans l'ancien code (`../bdoh/src/Irstea/`) :

- **34 administrations Sonata** (`BdohAdminBundle/Admin/`, 4 479 lignes), déclarées dans `BdohAdminBundle/Resources/config/services.yml` (étiquette `sonata.admin`, groupes AdminCenter, AdminGeographic, AdminPeripheral, AdminTechnical, AdminTheia, AdminUser) ; la classe de base `Admin/Admin.php` (nom et adresse des routes tirés du nom de la classe, `bdoh_admin_…`, libellés traduits sans stratégie, pas d'export ni d'action groupée) et `Admin/SimpleEntity.php`. Les plus grosses : `Partenaire` (479 lignes), `Observatoire` (461), `Chronique` (396), `DataSet` et `ChroniqueContinue` (269), `Station`, `TypeParametre`, `FamilleParametres`, `Unite`, `Utilisateur`.
- **Contrôleurs à part** : `ChroniqueController` de Sonata (recalcul, codes de chronique, chroniques filtrées), `ColorsController` (383 lignes), `ConversionController` (331), `FillingRateController`, `InfoController`.
- **Gabarits** : `standard_layout.html.twig`, `CRUD/` (édition et suppression propres à plusieurs entités, actions de liste), `Form/widgets.html.twig`, `Colors/`, `Conversion/`, `Mail/newAccount.txt.twig` ; et trois gabarits de Sonata surchargés au niveau de l'application (`app/Resources/SonataAdminBundle/views/`) : le bouton « Nouveau », les boutons « Annuler » et « Cloner » du formulaire, la page de clonage.
- **Formulaires** : `FileType`, `TheiaThesaurusType`, `WysiwygType` et leurs transformateurs.
- **Menu** : `MenuBuilderListener` (barre latérale de Sonata, KnpMenu 2 vers 3).
- **Droits** : `BdohSecurityHandler` (`BdohSecurityBundle/Security`, 108 lignes), le gestionnaire de droits de Sonata, exclu du module 5.
- **Historique** : `BdohLoggerBundle/Controller/LoggerController` (`/historique`, `/historique/recent`), dont les routes attendent dans `config/routes_pending/logger.yaml`.
- Les 241 routes d'attente de `config/routes_pending/admin.yaml` : 34 administrations (5 à 8 routes chacune) et 15 routes à part.

## Versions

Sonata Admin 4.43 et `sonata-project/doctrine-orm-admin-bundle` 4.21 prennent Symfony 7.3 et plus, PHP 8.2 et plus, DBAL 4 et ORM 3 (`composer show -a`, 26 octobre 2026) ; ils demandent KnpMenu 3 et `symfony/stimulus-bundle`. Sonata 4 garde Bootstrap 3 et AdminLTE 2.

## Ce qui change de Sonata 3 à 4 (à vérifier en route)

- Signatures typées (`configureFormFields(FormMapper $form): void`…), filtres et types de formulaires désignés par leur classe (`StringFilter::class`, `ModelType::class`), plus de `getConfigurationPool()->getContainer()` : les services s'injectent.
- Nom et adresse des routes : `generateBaseRouteName()` et `generateBaseRoutePattern()`, ou les options de l'étiquette, à la place des propriétés `baseRouteName` et `baseRoutePattern`. Les adresses et les noms de routes de l'ancien restent (règle du `CLAUDE.md`).
- Gabarits : le registre de gabarits (`configureTemplates` ou les appels `setTemplate`) à la place de `getTemplate()` ; les gabarits CRUD de l'ancien héritent de ceux de Sonata 3, à rebaser sur ceux de Sonata 4.
- Le gestionnaire de droits implémente `SecurityHandlerInterface` de Sonata 4.
- Les comptes créés par l'administration n'ont plus de mot de passe (l'ancien leur en donnait un par défaut) : chaîne vide, connexion par lien (`dev/differences.md`, « Connexion par lien »).
- « Seuils de validité arrondis » (`dev/differences.md`) : décidé, à faire ici, au formulaire d'une chronique.

## Comparaison

`compare.py` gagne un mode `admin`, sur une copie de la base par côté, au nom d'un administrateur et d'un gestionnaire d'observatoire :

1. Pages : tableau de bord, liste de chaque administration, formulaire de création, formulaire d'édition de la première entité, page de suppression. Les noms de formulaires de Sonata (`s<uniqid>`) sont déjà normalisés.
2. Écritures, pour chaque administration : l'édition renvoyée sans changement (la base ne doit pas bouger, hors l'historique), une édition qui change un champ, une création minimale, la suppression de ce qui a été créé ; réponses et état des tables touchées, comparé table par table.
3. Les contrôleurs à part : couleurs, conversion, taux de remplissage, recalcul.

## Étapes

1. Paquets : Sonata Admin 4, doctrine-orm-admin-bundle, KnpMenu 3 ; configuration reprise de `app/config/sonata.yml`.
2. La classe de base et une administration simple (`SimpleEntity`, puis `Unite`), jusqu'à la comparaison de ses pages et de ses écritures : elle fixe la façon de faire pour les autres.
3. Les autres administrations, par groupe, chacune comparée avant la suivante.
4. Contrôleurs à part, menu, gestionnaire de droits, historique ; les routes d'attente disparaissent.
5. Relecture par un second Claude, ciblée sur ce que la comparaison ne joue pas.

## Suivi

- 2026-10-26 : plan écrit, à reprendre une fois le module 5 clos.
- 2026-10-26 : étapes 1 et 2.
  - Sonata Admin 4.43, `doctrine-orm-admin-bundle` 4.21, KnpMenu 3 et `symfony/stimulus-bundle` (que les gabarits de Sonata 4 emploient), bundles rangés dans l'ordre de l'ancien noyau ; les fichiers que la recette de Stimulus ajoutait (`assets/`, `package.json`) retirés. Configuration reprise de `sonata.yml` (`config/packages/sonata_admin.yaml`), routes de Sonata en tête du `routing.yml` du bundle comme dans l'ancien.
  - Classe de base, `SimpleEntity`, `Unite`, gestionnaire de droits, menu, mise en page et gabarits CRUD portés ; surcharges de l'application dans `templates/bundles/SonataAdminBundle/`. Ce qui change de forme et pourquoi : `dev/decisions.md` (« Classe de base des administrations », « JavaScript de l'administration »).
  - `compare.py admin` : les pages de chaque administration portée pour un administrateur et un gestionnaire qui n'est que gestionnaire, puis les écritures (édition renvoyée telle quelle, édition, création, suppression) et l'état des tables. Unité : pages identiques hors du menu, qui attend les autres administrations ; écritures identiques, tables `unite` et `historique` comprises. Les écarts propres à Sonata 4 sont admis (`dev/differences.md`, « Administration sur Sonata 4 »).
  - Suite : les autres administrations, par groupe (étape 3), en commençant par les plus simples (`SimpleEntity`, données Theia/OZCAR, données techniques).
- 2026-10-26 : étape 3, 27 administrations sur 34 portées : toutes sauf les observatoires, les cinq chroniques et les jeux de données. Pages et écritures identiques à l'ancien, hors du menu et des liens vers les administrations pas encore portées ; seuls écarts admis, le courriel d'un compte créé (connexion par lien) et les fins de ligne des textes longs (`dev/differences.md`).
  - Ce que Sonata 4 change et qui est remis comme dans Sonata 3 : tri par défaut (identifiant, croissant) ; route des liens d'identifiant selon les droits, colonnes d'entités liées non triables (`Sonata3ListExtension`) ; choix des filtres de texte (`StringOperatorTypeExtension`) ; liens vers une entité liée vers son édition (`default_admin_route`) ; pas de bouton « Ajouter » sur la commune d'une station ; champ d'adresse web de type url.
  - Repris autrement : `setHelps` de Sonata 3 devient l'option `help` de chaque champ (`help_html`, affichée en HTML comme alors) ; `validate()` passe par une `InlineConstraint` (classe de base) ; `getNewInstance()` devient `alterNewInstance()` ; les choix de `ChoiceType` retournés (libellé ⇒ valeur) ; les types de champs en chaînes (`'number'`, `'date'`…) en classes, `DateType` avec `widget: choice`.
  - Les familles de paramètres se rangent par niveaux dans select2 4 par `familles-types.js`, qui ne servait à rien sous select2 3.5 (celui-ci recopiait la classe des options) : à regarder dans un navigateur.
  - `compare.py admin` : l'administrateur fonctionnel n'a de droits sur les données que sur ses observatoires, d'où celui qu'il gère le plus fourni ; courriels lus dans Mailpit ; normalisations propres à Sonata 4 documentées dans le script.
  - Reste : observatoires (`default_protocol: null` pour le lien, fichiers joints, couleurs, Theia), chroniques (cinq administrations, `ChroniqueController`, clonage, scripts `edit-chronique*.js`), jeux de données ; puis l'étape 4 (contrôleurs à part, historique) et la relecture.
- 2026-10-26 : étape 3 finie, les 34 administrations portées. `compare.py admin` : 399 parties, toutes identiques hors des écarts admis (courriel d'un compte créé, mot de passe vide), et la vérification « nouveau seul » des seuils.
  - Observatoires : le mot de passe Theia se chiffre par le service du bundle Theia/OZCAR, porté en avance (`PasswordEncryptionService`, `PasswordEventSubscriber`), avec sa clé dans l'environnement (`THEIA_PASSWORD_SECRET_KEY`).
  - Chroniques : `ChroniqueController::cloneAction` sur le `CRUDController` de Sonata 4 ; `InfoController` (code proposé d'une chronique) porté avec elles ; options des filtres passées dans `field_options` ; `setParameters` d'ORM 3 avec une `ArrayCollection` là où une requête remplace les paramètres de son dépôt.
  - Seuils de validité sans arrondi (`PreciseNumberType`), décision du 23 octobre appliquée.
  - Filtres de dates « est vide » et « n'est pas vide » gardés (`Filter/DateFilter.php`) ; aide des champs dans une span comme sous Sonata 3 (`Form/widgets.html.twig`).
  - Relevé en route : deux autres compteurs d'identifiants en retard, que le contrôle de `inspect.sql` ne voyait pas ; seconde migration (`dev/differences.md`).
  - `compare.py admin` : chaque administration s'ouvre sur celui des observatoires de l'administrateur qui a une entité à montrer (l'ancien fige les rôles sur l'observatoire de la connexion : un navigateur par observatoire).
- 2026-10-26 : étape 4 finie, les routes d'attente et `PendingController` retirés. `compare.py admin` : 456 parties, toutes identiques hors des écarts admis (courriel et mot de passe vide d'un compte créé, taux de remplissage d'une chronique inconnue).
  - Couleurs, conversion, taux de remplissage : contrôleurs portés avec leurs routes en attributs, chargées en fin du `routing.yml` du bundle comme les annotations de l'ancien ; mêmes noms, adresses et contraintes que l'ancien (`debug:router` comparé). Le jeton CSRF maison de ces pages est gardé ; le nom du jeu de qualité s'échappe dans le message de succès, affiché en HTML.
  - Taux de remplissage : `find()` à la place de `findOneById()`, qui levait une exception avant le test de la chronique inconnue : 404 au lieu de 500 (`dev/differences.md`).
  - Historique : `LoggerController` et ses 44 gabarits ; nom du dépôt `HistoriqueAdministrationDataSet` à la bonne casse (l'ancien ne le trouvait que parce que la classe était déjà chargée) ; `max()` d'une liste d'années vide gardé à `false`. Pages identiques, 144 000 lignes pour l'observatoire le plus fourni.
  - `compare.py admin` gagne les contrôleurs à part : pages des couleurs (chaque jeu de qualité), de la conversion (chronique sans conversion, avec fille, sans fille), de l'historique (année récente, deux derniers mois, plus ancienne, hors liste) ; écritures des couleurs et de la conversion (telles quelles, changées, valeurs refusées, sans jeton CSRF) et du taux de remplissage ; état de `jeuqualite`, `qualite`, `conversion`, `jeuconversion` et des tâches. Normalisés : le jeton CSRF maison, les identifiants tirés au hasard des barèmes de l'historique, les dates du jour (connexions de la comparaison, barème sans date de début, que Twig remplace par la date du moment).
  - Configurations incomplètes (`dev/audits/2026-10-23_reference-db.md`) : leurs pages s'affichent à l'identique (`compare.py pages` et `admin`), leur recalcul échoue sans rien écrire, des deux côtés (`compare.py state`) ; un message clair reste « à trancher » (`dev/differences.md`).
  - Relevé en route, dans `dev/local/security.md` : les erreurs d'import et de conversion citent en HTML brut des valeurs lues dans le fichier ou saisies ; correction à faire à part.
  - À essayer dans un navigateur (Lou) : les pages des couleurs (sélecteur de couleur) et de la conversion, compilées par l'ancien et copiées de la référence, et les onglets et filtres de l'historique.
  - Suite : l'échappement des erreurs, puis l'étape 5, la relecture.
- 2026-10-26 : les valeurs citées dans les erreurs d'import et de conversion s'échappent (`Errors::translations`, `dev/local/security.md`) ; `compare.py imports` : deux cas de plus, seuls nouveaux écarts.
