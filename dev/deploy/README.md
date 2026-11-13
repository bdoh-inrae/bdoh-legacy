# Installer BDOH sur la VM

Sur la VM, BDOH est un clone du dépôt (`/var/www/bdoh-prod`), et chaque étape une cible du Makefile, lancée en root dans ce dossier : `sudo make vm-… NOM=prod`. Le code s'y lit par tous, comme sur GitHub (`make help`) ; seuls `.env` et `var/` sont fermés aux autres comptes que root et Apache (`vm-build`). Jusqu'à la 0.5.0, `vm-build` fermait tout le dossier, et la mise à jour vers la 0.6.0 tourne encore avec son script : d'ici la mise à jour suivante, `make` y va lui-même, `sudo make -C /var/www/bdoh-prod vm-… NOM=prod`. Les commandes elles-mêmes sont dans [config/deploy/vm.sh](../../config/deploy/vm.sh), les fichiers qu'elles installent à côté ; ce guide dit l'ordre, et ce qu'il faut regarder entre deux. Le pourquoi des choix : [dev/plans/2026-10-26_deployment.md](../plans/2026-10-26_deployment.md) et `dev/decisions.md` (« Déploiement par `git clone` et Makefile »).

Écrit pour Ubuntu 26.04 (PHP 8.5, PostgreSQL 18, PostGIS 3.6), et suivi de bout en bout le 10 novembre pour installer la production (`dev/plans/2026-10-26_deployment.md`, Suivi). `vm-update` est éprouvé le 13 novembre (0.3.2 vers 0.5.0). Restent à éprouver : la fusion d'OTHU (section 2 bis), la restauration d'une sauvegarde, et la section 5. Ce qui accroche se corrige ici.

Aucune étape ne supprime une base, et aucune ne restaure dans une base qui existe. Une erreur s'arrête et se lit : ne pas relancer à l'aveugle.

## 0. La VM livrée : le disque et le système

Fait le 9 novembre (`dev/plans/2026-10-26_deployment.md`, Suivi). La VM était livrée sous Ubuntu 22.04, avec un disque de 200 Go dont 19 seulement attribués, en LVM (groupe `vg1`). Les volumes s'agrandissent sans arrêter la machine ; les tailles sont absolues, une ligne relancée ne grandit pas deux fois :

```
sudo lvextend -r -L 15G  /dev/vg1/root
sudo lvextend -r -L 140G /dev/vg1/var
sudo lvextend -r -L 8G   /dev/vg1/tmp
sudo lvextend -r -L 5G   /dev/vg1/varlog
```

Puis le système, une version LTS à la fois, en redémarrant après chacune : `sudo apt update && sudo apt full-upgrade`, `sudo apt install usrmerge` (24.04 refuse un `/usr` non fusionné), `sudo do-release-upgrade`, deux fois. Les questions sont en français : « [oN] » attend `o`, toute autre lettre vaut non. Garder la version installée des fichiers de configuration modifiés par l'infra (`sshd_config`, `smb.conf`).

## 1. Le dépôt et la machine, une fois

`make` et `git` d'abord, puis le clone de la production, à la version voulue (un tag, jamais `main`) :

```
sudo apt-get install -y make git
sudo git clone https://github.com/bdoh-inrae/bdoh-legacy /var/www/bdoh-prod
cd /var/www/bdoh-prod
sudo git -c advice.detachedHead=false checkout v0.3.5
sudo make vm-deps
```

`vm-deps` installe les paquets (PHP, PostgreSQL, Apache, Node et Composer pour construire), les langues, les réglages de PostgreSQL (`config/deploy/postgresql.conf`), les modules d'Apache, les services de la file et des sauvegardes, et les dossiers des sauvegardes. Il dit à la fin si `/tmp` est un `tmpfs` : sur la VM, un volume ext4 de 8 Go ; un `tmpfs` serait à masquer (`systemctl mask tmp.mount`), les imports et les exports y écrivent.

## 2. Le code et la base

Une seule instance, la production (`NOM=prod`, port 443) : OTHU y devient un observatoire (section 2 bis). `ADRESSE` est l'adresse IP de la VM, ou son nom.

```
sudo make vm-env NOM=prod ADRESSE=…
sudo make vm-build NOM=prod
```

`vm-env` crée le rôle PostgreSQL de l'instance et son `.env`, avec des secrets tirés au hasard ; il refuse si `.env` existe. `MAILER_DSN` y vaut `null://null` : rien ne part. Les courriels se règlent ensuite par `vm-mail`, qui écrit dans cet ordre, pour qu'aucun ne puisse partir ailleurs que chez Lou, l'adresse de renvoi des essais (`MAILER_DIVERT_TO`), puis le relais de l'INRAE avec le compte de l'ancienne production, comme elle (port 587, STARTTLS). Il demande l'identifiant et le mot de passe au clavier, sans afficher le second, que Lou recopie de l'ancien serveur, et les écrit encodés dans `MAILER_DSN` :

