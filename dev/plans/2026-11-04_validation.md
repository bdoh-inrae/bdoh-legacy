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

- [ ] B1. `dev/anonymize/anonymize.sql` : chroniques cachées traitées, rapport qui s'arrête s'il en reste.
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

## Suivi

- 2026-11-04 : plan ouvert, décisions notées.
