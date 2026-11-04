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

- [x] A1. Durcir le comparateur : règle d'arrondi selon la nature du nombre, empreintes sans `concat_ws` et en binaire (`dev/reference/fingerprint.sql`), `plage`, toutes les autres tables, provenance du rapport, code de sortie, tests de ses fonctions (dans les contrôles de GitHub).
- [ ] A2. L'éprouver : altérations SQL (`compare.py selftest`, fait), passe de l'ancien contre l'ancien (faite), fautes remises dans le code (`dev/compare/mutants.py`, reste à jouer).
- [x] A3. Corriger le calcul en échec qui vide la chronique, avec son cas dans `state`.
- [ ] A4. Recalcul contre la base : chroniques calculées et converties, les plus profondes d'abord, codes comptés, identifiants vérifiés, tri par l'ancien sur PostgreSQL 11 ; taux de remplissage ; temps et mémoire ; OTHU restaurée, empreinte et taux.

**Phase B, la régénération (Lou lance)**

- [x] B1. `dev/anonymize/anonymize.sql` : chroniques cachées traitées, rapport qui s'arrête s'il en reste ; essayé sur des lignes inventées.
- [ ] B2. Lou confirme la décision 2, lance `make anonymize` sur les deux dumps bruts, détruit ce qui vient des anciens dumps.
- [ ] B3. Base de développement sur les paquets de Debian 13, rôle sans superuser ; restauration.

**Phase C, barrière tombée**

- [ ] C1. Registre des écarts admis (`dev/compare/expected.*`) ; « autre ordre » limité aux listes nommées ; un écart inattendu fait échouer le mode.
- [ ] C2. Couverture PHP (PCOV) et SQL (`plpgsql_check`), lecture de ce qui n'est jamais exécuté, ce qui écrit d'abord.
- [ ] C3. Élargir les cas : « 1 événement », 5 et 15 minutes, fuseaux employés, plages, sortes de calcul rares, tirages au hasard ; adapter les modes à la forme d'OTHU.
- [ ] C4. Invariants (`E(I(E(x))) = E(x)`, relations sur les calculs). Le petit jeu inventé, résultats écrits à la main, est fait pour les calculs (`dev/compare/invented.py`, joué par `state`).
- [ ] C5. Pannes provoquées (exécuteur tué, PostgreSQL redémarré, `/tmp` plein) et relecture ciblée de ce qui écrit.
- [ ] C6. Code gelé (tag), tous les modes sur la base complète et sur OTHU, une fois.

**Phase D, sur la VM** (selon la réponse de l'équipe infra) : `dev/plans/2026-10-26_deployment.md`.

## Ce qui reste à Lou

- Confirmer la décision 2, puis lancer `make anonymize` (B2).
- Les questions de fait de l'audit : fichiers d'imports restés sur l'ancien serveur, collation et rôle de la base de production, tâches à code 16 ou 32 sur le dump brut.

## Pour reprendre

Dans l'ordre :

1. `dev/compare/mutants.py` (environ 2 h pour toutes, `--only` pour quelques-unes ; modifie `src/`, rien d'autre en parallèle, dépôt propre) : une faute non vue appelle un cas, un arrêt se lit.
2. A4 : `dev/compare/recompute.py --base bdoh` (3 à 5 h), puis temps et mémoire (`dev/compare/volumes.py`, pas encore lancé).

Pour jouer tous les modes, l'un après l'autre (environ 1 h 10) :

```
dev/compare/compare.py pages   --old reference-17 --new new --base bdoh_small --server bdoh-db
dev/compare/compare.py exports --old reference --new new --base bdoh_small
dev/compare/compare.py MODE    --base bdoh_small        # state, queue, imports, accounts, admin
dev/compare/compare.py theia   --base bdoh              # ses envois ne vont au bout que sur la base complète
```

`pages` contre `reference` mesure en plus l'effet de PostgreSQL (`dev/decisions.md`, « Deux références pour comparer »). Après une passe sur `bdoh` : `dev/compare/use-base.sh bdoh_small`.

Avant de détruire les rapports (B2) : noter les comptes utiles ici.

## Suivi

- 2026-11-04, journée :
  - Fautes remises dans le code, première passe complète : 21 vues sur 29, 7 non vues, 1 arrêt. Le rapport en annonçait 22 parce qu'il comptait l'arrêt comme une faute vue. Or cet arrêt n'avait rien à voir avec la faute : l'Apache de `reference-17` plantait à presque chaque requête après une cinquantaine de vidages de cache (cache d'opcodes de PHP 7.1). `compare.py` recharge désormais Apache après chaque vidage, et `mutants.py` donne la dernière ligne d'erreur d'un arrêt sans le compter.
  - Les 7 non vues (6 dans les fonctions SQL, 1 sur les dates de la chronique) : les données ne passent pas par ces chemins. Dans la base réduite, aucune mesure limite sur une mère, aucune mesure pile sur un début de validité, aucune sous un barème lacune ; sur les 430 chroniques calculées visibles, aucune ne prend la moitié d'une limite ni ne la change en lacune, même dans la base complète. `dev/compare/invented.py` y passe : un petit jeu inventé, résultats écrits à la main, joué par `state` contre l'ancien, contre la main et sous PostgreSQL 11. `state` : 43/0/0/0, soit 21 cas de plus. Aucune différence au bit près entre PostgreSQL 11 et 17, dont les politiques de limites que rien d'autre n'emploie.
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