```
sudo make vm-mail NOM=prod RELAIS=hôte:587 RENVOI=adresse-de-Lou
```

Reste `MAILER_ALLOWED_RECIPIENTS` (`sudo nano .env`) : vide pour les essais de Lou ; la ligne de `allowed-recipients.env` (`dev/test-kit/README.md`) quand les gestionnaires essaient, pour que chacun reçoive ses propres liens.

Le site relit `.env` à chaque requête : pas de cache à vider. La file de tâches, elle, le lit à son démarrage : après un changement, `sudo systemctl restart bdoh-job-worker@prod` (sinon, elle le reprend à sa relance d'heure en heure).

Les dumps bruts et les fichiers déposés se copient depuis le poste dans `/var/backups/bdoh-sources/`, que `vm-deps` a créé pour le compte d'administration (pas dans son dossier personnel : `/home` fait 1 Go). Le dossier passe ensuite à root, les dumps bruts contenant les comptes :

```
sudo chown -R root:root /var/backups/bdoh-sources
sudo make vm-db NOM=prod DUMP=/var/backups/bdoh-sources/….dump
```

`vm-db` restaure le dump dans une base neuve (`--locale=en_US.UTF-8`), la donne au rôle de l'instance (`dev/reference/own-by-bdoh.sql`), applique les migrations entre deux contrôles, et pour la production déballe les logos et images (`bdoh-uploads-prod.tar.gz`). À lire à la fin : `inspect.sql` ne change que sur la ligne « séquences en retard », qui passe de 7 à 0 ; l'empreinte ne lit que les mesures (`dev/reference/fingerprint.sql`), que les migrations ne touchent pas : aucune chronique ne doit y être listée. C'est ce qu'a donné la production le 10 novembre.

Si l'archive des logos n'était pas encore dans `/var/backups/bdoh-sources/`, la déballer ensuite, dans `/var/www/bdoh-prod` :

```
sudo tar -xzf /var/backups/bdoh-sources/bdoh-uploads-prod.tar.gz --strip-components=1 -C public web/uploads
sudo chown -R www-data:www-data public/uploads
```

## 2 bis. OTHU, un observatoire de la production

Une fois la production installée et à la version qui porte la fusion (0.6.0 ou après), le dump brut d'OTHU copié dans `/var/backups/bdoh-sources/` :

```
sudo make vm-merge-othu NOM=prod DUMP=/var/backups/bdoh-sources/bdoh-othu-2026-10-21.dump
```

À lancer hors de la sauvegarde de la nuit (vers 2 h), et dans `tmux`, un terminal qui vit sur la VM et non dans la connexion ssh : une coupure du VPN arrêterait sinon tout en route. `tmux new -s fusion` ouvre une session (une barre verte en bas) ; on y lance la commande, suivie de `2>&1 | tee ~/fusion-othu.log` pour en garder le compte rendu, fait de comptes et de libellés de listes ; Ctrl+b puis `d` s'en détache, la fusion continuant ; `tmux attach -t fusion` y revient, après une reconnexion s'il le faut ; Ctrl+b puis `[` remonte dans ce qui a défilé (`q` en sort) ; `exit` ferme la session. Ferme le site et arrête la file jusqu'à la fin, vérifie qu'il reste 60 Go libres, sauvegarde la base dans `/var/backups/bdoh-kept/`, que la sauvegarde de la nuit ne vide pas (`prod-avant-fusion-othu-…`, à supprimer à la main une fois les essais passés), restaure OTHU dans une base temporaire, la charge à part dans la production, puis la fond en une seule transaction (`dev/merge-othu/merge.sql`) : une erreur, et rien n'a changé. Le script vérifie l'empreinte de chaque chronique d'OTHU avant de valider, et dit ce qu'il a ajouté et rapproché : comptes, unités, paramètres, communes (`dev/plans/2026-11-13_othu-merge.md`). À lire ensuite : aucune chronique de la production dans l'empreinte changée. 48 minutes sur le poste avec les copies anonymisées, dont 39 pour la fusion elle-même (106 millions de mesures versées dans une table qui en a 257 millions) ; sans doute plus sur la VM. Ses trois fonctions SQL disparaissent avec sa base : les exports d'OTHU suivent celles de la production.

Pour revenir en arrière, en 4 minutes sur le poste, tant que personne n'a écrit sur OTHU (un export n'y écrit rien) et que rien dans la production ne cite ce que la fusion a ajouté (un rôle donné à un ancien compte d'OTHU, par exemple) : sinon la commande le dit et ne change rien, et le retour se fait par la sauvegarde d'avant la fusion :

```
sudo make vm-unmerge-othu NOM=prod
```

## 3. Le site

```
sudo make vm-apache NOM=prod
```

Écrit le site Apache (`config/deploy/apache-site.conf`), demande le mot de passe d'accès des essais (compte `test`, partagé avec les testeurs ; il se change par `sudo htpasswd /etc/apache2/bdoh-prod.htpasswd test`), et recharge Apache. Le certificat est celui, auto-signé, du paquet `ssl-cert`, tant que l'infra n'en fournit pas.

## 4. Courriels, file de tâches, sauvegardes

La file de tâches enverra aussitôt les courriels des tâches restées en attente dans le dump : un courriel d'essai d'abord, qui doit arriver à `MAILER_DIVERT_TO` et à personne d'autre. `vm-worker` refuse si `MAILER_DIVERT_TO` est vide : sans lui, `MAILER_ALLOWED_RECIPIENTS` est ignoré et tout part aux vrais destinataires.

```
sudo make vm-mail-test NOM=prod
sudo make vm-worker NOM=prod
sudo make vm-backup NOM=prod
```

Les tâches restées « en cours » dans le dump passent alors en « incomplètes », relançables. Les sauvegardes partent chaque nuit dans `/var/backups/bdoh/` (base) et `/var/backups/bdoh-files/` (`.env`, dont la clé Theia, et fichiers déposés), gardées 8 jours ; une sauvegarde qui échoue ne prévient personne : `systemctl --failed`. Une sauvegarde ne vaut qu'une fois restaurée : l'essayer une fois dans une base à part, où `inspect.sql` doit donner les mêmes comptes, puis la supprimer, seule base qu'on supprime :

```
sudo runuser -u postgres -- createdb -T template0 -E UTF8 --locale=en_US.UTF-8 bdoh_essai
sudo runuser -u postgres -- pg_restore -d bdoh_essai --no-owner --no-privileges /var/backups/bdoh/prod-AAAA-MM-JJ.dump
sudo runuser -u postgres -- dropdb bdoh_essai
```

## 5. Vérifier

- `sudo make vm-status NOM=prod` : version, services, et l'accueil qui répond 401 (derrière le mot de passe d'accès).
- L'accueil, dans un navigateur ; les logos sont là ; OTHU parmi les observatoires.
- Un lien de connexion arrive sur une adresse INRAE et sur une adresse extérieure, et le bouton connecte. Une messagerie qui ouvre les liens pour les analyser ne doit pas le consommer.
- Un courriel vers une adresse hors de la liste arrive à `MAILER_DIVERT_TO`, et pas à son destinataire.
- Un export, un import, et un calcul qui se relance : `systemctl status bdoh-job-worker@prod`, et la page des tâches.
- Arrêter PostgreSQL 30 s, le relancer : la file repart seule.
- Un import en plusieurs étapes, en redémarrant Apache entre deux : le service Apache d'Ubuntu a `PrivateTmp=true`, à voir ce que ça donne.

