# bdoh-legacy

BDOH, la Base de Données des Observatoires Hydrologiques, telle qu'elle est en service, portée de Symfony 2.8 et PHP 7.1 vers Symfony 7.4 et PHP 8.4. Elle est maintenue jusqu'à son remplacement par le nouveau BDOH, développé dans les autres dépôts de [bdoh-inrae](https://github.com/bdoh-inrae), puis archivée.

Le portage garde le comportement, les adresses et les données de l'application en service. Rien n'y tourne encore : il remplacera à la bascule le code du dépôt d'origine (forge INRAE, accès réservé). L'avancée est dans [ROADMAP.md](ROADMAP.md).

Les commandes du projet sont dans le `Makefile` : `make` les liste.

## Pour développer

Le portage se compare à une référence : l'ancien BDOH, tel qu'il tourne en production, sur des copies anonymisées de ses bases, dans Docker et sans accès à l'extérieur (`dev/reference/`). Les dumps, les fichiers des bases et le code installé vivent hors du dépôt, dans `../bdoh_reference/`.

```
make db                restaure un dump anonymisé
make db-small          en tire la base de travail réduite, bdoh_small
make reference-app     installe l'ancien BDOH (à faire une fois)
make reference-start   lance la référence : http://172.30.25.10/
make reference-base    la branche sur une autre base (BASE=bdoh_small)
make reference-stop    l'arrête
```

La comparaison ne lance pas de JavaScript : l'interface s'essaie dans un navigateur (`make browser-check`, `dev/browser/README.md`).

Le nouveau BDOH a son propre serveur de base, PostgreSQL 17 comme Debian 13 (`compose.yaml`) : `SERVER=new` y mène les commandes `db`, `db-small` et `inspect`. Il se lance par `make start` et répond sur http://172.30.26.10/.

Dans la référence, tous les comptes de la base anonymisée ont le mot de passe `bdoh-test`. Le nouveau BDOH n'a pas de mot de passe : il envoie un lien de connexion par courriel. En développement, rien ne part ; le lien s'obtient de deux façons :

- par la console : `docker exec bdoh-app php bin/console security:users:login-link ADRESSE OBSERVATOIRE` ;
- par Mailpit, qui retient les courriels (`make mail-start`, puis http://172.30.26.25:8025/), une fois la ligne `MAILER_DSN=smtp://mailpit:1025` ajoutée à `.env.local`. `dev/compare/use-base.sh` et `compare.py` réécrivent ce fichier, et la ligne disparaît.

Une adresse e-mail, à l'inscription comme ailleurs, doit finir en `.invalid` : BDOH refuse un domaine sans serveur de courrier, et le DNS du développement n'en donne qu'à ceux-là (`dev/dns/Corefile`).

## Licence et citation

Le code est sous licence [GNU AGPL v3 ou ultérieure](LICENSE). Les bibliothèques copiées (dossiers `vendor` de `src/`) gardent leur propre licence, et les logos restent à leurs titulaires.

Pour citer BDOH et connaître ses auteurs : [CITATION.cff](CITATION.cff) et [codemeta.json](codemeta.json).
