# Le plan de validation, étape par étape

État : en cours, ouvert le 2026-11-04. Point « Preuves de solidité » de `ROADMAP.md` ; raisons et mesures dans [dev/audits/2026-11-03_validation-plan.md](../audits/2026-11-03_validation-plan.md), qui ne change plus. Ce fichier suit l'avancée, pour reprendre sans perdre le fil.

## Décisions de Lou (2026-11-04)

- Quand régénérer les dumps : confié à Claude, soit juste après le recalcul et les volumes (décision 1 de l'audit).
- Par quoi remplacer les chroniques cachées (décision 2, précisée par Lou le même jour, Claude d'accord) : seules les valeurs sont privées. Elles sont remplacées dans les dumps ; dates, qualités, métadonnées et positions restent, puisque le site les montre et que rien de cela n'est sensible. Les chroniques calculées visibles gardent leurs valeurs : elles sont publiques (`dev/decisions.md`, « Ouverture des données »).
- Les dumps bruts du 21 octobre sont là, hors du dépôt : c'est Lou qui lance `make anonymize` dessus.
- Décisions 3 à 6 : les recommandations de l'audit s'appliquent tant que Lou ne dit pas autre chose.
- L'ouverture des données passe avant les essais (`dev/plans/2026-11-04_open-data.md`). Deux instances sur une VM, pour l'instant ; site derrière le VPN et un mot de passe pour les essais.

## Ordre retenu

L'ordre de l'audit, resserré autour de la barrière : ce qui demande de lire des écarts librement (le registre des écarts, la couverture) vient après la régénération, où la barrière tombe. Le poste passe sur les paquets de Debian 13 au même moment, parce que les données des serveurs sont alors reprises de zéro.

**Phase A, sur les dumps actuels (barrière en place, rapports lus en comptes)**

- [x] A1. Durcir le comparateur : règle d'arrondi selon la nature du nombre, empreintes sans `concat_ws` et en binaire (`dev/reference/fingerprint.sql`), `plage`, toutes les autres tables, provenance du rapport, code de sortie, tests de ses fonctions (dans les contrôles de GitHub).
- [x] A2. L'éprouver : altérations SQL (`compare.py selftest`), passe de l'ancien contre l'ancien, fautes remises dans le code (`dev/compare/mutants.py`), toutes les 29 vues.
- [x] A3. Corriger le calcul en échec qui vide la chronique, avec son cas dans `state`.
- [x] A4. Recalcul contre la base : chroniques calculées et converties, les plus profondes d'abord, codes comptés, identifiants vérifiés, tri par l'ancien sur PostgreSQL 11 ; taux de remplissage ; temps et mémoire ; OTHU restaurée, empreinte et taux.

**Phase B, la régénération (Lou lance)**

- [x] B1. `dev/anonymize/anonymize.sql` : chroniques cachées traitées, rapport qui s'arrête s'il en reste ; essayé sur des lignes inventées.
- [x] B2. Dumps régénérés par Lou le 4 novembre ; ce qui venait des anciens est détruit, et la barrière levée.
- [x] B3. Base de développement sur les paquets de Debian 13, rôle sans superuser, sur les nouveaux dumps : identique au bit près à la référence.

**Phase C, barrière tombée**

- [x] C1. Registre des écarts admis (`dev/compare/expected.py`) ; « autre ordre » limité aux listes nommées ; un écart inattendu fait échouer le mode.
- [x] C2. Couverture PHP (PCOV) et SQL (`plpgsql_check`), lecture de ce qui n'est jamais exécuté, ce qui écrit d'abord. Aucun défaut trouvé, des chemins jamais joués : ils font la liste de C3, plus bas.
- [ ] C3. Élargir les cas : « 1 événement », 5 et 15 minutes, fuseaux employés, plages, sortes de calcul rares, tirages au hasard ; adapter les modes à la forme d'OTHU.
- [ ] C4. Invariants (`E(I(E(x))) = E(x)`, relations sur les calculs). Le petit jeu inventé, résultats écrits à la main, est fait pour les calculs (`dev/compare/invented.py`, joué par `state`).
- [ ] C5. Pannes provoquées (exécuteur tué, PostgreSQL redémarré, `/tmp` plein) et relecture ciblée de ce qui écrit.
- [ ] C6. Code gelé (tag), tous les modes sur la base complète et sur OTHU, une fois.

**Ouverture des données**, après C1 et C2, comparée à part : `dev/plans/2026-11-04_open-data.md`. C3 à C5 peuvent se poursuivre en parallèle des essais (décision 3) ; C6 vient après leurs retours, et porte sur le code ouvert.

**Phase D, sur la VM** (selon la réponse de l'équipe infra) : `dev/plans/2026-10-26_deployment.md`.

## Ce qui reste à Lou

- Rien pour la validation : dumps régénérés, fichiers d'import et fichiers déposés rapatriés. La VM dépend de la réponse de l'équipe infra.

## Pour reprendre

État au 6 novembre : version 0.3.0 publiée, l'état que verront les testeurs ; C1 fini, les 47 fautes vues, les 62 étapes du navigateur passées en une seule passe (Suivi). Dans l'ordre :

1. **C3**, la liste plus bas : ce qui écrit d'abord, puis les exports et les pages. Chaque groupe de cas s'écrit, se joue contre l'ancien, et voit sa faute (`mutants.py --only`) avant le suivant. Une nouvelle passe de couverture (`dev/coverage/README.md`) dira ensuite ce qui reste.
2. **La carte de l'accueil**, dans une session à part, pendant l'attente de la VM (`ROADMAP.md`, « Modernisations »).
3. **La liste de Lou** : [dev/test-kit/lou-checklist.md](../test-kit/lou-checklist.md), ce que la machine ne juge pas et que Lou essaie sur la VM, avant les gestionnaires.
4. **La VM**, selon la réponse de l'équipe infra, en suivant `dev/deploy/README.md`. Les fichiers déposés de la production (logos, images) sont dans `../bdoh_reference/uploads/bdoh-uploads-prod.tar.gz`, à reprendre dans `public/uploads/` sur la VM ; OTHU n'en a pas.

**C3, les cas à ajouter**, tirés de la passe de couverture du 6 novembre (listes dans `../bdoh_reference/coverage/`, Suivi) et de l'usage réel compté dans `historique` de la base complète. Dans l'ordre : ce qui écrit, puis ce qui se lit.

1. **Droits d'un compte** (`UtilisateurController::rightAction`, lignes 460 à 535, jamais exécutées) : donner puis retirer, sur un observatoire, le rôle de gestionnaire et celui d'utilisateur ; sur un site, celui de gestionnaire et celui de contributeur. État de `role`, historique et courriels, ancien contre nouveau. En production : 355 rôles d'utilisateur et 47 de gestionnaire sur un observatoire, 98 gestionnaires et 58 contributeurs de site. Et la lecture qui suit un droit sur une seule chronique (`ChroniqueRepository`, lignes 179 à 182) : 81 584 droits de cette sorte. **Fait le 6 novembre** (Suivi).
2. **Plages** (chroniques discontinues) : import qui écrase (`PlageRepository`, lignes 212 à 232), import qui garde sur un chevauchement du début et de la fin (lignes 172 et 178), lacunes à `-9999` et autres qualités de lacune, qui s'écrivent `NULL` (`BdohPlageImporter`, lignes 61 à 67), « vider une période » (lignes 526 à 538). **Fait le 6 novembre**, avec le chevauchement de mesures du point 3 (Suivi).
3. **Mesures** : import qui garde sur un chevauchement du début et de la fin (`Importer`, lignes 542 à 547 : les dates de l'entrée d'historique ; fait le 6 novembre), messages de recouvrement (`MeasureImportController`, lignes 579 à 588), deux lignes Qtvar au même instant (`QtvarImporter`, lignes 53 et 54 : la seconde décalée de 5 s), erreurs jamais jouées (valide sans valeur, limite interdite, qualité hors du jeu, minimum ou maximum non numérique, même chronique deux fois dans le fichier). **Fait le 6 novembre**, sauf les messages de recouvrement et « qualité hors du jeu », qu'un fichier au format BDOH ne peut atteindre : sa qualité se lit dans le jeu de l'observatoire lui-même (Suivi).
4. **Points de contrôle** : import qui recouvre l'existant en partie (`PointControleController`, lignes 323 à 341), et les erreurs de `Controle/Manager` (fuseau, station, chronique, convertie, date, valeur, qualité, doublon).
5. **Transformations et calcul** : première et seconde transformation créées sur une chronique qui n'en a pas, jeu de barèmes changé, d'où un nouveau `JeuBareme` et une entrée d'historique (`TransformationController`, lignes 562 à 588 ; `BdohLogger`, ligne 915) ; calcul à deux entrées sans délai de propagation (`bdoh_compute_chronique`, lignes 25 et 30) ; insertion refusée à deux entrées (`bdoh_insert_mesures_two_transformations`, `RETURN 16`).
6. **Administration** : fichier joint envoyé, remplacé, retiré (logo, photo, conditions : `IrsteaBdohDataBundle`, lignes 66 à 108, `DocumentedEntityListener`) ; couleurs d'un observatoire changées, thème recompilé comparé à l'ancien (`CssManager`, avec l'écart admis « Thèmes des observatoires ») ; fiche d'une chronique qui a déjà des limites (`Admin/Data/Chronique.php`, lignes 291 à 310) ; bassin modifié et retiré, cours d'eau retiré ; shapefile importé en gardant l'existant.
7. **Exports**, d'après `historique` : « 1 événement » en moyenne (998 demandes) et en cumul (235), 5 et 15 minutes en cumul et en instantané, 1 440 minutes en instantané ; les rééchantillonnés aussi en `+00:00` (13 218 demandes, le fuseau le plus employé, que les cas ne jouent qu'à l'identique) et en `+02:00` ; une chronique échantillonnée en moyenne (`bdoh_integrate_mean_interval`, jamais appelée) et une en cumul (`bdoh_interp_cumul_to_cumul`, branche « 1 événement ») ; une chronique d'une seule mesure. Puis des tirages au hasard parmi les chroniques visibles, graine notée.
8. **Pages** : le visualiseur d'une chronique en moyenne de direction `ahead` (35 en production) et d'une en cumul (`ChroniqueContinueRepository`, lignes 448 à 463 et 661 à 669).
9. **OTHU** : adapter `state`, `imports` et `queue` à une base sans transformation ni conversion (audit, 2.7).

Ne sont pas à jouer, faute de chemin qui y mène, dans l'ancien comme dans le nouveau : les branches `existing-measure` des fonctions d'export (`ChroniqueContinueRepository` passe toujours `round-time`, et `displayStartField`, calculé par `ChroniqueController`, n'est employé par aucun gabarit) ; `bdoh_interp_instant_to_instant`, `bdoh_interp_instant_to_mean_or_cumul` et `trunc_timestamp`, qu'aucun code ni aucune fonction n'appelle ; la garde de `bdoh_interp_for_bareme` pour une limite changée en lacune, que `bdoh_transformed_mesures` traite avant elle ; la route de la mise à jour automatique vers Theia, dont la case est en commentaire.

Pour C5, relevés par la même passe : la reprise des tâches laissées en cours, la durée maximale dépassée, le second exécuteur refusé et l'arrêt demandé (`RunJobsCommand`).

Pour repartir de zéro depuis les dumps, en 35 min (mesure du 4 novembre) :

```
make stop && make reference-stop
docker rm bdoh-app bdoh-db bdoh-dns bdoh-reference-app bdoh-reference-worker bdoh-reference-db bdoh-reference-dns bdoh-reference-app-17
rm -rf ../bdoh_reference/postgres ../bdoh_reference/postgres-17-debian
make db
make db DUMP=../bdoh_reference/bdoh-othu-2026-10-21-anon.dump BASE=bdoh_othu
make db-small
make db SERVER=new
make start
make migrate BASE=bdoh
make db SERVER=new DUMP=../bdoh_reference/bdoh-othu-2026-10-21-anon.dump BASE=bdoh_othu
make migrate BASE=bdoh_othu
make db-small SERVER=new
make reference-start
make reference-17-start
dev/compare/use-base.sh bdoh_small
```

Pour rejouer les fautes : `dev/compare/mutants.py` (environ 2 h 30 pour toutes, `--only` pour quelques-unes ; modifie `src/`, rien d'autre en parallèle, dépôt propre). Une faute non vue appelle un cas, un arrêt se lit. Chaque correction de la relecture y a sa faute (« ancien comportement »).

Pour jouer tous les modes, l'un après l'autre (environ 1 h 10 ; `make mail-start` d'abord : `state`, `accounts` et `admin` lisent les courriels dans Mailpit) :

```
dev/compare/compare.py pages   --old reference-17 --new new --base bdoh_small --server bdoh-db
dev/compare/compare.py exports --old reference --new new --base bdoh_small
dev/compare/compare.py MODE    --base bdoh_small        # state, queue, imports, accounts, admin
dev/compare/compare.py theia   --base bdoh              # ses envois ne vont au bout que sur la base complète
```

`pages` contre `reference` mesure en plus l'effet de PostgreSQL (`dev/decisions.md`, « Deux références pour comparer »). Après une passe sur `bdoh` : `dev/compare/use-base.sh bdoh_small`.

Les comptes utiles des rapports sont notés dans le Suivi, et le détail du recalcul dans `dev/local/data-issues.md` : les rapports peuvent partir.

## Suivi

- 2026-11-06, 3 h 40, C3, groupe 3, **les fichiers refusés et les lignes au même instant**. `imports` gagne neuf cas : deux premières lignes d'en-tête de longueurs différentes, troisième ligne fausse, qualité valide sans valeur, minimum non numérique, trop d'erreurs, même chronique deux fois, limite de quantification sur une chronique qui ne les accepte pas (une chronique du jeu `VALIDE`), et deux lignes Qtvar au même instant, sur la chronique des chevauchements. Les fichiers refusés visent la chronique 9, que protège le contrôle ligne à ligne : un fichier accepté à tort s'y verrait. `imports` 89/0/0/10/0 ; trois fautes, toutes vues (valide sans valeur acceptée, même chronique deux fois acceptée, seconde ligne Qtvar non décalée). Pas joués : « qualité hors du jeu de l'observatoire » (`noTransInObservatoireJeu`), qu'un fichier au format BDOH ne peut atteindre, son jeu source étant celui de l'observatoire ; les messages de recouvrement de l'import de mesures (`boundsEqual`, `oldInNew`), qui ne changent que le texte de l'étape 3.
- 2026-11-06, 3 h 15, C3, groupe 2, **les plages et les chevauchements d'import**. `imports` gagne sept cas : plages écrasées (celles de janvier 2024 décalées d'une heure, pour que des plages existantes soient chevauchées en partie), lacunes (`-9999` sous `gap`, et une autre qualité de lacune dont la valeur ne compte pas), lacune incohérente refusée, plages ponctuelles qui chevauchent le début et la fin de l'existant, mesures qui chevauchent le début et la fin de l'existant, sur une chronique que rien d'autre ne touche. `state` vide une période de plages, du milieu d'une plage à celui d'une autre.
  - **Un vrai défaut, trouvé par ce dernier cas** : vider une période d'une chronique discontinue répondait 500 dans le nouveau BDOH, quand l'ancien supprimait 77 plages. `PlageRepository::emptyChroniquePeriode` passait ses paramètres sous les clés `':id'`, `':begin'`, `':end'`, que DBAL 4 ne lit plus. Corrigé (cdba1f8), piège ajouté à `dev/decisions.md` ; c'était le seul de cette forme dans `src/`. Régression de la 0.1.0 : aucun mode ne vidait une chronique discontinue.
  - Passes : `imports` 81/0/0/10/0 (7 cas de plus, identiques), `state` 81/0/0/0/0 avec la correction.
  - Fautes : 7 vues sur 8, dont les 5 nouvelles moins une, et la faute du visualiseur, rendue plus fine (elle retirait la clause et laissait ses paramètres : la page tombait en erreur ; elle change désormais la liste sans erreur, et reste vue). La non vue est une faute équivalente, retirée : la valeur d'une qualité de lacune est vidée deux fois, dans `BdohPlageImporter` et dans `Importer::processGeneric`, et retirer l'une ne change rien ; le cas, relu en base, écrit bien une valeur vide sous la qualité `i`.
  - Le calcul à deux entrées sans délai de propagation (point 5) ne se joue qu'à vide : une seule chronique est dans ce cas, dans la base réduite comme dans la complète (810), et ses chroniques mères n'ont aucune mesure. Pas de cas : aucune faute n'y serait visible.
  - Vu en passant dans le journal du nouveau : `ChroniqueRepository::updateMeasuresMetadata` écrit un avertissement pour chaque chronique vidée (la réponse de `bdoh_update_measures_metadata` est vide, et sa lecture en tableau échoue). Le résultat n'est pas employé, la fonction SQL ayant déjà écrit les dates : rien ne change, l'ancien en faisait une notice. Laissé.
- 2026-11-06, 2 h 30, C3, groupe 1, **les droits d'un compte**. `accounts` donne puis retire, par la page des droits, le rôle d'utilisateur de l'observatoire, ceux de gestionnaire et de contributeur de deux sites, puis, en administrateur, celui de gestionnaire de l'observatoire ; il relève les rôles du compte après chaque envoi, sans quoi des droits donnés puis retirés ne laisseraient rien à comparer. `pages` gagne un profil, `utilisateur-chronique` : un compte qui n'a de droits que sur des chroniques, dont une cachée qui a des mesures dans la fenêtre du visualiseur, avec la page de cette chronique, ses mesures et le visualiseur de son observatoire. `accounts` 37/0/0/10/0 (8 cas de plus, identiques), `pages` 1253/0/55/212/0 (les écarts de plus sont ceux du nouveau profil, déjà admis : points de contrôle d'une chronique visible, textes de l'ouverture). Trois fautes, toutes vues : contributeur de site donné comme gestionnaire, rôle d'utilisateur de l'observatoire jamais retiré, droit sur une seule chronique oublié par le visualiseur. `mutants.py` joue désormais `pages` contre `reference-17`, comme la passe ordinaire : contre `reference`, ce mode échoue exprès sur ses pages de mesures.
- 2026-11-06, 2 h, **C2 fini**. Passe de couverture au code ec2b38a, de 0 h 05 à 1 h 38 : tous les modes, puis `make browser-check`, avec PCOV et `plpgsql_check`. Les mêmes résultats qu'à la dernière passe, couverture branchée : `pages` 1007/0/0/161/0, `exports` 15/0/0/126/0, `state` 81/0/0/0/0, `queue` 11/0/0/0/0, `imports` 74/0/0/10/0, `accounts` 29/0/0/10/0 (le cas de plus est le détournement des courriels, ajouté au commit 0881217), `admin` 649/0/0/9/0, `theia` 38/0/0/0/0 ; navigateur 62 étapes sur 62.
  - PHP : 10 767 lignes exécutées, 2 114 vues sans l'être, 41 fichiers jamais chargés : surtout des traductions anglaises et des exceptions, et trois fichiers que rien n'appelle (`MathUtil`, `ToDateTimeMsFastTransformer`, `HistoriqueDonneesSuppressionRepository`). Parmi les lignes non exécutées, beaucoup ne s'exécutent qu'à la compilation du conteneur de Symfony (`getSubscribedServices`, `build` des bundles), faite avant la passe.
  - SQL : 126 instructions jamais exécutées sur 418, dans les 27 fonctions plpgsql ; 3 jamais appelées par rien. Deux fonctions en SQL et l'agrégat `bdoh_min_quality_order` échappent à l'outil.
  - Aucun défaut du code trouvé : la liste de C3 en sort, plus haut.
  - Trois défauts de l'outil, corrigés. `coverage.py` n'était pas exécutable, alors que son mode d'emploi l'appelle ainsi. Un processus PHP qui n'a chargé aucun fichier de `src/` écrit `[]`, que `report` ne lisait pas. Surtout, le relevé SQL additionnait des fonctions de textes différents : OTHU a une autre version de 10 fonctions sur 27, et ses 151 instructions, jamais exécutées, se mêlaient à celles de la production sous d'autres numéros (277 non exécutées sur 569 annoncées, au lieu de 126 sur 418). Chaque relevé porte désormais l'empreinte du texte de sa fonction, et seules les versions de la base réduite se fusionnent ; les autres se comptent à part. Les relevés de cette passe ont reçu cette empreinte après coup : toutes les bases encore là, sauf `bdoh_othu`, ont les fonctions de `bdoh_small`, et les copies détruites venaient des mêmes gabarits.
- 2026-11-06, 0 h 03, **une passe unique de `make browser-check` : 62 étapes sur 62**, sans erreur relevée, au code f56393b, en 11 min. 250 pages parcourues sans compte et 250 en gestionnaire ; 16 administrations ouvertes et 8 fiches enregistrées en gestionnaire, 34 et 20 en administrateur technique ; aucune fiche refusée par le navigateur. Version 0.3.0 publiée sur ce code.
- 2026-11-05, 23 h 50, **toutes les fautes avec le registre : 47 vues sur 47**, une fois bouchés les deux trous qu'elles ont montrés. Passe complète au code b1aa6a4, de 16 h 50 à 23 h 20 (une veille du poste en pleine faute n'a rien faussé) : 45 vues, 2 ADMISE, aucune non vue, aucun arrêt.
  - « import : minimum et maximum échangés ». Le registre masque l'empreinte des mesures des chroniques 9 et 936, où écrivent tous les imports ; seul le contrôle ligne à ligne les protège, et il ne se jouait qu'avec les cas QJO, donc pas avec `--only`. Il se joue désormais à chaque passe d'`imports`, et rien ne doit différer sans cas QJO. Rejouées : cette faute et « points de contrôle : minimum et maximum échangés » sont vues.
  - « export : arrondi sans les 15 chiffres ». 367 paires de nombres arrondies au lieu de 6, mais un cas dont un écart est admis (la licence) l'était en entier, arrondis compris. Un nombre qui change de valeur n'est plus admis par aucune règle (`dev/decisions.md`). Les 6 paires de la passe sans faute ne changent que l'écriture : `3,34` et `3.34`, la virgule de l'ancien dans l'en-tête Qtvar de deux chroniques. Rejouée : vue, 4 différents ; `exports` sans faute reste 15/0/0/126/0. 34 tests de l'outil, dont ce cas.
  - C2 : le relevé SQL (`save_sql_coverage`) est écrit et essayé sur une copie jetable de `bdoh_small` : 418 instructions relevées dans 27 fonctions, seules celles appelées comptées, extension retirée, et `coverage.py` en fait sa liste. Le relevé compile les fonctions : il lui faut le chemin `bdoh`, que `postgres` n'a pas.
- 2026-11-05, le guide d'installation et `contacts.py` relus par un second Claude : `dev/plans/2026-10-26_deployment.md`, Suivi.
- 2026-11-05, 20 h 26, fautes en cours, code b1aa6a4 : 33 jouées sur 47, **32 vues**, 1 ADMISE (ci-dessous), aucune NON VUE, aucun arrêt. Vues : toutes celles de l'import (décalage horaire, écraser, garder, plages, points de contrôle, QJO, shapefile), les 10 de l'ouverture, les 9 « ancien comportement » et 6 sur les calculs et la conversion. Restent 14, surtout sur `state` (8 min chacune), fin estimée vers 22 h 15.
- 2026-11-05, 17 h, fautes en cours. Premier constat, à traiter à la fin de la passe : « import : minimum et maximum échangés » sort ADMISE. Elle se joue avec `--only`, qui écarte les cas QJO, et donc le contrôle ligne à ligne de la chronique 9 : le masque de `état mesure` cache alors la faute. Une passe complète de `imports` la verrait ; un `--only` non.
- 2026-11-05, après-midi, parcours dans le navigateur (`dev/browser/journeys.py`), 62 étapes, toutes passent, dont l'ouverture : export sans compte jusqu'au fichier, chronique cachée refusée, droit donné puis retiré, archive refusée après coup. 500 pages parcourues sans erreur, les 34 administrations ouvertes et 27 fiches enregistrées. **Un vrai défaut trouvé, que tous les modes laissaient passer :** toute suppression dans l'administration finissait sur « Pas trouvé » (`dev/decisions.md`, « Pièges »). `compare.py` envoyait un bouton nommé que le formulaire n'a pas : corrigé, il voit désormais le défaut sur 5 cas, et les gabarits sont corrigés, `admin` 649/0/0/9/0.
- 2026-11-05, après-midi, Symfony 7.4.23 (16 avis lus, aucun ne touche ce que BDOH emploie : `dev/local/security.md`), et C1 fini : tous les modes avec le registre, commit 3cc5c8a (`theia` sur `bdoh`, les autres sur `bdoh_small`), 46 min en tout. En identiques / à un arrondi près / autre ordre / écarts admis / différents : `pages` 1007/0/44/161/0, `exports` 15/0/0/126/0, `state` 81/0/0/0/0, `queue` 11/0/0/0/0, `imports` 74/0/0/10/0, `accounts` 28/0/0/10/0, `admin` 649/0/0/9/0, `theia` 38/0/0/0/0. Aucune règle sans écart. Le cas de plus d'`imports` est la chronique fille 936, qui ne diffère de l'ancien qu'aux dates où sa mère diffère. Les 44 pages de moins en écart sont `terms_of_use`, qui ne compare plus que son statut et son type.
- 2026-11-05, nuit, registre des écarts (C1) écrit, pas encore éprouvé sur les modes. `dev/compare/expected.py` : des lignes admises, des masques pour un seul champ d'une ligne d'état, des lignes qui doivent s'y trouver, et un autre ordre seulement pour les deux listes sans tri. Chaque règle renvoie à un titre de `dev/differences.md`, qu'un test vérifie ; 33 tests passent. Rejouées à part sur les fichiers d'écarts de la passe ci-dessous, avec un script jetable : tout est expliqué et chaque règle sert. Ce script ne voit que les lignes du diff, pas des lignes entières : c'est un indice, pas la preuve, que seul le rejeu donnera. `terms_of_use` ne compare plus que son statut et son type, comme `conditions` : le diff d'un PDF ne se lit pas.
- 2026-11-05, nuit, les 10 fautes de l'ouverture, sur `state`, commit 8a8007c : 10 vues sur 10, chacune en 8 min. Avant le registre, donc selon l'ancien verdict de `mutants.py`, mais `state` n'a aucun écart admis : le nouveau verdict donnerait le même résultat.
- 2026-11-04, nuit, tous les modes sur les nouveaux dumps, commit 1df7b1c, ouverture comprise (`theia` sur `bdoh`, les autres sur `bdoh_small`). En identiques / à un arrondi près / autre ordre / différents : `pages` 963/0/44/205, `exports` 15/0/0/126, `state` 81/0/0/0, `queue` 11/0/0/0, `imports` 73/0/0/10, `accounts` 28/0/0/10, `admin` 649/0/0/9, `theia` 38/0/0/0. Lecture de tous les écarts, ligne à ligne : aucun d'inattendu.
  - `exports` : les 126 écarts ne portent que sur le nom de la licence jointe, sur les 8 chroniques visibles et les 2 discontinues. Les 15 identiques sont la chronique convertie 2521, cachée, qui garde ses conditions. Les 6 arrondis d'avant sont toujours là, dans ces écarts.
  - `pages`, les 205 : `contact` 48 (la nouvelle adresse) ; pages de chronique 47, seulement pour l'anonyme et l'utilisateur sans droit, qui gagnent leur ligne de points de contrôle (25 chroniques, toutes visibles, vérifié en base) ; `terms_of_use` 44 (le PDF des conditions, qui dit INRAE) ; `resetting` 24 (renvoi vers la connexion) ; `login` 12, `user/edit` 9, `user/new` 3 (connexion par lien) ; `select_lang` 8 (l'ancien répond 500) ; `/` 4, `besoin/new` 2 (textes de l'ouverture) ; `messages.js` 4 (clés retirées et ajoutées). Les 44 « autre ordre » sont les deux listes sans tri, inchangées.
  - `imports`, `accounts`, `admin` : les écarts déjà admis, cas par cas. Le onzième de `imports` a disparu, comme attendu.
- 2026-11-04, soir, restauration des nouveaux dumps. Détruits : `postgres/`, `postgres-17/` (ancien serveur, que plus rien n'emploie), `postgres-17-debian/`, `compare/`, et aussi les conteneurs, leur `/tmp`, les journaux et les fichiers d'import des applications, qui pouvaient citer d'anciennes valeurs (`var/log`, `var/imports`, `app/app/logs`, `app/app/imports`, de même dans `app-17`). Empreintes des deux dumps conformes à leurs rapports, contrôles à 0. Restauration de 35 min en tout : production 11 min 49 et 11 min 23, OTHU 5 min 14 et 4 min 29 (PostgreSQL 11 puis 17). Empreintes au bit près entre les deux serveurs, tout identique : `bdoh` (`mesure` 2 019 chroniques sur 2 019, `plage` 880, `pointcontrole` 135, `tauxremplissage` 2 019), `bdoh_othu` (57 et 57), `bdoh_small` (710, 880, 135, 711). `inspect.sql` : seules diffèrent les 7 séquences en retard, que les migrations recalent. 344 Go détruits, 103 Go écrits.
- 2026-11-04, soir, défaut de l'outil trouvé par cette empreinte : la base réduite de la référence avait perdu des bits, 373 chroniques sur 710. `small-db.sh` copie les mesures par un `COPY` en texte, où PostgreSQL 11 n'écrit que 15 chiffres par défaut, là où `pg_dump` en demande 17. Corrigé (`extra_float_digits=3`). Sans effet sur ce qui a été prouvé : `state`, `queue`, `imports`, `accounts`, `admin` et `pages` comparent deux copies sur le serveur PostgreSQL 17, et `exports`, seul à lire cette base de la référence, ramène ses nombres à 15 chiffres.
- 2026-11-04, avant la coupure : `state` 81/0/0/0 au commit 7624351, cas d'ouverture compris, et analyse statique sans erreur. `imports` a tourné avant la dernière correction (72/0/0/11, voir plus bas), les autres modes avant l'ouverture : à rejouer sur les nouveaux dumps. Les fautes de l'ouverture n'ont pas encore tourné.
- 2026-11-04, les six vrais fichiers d'import donnés par Lou (`../bdoh_reference/data-test-import/`), joués par `imports`, ancien contre nouveau : mêmes réponses à chaque étape, 72/0/0/11, dont les 10 écarts admis. Cinq vont au bout. `mercure_discontinu.txt` a deux plages qui se chevauchent, et les deux BDOH le refusent : c'est juste. Le BOM, les en-têtes en minuscules, les espaces et `UTC+1` passent. Le onzième écart venait de la vérification, qui attendait aussi ce fichier-là au bout : corrigée, pas rejouée.
- 2026-11-04, fichiers déposés de la production (`../bdoh_reference/uploads/`, empreintes vérifiées) : 104 fichiers, logos et images. OTHU n'en a aucun. Les conditions d'Oracle ont le même texte que celles de BDOH : à la mise en service, vider `observatoire.conditionsutilisation`, pour qu'il prenne le texte actualisé.
- 2026-11-04, tous les modes sur la base Debian sans superuser, commit 227ba6f, sur les dumps actuels : `state` 44/0/0/0, `imports` 59/0/0/10, `queue` 11/0/0/0, `accounts` 28/0/0/10, `admin` 649/0/0/9, `pages` 1108/0/44/60, `exports` 135/6/0/0, `theia` 38/0/0/0. Aucun écart nouveau, et le rôle sans superuser n'a rien cassé. Rapport de `make anonymize` de la production : 1 tâche de l'ancien sortie sur le code 16, aucune sur le 32. Version 0.2.0 posée là, avant l'ouverture.
- 2026-11-04, B3 essayé sur les dumps actuels (à refaire sur les dumps régénérés) : serveur sur les paquets de Debian 13 (PostgreSQL 17.11, PostGIS 3.5.2, GEOS 3.13.1, PROJ 9.6.0), rôle `bdoh` sans superuser, propriétaire de ses bases et de ses objets. Production, OTHU et base réduite restaurées en 19 min, les 4 migrations appliquées par ce rôle. Empreintes au bit près contre l'ancien serveur de PostgreSQL 17 : tout identique, `mesure` 2 019 chroniques sur 2 019, `plage` 880, `pointcontrole` 135, `tauxremplissage` 2 019 ; OTHU `mesure` et `tauxremplissage` 57 sur 57.
- 2026-11-04, temps et mémoire (`volumes.py`, base complète, plus grosse chronique, 2 397 581 mesures ; une seule passe, ordres de grandeur). Le nouveau n'est plus lent nulle part, et sa mémoire reste voisine :

  ```
  Export                 ancien             nouveau
  à l'identique           59 s  328 Mo       43 s  336 Mo
  instantané, 1 heure     60 s  208 Mo       47 s  193 Mo
  moyenne, 1 heure       429 s   80 Mo      422 s   88 Mo
  cumul, 1 jour           48 s   51 Mo       37 s   58 Mo

  Calcul annulé          PostgreSQL 11      PostgreSQL 17
  calcul                 171 s              150 s
  taux de remplissage    108 s               89 s

  Page, administrateur   ancien             nouveau
  nos données            0,7 s  2,4 Mo      0,6 s  2,4 Mo
  mesures, toute période 11,2 s 1,0 Mo     10,5 s  1,0 Mo
  historique             0,9 s  2,1 Mo      0,9 s  2,1 Mo
  ```

  Import d'une archive de 0,8 Mo, 203 748 lignes, en écrasant : 7 s des deux côtés.
- 2026-11-04, corrections de la relecture (constats 1, 2, 4, 5, 6, 8, 9 ; écarts dans `dev/differences.md`) :
  - Joués sans correction, 6 des 7 cas de `imports` ont échoué comme prévu, avec par exemple 355 mesures écrites pour 99 lues sur les archives de même nom. Avec les corrections, les 7 passent.
  - `/tmp` plein passait sans correction : une ligne coupée au bord du disque fait échouer le `COPY`, sans dire la cause. Le cas exige désormais le message, que seul le nouveau code écrit.
  - Le montage d'un `/tmp` minuscule est refusé même en root avec `docker exec --privileged`. `small_tmp` recrée le conteneur avec `dev/compare/small-tmp.yaml`, ce qui perd ses sessions : on se connecte dans le bloc.
  - Un arrêt du mode perdait le rapport entier. Les vérifications de panne s'y écrivent au fil de l'eau, et `imports --only pannes` les joue seules, en 75 s.
  - Passes avec les corrections : `imports` 59/0/0/10, les 10 mêmes écarts admis qu'avant ; `state` 44/0/0/0 ; `theia` sur `bdoh` 38/0/0/0, en 15 min.
  - Chaque correction retirée, son cas la voit : 9 fautes sur 9 (`mutants.py --only "ancien comportement"`, 1 h), dont celles de `state` et de `theia` qui n'avaient pas été jouées sans correction.
- 2026-11-04, ce qui est privé (décision 2, plus haut) : `dev/anonymize/anonymize.sql` ne traite plus que les valeurs, et plus les codes, les libellés, les stations, les sites, l'historique ni les barèmes. Rejoué sur une copie jetable de la base réduite, sous PostgreSQL 11, en 54 s : contrôles à 0 ; au bit près, toutes les chroniques visibles restent identiques (467 mesures, 746 plages, 98 points) et tous les taux de remplissage aussi ; toutes les cachées changent, sauf une qui n'a que des lacunes. Cet essai a montré un défaut, corrigé : une chronique cachée à valeur unique gardait sa vraie valeur (bornes égales), ce que le contrôle du rapport ne voyait pas ; 8 cas dans la base complète, d'après les comptes.
- 2026-11-04, recalcul de la base complète, 67 min en tout (et non 3 à 5 h, l'estimation venait de l'audit), commit 98fcd2e, rapport `2026-11-04_075855_recompute` :
  - Codes : 422 calculées au code 0, 151 converties au code 0. Les 28 calculées au code 255 et la convertie au code 1 sont exactement les 28 chroniques calculées sans première transformation et la convertie sans conversion, configurations déjà connues et refusées proprement.
  - Aucune chronique ne garde de mesure calculée d'avant le recalcul : toutes ont vraiment été recalculées.
  - Mesures : 2 019 chroniques, **19 qui diffèrent, toutes « histoire »** : les fonctions sous PostgreSQL 11 rendent, au bit près, le même résultat que le nouveau sous PostgreSQL 17. Aucune « à examiner ». Ce sont donc des chroniques que l'ancien BDOH n'avait pas recalculées depuis un changement de leurs mères, et le portage n'y est pour rien. Lignes de ces 19 : 97 343 seulement après, 129 seulement avant, 3 356 valeurs, 7 qualités, 1 843 marques « calculée ». Le détail des visibles est dans `visibles.txt` du rapport ; à regarder de près si l'on veut dire aux gestionnaires ce qu'un recalcul changerait chez eux.
  - Taux de remplissage : 2 019 chroniques en 1 867 s, tous au code 0 ; 18 qui diffèrent, **aucune sans que ses mesures aient changé**.
- 2026-11-04, relecture de ce qui écrit (C5, second Claude en lecture seule, constats revérifiés dans le code) : **aucune régression du portage sur les mesures**, les défauts sont ceux de l'ancien. À corriger une fois le recalcul fini, chacun avec son cas :
  1. L'import « écraser » efface puis insère sans transaction : une panne entre les deux laisse la période vide (mesures, plages, points de contrôle).
  2. Un import de plusieurs chroniques qui échoue sur l'une garde les précédentes, sans historique ni message.
  3. Relance de l'exécuteur par systemd : corrigé (`RestartSec`, plan de mise en service).
  4. Une sortie de tâche en UTF-8 invalide (réponse d'un serveur distant) fait tomber l'exécuteur, ce qui tue l'autre tâche en cours.
  5. Deux imports zip dont le fichier intérieur porte le même nom s'écrasent entre deux étapes : l'un peut lire les lignes de l'autre.
  6. L'écriture du CSV d'import n'est pas contrôlée : `/tmp` plein peut raccourcir un import sans message. Le comportement de PHP est à mesurer.
  7. Un exécuteur tué hors de systemd libère la file alors que sa tâche tourne encore. Pas de correction : on ne le lance que par le service ; à jouer dans les pannes provoquées.
  8. Vider une période intérieure supprime au lieu de mettre en lacune si la dernière mesure a des millisecondes (aucune aujourd'hui, le format d'import les accepte).
  9. Point de contrôle : une date future passe, le test compare un texte à un objet ; une valeur écrite `0` est refusée.
- 2026-11-04, journée :
  - Fautes remises dans le code, première passe complète : 21 vues sur 29, 7 non vues, 1 arrêt. Le rapport en annonçait 22 parce qu'il comptait l'arrêt comme une faute vue. Or cet arrêt n'avait rien à voir avec la faute : l'Apache de `reference-17` plantait à presque chaque requête après une cinquantaine de vidages de cache (cache d'opcodes de PHP 7.1). `compare.py` recharge désormais Apache après chaque vidage, et `mutants.py` donne la dernière ligne d'erreur d'un arrêt sans le compter.
  - Les 7 non vues (6 dans les fonctions SQL, 1 sur les dates de la chronique) : les données ne passent pas par ces chemins. Dans la base réduite, aucune mesure limite sur une mère, aucune mesure pile sur un début de validité, aucune sous un barème lacune ; sur les 430 chroniques calculées visibles, aucune ne prend la moitié d'une limite ni ne la change en lacune, même dans la base complète. `dev/compare/invented.py` y passe : un petit jeu inventé, résultats écrits à la main, joué par `state` contre l'ancien, contre la main et sous PostgreSQL 11. `state` : 43/0/0/0, soit 21 cas de plus. Aucune différence au bit près entre PostgreSQL 11 et 17, dont les politiques de limites que rien d'autre n'emploie.
  - Rejouées avec ce jeu, les 8 fautes qui restaient sont vues, et « facteur oublié » passe de 1 à 7 cas en écart : **29 sur 29**, A2 clos. La faute sur les dates ne se voit que dans les arguments de la tâche de taux de remplissage, seul effet réel : `MeasuresMetadataListener` réécrit aussitôt les dates de la chronique. Apache de `reference-17` : aucun plantage depuis son redémarrage, en 23 rechargements.
  - Un piège, pris en écrivant ce jeu : `../bdoh/app/sql/` est plus ancien que les fonctions en base (`dev/decisions.md`). Mes premiers résultats à la main, tirés de ces fichiers, étaient faux ; les deux serveurs les contredisaient d'accord.
  - Les 8 fonctions qui diffèrent entre les deux serveurs : les seuls `::double precision` de la migration déjà connue (`dev/differences.md`, « EXTRACT sous PostgreSQL 14 et plus »).
  - OTHU (A4, en partie) : restaurée sous PostgreSQL 17 en 7 min 22. Empreintes contre PostgreSQL 11 identiques, `mesure` 57 chroniques sur 57 (106 006 788 lignes), `tauxremplissage` 57 sur 57. Les 4 migrations appliquées ne changent pas l'empreinte. `recompute.py --base bdoh_othu` : taux de 57 chroniques en 668 s, tous au code 0, aucun qui diffère. Aucune chronique à recalculer : elles sont toutes continues.
  - Anonymisation relue avant que Lou la lance : les tables de travail du schéma `temp`, certaines lignes de l'historique et les sites sans station visible échappaient au traitement. Corrigé, avec un contrôle au rapport pour chacun (détail dans `dev/local/hidden-series.md`). Essayé cette fois sur une copie jetable de la base réduite, sous PostgreSQL 11 : 55 s, tous les contrôles à 0. C'est l'essai du SQL, pas de toute la chaîne de `make anonymize`.
- 2026-11-04, matin : les trois défauts de la passe de minuit corrigés (jour de la ligne `DEB`, masque de `queue`, noms de fichiers d'écarts qui s'écrasaient). `exports` retrouve 135/6/0/0 ; `queue` 11/0/0/0, dont trois parties nouvelles au rapport : aucun recouvrement dans une même file, et 53 et 64 paires de tâches menées de front, sans quoi l'essai ne prouverait rien. `pages` et `theia` expliqués, sans régression :
  - `pages` : la passe de cette nuit opposait `reference`, les précédentes `reference-17`, ce que leurs rapports ne disaient pas encore. Rejouée contre `reference-17` : 1108/0/44/60, les mêmes cas dans les mêmes catégories. L'écart vient donc de PostgreSQL 11 (10 pages de mesures à 15 chiffres contre 17, 28 pages de plus dans un autre ordre, toutes de jeux de données), plus 6 listes de comptes : un compte d'essai de Lou dans `bdoh_small` du serveur PostgreSQL 17, seule différence de contenu entre les deux copies (comptes des tables, à la table de suivi des migrations près). Laissé : la régénération le retirera.
  - `theia` : les 3 vérifications d'envoi ne se jouent que sur la base complète, où se trouve une fiche qui passe le contrôle du schéma. 34/0/0/0 sur `bdoh_small`, 37/0/0/0 sur `bdoh` (rejoué) : aucun cas perdu.
  - Trouvé en chemin : l'empreinte de « toutes les autres tables » assemblait un texte pour toutes les mesures, et dépassait la mémoire de PostgreSQL sur la base complète (`theia --base bdoh`). C'est une somme désormais, et les tables de mesures passent par leur empreinte binaire. La passe finale (C6) aurait échoué là.
  - `mutants.py` distingue un mode qui s'arrête, vérifie que ses textes existent, et lit les fichiers d'écarts (sans les montrer) : un cas de plus en écart, ou un écart qui change. `recompute.py` écrit son rapport au fil de l'eau et compte aussi les bornes.
  - A3 fait : le calcul en échec lève une exception, et la console sort sur le même code. `state` : 22/0/0/0, dont l'insertion refusée (16), l'effacement refusé (32), et le calcul refait, qui rend les mêmes mesures.
- 2026-11-04, nuit : tous les modes avec le comparateur durci, sur `bdoh_small` (commit 1a1920c). En identiques / à un arrondi près / autre ordre / différents :

  ```
  Mode       Avant le durcissement      Cette nuit
  pages      1108 /  0 / 44 / 60        1064 / 10 / 72 / 66
  exports     135 /  6 /  0 /  0 (*)     114 /  0 /  0 / 27
  queue         8 /  0 /  0 /  0           7 /  0 /  0 /  1
  imports      49 /  1 /  0 / 10          52 /  0 /  0 / 10
  accounts     27 /  0 /  0 / 10          28 /  0 /  0 / 10
  admin       648 /  0 /  0 /  9         649 /  0 /  0 /  9
  theia        36 /  0 /  0 /  0          34 /  0 /  0 /  0
  (*) sur la base complète
  ```

  Écarts relevés en forme masquée, sans lire de nom de cas ni de valeur :
  - `exports` : sur les 24 fichiers d'écart, une seule ligne diffère, le jour de la ligne `DEB`, 20261104 d'un côté, 20261103 de l'autre, dans les 24. C'est l'horloge. Les 3 autres cas restent à voir (point 3 ci-dessus).
  - `queue` : 48 lignes de `bdoh:compute:filling_rates`, même chronique et même commande, dates du moment décalées de 140 à 174 s. Le masque du comparateur, pas le calcul.
  - `pages`, par rapport au rapport précédent : 36 cas passent d'identiques à « autre ordre » (8 en sens inverse), 10 d'identiques à « à un arrondi près » (20 paires de nombres ou plus par page), 6 d'identiques à différents (4 lignes ajoutées côté nouveau, avec un courriel : ressemble au menu d'un compte connecté). **Pas encore expliqué.** L'ancien contre lui-même ne bouge pas : ce n'est pas du bruit de l'outil.
  - `imports`, `accounts`, `admin` : les mêmes nombres de différents qu'avant, à inscrire au registre (C1).
- 2026-11-04 : plan ouvert, décisions notées. Comparateur durci (commit 1a1920c) : `compare.py selftest` voit ses 30 erreurs, après avoir révélé que l'état des bases se comparait à tort avec une tolérance d'arrondi. L'ancien contre lui-même : aucun bruit (`pages` 1 212 cas, `exports` 141, `state` 18). Traitement des chroniques cachées écrit (`dev/anonymize/`), essayé sur des lignes inventées seulement : l'outil d'autorisation de Claude refuse de lancer le script sur un dump, même déjà anonymisé ; la première passe réelle sera celle de Lou. Image de base sur les paquets de Debian 13 prête (`docker/db/`), à brancher en B3.
