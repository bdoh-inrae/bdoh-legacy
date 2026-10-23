# Portage de BDOH : où il peut abîmer les données, état des lieux du 23 octobre 2026

Audit daté : il ne change plus une fois écrit. Ses suites sont dans [ROADMAP.md](../../ROADMAP.md).

Lu dans l'ancien dépôt (`../bdoh/`), dont `src/` et `app/` ne diffèrent du commit en production (`523cf8e32`) que par une migration corrigée ; dans la structure de la base de production (dump du 21 octobre 2026, sans données) ; et dans la documentation des versions visées.

## En bref

- Le portage ne peut corrompre des données que là où BDOH écrit : imports, recalculs de chroniques, suppressions, saisie dans l'administration. Ailleurs, il ne peut qu'afficher faux, ce que la comparaison avec l'ancien BDOH voit. La comparaison doit donc porter aussi sur l'état de la base après chaque écriture, pas seulement sur les fichiers et les pages.
- Le point le plus risqué n'est pas PHP mais la file de tâches : l'ancienne garantit qu'une seule tâche à la fois touche une chronique donnée. Son remplaçant doit garder cette garantie, sinon deux recalculs concurrents peuvent écrire des mesures incohérentes.
- Les calculs vivent dans PostgreSQL et les valeurs importées passent de fichier à base sous forme de texte : c'est ce qui protège le mieux les mesures, à garder tel quel.
- Entre PHP 7.1 et 8.4, plusieurs changements silencieux touchent BDOH : nombres écrits avec une virgule selon la langue réglée, arrondis, comparaisons entre texte et nombre. Aucun ne touche les mesures stockées ; ils peuvent changer le dernier chiffre d'un export, ou faire accepter un fichier que l'ancien refusait.
- Les dates des mesures doivent garder leurs millisecondes et le fuseau par défaut de PHP doit rester celui de Paris.

## Constats

### 1. Où BDOH écrit

| Écriture                         | Code de l'ancien dépôt                                                      | Ce qu'elle touche                          |
|----------------------------------|-----------------------------------------------------------------------------|--------------------------------------------|
| Import de mesures et de plages   | `BdohAdminBundle/Importer/Measure/`, `MeasureImportCommand`                 | `mesure`, `plage`, tables `temp.tmp_*`     |
| Import de points de contrôle     | `BdohAdminBundle/Importer/Controle/Manager.php`                             | `pointcontrole`                            |
| Import de barèmes                | `BdohAdminBundle/Importer/Bareme/Manager.php`                               | `bareme` (tableau `float8` à 2 dimensions) |
| Import de shapefiles             | `BdohAdminBundle/Importer/Shapefile/Manager.php` (`shp2pgsql`)              | `station`, `bassin`, `courseau`            |
| Recalcul des chroniques          | `ComputeChroniqueCommand`, `ConvertChroniqueCommand`, fonctions SQL         | mesures des chroniques calculées           |
| Taux de remplissage, métadonnées | `ComputeFillingRateCommand`, `bdoh_update_*`                                | `tauxremplissage`, `chronique`             |
| Vider une chronique              | `ChroniqueController::emptyChroniqueAction`                                 | mesures                                    |
| Administration (Sonata)          | `BdohAdminBundle/Admin/`                                                    | métadonnées, droits, comptes               |
| Historique                       | `DocumentedEntityListener`                                                  | `historique`                               |

Chemins dans `src/Irstea/`. L'import se fait en deux temps : lecture du fichier vers une table temporaire et contrôles, puis confirmation par l'utilisateur ; l'objet d'import est sérialisé entre les deux (`Importer::__sleep`). Un import abandonné laisse sa table temporaire : la production en garde 80 dans le schéma `temp`.

### 2. Ce qui protège les mesures, à garder

- Les valeurs importées ne sont jamais converties en nombres par PHP : contrôlées par `is_numeric`, elles passent telles quelles du fichier à un CSV, puis à PostgreSQL par `COPY` (`Importer.php`, `processMesure`). Aucune perte de précision possible côté PHP.
- Le décalage horaire du fichier est appliqué en SQL (`adjustDates`, `Importer.php`, ligne 425), sur des dates traitées comme du texte.
- Les calculs (interpolations, cumuls, barèmes, taux de remplissage) sont 28 fonctions PostgreSQL. Elles n'arrondissent rien et ne convertissent pas les nombres en texte (aucun `::numeric`, `round`, `to_char` dans `app/sql/`) : elles rendront les mêmes valeurs sous PostgreSQL 17.
- Toutes les fonctions présentes en production sont définies dans le code (`app/sql/` ou les migrations Doctrine). La référence reste la base : le nouveau dépôt part de la structure de la production (fonctions comprises), pas d'une relecture des 167 migrations.

