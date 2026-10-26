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
