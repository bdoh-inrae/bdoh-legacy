# bdoh-legacy

BDOH, la Base de Données des Observatoires Hydrologiques, telle qu'elle est en service, portée de Symfony 2.8 et PHP 7.1 vers Symfony 7.4 et PHP 8.4. Elle est maintenue jusqu'à son remplacement par le nouveau BDOH, développé dans les autres dépôts de [bdoh-inrae](https://github.com/bdoh-inrae), puis archivée.

Le portage garde le comportement, les adresses et les données de l'application en service. Rien n'y tourne encore : l'application en service reste celle du dépôt d'origine (forge INRAE, accès réservé) jusqu'à la bascule. L'avancée est dans [ROADMAP.md](ROADMAP.md).

Les commandes du projet sont dans le `Makefile` : `make` les liste.

## Pour développer

Le portage se compare à une référence : l'ancien BDOH, tel qu'il tourne en production, sur des copies anonymisées de ses bases, dans Docker et sans accès à l'extérieur (`dev/reference/`). Les dumps, les fichiers des bases et le code installé vivent hors du dépôt, dans `../bdoh_reference/`.

```
make reference-db      restaure un dump anonymisé
make reference-app     installe l'ancien BDOH (à faire une fois)
make reference-start   lance la référence : http://172.30.25.10/
make reference-stop    l'arrête
```

Tous les comptes de la base anonymisée ont le mot de passe `bdoh-test`.

Licence : [GNU AGPL v3](LICENSE).
