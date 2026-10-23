# File de tâches et exports

État : en préparation, le 2026-10-23. Point 3 du portage dans `ROADMAP.md`.

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
