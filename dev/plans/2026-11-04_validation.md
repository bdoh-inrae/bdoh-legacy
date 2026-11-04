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
- [ ] B2. Dumps régénérés par Lou le 4 novembre ; reste à détruire ce qui vient des anciens (accord de Lou, « Pour reprendre »).
- [ ] B3. Base de développement sur les paquets de Debian 13, rôle sans superuser : en place et éprouvée sur les anciens dumps ; reste la restauration des nouveaux.

**Phase C, barrière tombée**

- [ ] C1. Registre des écarts admis (`dev/compare/expected.*`) ; « autre ordre » limité aux listes nommées ; un écart inattendu fait échouer le mode.
- [ ] C2. Couverture PHP (PCOV) et SQL (`plpgsql_check`), lecture de ce qui n'est jamais exécuté, ce qui écrit d'abord.
- [ ] C3. Élargir les cas : « 1 événement », 5 et 15 minutes, fuseaux employés, plages, sortes de calcul rares, tirages au hasard ; adapter les modes à la forme d'OTHU.
- [ ] C4. Invariants (`E(I(E(x))) = E(x)`, relations sur les calculs). Le petit jeu inventé, résultats écrits à la main, est fait pour les calculs (`dev/compare/invented.py`, joué par `state`).
- [ ] C5. Pannes provoquées (exécuteur tué, PostgreSQL redémarré, `/tmp` plein) et relecture ciblée de ce qui écrit.
- [ ] C6. Code gelé (tag), tous les modes sur la base complète et sur OTHU, une fois.

**Ouverture des données**, après C1 et C2, comparée à part : `dev/plans/2026-11-04_open-data.md`. C3 à C5 peuvent se poursuivre en parallèle des essais (décision 3) ; C6 vient après leurs retours, et porte sur le code ouvert.

**Phase D, sur la VM** (selon la réponse de l'équipe infra) : `dev/plans/2026-10-26_deployment.md`.

## Ce qui reste à Lou

- Rien pour la validation : dumps régénérés, fichiers d'import et fichiers déposés rapatriés. La VM dépend de la réponse de l'équipe infra.

## Pour reprendre

État à la coupure du 4 novembre, fin d'après-midi. Les phases A et B1 sont finies, et les dumps sont régénérés par Lou (`bdoh_reference/*-anon.dump`, écrits à 15 h 10 et 15 h 35). L'ouverture des données est écrite et fusionnée dans `main` (`dev/plans/2026-11-04_open-data.md`). Le serveur du nouveau BDOH tourne déjà sur les paquets de Debian 13 avec un rôle sans superuser, mais encore sur les bases des anciens dumps. Rien ne tourne. Dans l'ordre :

1. **Détruire ce qui vient des anciens dumps, puis restaurer les nouveaux.** Lou a donné son accord le 4 novembre. Arrêter les trois serveurs (`make stop`, `make reference-stop`), puis retirer dans `../bdoh_reference/` : `postgres/`, `postgres-17/`, `postgres-17-debian/`, `compare/`. Les dumps bruts de `../incident-2026-10-21/` ne bougent pas. Lire d'abord le rapport de chaque dump (`*-anon.txt`) : contrôles à 0. Restaurer ensuite, dans cet ordre :
   ```
   make db
   make db DUMP=../bdoh_reference/bdoh-othu-2026-10-21-anon.dump BASE=bdoh_othu
   make db-small
   make db SERVER=new
   make migrate BASE=bdoh
   make db SERVER=new DUMP=../bdoh_reference/bdoh-othu-2026-10-21-anon.dump BASE=bdoh_othu
   make migrate BASE=bdoh_othu
   make db-small SERVER=new
   dev/compare/use-base.sh bdoh_small
   ```
   La barrière sur les valeurs cachées tombe alors : retirer sa ligne de `CLAUDE.md`, et la section qui en parle dans `dev/local/hidden-series.md`.
2. **Rejouer tous les modes sur ces dumps** (commandes plus bas), ouverture comprise, et lire leurs écarts, maintenant permis. Nouveau : `state` joue l'ouverture, `imports` les six vrais fichiers de `../bdoh_reference/data-test-import/`.
3. **Les fautes de l'ouverture** : `mutants.py --only "ouverture :"`, puis toutes une fois, leurs textes ayant changé.
4. **C1, C2**, puis essayer l'ouverture dans un navigateur (`make browser-check`, à étendre : export sans compte, fenêtre d'une chronique cachée).
5. **La VM**, selon la réponse de l'équipe infra. Les fichiers déposés de la production (logos, images) sont dans `../bdoh_reference/uploads/bdoh-uploads-prod.tar.gz`, à reprendre dans `public/uploads/` sur la VM ; OTHU n'en a pas.

Pour rejouer les fautes : `dev/compare/mutants.py` (environ 2 h 30 pour toutes, `--only` pour quelques-unes ; modifie `src/`, rien d'autre en parallèle, dépôt propre). Une faute non vue appelle un cas, un arrêt se lit. Chaque correction de la relecture y a sa faute (« ancien comportement »).

Pour jouer tous les modes, l'un après l'autre (environ 1 h 10) :

```
dev/compare/compare.py pages   --old reference-17 --new new --base bdoh_small --server bdoh-db
dev/compare/compare.py exports --old reference --new new --base bdoh_small
dev/compare/compare.py MODE    --base bdoh_small        # state, queue, imports, accounts, admin
dev/compare/compare.py theia   --base bdoh              # ses envois ne vont au bout que sur la base complète
```

`pages` contre `reference` mesure en plus l'effet de PostgreSQL (`dev/decisions.md`, « Deux références pour comparer »). Après une passe sur `bdoh` : `dev/compare/use-base.sh bdoh_small`.

Les comptes utiles des rapports sont notés dans le Suivi, et le détail du recalcul dans `dev/local/data-issues.md` : les rapports peuvent partir.

## Suivi

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
