# Le plan de validation, étape par étape

État : en cours, ouvert le 2026-11-04. Point « Preuves de solidité » de `ROADMAP.md` ; raisons et mesures dans [dev/audits/2026-11-03_validation-plan.md](../audits/2026-11-03_validation-plan.md), qui ne change plus. Ce fichier suit l'avancée, pour reprendre sans perdre le fil.

## Décisions de Lou (2026-11-04)

- Quand régénérer les dumps : confié à Claude, soit juste après le recalcul et les volumes (décision 1 de l'audit).
- Par quoi remplacer les chroniques cachées : dates et qualités gardées, valeurs remplacées, codes, libellés et positions retirés (décision 2) ; accord provisoire, à confirmer avant de lancer la régénération.
- Les dumps bruts du 21 octobre sont là, hors du dépôt : c'est Lou qui lance `make anonymize` dessus.
- Décisions 3 à 6 : les recommandations de l'audit s'appliquent tant que Lou ne dit pas autre chose.

## Ordre retenu

L'ordre de l'audit, resserré autour de la barrière : ce qui demande de lire des écarts librement (le registre des écarts, la couverture) vient après la régénération, où la barrière tombe. Le poste passe sur les paquets de Debian 13 au même moment, parce que les données des serveurs sont alors reprises de zéro.

**Phase A, sur les dumps actuels (barrière en place, rapports lus en comptes)**

- [ ] A1. Durcir le comparateur : règle d'arrondi selon la nature du nombre, empreintes sans `concat_ws` et en binaire (`dev/reference/fingerprint.sql`), `plage`, toutes les autres tables, provenance du rapport, code de sortie, tests de ses fonctions (dans les contrôles de GitHub).
- [ ] A2. L'éprouver : altérations SQL (`compare.py selftest`), passe de l'ancien contre l'ancien, fautes remises dans le code (`dev/compare/mutants.py`).
- [ ] A3. Corriger le calcul en échec qui vide la chronique, avec son cas dans `state`.
- [ ] A4. Recalcul contre la base : chroniques calculées et converties, les plus profondes d'abord, codes comptés, identifiants vérifiés, tri par l'ancien sur PostgreSQL 11 ; taux de remplissage ; temps et mémoire ; OTHU restaurée, empreinte et taux.

**Phase B, la régénération (Lou lance)**

- [x] B1. `dev/anonymize/anonymize.sql` : chroniques cachées traitées, rapport qui s'arrête s'il en reste ; essayé sur des lignes inventées.
- [ ] B2. Lou confirme la décision 2, lance `make anonymize` sur les deux dumps bruts, détruit ce qui vient des anciens dumps.
- [ ] B3. Base de développement sur les paquets de Debian 13, rôle sans superuser ; restauration.

**Phase C, barrière tombée**

- [ ] C1. Registre des écarts admis (`dev/compare/expected.*`) ; « autre ordre » limité aux listes nommées ; un écart inattendu fait échouer le mode.
- [ ] C2. Couverture PHP (PCOV) et SQL (`plpgsql_check`), lecture de ce qui n'est jamais exécuté, ce qui écrit d'abord.
- [ ] C3. Élargir les cas : « 1 événement », 5 et 15 minutes, fuseaux employés, plages, sortes de calcul rares, tirages au hasard ; adapter les modes à la forme d'OTHU.
- [ ] C4. Invariants (`E(I(E(x))) = E(x)`, relations sur les calculs) et petit jeu inventé, résultats écrits à la main.
- [ ] C5. Pannes provoquées (exécuteur tué, PostgreSQL redémarré, `/tmp` plein) et relecture ciblée de ce qui écrit.
- [ ] C6. Code gelé (tag), tous les modes sur la base complète et sur OTHU, une fois.

**Phase D, sur la VM** (selon la réponse de l'équipe infra) : `dev/plans/2026-10-26_deployment.md`.

## Ce qui reste à Lou

- Confirmer la décision 2, puis lancer `make anonymize` (B2).
- Les questions de fait de l'audit : fichiers d'imports restés sur l'ancien serveur, collation et rôle de la base de production, tâches à code 16 ou 32 sur le dump brut.

## Pour reprendre

Rien ne tourne ; les deux instances sont sur `bdoh_small`. Dans l'ordre :

1. Trois défauts du comparateur, révélés par une passe qui a franchi minuit :
   - `run_exports` : masquer le jour de la ligne `DEB;EXP-HYDRO` (`date('Ymd')`, `Qtvar.php:71`), Paris pour l'ancien en ligne de commande, UTC pour le nouveau ;
   - `run_queue` (`compare.py:1001`) : le masque ne connaît que la date locale du jour, alors que le nouveau écrit en UTC ; prendre aussi la veille, comme `state_lines` ;
   - `Report.add` : deux noms de cas qui donnent le même nom de fichier s'écrasent (27 cas, 24 fichiers d'écart) ; rendre le nom unique.
2. Ce qui manque à `compare.py` pour la suite : `--inject-sql FICHIER` (appliqué à la copie du nouveau juste après `copy_base`, au moins dans `run_state`), attendu par `mutants.py` ; les recouvrements de `queue` dans le rapport (aujourd'hui seulement imprimés).
3. Rejouer `exports` et `queue` : on attend les comptes d'hier (135/6/0/0 et 8/0/0/0). Les 3 cas d'exports sans fichier d'écart ne sont vérifiés qu'à ce moment-là.
4. Tirer au clair ce qui a bougé depuis hier, en comptes et en forme masquée seulement : `pages` (ci-dessous) et `theia` (2 cas de moins alors qu'un cas s'est ajouté : un cas perdu sans bruit serait grave).
5. A3, puis `mutants.py` (environ 1 h 30, modifie `src/`, rien d'autre en parallèle), puis A4 : `recompute.py --base bdoh` (3 à 5 h). `mutants.py` et `recompute.py` n'ont encore jamais tourné.

Pour jouer tous les modes : `dev/compare/compare.py MODE --old reference --new new --base bdoh_small`, l'un après l'autre, avec `pages`, `exports`, `queue`, `imports`, `accounts`, `admin`, `theia`. Environ 1 h 10.

Avant de détruire les rapports (B2) : noter les comptes utiles ici.

## Suivi

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
