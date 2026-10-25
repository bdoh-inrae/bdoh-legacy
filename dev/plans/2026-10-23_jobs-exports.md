# File de tâches et exports

État : en cours, commencé le 2026-10-23. Point 3 du portage dans `ROADMAP.md`.

But : les recalculs de chroniques, les taux de remplissage, les exports et l'envoi vers Theia passent par une file de tâches qui garde la garantie de l'ancienne (une tâche à la fois par chronique, audit des risques, constat 3) ; les pages de suivi des tâches (`/{obs}/jobs/`) et les exports se comparent à l'identique.

## Ce que l'ancien attend de la file (JMS JobQueue 1.4)

- **Files nommées**, chacune une tâche à la fois (`max_concurrent_jobs: 1`) : une par chronique (`job_queue_chronique_<id>` : recalcul, conversion, taux de remplissage), une pour tous les exports (`job_queue_export`), une pour Theia (`json_theia`). Un seul exécuteur, au plus deux tâches en même temps (`--max-concurrent-jobs=2`), qui s'arrête au bout d'une heure et que systemd relance.
- **Priorités** : recalculs et Theia avant les taux de remplissage, eux avant les exports.
- **Chaque tâche est une commande de la console** (`bdoh:export:mean …`), lancée dans son propre processus, dont la sortie, le code de retour et les dates sont gardés dans `jms_jobs`.
- **Entités liées** (`jms_job_related_entities`) : la chronique, l'observatoire, l'utilisateur ; elles servent aux pages de suivi (tâches d'un observatoire), aux droits (`JobVoter`) et à `hasActiveJobs`.
- **Relancer** (copie de la tâche) et **annuler** (une tâche pas encore commencée), depuis les pages de suivi.

JMS JobQueue s'arrête à Symfony 4 ; il n'a pas de successeur.

## Choix : un exécuteur à nous, sur les tables de l'ancienne file

Les tables `jms_jobs` et `jms_job_related_entities` restent telles quelles ; une entité du nouveau BDOH les lit et les écrit avec la même signification. Un exécuteur (commande de la console, lancée par systemd comme l'ancien) prend les tâches en attente par priorité, sans en démarrer deux d'une même file, et lance chacune dans son propre processus (`symfony/process`). Un seul exécuteur tourne à la fois : il le vérifie au démarrage par un verrou de PostgreSQL (`pg_try_advisory_lock`), ce qui écarte toute course entre deux exécuteurs.

Pourquoi pas Symfony Messenger, prévu au départ : Messenger transporte des messages, il ne garde pas l'historique des tâches que les pages de suivi montrent. Il faudrait sa propre table, un second enregistrement par tâche pour ces pages, et un verrou par chronique ; l'exécuteur à nous garde les mêmes données, les mêmes pages et la même garantie, sans table nouvelle ni migration, en une centaine de lignes. Repère à noter dans `dev/decisions.md`.

## Étapes

1. Entité `Job` et dépôt, sur `jms_jobs` ; entités liées ; `JobManager` porté.
2. Exécuteur : choix des tâches, lancement, sortie et états, tâches restées « en cours » d'un exécuteur arrêté (marquées incomplètes au démarrage, comme l'ancien), arrêt au bout d'une heure.
3. Commandes : exports (les quatre sortes), recalcul, conversion, taux de remplissage, planification des recalculs ; écouteurs de `MEASURES_UPDATE`.
4. Pages de suivi des tâches et `JobVoter` ; `ExportController`.
5. Courriel de l'export (pièce jointe) : Symfony Mailer à la place de Swiftmailer, transport nul en développement ; le contenu du courriel comparé à celui de l'ancien (Mailpit au besoin).
6. Vérification :
   - `compare.py exports` sans écart, sur la base réduite puis la base complète ;
   - l'essai de l'audit : deux imports rapprochés sur une chronique et sur une chronique qui en dépend, comparés à leur exécution l'une après l'autre (avec le module 4, qui porte les imports ; d'ici là, deux recalculs rapprochés) ;
   - l'état de la base après un recalcul, une conversion et un calcul de taux de remplissage, comparé à l'ancien, sans tolérance d'arrondi.

## Suivi

- 2026-10-23 : plan écrit.
- 2026-10-25 : étapes 1 à 3 faites, et la vérification par la comparaison sur la base réduite.
  - File : entité `Job` sur `jms_jobs`, entités liées (`JobRelatedEntities`), `JobManager` porté, exécuteur `bdoh:jobs:run` ; `PendingJobManager` retiré. Commandes de calcul et d'export en injection par le constructeur ; écouteurs de `MEASURES_UPDATE` en attributs, priorités réglées pour garder l'ordre de l'ancien conteneur (taux de remplissage, métadonnées, chroniques filles).
  - Exports : `compare.py exports`, contre l'ancien code sur PostgreSQL 17 comme contre la référence sur PostgreSQL 11 : 135 cas identiques, 6 qui ne diffèrent que par la virgule de l'altitude en Qtvar (bogue des virgules de l'ancien, `dev/differences.md`). En route : un résultat de DBAL 4 ne se parcourt plus par `foreach` (fichiers de mesures vides) ; `QueryBuilder::select()` ne prend plus de tableau ; l'arrondi de PHP 8.4 (ci-dessous) ; la fabrique des formats, fichier vide depuis le commit précédent, réécrite.
  - Arrondi : PHP 8.4 n'arrondit plus d'abord à 15 chiffres significatifs, et 2.8674999999999997 s'écrivait 2.867 au lieu de 2.868, sur près de 1 % des valeurs d'un export instantané. Les extracteurs ramènent la valeur à 15 chiffres avant de l'écrire (`AbstractExtractor::formatNumber`) : exports identiques à l'ancien, sur PostgreSQL 11 comme 17.
  - Cas retirés de la comparaison, qui échouent des deux côtés et que l'interface ne propose pas : le format Vigilance, les exports rééchantillonnés d'une chronique discontinue. Le fuseau passe entre apostrophes, comme `JobManager` le donne à la commande.
  - État de la base : `compare.py state` mène, sur une copie de la base par côté, une chaîne de chroniques calculées recalculée avec propagation, une conversion avec et une sans fille, un calcul de taux de remplissage, puis la file jusqu'au bout (39 tâches), et compare chroniques, mesures et taux au chiffre près, historique, tâches et entités liées : identiques. En route : `transactional()` d'ORM 3 devenu `wrapInTransaction()` ; `new \DateTime(null)`, déprécié ; les journaux de prod écrits dans des fichiers comme l'ancien, et non plus sur la sortie d'erreur que la file garde avec chaque tâche ; le fuseau `Europe/Paris` de la ligne de commande, qui manquait à l'image.
  - Pages de suivi des tâches : liste et détail, pour les quatre profils, sur les copies de `state` aux dates alignées : identiques, hors des dates « maintenant » des taux de remplissage d'une chronique vide.
  - Trouvé en route, hors du module : une station ou un jeu de données inconnus rendaient une erreur 500 au lieu de 404 (`ORMException` d'ORM 3 n'est plus une classe) ; corrigé, et ces adresses entrent dans la comparaison des pages.
  - Relecture de la file par un second Claude : six défauts corrigés. Une tâche coupée par un signal passait pour finie (code 0 de Symfony 7) ; une annulation pendant le démarrage faisait tomber l'exécuteur (démarrage et annulation désormais conditionnés à l'état, dans la base) ; tâche et entités liées hors d'une même transaction ; entités liées pas regroupées par classe comme JMS ; verbosité de l'exécuteur passée aux tâches. À la bascule : arrêter l'ancien exécuteur et attendre ses tâches avant de lancer le nouveau ; service systemd en `KillMode=mixed` (`dev/differences.md`).
  - Trouvé par l'export depuis le formulaire : `setParameters` d'ORM 2 remplaçait les paramètres de la requête, et sa conversion en `setParameter` les ajoutait ; la validation du formulaire d'export échouait (`ChroniqueRepository::findAnyByStationAndCode`, où `createQueryBuilder` pose l'observatoire courant). Seul cas dans le code porté.
  - `compare.py state` joue aussi l'export demandé par le formulaire (identique, moyenne journalière, Vigilance qui échoue, Qtvar annulé), « vider une période » au nom d'un gestionnaire, la relance de la tâche en échec, et compare les courriels reçus par Mailpit (`make mail-start`), pièces jointes dépliées : tout est identique à l'ancien, et les pages de suivi de ces tâches aussi.
  - Essai de l'audit (constat 3), `compare.py queue` : trois recalculs rapprochés avec propagation, sur une chronique, sur une chronique qui en dépend et de nouveau sur la première, menés deux à la fois puis un à un, chacun sur sa copie : 106 tâches, 53 paires menées en même temps, aucune dans une même file ; même état final. À refaire avec des imports, au module 4.
  - Service de la file : `config/bdoh-job-worker.service` pour la production (comme l'ancien, plus `KillMode=mixed`) ; `worker` dans `compose.yaml`, à part (`make worker-start`), parce qu'il lit sa base à son démarrage.
  - Reste : les exports sur la base complète.
