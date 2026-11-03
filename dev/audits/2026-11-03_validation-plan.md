# Le plan de validation du portage : état des lieux du 3 novembre 2026

Audit daté : il ne change plus une fois écrit. Ses suites sont dans [ROADMAP.md](../../ROADMAP.md).

Il reprend [l'état des lieux du même jour](2026-11-03_migration-trust.md) et pose une question : son plan suffit-il pour faire confiance à la migration, surtout sur le calcul et la gestion des données ?

Lu : `dev/compare/compare.py` (en entier pour les modes `pages`, `exports`, `state`, `queue`, `imports`, en survol pour `accounts`, `admin`, `theia`), les fonctions SQL de calcul, les commandes de calcul, l'exécuteur de tâches, les plans et repères du dépôt. Mesuré sur le poste de développement : sur des copies de travail, dans des transactions annulées ou des copies jetables. Aucun mode de `compare.py` n'a été rejoué, et rien n'a été corrigé.

Barrière respectée : aucune chronique cachée lue. Les requêtes sur les bases ne sortent que des comptes et des drapeaux. Les calculs et les exports essayés ne portent que sur des chroniques visibles dont toutes les entrées sont visibles. Des rapports de `compare.py`, seuls les comptes ont été lus, et, pour un cas d'arrondi, la forme des lignes, chiffres et lettres masqués.

## En bref

- Les preuves que le plan demande sont les bonnes, mais il lui manque l'étape qui vient avant : éprouver l'outil. `compare.py` n'a jamais été testé. Sa règle d'arrondi classe « à un arrondi près » une heure de décalage, un jour, un compteur à +1, un identifiant à +1, un code de sortie 0 contre 1.
- Le recalcul complet est une bonne preuve, à condition de savoir ce qu'il prouve. Essayé sur les 50 chroniques calculées visibles aux mères visibles (14 millions de mesures) : 46 identiques à ce que l'ancien avait écrit au fil des années, 4 différentes par des dates. Mais il éprouve surtout PostgreSQL 17 et non le code porté, et un calcul en échec peut y passer pour un succès (constat 3).
- Un calcul en échec vide la chronique calculée, dans l'ancien comme dans le nouveau : essai de bout en bout, 17 574 mesures, puis 0, et des métadonnées qui en annoncent toujours 17 574.
- Repasser tous les modes sur la base complète apporte peu de variété : 303 des 304 adresses de `pages` sont déjà celles de la base réduite. Il faut d'autres cas, tirés de l'usage réel : le pas « 1 événement » (1 233 exports) n'est joué par aucun cas.
- La couverture par PCOV ne verra pas le calcul, qui vit en SQL. Il faut aussi `plpgsql_check`.
- La barrière entre en conflit avec le plan : les écarts des modes rejoués ne pourront pas se lire. Il faut un registre des écarts admis, et régénérer les dumps plus tôt, juste après les deux preuves qui ont besoin des vraies données.
- Le retour arrière n'est pas celui des manuels : l'ancien BDOH est fermé, sans écriture depuis le 20 octobre. Plus de reprise de données à rattraper, mais peut-être plus d'ancien où revenir. La répétition sur la seule base anonymisée ne suffit pas.
- Ce qui tient est solide : 257 025 612 mesures identiques au bit près entre PostgreSQL 11 et 17 (mesure de cet audit), et un calcul qui n'emploie aucune fonction dépendante de la plateforme.

## Constats

### 1. L'outil de comparaison n'a jamais été éprouvé

**1.1 La règle d'arrondi est trop large.** Essai de `same_but_rounding` (`compare.py:457`) sur des erreurs inventées :

| Erreur inventée                                 | Classée en         |
|-------------------------------------------------|--------------------|
| Heure d'un export décalée d'une heure           | à un arrondi près  |
| Date décalée d'un jour, année décalée d'un an   | à un arrondi près  |
| Nombre de mesures 1000 devenu 1001              | à un arrondi près  |
| Identifiant de chronique dans une adresse, +1   | à un arrondi près  |
| Code de sortie 0 devenu 1                       | à un arrondi près  |
| Altitude `3,34` devenue `4,30`                  | à un arrondi près  |
| Valeur `100` devenue `100.9`                    | à un arrondi près  |
| Valeur `3.500` devenue `3.501` (le cas voulu)   | à un arrondi près  |
| Valeur `3.500` devenue `3.502`                  | différents         |
| Statut 500 devenu 501 (exclu par la règle)      | différents         |

La règle trouve les nombres par une expression régulière, sans savoir ce qu'ils sont, et prend le nombre de décimales de l'ancien seul, sans compter la virgule. L'audit du 23 octobre demandait davantage : un écart admis « que si la valeur exacte tombe à la limite d'un arrondi » (constat 4) ; ce n'est pas vérifié.

Le cas s'est produit : le rapport d'imports du 3 novembre à 22 h 10 compte 12 « arrondis » dans `état jms_jobs`, tous sur des entiers, à un écart de 1. Ce sont les `+01:00` et `+00:00` des arguments des tâches : un écart expliqué (`dev/differences.md`, « Deux fuseaux horaires »), normalisé depuis, mais classé comme un arrondi. Cette catégorie est comptée à part « pour être relue » ; sous la barrière, Claude ne lit les rapports qu'en comptes, et elle n'est plus relue.

**1.2 L'empreinte des mesures confond des lignes.** `STATE_QUERIES` assemble chaque ligne par `concat_ws`, qui [ignore les arguments NULL](https://www.postgresql.org/docs/17/functions-string.html). Un minimum NULL et un maximum 5 donnent le même texte qu'un minimum 5 et un maximum NULL (essai : vrai). Les audits ont relevé des minimums et maximums inversés sur OTHU (`2026-10-23_reference-db.md`) : c'est le genre d'erreur qui passerait. L'empreinte passe aussi par le texte des nombres : exact entre deux copies sous PostgreSQL 17, faux entre 11 et 17.

**1.3 Une table écrite n'est pas comparée.** `plage` n'est dans aucun état comparé (`STATE_QUERIES`, `OTHER_STATE`, `ACCOUNT_QUERIES`) : 60 895 lignes, 5 346 imports au format `BdohPlage` dans l'historique. Après un import de plages, seuls les réponses et les comptes de `chronique` se comparent. Plus largement, la liste des tables est fixe : une écriture dans une table imprévue ne se voit pas.

**1.4 Le rapport ne prouve pas ce qu'il dit.** `Report` n'écrit ni le commit, ni la base, ni les versions. `--base` ne dit que l'endroit où tirer les cas : `pages` et `exports` interrogent les instances telles qu'elles sont branchées (`compare.py:536-574`), sans vérifier qu'elles lisent cette base. « Sur le code du 3 novembre », « sur la base complète » ne peuvent pas se vérifier après coup. Le code de sortie vaut toujours 0 (`compare.py:2826-2827`) : rien ne peut échouer.

**1.5 Les écarts admis ne s'écrivent nulle part sous une forme que l'outil lit.** `dev/differences.md` dit que l'outil « admet ces écarts et eux seuls » ; `compare.py` n'a pas de registre. Les derniers rapports comptent 89 cas « différents » et 44 « autre ordre » (`pages` 60 et 44, `imports` 10, `accounts` 10, `admin` 9), triés à la lecture. Sous la barrière, cette lecture n'est plus permise (constat 7).

**1.6 Pas de témoin.** Aucune passe de l'ancien contre l'ancien ne mesure le bruit, alors que `--old` et `--new` valent `reference` par défaut. Une vingtaine de normalisations (`NORMALIZE`, `SONATA_*`, `WIRING`, `canonical_query`) sont chacune un angle mort sans test. La catégorie « autre ordre » n'est pas limitée aux deux listes sans tri que nomme `dev/differences.md`. Les contrôles sur GitHub ne touchent pas à `dev/compare/` (`.github/workflows/checks.yaml`).

### 2. Les cas : le volume n'apporte pas la variété

**2.1 Mêmes règles, mêmes cas.** Les cas sont « le premier de chaque sorte », sur une fenêtre de 2024. Mesuré en tirant les cas sur les deux bases :

| Mode      | Base réduite  | Base complète  | Communs  |
|-----------|---------------|----------------|----------|
| `pages`   | 303 adresses  | 304 adresses   | 303      |
| `exports` | 126 cas       | 141 cas        | 66       |

Repasser sur la base complète reste utile pour le volume, pas pour la variété.

**2.2 L'usage réel n'est pas ce que jouent les cas.** D'après `historique` (comptes) :

- Exports : `compare.py` joue les pas 1 heure, 1 jour, 1 mois, 1 an (`export_cases`). « 1 événement » (998 en moyenne, 235 en cumul) n'y est pas, alors que trois fonctions SQL ont une branche pour lui. 5 minutes (238 en cumul) et 15 minutes (115) non plus.
- Imports : `Bdoh` 12 849, `BdohPlage` 5 346, QJO aucun. Il y a 2 cas de plages et 3 de QJO. Les fuseaux employés sont +00, +01, +02, -01, -03, -04 et +10 ; les cas jouent +00, +01 et -05.
- Essai de cet audit : 8 combinaisons de pas jamais jouées, sur 2 chroniques visibles, ancien contre nouveau. Aucune différence de structure, et 5 valeurs à une unité du dernier chiffre aux pas de 5 et 15 minutes, sur environ 80 000 lignes. Sur le cas relancé (la moyenne à 5 minutes), elles viennent de PostgreSQL : l'ancien sous PostgreSQL 17 donne les mêmes lignes que le nouveau. Pas de régression, donc : la règle « Arrondis à la limite » joue, mais rien ne vérifie qu'elle joue seule (1.1).

**2.3 Les sortes de calcul.** Comptes sur la base de production anonymisée, comparés à la chaîne de trois chroniques de `state` :

| Sorte                                      | Nombre                 | Dans la chaîne de `state` |
|--------------------------------------------|------------------------|---------------------------|
| Une entrée                                 | 53 chroniques          | oui                       |
| Deux entrées                               | 368 chroniques         | oui                       |
| Facteur multiplicatif                      | 34 chroniques          | oui                       |
| Barème `identite`                          | 742 transformations    | oui                       |
| Barème en courbe                           | 50 transformations     | oui                       |
| Limites `true_value`                       | 316 transformations    | oui                       |
| Barème `lacune`                            | 3 transformations      | non                       |
| Barème `manuel`                            | 4 transformations      | non                       |
| Délai de propagation                       | 6 transformations      | non                       |
| Limites `gap`, `half_value`, `placeholder` | 0, le code les prévoit | non                       |

La chaîne, tirée par une règle sans rapport, couvre donc les sortes les plus employées ; les plus rares manquent. Lu dans la configuration des transformations ; seule la couverture du SQL dira ce qui s'exécute vraiment (constat 4).

**2.4 Les conversions, seul calcul écrit en PHP, sont presque toutes cachées.** 150 des 152 chroniques converties le sont (`ChroniqueConvertieRepository::convertChronique`). `state` n'en calcule que deux, et l'usage monte (303 conversions en 2025, 155 en 2026). Leurs écarts ne peuvent pas se lire aujourd'hui, et une « petite série inventée » à la place (`dev/local/hidden-series.md`) ne garderait pas ce qui les éprouve : plages contiguës, ponctuelles, trous plus grands que l'intervalle.

**2.5 Ce que l'anonymisation retire ne s'essaie pas.** `jms_jobs` et ses tables sont vidées, `confirmationtoken` et `theiapassword` effacés, les adresses remplacées (`dev/anonymize/anonymize.sql`). Donc jamais essayés sur des données réelles : relire les tâches écrites par l'ancien, envoyer un lien à une vraie adresse, rechiffrer les mots de passe de Theia. `state` fait écrire à chaque côté ses propres tâches : aucune lecture croisée.

**2.6 Pas de corpus réel.** Les fichiers d'import sont fabriqués depuis la base. Deux sources possibles, à vérifier : les fichiers restés sur l'ancien serveur (son application pèse 10 Go, `dev/plans/2026-10-26_deployment.md`, sans qu'on sache de quoi) et les journaux d'Apache déjà consultés (`dev/differences.md`), dont les adresses peuvent se rejouer des deux côtés.

**2.7 OTHU n'a pas la forme que supposent les modes.** Ses 119 chroniques sont toutes continues : ni transformation, ni conversion (requête de cet audit ; `2026-10-23_reference-db.md`). `state_commands`, `run_state`, `run_imports` et `run_queue` prennent leur premier résultat sans garde (`first(…)`, `query(…)[0]`) : d'après le code, ces modes s'arrêteront sur OTHU. « Y passer la comparaison » demande d'abord de les adapter. Ce qui compte pour elle : la restauration, l'empreinte (3.7), les taux, et les exports en moyenne et en cumul, puisque deux de ses fonctions diffèrent de la production.

### 3. Le recalcul complet

**3.1 Ce qu'il éprouve.** Les fonctions de calcul sont les mêmes des deux côtés, à `EXTRACT` près, et le PHP n'y fait qu'un `SELECT bdoh_compute_chronique(…)` (`ChroniqueCalculeeRepository`). « Avec le nouveau code » veut donc dire : PostgreSQL 17, la migration d'`EXTRACT` et cet appel. Le code porté n'y joue vraiment que pour les conversions. C'est déjà utile : `state` compare l'ancien et le nouveau sous PostgreSQL 17 seulement, et, avant l'essai de 3.2, aucun calcul n'avait été comparé d'une version à l'autre, sauf les exports.

**3.2 Les valeurs en base ne sont pas toutes le résultat d'un calcul d'aujourd'hui.** Sur 788 liens de chronique mère à chronique fille ayant des mesures, 202 ont une mère mise à jour après sa fille, et 291 une mère sans date (`chronique.miseajour`, qui bouge aussi pour une simple modification : c'est un indice). La propagation est facultative à l'import, et les fonctions ont changé au fil de 166 migrations. Un écart ne dira donc pas de lui-même si c'est le portage, PostgreSQL ou l'histoire de la chronique.

Essai de cet audit, pour mesurer ce risque : les 50 chroniques calculées visibles dont toutes les mères sont visibles, recalculées une à une depuis leurs mères en base, sous PostgreSQL 17, comparées à l'égalité exacte à leurs mesures, puis annulées.

| Entrées | Chroniques | Mesures    | Identiques | Dates en plus ou en moins | Mêmes dates, autres valeurs |
|---------|------------|------------|------------|---------------------------|-----------------------------|
| Une     | 41         | 11 854 306 | 38         | 3                         | 0                           |
| Deux    | 9          | 2 186 439  | 8          | 1                         | 0                           |

Aucun code d'échec. C'est un résultat fort, et meilleur que ne le laissait craindre l'indice ci-dessus : des valeurs écrites au fil des années, sous d'anciennes versions, se retrouvent à l'identique sous PostgreSQL 17. Les 4 chroniques qui diffèrent totalisent 2 860 mesures de plus au recalcul, 59 de moins et 511 valeurs changées, ce qui ressemble à une mère complétée sans propagation ; cela reste à vérifier. Limites : l'échantillon n'est pas tiré au hasard. Il exclut les chroniques à mère cachée, soit l'essentiel de celles à deux entrées (9 ici sur 368), et il ne passe ni par le PHP ni par les conversions.

**3.3 Deux pièges du code de retour,** dans `bdoh_compute_chronique` et ses fonctions d'insertion :

- **Code 32 (lu, pas essayé) :** l'exception remonte au bloc de `bdoh_compute_chronique`, qui annule aussi la purge. Les mesures stockées restent. Une comparaison à la base dit alors « identique » sans que rien ait été calculé.
- **Code 16 :** l'exception est rattrapée dans la fonction d'insertion ; la purge, faite avant, n'est pas annulée, et le PHP valide la transaction, parce que la fermeture de `wrapInTransaction` rend normalement. Essai de bout en bout sur une copie jetable, avec une insertion refusée : commande sortie sur 16, 17 574 mesures calculées, puis 0, métadonnées inchangées ; un nouveau calcul rend les 17 574. L'ancien a le même code (`transactional`, `ComputeChroniqueCommand`). Une violation de l'index unique `(chronique_id, date)`, par exemple, y mène ; le nombre de tâches en production sorties sur 16 ou 32 ne se voit que sur le dump brut.

Contrôle positif disponible : après un calcul réussi, aucun identifiant de mesure n'est gardé (essai : 0 sur 17 574).

**3.4 L'ordre des calculs.** Recalculer les mères avant les filles propage un écart et mélange les causes. La profondeur va jusqu'à 3 (67, 116 et 239 chroniques). Calculer les plus profondes d'abord, sans propagation, garde à chaque chronique ses mères telles qu'elles sont en base : chaque écart est alors celui de cette chronique.

**3.5 Trier plutôt que tout rejouer.** D'abord le nouveau contre la base. Une chronique identique et réellement recalculée est prouvée. Pour les autres seulement, l'ancien sous PostgreSQL 11 : s'il donne comme le nouveau, c'est l'histoire de la chronique (à signaler aux gestionnaires) ; sinon, l'ancien sous PostgreSQL 17 sépare PostgreSQL du portage.

**3.6 Le coût est bas.** Sur la copie complète, en transaction annulée : la plus grosse chronique visible (2 397 581 mesures) se calcule en 2 min 21 s, ses taux en 1 min 21 s. Soit 17 000 et 30 000 mesures par seconde. L'essai de 3.2, avec sa comparaison et sous une autre charge, a tourné à 9 400 mesures par seconde. D'où 45 min à 1 h 20 pour les 45 millions de mesures calculées, et environ 2 h 30 pour les taux, en série. Disque : 433 Go libres, 31 Go par copie.

**3.7 Les comptes ne suffisent pas, et l'empreinte au bit près est peu coûteuse.** `inspect.sql` compare des comptes : il ne voit pas une valeur changée. Essai de cet audit, par chronique, sur les nombres en binaire (`float8send`), en moins de 4 minutes par serveur, entre les copies de la production anonymisée sous PostgreSQL 11 et 17 :

| Table             | Lignes         | Chroniques  | Identiques |
|-------------------|----------------|-------------|------------|
| `mesure`          | 257 025 612    | 2 019       | 2 019      |
| `plage`           | 60 895         | 880         | 880        |
| `pointcontrole`   | 15 672         | 135         | 135        |
| `tauxremplissage` | 256 775        | 2 019       | 2 019      |

Cela confirme [ce que pg_dump est censé garantir](https://postgrespro.com/list/thread-id/2575395). Limite : les deux copies viennent du même dump anonymisé. Sur la VM, il faudra comparer au dump brut.

**3.8 « Export puis réimport redonne les mêmes mesures » est faux tel quel.** Un export écrit 3 décimales (`AbstractExtractor`, `precision`), et les lacunes y valent -9999, là où la base a parfois NULL. La relation juste : l'export d'un réimport redonne l'export, `E(I(E(x))) = E(x)`, mêmes dates et mêmes qualités.

**3.9 « Identique » ne dit toujours pas « juste ».** Comparer à l'ancien ne voit pas ses erreurs, et [des versions écrites indépendamment échouent ensemble plus que le hasard ne le veut](https://libraopen.lib.virginia.edu/public_view/jd472w463). Quand on n'a pas la bonne réponse (le [problème de l'oracle](https://arxiv.org/pdf/1804.01954.pdf)), les [relations métamorphiques](https://doi.org/10.1109/TSE.2016.2532875) servent d'oracle partiel. Ici : la moyenne d'une série constante vaut la constante, le cumul s'additionne sur les sous-pas, un barème linéaire multiplie le résultat, un recalcul refait rend la même chose. Et sur un petit jeu inventé, public, on peut écrire les résultats à la main.

### 4. La couverture mesure moins que le plan ne l'annonce

- [PCOV](https://github.com/krakjoe/pcov) ne donne que les lignes, et seulement le PHP. Le calcul vit dans 31 fonctions SQL : PCOV verra un `SELECT`.
- [`plpgsql_check`](https://github.com/okbob/plpgsql_check) donne la couverture des instructions et des branches (`plpgsql_coverage_statements`, `plpgsql_coverage_branches`), sous `shared_preload_libraries` ; [`track_functions`](https://www.postgresql.org/docs/17/monitoring-stats.html) donne au moins le nombre d'appels. C'est là que 2.3 se tranchera.
- Rien n'est prêt pour le PHP : le projet n'a pas de PHPUnit. Il faut [un fichier préfixé (`auto_prepend_file`) et une fusion par `phpcov`](https://github.com/kekecoco/phpcoverage), pour Apache et pour chaque processus enfant de la file.
- [La couverture montre ce qui n'est pas essayé, elle ne dit pas la qualité des essais](https://cs.ubc.ca/~rtholmes/papers/icse_2014_inozemtseva.pdf). Pas de pourcentage à atteindre : une liste à lire, ce qui écrit d'abord. Une ligne exécutée peut rester non vérifiée : d'où les erreurs injectées.

### 5. Le poste de développement n'est pas la cible

| Composant  | Référence    | Nouveau, en dev (image `postgis/postgis:17-3.5`) | Debian 13                                                      |
|------------|--------------|--------------------------------------------------|----------------------------------------------------------------|
| Système    | Debian 9     | Debian 11                                        | Debian 13                                                      |
| PostgreSQL | 11           | 17.5                                             | [17.11](https://packages.debian.org/trixie/postgresql-17)      |
| PostGIS    | 2.5.5        | 3.5.2                                            | [3.5.2](https://packages.debian.org/postgresql-17-postgis-3)   |
| PROJ       | 4.9.3        | 7.2.1                                            | [9.6.0](https://packages.debian.org/trixie/libproj25)          |
| GEOS       | 3.7.1        | 3.9.0                                            | [3.13.1](https://packages.debian.org/trixie/libgeos3.13.1)     |
| glibc      | 2.24         | 2.31                                             | [2.41](https://packages.debian.org/trixie/libc6)               |
| Collation  | `en_US.utf8` | `en_US.utf8`                                     | à fixer ; celle de la production est à relever                 |
| Rôle       | superuser    | superuser                                        | à créer ; ceux de la production ne sont pas connus             |

Le repère « sur les paquets de Debian 13 » (`dev/decisions.md`) vaut pour PHP et Apache, pas pour la base. Ce que cela change :

- **Le calcul, a priori rien :** les fonctions n'emploient que les quatre opérations, `round`, `floor`, `extract`, `date_part` et `at time zone` (relevé dans leur source), rien de `libm`. À confirmer par une empreinte sur la VM.
- **Les tris sur du texte :** [une autre glibc peut changer l'ordre](https://wiki.postgresql.org/wiki/Locale_data_changes). Une restauration reconstruit les index, sans risque pour eux, mais les listes triées peuvent bouger.
- **PROJ :** seul l'envoi vers Theia en dépend (`ST_Transform(…, 4326)`). L'entrée « Coordonnées envoyées à Theia » de `dev/differences.md` a été mesurée avec PROJ 7.2.1, pas 9.6.0. La migration du point des stations n'en dépend pas (`ST_SetSRID`).
- **Le rôle superuser** cache tout problème de droits : schéma `temp`, séquences, fonctions. La copie des imports, elle, passe par le client (`pdo->copyFromFile`), donc sans droit particulier.
- **`PrivateTmp=true`** dans le service Apache de Debian 13 (lu dans l'image), que Docker ne joue pas : un import en plusieurs étapes perd ses fichiers si Apache redémarre entre deux, et Apache et la file ne partagent pas `/tmp` (les commandes de la file n'y comptent pas, d'après le code). À joindre à ce que le plan de déploiement relève sur `/tmp`.

### 6. La bascule et le retour arrière

**6.1 Rien n'est écrit au-delà de la liste.** `dev/plans/2026-10-26_deployment.md` porte les tâches, sans durées, sans critère de décision, sans personne qui décide. La pratique, d'après des guides d'éditeurs sans norme derrière : [plusieurs répétitions de bout en bout, durées notées, et le retour arrière quand il est crédible](https://if4it.org/best-practices/systems-development-lifecycle-sdlc/data-migration-and-conversion-rehearsal-within-the-sdlc/) ; [des conditions de retour mesurables, écrites d'avance, une personne nommée pour le décider, et ce qu'on fait des données écrites entre-temps, « la partie que la plupart des plans omettent »](https://www.enterprisedb.com/sites/default/files/2026-10/Oracle_Migration_Governance_Runbook.pdf). Plus solide, Google : [« personne ne veut de sauvegardes, on veut des restaurations », et seule une restauration faite prouve qu'on sait restaurer](https://sre.google/sre-book/data-integrity/).

**6.2 Le retour arrière n'est pas celui des manuels.** L'ancien est éteint (`dev/decisions.md`), et la dernière entrée de l'historique date du 20 octobre, à la veille du dump. Si rien n'a été écrit depuis, deux conséquences :

- En mieux : aucune écriture à rattraper, pas d'écriture double, pas de fenêtre à tenir. Le dernier dump est l'état final. La bascule est bien plus simple que dans les manuels.
- En moins : « revenir à l'ancien » n'existe peut-être plus, s'il ne doit pas rouvrir. Il faut l'écrire, et décider de ce qui en tient lieu :

| Quand                            | Retour                                                  | Ce qu'on perd                                 |
|----------------------------------|---------------------------------------------------------|-----------------------------------------------|
| Avant l'ouverture                | Aucun besoin : on corrige, l'ancien reste fermé         | Rien                                          |
| Après, défaut du code            | Version précédente du code, même base                   | Rien, si la version n'a pas migré             |
| Après, données abîmées           | Dump d'avant l'ouverture, ou de la nuit, puis réimports | Les écritures depuis ce dump                  |
| Après, retour vers l'ancien code | Seulement s'il rouvre                                   | Les comptes créés, sans mot de passe pour lui |

L'historique de la production dit combien d'écritures seraient à refaire : 743 à 3 641 imports par an depuis 2019, soit 2 à 3 par jour en 2025 et 2026. Un dump quotidien hors de la VM, et les fichiers que les gestionnaires gardent, suffisent ; un archivage continu des journaux de PostgreSQL ne se justifie pas ici. L'historique dit aussi quelles écritures refaire.

**6.3 La base anonymisée ne suffit pas à répéter.** Elle ne joue rien de 2.5. Et une fois régénérée sans les données cachées, si l'on retient une petite série inventée, elle perd 42 % des mesures (109 millions sur 257) : ni le temps de restauration ni le disque ne seraient justes. La dernière répétition se fait donc sur le dump brut : c'est Lou qui la mène, Claude n'en voit que des comptes.

**6.4 Le dump brut envoie de vrais courriels.** Une répétition y écrit à de vraies adresses. Symfony sait [tout détourner, sauf une liste autorisée](https://symfony.com/doc/7.4/mailer.html) (`envelope.recipients`, `allowed_recipients`, depuis 7.1) ; `config/packages/mailer.yaml` n'en a pas.

**6.5 Les essais des gestionnaires ont des trous.** Sans vraie adresse, pas de lien de connexion : la base anonymisée ne peut pas leur servir telle quelle, et le plan ne dit pas laquelle ils emploient. L'ancien fermé, ils n'ont pas d'ancien à comparer. Leurs « vrais fichiers » peuvent en revanche passer par `compare.py imports`, menés par Lou, en comptes.

**6.6 Le pic sera à la réouverture.** Le site est fermé depuis octobre et les capteurs continuent : les premiers jours verront des semaines d'imports et leurs recalculs, sur une file à deux tâches de front. C'est cela que l'« essai de charge léger » doit jouer, plus que des visiteurs.

**6.7 Pas de critère d'arrêt.** Rien ne dit quand la validation est finie. La [règle de trois](https://en.wikipedia.org/wiki/Rule_of_three_(statistics)) aide pour ce qu'on tire au hasard : aucun écart sur N tirages veut dire moins de 3/N au seuil de 95 %, soit moins de 1 % pour 300 exports. Elle ne vaut que pour un tirage au hasard, pas pour « le premier de chaque sorte ».

**6.8 Rien pour l'après.** Un invariant simple, sans lecture : après l'ouverture, toute chronique dont l'empreinte change doit avoir une entrée d'historique. S'y ajoutent `inspect.sql`, et les tâches sorties sur 16 ou 32 (3.3). Et les adresses que citent les DOI, à essayer une à une à l'ouverture au public (`CLAUDE.md`).

### 7. La barrière et l'ordre des étapes

`ROADMAP.md` place toutes les preuves avant de remplacer les chroniques cachées. Regardé preuve par preuve :

| Preuve                   | Vraies données cachées ?                                            | Quand                  |
|--------------------------|---------------------------------------------------------------------|------------------------|
| Recalcul contre la base  | Oui : 356 des 430 chroniques calculées visibles ont une mère cachée | Avant de régénérer     |
| Volume, temps, disque    | Oui, si la série inventée est petite (6.3)                          | Avant de régénérer     |
| Empreinte au bit près    | Oui pour comparer au brut ; le reste est fait (3.7)                 | Faite, puis sur la VM  |
| Ancien contre nouveau    | Non : il suffit que la donnée soit la même des deux côtés           | Après, plus simple     |
| Lecture des écarts       | Non : gênée par la barrière                                         | Après, ou par registre |
| Couverture, cas nouveaux | Non : gênés par la barrière                                         | Après                  |

L'ordre de `ROADMAP.md` ne tient donc que pour les deux premières. Le reste gagne à ce que la barrière soit tombée, d'autant qu'elle ne repose que sur une consigne (`dev/local/hidden-series.md`).

### 8. Les relectures ne sont pas indépendantes

Chaque module a été relu par un second Claude, chaque constat traité. Les régressions trouvées le 3 novembre (choix de la langue, erreur JavaScript, points de contrôle) ont passé ces relectures et la comparaison. Même modèle, mêmes angles morts : c'est le constat de [Knight et Leveson](https://libraopen.lib.virginia.edu/public_view/jd472w463) sur les versions indépendantes, à plus forte raison ici. Une relecture de plus de l'exécuteur vaut d'être faite, ciblée. Elle vaudra plus accompagnée de pannes provoquées : tuer l'exécuteur et l'enfant pendant un calcul, redémarrer PostgreSQL, remplir `/tmp`, puis `inspect.sql` et l'empreinte.

## Ce qui tient bien

- **Le fond de la méthode.** Un portage comparé module par module à l'ancien, sur des copies, c'est le [test différentiel](https://en.wikipedia.org/wiki/Differential_testing) (McKeeman, 1998) et l'esprit de la [marche en parallèle](https://www.martinfowler.com/articles/patterns-legacy-displacement/divert-the-flow.html), hors ligne.
- **Une chose change à la fois.** L'instance `reference-17` a permis ici, en une commande, de voir que 3 valeurs venaient de PostgreSQL et pas du portage (2.2).
- **Les valeurs restent intactes :** 3.7, au bit près.
- **Le calcul sous PostgreSQL 17 retrouve l'histoire :** 46 chroniques sur 50 à l'identique (3.2).
- **Le calcul ne dépend pas de la plateforme,** et les nombres passent par du texte (`dev/decisions.md`, « Nombres lus en base »).
- **L'état de la base se compare sans tolérance** entre ancien et nouveau sous PostgreSQL 17, à la lacune de 1.2 près : la règle d'arrondi n'y joue pas sur les empreintes, mais joue sur les lignes de `chronique`.
- **L'exécuteur a son essai propre** (`compare.py queue`), qui cherche les recouvrements dans une même file.
- **Les migrations** sont lues et essayées dans les deux sens sur la base complète (`dev/differences.md`).
- **Les vérifications « nouveau seul »** comparent à une valeur attendue, sans passer par l'ancien : c'est déjà un oracle indépendant.
- **Les pas jamais joués** ne montrent pas de régression à l'essai (2.2).
- **L'anonymisation** classe chaque colonne de texte et s'arrête sur une colonne inconnue (`dev/anonymize/anonymize.sql`).
- **Ne pas viser un score de mutation.** [Infection](https://infection.github.io/guide/) demande un cadre de tests (PHPUnit, PhpSpec, Codeception, Testo), que le projet n'a pas ; [73 % des vraies fautes se couplent à des mutants](https://people.cs.umass.edu/%7Erjust/publ/mutants_real_faults_fse_2014.pdf), mais [le lien entre score et fautes trouvées est faible, une fois la taille des suites contrôlée](https://typeset.io/pdf/are-mutation-scores-correlated-with-real-fault-detection-a-t9e7f0t5d6.pdf). Une liste choisie de fautes réelles convient mieux à un dépôt voué à l'archive.

## Ce qui se fait ailleurs, et où en est BDOH

- **Mesurer le bruit par deux instances de l'ancien.** [Diffy](https://blog.x.com/engineering/en_us/a/2015/diffy-testing-services-without-writing-tests) (Twitter) soustrait les écarts entre deux instances de l'ancien de ceux du candidat. BDOH : à faire, peu coûteux (1.6).
- **Déclarer les écarts connus, et les retirer à la fin.** [Scientist](https://github.com/github/scientist) (GitHub) a ses blocs `ignore`, et rappelle qu'avec eux le candidat est garanti différent. BDOH : les écarts sont écrits pour des lecteurs, pas pour l'outil (1.5).
- **Réconcilier par comptes, agrégats et empreintes de lignes.** C'est ce que fait le [Data Validation Tool](https://pypi.org/project/google-pso-data-validator/4.5.0/). BDOH : dix lignes de SQL y suffisent (3.7), pas besoin d'outil.
- **Injecter des fautes pour éprouver les essais.** Les études ci-dessus. BDOH : rien (1), à faire à partir de `dev/decisions.md`, « Pièges de la montée de version », et des corrections du 3 novembre.
- **Tester selon l'usage réel** ([profil opérationnel, Musa, 1993](https://www.scilit.net/publications/715ca10803e745574e3f0c6d1814f5c5)). BDOH : `historique` donne ce profil, jamais employé (2.2).
- **Répéter la bascule et le retour arrière.** BDOH : prévu, pas écrit (6.1).

Là où le projet s'écarte de tout cela, il a souvent raison : pas de marche en parallèle en ligne (le site est fermé), pas d'écriture double (rien à rattraper), pas de cadre de mutation, pas de sauvegarde continue (2 à 3 imports par jour). Il fait fausse route sur un seul point : avoir tout misé sur un outil sans jamais l'éprouver.

## Le plan de départ, point par point

- **OTHU restaurée et comparée :** à garder, en adaptant les modes (2.7).
- **Tous les modes sur la base complète :** à garder, mais à la fin, une seule fois (2.1).
- **Recalcul complet, taux de remplissage :** à garder, mieux conçu ; le premier essai est encourageant (constat 3).
- **Couverture par PCOV :** à compléter par la couverture du SQL (constat 4).
- **Invariants :** à corriger pour l'export et le réimport, à compléter (3.8, 3.9).
- **Relecture croisée :** à garder, ciblée, avec des pannes provoquées (constat 8).
- **Temps et mémoire :** à garder ; une part est mesurée ici (3.6), et il manque le pic de la réouverture (6.6).
- **Répétition sur la VM :** deux répétitions, dont une sur le dump brut avec le retour arrière (6.3).
- **Essais des gestionnaires :** à préciser, quelle base, quel critère, leurs fichiers rejoués (6.5).

Ce qui manque : éprouver l'outil (constat 1), un registre des écarts, le tri par l'usage réel (2.2), l'empreinte au bit près (3.7), l'écart entre le poste et la cible (constat 5), les critères d'arrêt (6.7), l'après (6.8).

## Le plan consolidé

Ordre recommandé. Durées machine mesurées ou déduites de 3.6 ; travail en estimation de Claude, à prendre comme des ordres de grandeur.

| Étape                                         | Travail     | Machine       | Qui           |
|-----------------------------------------------|-------------|---------------|---------------|
| 1. Durcir le comparateur                      | 1 jour      | minutes       | Claude        |
| 2. Éprouver le comparateur                    | 1 à 2 jours | quelques h    | Claude        |
| 3. Corriger le calcul qui vide                | 1/2 jour    | minutes       | Claude        |
| 4. Couverture PHP et SQL                      | 1 jour      | 1 h           | Claude        |
| 5. Recalcul et volumes, sur les dumps actuels | 1 jour      | 3 à 5 h       | Claude        |
| 6. Régénérer les dumps                        | 1/2 jour    | quelques h    | Claude, Lou   |
| 7. Élargir les cas                            | 1 à 2 jours | selon les cas | Claude        |
| 8. Invariants et jeu inventé                  | 1 à 2 jours | minutes       | Claude        |
| 9. Pannes provoquées, relecture ciblée        | 1 jour      | 1 h           | Claude        |
| 10. Rapprocher le poste de la cible           | 1/2 jour    | 1 h           | Claude        |
| 11. Gel du code, passe finale, OTHU           | 1/2 jour    | 1/2 à 1 jour  | Claude        |
| 12. VM, première répétition                   | 2 à 3 jours | 1 jour        | Lou, Claude   |
| 13. Essais des gestionnaires                  | leur temps  |               | gestionnaires |
| 14. Répétition avec retour arrière, brut      | 1 jour      | 1 jour        | Lou           |
| 15. Bascule, surveillance                     | 1 semaine   |               | Lou, Claude   |

Détail :

1. **Durcir.** Comparer un nombre selon ce qu'il est : les dates, entiers, statuts et codes à l'identique, seules les valeurs décimales au dernier chiffre. Empreintes sans `concat_ws`, sur le binaire. Toutes les tables, sauf une liste d'exclusions écrite, `plage` comprise. Le rapport note commit, base réellement lue de chaque côté, versions. Registre des écarts admis (`dev/compare/expected.*`, sans données), ligne à ligne, chaque entrée renvoyant à `dev/differences.md` ; code de sortie non nul s'il reste un écart inattendu. Les écarts inattendus ne citent que ce qui peut se lire.
2. **Éprouver.** Tests de `same_but_rounding`, `compare`, `page_lines`, en bibliothèque standard, dans les contrôles de GitHub. Une quinzaine d'altérations SQL sur une copie (un bit de valeur, une ligne en moins, une heure de décalage, minimum et maximum échangés avec NULL, une qualité, une plage, une table imprévue) : chacune doit se voir. Passe de l'ancien contre l'ancien. Les pièges de `dev/decisions.md` et les corrections du 3 novembre remis un à un : chaque faute non vue appelle un cas.
3. **Le calcul qui vide** (constat 3.3). Une erreur évidente, peu coûteuse : si le code n'est pas 0, lever une exception, pour annuler la transaction. Cas dans `compare.py state`, entrée dans `dev/differences.md`.
4. **Couverture.** PCOV et `plpgsql_check`, le temps d'une passe de tous les modes. Lire ce qui n'est pas exécuté, ce qui écrit d'abord.
5. **Recalcul et volumes, tant que les vraies données sont là.** Sur une copie, chroniques les plus profondes d'abord, sans propagation, par la vraie commande. Compter les codes, vérifier que les identifiants ont changé, comparer par l'empreinte, trier (3.5). Conversions de même, et taux de remplissage. Sortie : comptes, chroniques cachées sous un pseudonyme. Sur la même passe : temps et mémoire. OTHU, restaurée sous PostgreSQL 17 : empreinte et taux (2.7).
6. **Régénérer** (décisions 1 et 2), puis détruire ce qui vient des anciens dumps (`dev/local/hidden-series.md`).
7. **Élargir les cas,** d'après 4 et d'après `historique` : « 1 événement », 5 et 15 minutes, fuseaux employés, plages, sortes de calcul de 2.3. Des chroniques visibles tirées au hasard, graine notée, pour pouvoir appliquer la règle de trois : 300 exports, par exemple. Les fichiers réels et les journaux, si Lou les a (2.6).
8. **Invariants et jeu inventé.** Relations de 3.8 et 3.9, sur un petit jeu public, résultats écrits à la main : la seule preuve qui ne doit rien à l'ancien, et qui survivra à sa disparition.
9. **Pannes provoquées,** relecture de ce qui écrit (constat 8).
10. **Rapprocher le poste.** Une base sur les paquets de Debian 13, comme l'image de l'application. Un rôle sans superuser, et toutes les comparaisons avec lui.
11. **Gel et passe finale.** Tous les modes, base complète et OTHU, sur un tag : c'est là que le plan de départ met « repasser », et une seule fois.
12. **Première répétition sur la VM,** avec les dumps régénérés : le chemin d'installation, écrit au fur et à mesure et chronométré. Courriel réel vers Lou, charge de 6.6, empreinte comparée à celle du poste.
13. **Essais des gestionnaires,** selon la décision 3.
14. **Dernière répétition sur le dump brut,** avec le retour arrière joué : écrire, restaurer, comparer l'empreinte, chronométrer. Le relevé des tâches à code 16 ou 32. Le rechiffrement de Theia.
15. **Bascule et première semaine** (6.8).

## Décisions à prendre

Elles reviennent à Lou ; aucune ne bloque les étapes 1 à 5.

1. **Quand régénérer les dumps ?** On peut garder les dumps actuels jusqu'au bout : rien ne se perd, mais tous les écarts se lisent en aveugle, par registre et pseudonymes, et les conversions restent illisibles. On peut régénérer tout de suite : la barrière tombe, mais le recalcul contre la base perd au moins 356 des 450 chroniques. **Recommandé : entre les deux,** recalcul et volumes d'abord (étape 5), puis régénérer. Il faut pour cela les dumps bruts. Le choix ne dépend pas de la décision sur l'ouverture des données : c'est un choix de données d'essai.
2. **Par quoi remplacer les chroniques cachées ?** La proposition actuelle (`dev/local/hidden-series.md`) : une petite série inventée. Simple et sûre, mais elle perd 42 % du volume et les formes qui éprouvent les conversions (2.4). Autre option : garder dates et qualités, remplacer les valeurs par une transformation à sens unique, retirer codes, libellés et positions comme prévu. Le volume et les formes restent. **Recommandé : la seconde,** si les dates de mesure seules ne sont pas sensibles, une fois codes et stations retirés. C'est le jugement de Lou et de sa cheffe.
3. **Qu'est-ce qui doit précéder les essais des gestionnaires ?** `ROADMAP.md` met toutes les preuves avant, alors que la priorité est de les faire tester vite. Leurs écritures sur une copie ne risquent rien : le vrai risque est de gâcher leur temps. **Recommandé : scinder.** Avant les essais : étapes 1 à 5, et l'installation. Avant la bascule : tout le reste, en parallèle des essais. L'étape 11 se refait après leurs retours.
4. **Sur quelle base essaient-ils ?** Le dump brut sur la VM, sous VPN : leurs vrais comptes et leurs vraies données, courriels détournés sauf liste autorisée (6.4). Ou la base anonymisée, avec des comptes créés pour eux : aucune donnée réelle en jeu, mais pas leurs droits, et, après régénération, des chroniques cachées factices. **Recommandé : le dump brut,** repris à zéro à la bascule. Cela met de vraies données sur la VM plus tôt : à voir avec l'équipe infra et le RSSI.
5. **L'ancien peut-il rouvrir ?** Si oui, le retour vers l'ancien est à essayer et à écrire. Sinon, il est à rayer, et le tableau de 6.2 est le plan. **Recommandé : le rayer,** ce que Lou décide avec le RSSI, et déclarer une perte maximale d'un jour d'écritures. À confirmer au passage : rien n'a été écrit depuis le dump du 21 octobre.
6. **Jusqu'où vérifier la justesse ?** S'en tenir à l'identique ; ou ajouter les invariants et un jeu inventé (étape 8) ; ou récrire les fonctions de calcul à part, en Python, pour comparer. **Recommandé : les invariants et le jeu inventé.** Une récriture coûterait cher, et une erreur de l'ancien se traiterait de toute façon à part.

Trois questions de fait, pour Lou :

- Les fichiers d'origine des imports sont-ils restés sur l'ancien serveur (les 10 Go de l'application) ?
- Quelle est la collation de la base de production (`LC_COLLATE`), et les droits de son rôle ?
- Sur le dump brut, combien de tâches sont sorties sur 16 ou 32 ? (`SELECT exitcode, count(*) FROM bdoh.jms_jobs GROUP BY 1` : des comptes seulement.)

## Ce qu'on attaquerait en premier

Dans cet ordre, sans attendre aucune décision :

1. La règle d'arrondi, l'empreinte et `plage` (1.1 à 1.3), avec leurs tests : c'est ce qui rend vraies toutes les preuves qui suivent, et une passe faite avant serait à refaire.
2. Le registre et la provenance du rapport (1.4, 1.5) : ils rendent les rapports lisibles sous la barrière.
3. Les fautes injectées (étape 2) : on saura alors ce que vaut l'outil, et pas seulement ce qu'il dit.
4. Le calcul qui vide (3.3), puis le recalcul contre la base (étape 5).

Ce qu'on ne ferait pas maintenant : repasser tous les modes sur la base complète. Cette passe arrive à l'étape 11, une fois l'outil durci et les cas élargis.

## Ce que cet audit n'a pas fait

- Il n'a pas lu en détail `run_accounts`, `run_admin` ni `run_theia`, ni `dev/local/security.md`.
- Il n'a rejoué aucun mode de `compare.py`.
- Les sources sur la bascule sont des guides d'éditeurs, concordants mais sans norme derrière ; les nombres de répétitions sont des habitudes. Les études sur la mutation et la couverture portent sur des suites de tests classiques, pas sur un test différentiel : elles orientent, sans s'appliquer telles quelles.
- Le recalcul d'un pas ne porte que sur 50 des 450 chroniques calculées, pas tirées au hasard (3.2) ; les conversions n'ont pas été essayées.

## Sources

- McKeeman, [Differential Testing for Software](https://en.wikipedia.org/wiki/Differential_testing), Digital Technical Journal, 1998 (référence vérifiée, article non lu).
- Twitter, [Diffy: testing services without writing tests](https://blog.x.com/engineering/en_us/a/2015/diffy-testing-services-without-writing-tests), 2015 (page refusée à la lecture directe ; lu par des extraits de recherche).
- GitHub, [Scientist](https://github.com/github/scientist).
- Cartwright, Horn, Lewis, [Divert the Flow](https://www.martinfowler.com/articles/patterns-legacy-displacement/divert-the-flow.html), Patterns of Legacy Displacement, 2022.
- Just et al., [Are mutants a valid substitute for real faults in software testing?](https://people.cs.umass.edu/%7Erjust/publ/mutants_real_faults_fse_2014.pdf), FSE 2014.
- Papadakis et al., [Are mutation scores correlated with real fault detection?](https://typeset.io/pdf/are-mutation-scores-correlated-with-real-fault-detection-a-t9e7f0t5d6.pdf), ICSE 2018.
- Inozemtseva, Holmes, [Coverage is not strongly correlated with test suite effectiveness](https://cs.ubc.ca/~rtholmes/papers/icse_2014_inozemtseva.pdf), ICSE 2014.
- Knight, Leveson, [An experimental evaluation of the assumption of independence in multiversion programming](https://libraopen.lib.virginia.edu/public_view/jd472w463), IEEE TSE, 1986.
- Kanewala, Bieman, [Testing scientific software: a systematic literature review](https://arxiv.org/pdf/1804.01954.pdf).
- Segura et al., [A survey on metamorphic testing](https://doi.org/10.1109/TSE.2016.2532875), IEEE TSE, 2016 (référence vue, article non lu).
- Hanley, Lippman-Hand, If nothing goes wrong, is everything all right?, JAMA, 1983, par [la règle de trois](https://en.wikipedia.org/wiki/Rule_of_three_(statistics)).
- Beyer et al., [Data Integrity](https://sre.google/sre-book/data-integrity/), Site Reliability Engineering, Google.
- Guides de bascule : [IF4IT](https://if4it.org/best-practices/systems-development-lifecycle-sdlc/data-migration-and-conversion-rehearsal-within-the-sdlc/), [EDB](https://www.enterprisedb.com/sites/default/files/2026-10/Oracle_Migration_Governance_Runbook.pdf) (recherche, extraits).
- PostgreSQL : [`concat_ws`](https://www.postgresql.org/docs/17/functions-string.html), [`extra_float_digits`](https://www.postgresql.org/docs/17/runtime-config-client.html), [`pg_stat_user_functions`](https://www.postgresql.org/docs/17/monitoring-stats.html), [changements de locale](https://wiki.postgresql.org/wiki/Locale_data_changes), [fil sur `extra_float_digits` et pg_dump](https://postgrespro.com/list/thread-id/2575395).
- Musa, [Operational profiles in software-reliability engineering](https://www.scilit.net/publications/715ca10803e745574e3f0c6d1814f5c5), IEEE Software, 1993 (référence vue, article non lu).
- Outils : [plpgsql_check](https://github.com/okbob/plpgsql_check), [PCOV](https://github.com/krakjoe/pcov), [couverture hors PHPUnit](https://github.com/kekecoco/phpcoverage), [Infection](https://infection.github.io/guide/), [Data Validation Tool](https://pypi.org/project/google-pso-data-validator/4.5.0/), [Symfony Mailer](https://symfony.com/doc/7.4/mailer.html).
- Paquets de Debian 13 : [PostgreSQL](https://packages.debian.org/trixie/postgresql-17), [PostGIS](https://packages.debian.org/postgresql-17-postgis-3), [PROJ](https://packages.debian.org/trixie/libproj25), [GEOS](https://packages.debian.org/trixie/libgeos3.13.1), [glibc](https://packages.debian.org/trixie/libc6).