### 3. La file de tâches : la garantie à ne pas perdre

- Chaque recalcul ou taux de remplissage d'une chronique part dans une file propre à cette chronique (`job_queue_chronique_<id>`, `JobManager.php`), et chaque file n'exécute qu'une tâche à la fois (`max_concurrent_jobs: 1`, `app/config/config.yml`). Le service lance au plus deux tâches en parallèle (`--max-concurrent-jobs=2`, `app/config/bdoh-job-worker.service`). Les recalculs passent avant les exports (priorités haute et basse).
- Un recalcul peut se propager aux chroniques qui dépendent de celle qui a changé (`--propagate`) ; l'administration refuse certaines modifications tant qu'une tâche est ouverte sur la chronique (`JobManager::hasActiveJobs`).
- `jms/job-queue-bundle` ne fonctionne pas avec les Symfony récents. Symfony Messenger, son remplaçant naturel, ne sait pas d'origine n'exécuter qu'un message à la fois par chronique ; il faut l'ajouter : un verrou par chronique (`symfony/lock`), ou un seul processus pour tous les recalculs. Le paquet de Sulu montre la première façon ([sulu/messenger](https://root.packagist.org/packages/sulu/messenger)).
- À éprouver par un essai dédié : deux imports rapprochés sur une chronique et sur une chronique qui en dépend, comparés à leur exécution l'une après l'autre.

### 4. Les changements silencieux de PHP 7.1 à 8.4

- **Nombres écrits avec une virgule.** `slugify` appelle `setlocale(LC_ALL, 'fr_FR.UTF-8')` (`IrsteaBdohDataBundle.php`, ligne 30). Sous PHP 7.1, tout nombre décimal converti en texte ensuite, dans la même requête, s'écrit avec une virgule ; par exemple le point d'une station, construit par concaténation (`StationRepository.php`, ligne 193). Depuis PHP 8.0, cette conversion ignore la langue ([php.net, PHP 8.0](https://www.php.net/manual/en/migration80.incompatible.php)). Un écart sur ce genre de chemin est un bogue de l'ancien code. Le portage ne règle plus `LC_ALL`.
- **Texte et nombres.** Depuis PHP 8.0, `0 == "abc"` est faux, et un nombre suivi d'espaces est reconnu par `is_numeric` (même source). L'import au format BDOH nettoie ses champs (`BdohImporter.php`, `processChroniqueInLine`), mais un fichier refusé par l'ancien BDOH peut être accepté par le nouveau ; PostgreSQL lit correctement ces valeurs. Le code compte une cinquantaine de comparaisons non strictes (`==`, `!=`, hors tests) : le portage les rend strictes, une par une.
- **Tris.** Depuis PHP 8.0, les tris sont stables : des éléments égaux peuvent sortir dans un autre ordre.
- **Arrondis.** PHP 8.4 a récrit l'arrondi et supprimé le « pré-arrondi » ([php.net, PHP 8.4](https://www.php.net/manual/en/migration84.other-changes.php)) ; `number_format`, qui met en forme chaque valeur exportée (`AbstractExtractor.php`, ligne 145, 3 décimales par défaut), arrondit de la même façon. Depuis PHP 7.2, il ne rend plus `-0` ([php.net, number_format](https://www.php.net/manual/en/function.number-format.php)) : l'ancien BDOH peut exporter `-0.000`.
- **Nombres lus en base.** Depuis PHP 8.4, `pdo_pgsql` rend les colonnes `float8` en nombres et non plus en texte (php.net, PHP 8.4). Depuis PostgreSQL 12, le texte d'un `float8` est le plus court qui redonne exactement la valeur, quand PostgreSQL 11 l'arrondissait à 15 chiffres ([EDB, PostgreSQL 12](https://enterprisedb.com/blog/postgresql-12-few-special-case-performance-enhancements)). Le nouveau BDOH verra donc les valeurs exactes, l'ancien des valeurs arrondies à 15 chiffres.

Conséquence pour la comparaison : les nombres se comparent comme des nombres. Un écart d'une unité sur le dernier chiffre affiché n'est admis que si la valeur exacte tombe à la limite d'un arrondi ; `-0.000` vaut `0.000` ; tout autre écart est une régression.

### 5. Les dates

- Les dates des mesures sont des `timestamp(3)`, avec millisecondes, lues et écrites comme du texte par un type Doctrine propre à BDOH (`DateTimeMsType`) et les transformateurs de `BdohBundle/DataTransformer/`. Le type `datetime` standard de Doctrine perd les millisecondes : à ne pas employer pour ces colonnes.
- Le fuseau par défaut de PHP intervient à plusieurs endroits : conversions de `DateTimeUtil`, dates de début et de fin des exports (`AbstractExportCommand.php`, formulaire d'export avec `model_timezone` UTC et fuseau d'affichage par défaut), contrôle « date postérieure à maintenant » des imports. L'image Docker de développement fixe `Europe/Paris` (`dev/docker/app/prepare-php`). Sur les serveurs, la valeur n'est pas connue, et elle peut différer entre Apache et la ligne de commande, sous laquelle tourne la file de tâches. Le nouveau BDOH fixe `Europe/Paris` dans son code.

### 6. Doctrine et la structure de la base

- Les types propres à BDOH (`DateTimeMsType`, `BaremeType` pour les barèmes, `GeometryType`, `TextArrayType`) sont à récrire pour DBAL 4, dont l'interface des types a changé et qui ne lit plus les commentaires de colonne servant à les reconnaître ([discussion DBAL #5107](https://github-redirect.dependabot.com/doctrine/dbal/pull/5107)). Doctrine ORM 3 ne lit plus la correspondance en YAML : elle passe en attributs.
- Une migration générée par Doctrine (`doctrine:migrations:diff`, `doctrine:schema:update`) peut alors proposer de modifier ou de supprimer des colonnes qu'elle reconnaît mal. Appliquée sans être lue, elle peut détruire des données.

### 7. PostgreSQL 11 vers 17, PostGIS 2.5 vers 3

- La production a un PostGIS 2, dont le raster fait encore partie (commentaire de l'extension dans la structure du dump). Le restaurer sous PostGIS 3 demande une procédure propre au raster ([PostGIS, raster](https://postgis.net/documentation/tips/tip-upgrading-raster-from-2-3/)). BDOH n'emploie que quelques fonctions (`ST_X`, `ST_Y`, `ST_Transform`, `ST_GeometryN`, emprise) et `shp2pgsql` ; il ne semble pas utiliser le raster.
- Le changement d'affichage des `float8` (constat 4) touche toute requête qui convertit un nombre en texte.

### 8. Ce que l'ancien code fait déjà de travers

- **Seuils de validité arrondis.** Le formulaire d'une chronique arrondit `minimumValide` et `maximumValide` à 3 décimales à chaque enregistrement (`'precision' => 3`, `Admin/Data/Chronique.php`, lignes 241-242). Pour un paramètre à petites valeurs, une concentration par exemple, le seuil change sans que personne l'ait voulu. Les coordonnées et l'altitude des stations ont le même réglage, sans effet notable : elles sont en mètres (Lambert-93, `Station::SRID`).
- `DateTimeUtil::getLastDayOfCurrentMonth` compare un texte à un entier (`!== 12`), toujours vrai ; le résultat reste juste par débordement de mois.

## Ce que ça appelle

- Le socle part de la structure de la production. Il fixe le fuseau `Europe/Paris` et ne règle pas `LC_ALL`.
- La comparaison couvre l'état de la base après chaque écriture, avec la règle de comparaison des nombres du constat 4. Elle joue des fichiers d'import variés : ceux de `dev/exemples/`, et des variantes avec espaces, fins de ligne Windows, virgules décimales, valeurs invalides, dates au changement d'heure, fuseaux.
- Le remplacement de la file de tâches garde l'exécution une à une par chronique, et passe l'essai du constat 3, avant le portage des imports.
- Une migration générée par Doctrine n'est jamais appliquée sans avoir été lue.
- Le passage à PostgreSQL 17 suit la procédure du raster, essayée d'abord sur la base anonymisée avec l'ancien code.
- Sur la base anonymisée : compter les seuils de validité à plus de 3 décimales, vérifier l'absence de raster.
- Sur les serveurs (Lou) : relever `date.timezone` pour Apache et pour la ligne de commande.
- À trancher (Lou) : le nouveau BDOH arrondit-il encore les seuils de validité ? Recommandé : non, il garde la valeur saisie.

## Sources

- [php.net, PHP 8.0 : changements incompatibles](https://www.php.net/manual/en/migration80.incompatible.php)
- [php.net, PHP 8.4 : autres changements](https://www.php.net/manual/en/migration84.other-changes.php)
- [php.net, number_format](https://www.php.net/manual/en/function.number-format.php)
- [EDB, PostgreSQL 12 et l'affichage des float8](https://enterprisedb.com/blog/postgresql-12-few-special-case-performance-enhancements)
- [PostGIS, mise à jour du raster](https://postgis.net/documentation/tips/tip-upgrading-raster-from-2-3/)
- [Doctrine DBAL, discussion #5107 sur les commentaires de type](https://github-redirect.dependabot.com/doctrine/dbal/pull/5107)
- [sulu/messenger, verrous pour Symfony Messenger](https://root.packagist.org/packages/sulu/messenger)