Ensuite, la liste de Lou : [dev/test-kit/lou-checklist.md](../test-kit/lou-checklist.md).

## Installer une nouvelle version

```
sudo make vm-update NOM=prod V=vX.Y.Z
```

Chaque étape s'annonce à l'heure ; la sauvegarde et les deux contrôles de la base, muets, prennent quelques minutes chacun sur la base complète. Arrête la file, sauvegarde la base sous un nom à part (`/var/backups/bdoh-kept/prod-avant-…`, que la sauvegarde de la nuit ne vide pas ; jusqu'à la 0.6.0, `/var/backups/bdoh/`, vidé au bout de 8 jours), passe au tag, puis laisse la suite au script de la version installée : il reconstruit, applique les migrations entre deux contrôles, recharge Apache et relance la file. Les thèmes compilés se recompilent avec la nouvelle version. Pour revenir en arrière : la version précédente par la même commande, et, si les migrations ont touché la base, ce dump.

## À l'ouverture au public

Retirer le bloc `<Location />` du site (`config/deploy/apache-site.conf`) ; vider `MAILER_DIVERT_TO` et `MAILER_ALLOWED_RECIPIENTS` ; le nom public, son certificat et `SYMFONY_TRUSTED_HOSTS` ; le flux vers Theia/OZCAR ; le reste dans `dev/plans/2026-10-26_deployment.md`.
