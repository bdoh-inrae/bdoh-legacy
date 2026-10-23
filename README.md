# bdoh-legacy

BDOH, la Base de Données des Observatoires Hydrologiques, telle qu'elle est en service, portée de Symfony 2.8 et PHP 7.1 vers Symfony 7.4 et PHP 8.4. Elle est maintenue jusqu'à son remplacement par le nouveau BDOH, développé dans les autres dépôts de [bdoh-inrae](https://github.com/bdoh-inrae), puis archivée.

Le portage garde le comportement, les adresses et les données de l'application en service. Rien n'y tourne encore : l'application en service reste celle du dépôt d'origine (forge INRAE, accès réservé) jusqu'à la bascule. L'avancée est dans [ROADMAP.md](ROADMAP.md).

Les commandes du projet sont dans le `Makefile` : `make` les liste.

Licence : [GNU AGPL v3](LICENSE).
