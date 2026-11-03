# Peut-on faire confiance au portage ? État des lieux du 3 novembre 2026

Audit daté : il ne change plus une fois écrit. Ses suites sont dans [ROADMAP.md](../../ROADMAP.md).

Écrit à la fin du portage, avant les essais des gestionnaires. Il sépare ce qui est mesuré de ce qui est seulement supposé.

## En bref

- La base est bonne : aucune fonction n'a été perdue en route, et les calculs vivent dans des fonctions SQL que le portage n'a pas réécrites.
- La preuve, elle, est plus étroite qu'il n'y paraît. « Identique à l'ancien » a été éprouvé surtout sur la base réduite, sur une poignée de calculs, jamais sur OTHU, et jamais sur une vraie installation sans Docker.
- Une partie de ce que l'outil de comparaison ne voit pas a été trouvée le même jour : une régression du choix de la langue, une erreur JavaScript, un décalage possible des points de contrôle du visualiseur. Il y en a sans doute d'autres, d'où la mesure de couverture proposée.

## Ce qui est mesuré

- **Routes :** 305 dans l'ancien, 269 dans le nouveau. Les 36 de moins sont toutes expliquées : les exports de Sonata, fermés (`dev/differences.md`), le mot de passe oublié, une route toujours en erreur. Une seule route nouvelle, l'envoi du lien de connexion.
- **Commandes :** 11 communes. Celles qui manquent sont remplacées ou voulues : JMS JobQueue par `bdoh:jobs:run`, deux commandes retirées (`dev/differences.md`), celles de Symfony.
- **Fonctions SQL :** 30 de chaque côté, identiques sauf le type de `EXTRACT` que demande PostgreSQL 14 et plus (`Version20261023130000`).
- **Comparaison :** `pages`, `exports`, `state`, `queue`, `imports`, `accounts`, `admin`, `theia`, sur le code du 3 novembre : les seuls écarts sont ceux de `dev/differences.md`.
- **Interface :** 33 étapes dans un vrai navigateur, sur un observatoire (`dev/browser/`).
- **Relecture :** chaque module par un second Claude, chaque constat traité.

## Ce qui n'est pas prouvé

1. **OTHU n'a jamais tourné sur le nouveau.** Sa base n'est pas sur le serveur PostgreSQL 17, alors qu'elle a 106 millions de mesures et trois fonctions SQL qui diffèrent de la production.
2. **La base complète n'a pas revu le code final.** Pages et exports y ont tourné les 24 et 25 octobre, avant bien des changements ; imports, administration, comptes et état de la base seulement sur la base réduite.
3. **Peu de calculs sont éprouvés.** `compare.py state` recalcule une chaîne de trois chroniques et quelques cas, sur 450 chroniques calculées et 152 converties. Le cœur du risque sur les données est là.
4. **« Identique » ne veut pas dire « juste ».** La comparaison reproduit les erreurs de l'ancien sans les voir. Rien ne vérifie les calculs indépendamment de l'ancien code.
5. **On ne sait pas ce que la comparaison n'exécute pas.** Le décalage des points de contrôle n'a été vu qu'à la lecture du code : aucun cas ne l'aurait détecté. Le projet n'a pas de mesure de couverture, ni de test hors de la comparaison.
6. **Aucune installation réelle.** Debian 13 sans Docker, service systemd, Apache en HTTPS, relais de courrier, `/tmp`, redémarrages de l'exécuteur : rien de cela n'a tourné.
7. **Les tâches planifiées, relevées le même jour : rien ne se perd.** Sur les deux serveurs, en dehors de ce que Debian installe, il n'y a que la sauvegarde quotidienne de la base et, en production, la file de tâches, relancée chaque heure par cron. Ni `bdoh:schedule:recompute`, ni nettoyage des tâches, ni envoi automatique vers Theia. Reste à savoir comment OTHU lance sa file.
8. **Aucune mesure de volume.** Temps et mémoire de la plus grosse chronique (2,4 millions de mesures), des plus gros exports et de la page de l'historique, comparés à l'ancien sous PHP 8.
9. **Pas de répétition de la bascule ni de retour arrière écrit.**

## Ce que ça appelle

Sans attendre, sur le poste de développement :

- Restaurer OTHU sur le serveur PostgreSQL 17 et y passer la comparaison.
- Repasser tous les modes sur la base complète, sur le code final.
- Recalculer toutes les chroniques calculées et converties d'une copie de la production avec le nouveau code, et comparer aux valeurs en base, écrites par l'ancien au fil des années : c'est la preuve la plus forte sur le calcul. De même pour les taux de remplissage. Ne sortir que des comptes par chronique.
- Mesurer la couverture du code pendant la comparaison (PCOV), et lire ce qui n'est jamais exécuté, en commençant par ce qui écrit des données.
- Vérifier des invariants indépendants de l'ancien : export puis réimport qui redonne les mêmes mesures, contrôles de `inspect.sql` avant et après.
- Une relecture croisée, code seulement, de tout ce qui écrit des données : l'exécuteur de tâches, écrit pour ce portage, en premier.
- Mesurer le temps et la mémoire des plus grosses opérations, contre l'ancien.

Sur le serveur d'OTHU (Lou) : relever comment sa file de tâches est lancée.

Sur la VM : répéter l'installation et la bascule sur la base anonymisée complète, avec le retour arrière ; essai du courrier ; essai de charge léger, le site ayant peu de visiteurs à la fois mais de grosses opérations.

Par les gestionnaires : leurs vrais fichiers, leurs vrais gestes. Rien ne le remplace.
