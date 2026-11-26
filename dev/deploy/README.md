# Installer BDOH sur la VM

Sur la VM, BDOH est un clone du dépôt (`/var/www/bdoh-prod`), et chaque étape une cible du Makefile, lancée en root dans ce dossier : `sudo make vm-…`. Le code s'y lit par tous, comme sur GitHub (`make help`) ; seuls `.env` et `var/` sont fermés aux autres comptes que root et Apache (`vm-build`). Jusqu'à la 0.5.0, `vm-build` fermait tout le dossier. La 0.6.0 de la VM a été construite par le script de la 0.5.0, qui ne passait pas encore la main à celui de la version installée : son dossier est resté fermé (constaté le 15 novembre). D'ici la mise à jour vers la 0.7.0, `make` y va donc lui-même, `sudo make -C /var/www/bdoh-prod vm-… NOM=prod` ; ensuite, `cd /var/www/bdoh-prod` suffit. Les commandes elles-mêmes sont dans [config/deploy/vm.sh](../../config/deploy/vm.sh), les fichiers qu'elles installent à côté ; ce guide dit l'ordre, et ce qu'il faut regarder entre deux. Le pourquoi des choix : [dev/plans/2026-10-26_deployment.md](../plans/2026-10-26_deployment.md) et `dev/decisions.md` (« Déploiement par `git clone` et Makefile »).

Écrit pour Ubuntu 26.04 (PHP 8.5, PostgreSQL 18, PostGIS 3.6), et suivi de bout en bout le 10 novembre pour installer la production, puis les 23 et 24 novembre sur une VM neuve, fusion d'OTHU comprise (`dev/plans/2026-10-26_deployment.md`, Suivi). `vm-update` est éprouvé le 13 novembre (0.3.2 vers 0.5.0). Restent à éprouver : la restauration d'une sauvegarde, et une partie de la section 5. Ce qui accroche se corrige ici.

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

`make` et `git` d'abord, puis le clone de la production, à la version voulue (un tag, jamais `main` ; la dernière publiée, dans `CHANGELOG.md`) :

```
sudo apt-get install -y make git
sudo git clone https://github.com/bdoh-inrae/bdoh-legacy /var/www/bdoh-prod
cd /var/www/bdoh-prod
sudo git -c advice.detachedHead=false checkout vX.Y.Z
sudo make vm-deps
```

`vm-deps` installe les paquets (PHP, PostgreSQL, Apache, Node et Composer pour construire), les langues, les réglages de PostgreSQL (`config/deploy/postgresql.conf`), les modules d'Apache, les services de la file et des sauvegardes, et les dossiers des sauvegardes. Il dit à la fin si `/tmp` est un `tmpfs` : sur la VM, un volume ext4 de 8 Go ; un `tmpfs` serait à masquer (`systemctl mask tmp.mount`), les imports et les exports y écrivent.

## 2. Le code et la base

Une seule instance, la production (port 443) : OTHU y devient un observatoire (section 2 bis). Jusqu'à la 0.7.0, chaque cible demandait encore `NOM=prod`, souvenir de l'instance prévue pour OTHU ; depuis la 0.7.1, c'est la valeur par défaut. `ADRESSE` est l'adresse IP de la VM, ou son nom.

```
sudo make vm-env ADRESSE=…
sudo make vm-build
```

`vm-env` crée le rôle PostgreSQL de l'instance et son `.env`, avec des secrets tirés au hasard ; il refuse si `.env` existe. `MAILER_DSN` y vaut `null://null` : rien ne part. Les courriels se règlent ensuite par `vm-mail`, qui écrit dans cet ordre, pour qu'aucun ne puisse partir ailleurs que chez Lou, l'adresse de renvoi des essais (`MAILER_DIVERT_TO`), puis le relais de l'INRAE avec le compte de l'ancienne production, comme elle (port 587, STARTTLS). Il demande l'identifiant et le mot de passe au clavier, sans afficher le second, que Lou recopie de l'ancien serveur, et les écrit encodés dans `MAILER_DSN` :

```
sudo make vm-mail RELAIS=hôte:587 RENVOI=adresse-de-Lou
```

Reste `MAILER_ALLOWED_RECIPIENTS` (`sudo nano .env`) : vide pour les essais de Lou ; la ligne de `allowed-recipients.env` (`dev/test-kit/README.md`) quand les gestionnaires essaient, pour que chacun reçoive ses propres liens. Une adresse ajoutée à la main se met au bout de la ligne, au format de `contacts.py` (`(?i)prenom[.]nom@inrae[.]fr`, séparée par une virgule). Lou reçoit alors une copie de chaque courriel, même de ceux qui partent aussi à leur destinataire, sans que son adresse paraisse dans les en-têtes : le renvoi n'agit que sur l'enveloppe (`EnvelopeListener` de Symfony). Pour savoir si une adresse passe, sur la VM :

```
sudo php -r 'require "vendor/autoload.php"; (new Symfony\Component\Dotenv\Dotenv())->load(".env"); $a = "prenom.nom@inrae.fr"; foreach (str_getcsv($_ENV["MAILER_ALLOWED_RECIPIENTS"] ?? "", ",", "\"", "") as $r) { if (preg_match("{\A" . $r . "\z}", $a)) { exit("Une règle laisse passer cette adresse.\n"); } } echo "Aucune règle ne laisse passer cette adresse.\n";'
```

Le site relit `.env` à chaque requête : pas de cache à vider. La file de tâches, elle, le lit à son démarrage : après un changement, `sudo systemctl restart bdoh-job-worker@prod` (sinon, elle le reprend à sa relance d'heure en heure).

Les dumps bruts et les fichiers déposés se copient depuis le poste dans `/var/backups/bdoh-sources/`, que `vm-deps` a créé pour le compte d'administration (pas dans son dossier personnel : `/home` fait 1 Go) : les deux dumps, et les deux archives de `../bdoh_reference/uploads/`, `bdoh-uploads-prod.tar.gz` (les logos et images de la production) et `bdoh-uploads-partners.tar.gz` (les logos des partenaires ajoutés le 23 novembre, avec leurs sources, hors du dépôt : `dev/decisions.md`, « Logos des partenaires hors du dépôt »). Le dossier passe ensuite à root, les dumps bruts contenant les comptes :

```
sudo chown -R root:root /var/backups/bdoh-sources
sudo make vm-db DUMP=/var/backups/bdoh-sources/….dump
```

`vm-db` refuse de commencer sans les deux archives. Il restaure le dump dans une base neuve (`--locale=en_US.UTF-8`), la donne au rôle de l'instance (`dev/reference/own-by-bdoh.sql`), déballe les deux archives dans `public/uploads/`, puis applique les migrations entre deux contrôles : celle des logos des partenaires s'arrête si l'un de ses fichiers manque, et dit combien elle en a posé (31). À lire à la fin : `inspect.sql` ne change que sur la ligne « séquences en retard », qui passe de 7 à 0 ; l'empreinte ne lit que les mesures (`dev/reference/fingerprint.sql`), que les migrations ne touchent pas : aucune chronique ne doit y être listée. C'est ce qu'a donné la production le 10 novembre.

## 2 bis. OTHU, un observatoire de la production

Une fois la production installée et à la version qui porte la fusion (0.6.0 ou après), le dump brut d'OTHU copié dans `/var/backups/bdoh-sources/`.

La fusion est écrite pour la base telle que la laisse la migration `Version20261113120000` (0.6.0). La migration de la relecture (`Version20261119120000`, 0.9.1) a depuis corrigé des libellés des listes communes, que la fusion veut identiques ligne pour ligne à ceux d'OTHU, et elle corrige aussi les lignes d'OTHU une fois fondues : elle passe après la fusion. Sur une installation neuve, où `vm-db` a tout appliqué, on revient donc d'abord à cette version, puis on fusionne, puis on rejoue les migrations suivantes (relecture, compteur des consultations, logos des partenaires ; éprouvé sur la petite base le 24 novembre, après l'échec de la fusion sur la VM, "Liste categorie : 1 ligne(s) d'OTHU sans pareil") :

```
sudo runuser -u www-data -- php bin/console --env=prod --no-interaction doctrine:migrations:migrate 'DoctrineMigrations\Version20261113120000'
```

Puis la fusion :

```
sudo make vm-merge-othu DUMP=/var/backups/bdoh-sources/bdoh-othu-2026-10-21.dump
```

À lancer hors de la sauvegarde de la nuit (vers 2 h), et dans `tmux`, un terminal qui vit sur la VM et non dans la connexion ssh : une coupure du VPN arrêterait sinon tout en route. `tmux new -s fusion` ouvre une session (une barre verte en bas) ; on y lance la commande, suivie de `2>&1 | tee ~/fusion-othu.log` pour en garder le compte rendu, fait de comptes et de libellés de listes ; Ctrl+b puis `d` s'en détache, la fusion continuant ; `tmux attach -t fusion` y revient, après une reconnexion s'il le faut ; Ctrl+b puis `[` remonte dans ce qui a défilé (`q` en sort) ; `exit` ferme la session. Ferme le site et arrête la file jusqu'à la fin, vérifie qu'il reste 60 Go libres, sauvegarde la base dans `/var/backups/bdoh-kept/`, que la sauvegarde de la nuit ne vide pas (`prod-avant-fusion-othu-…`, à supprimer à la main une fois les essais passés), restaure OTHU dans une base temporaire, la charge à part dans la production, puis la fond en une seule transaction (`dev/merge-othu/merge.sql`) : une erreur, et rien n'a changé. Le script vérifie l'empreinte de chaque chronique d'OTHU avant de valider, et dit ce qu'il a ajouté et rapproché : comptes, unités, paramètres, communes (`dev/plans/2026-11-13_othu-merge.md`). À lire ensuite : aucune chronique de la production dans l'empreinte changée. 48 minutes sur le poste avec les copies anonymisées, dont 39 pour la fusion elle-même (106 millions de mesures versées dans une table qui en a 257 millions) ; 44 minutes sur la VM neuve, le 24 novembre. Ses trois fonctions SQL disparaissent avec sa base : les exports d'OTHU suivent celles de la production.

Après la fusion, les migrations suivantes, qui doivent dire "31 logos de partenaires posés" :

```
sudo runuser -u www-data -- php bin/console --env=prod --no-interaction doctrine:migrations:migrate
sudo runuser -u www-data -- php bin/console --env=prod cache:clear
```

Pour revenir en arrière, en 4 minutes sur le poste, tant que personne n'a écrit sur OTHU (un export n'y écrit rien) et que rien dans la production ne cite ce que la fusion a ajouté (un rôle donné à un ancien compte d'OTHU, par exemple) : sinon la commande le dit et ne change rien, et le retour se fait par la sauvegarde d'avant la fusion :

```
sudo make vm-unmerge-othu
```

Une fois les migrations suivantes rejouées, ce retour par `vm-unmerge-othu` n'est pas éprouvé : la sauvegarde d'avant la fusion reste alors le chemin sûr.

Les trois partenaires d'OTHU arrivent avec la fusion, après la migration des logos : leurs logos se déposent dans l'administration (Administration, Partenaires, chacun son formulaire), depuis le poste, `../bdoh_reference/review/logos/othu-by-hand/` (sources dans `SOURCES.md`, à côté).

## 3. Le site

```
sudo make vm-anubis
sudo make vm-apache
```

`vm-anubis` installe Anubis, qui écarte les robots d'IA (`dev/plans/2026-11-26_ai-robots.md`) : le paquet du projet, qu'Ubuntu 26.04 n'a pas, à la version et à l'empreinte fixées dans `config/deploy/vm.sh`, puis ses réglages et sa règle (`config/deploy/anubis.env`, `anubis-policy.yaml`) et le service `anubis@bdoh-prod`, sur l'interface locale. À relancer quand ces fichiers changent. `vm-apache` refuse sans lui : le site lui passe tout.


Écrit le site Apache (`config/deploy/apache-site.conf`) : le port 443 termine le HTTPS et passe tout à Anubis, qui passe à BDOH, sur `127.0.0.1:3001`, ce qu'il laisse entrer. Il demande le mot de passe d'accès des essais (compte `test`, partagé avec les testeurs ; rien ne s'affiche pendant la frappe, et deux Entrée laissent un fichier sans le compte, que `vm-apache` relancé ne redemande pas ; il se pose ou se change par `sudo htpasswd /etc/apache2/bdoh-prod.htpasswd test`), et recharge Apache. Le site prend le nom de `DEFAULT_URI` (`ServerName`). Sur le port 80, il renvoie tout vers ce nom en `https`, chemin compris (0.9.8) : le nom tapé seul, un lien en `http`, une adresse IP. Son certificat est d'abord celui, auto-signé, du paquet `ssl-cert`.

Le certificat du nom public, une fois le nom mené à la VM et le port 443 ouvert à Internet (section 5, "Depuis Internet, le port") :

```
sudo make vm-certificate
```

Apache le demande à Let's Encrypt et le renouvelle seul, par son module `mod_md`. Le nom se valide par le port 443 (méthode TLS-ALPN-01) ; le 80, fermé depuis Internet jusqu'au 25 novembre, ne sert qu'au renvoi vers `https`. Tant qu'il n'a pas de certificat, `mod_md` répond 503 à tout le site : sans certificat au bout de trois minutes, ou si Apache s'arrête en chargeant le module, l'étape retire le module, le site revient au certificat auto-signé, et elle affiche ce qu'en dit `mod_md`. Ses comptes et ses certificats sont dans `/var/lib/apache2/md` (`MDStoreDir`) : le service Apache d'Ubuntu 26.04 rend `/etc` en lecture seule, et Apache s'arrêtait en chargeant le module avec le magasin par défaut, `/etc/apache2/md` (0.9.6, `dev/plans/2026-10-26_deployment.md`, Suivi du 26 novembre). Un certificat renouvelé attend dans `/var/lib/apache2/md/staging/`, et prend effet au rechargement d'Apache que logrotate fait chaque nuit. Revenir au certificat auto-signé, une fois le vrai en place : `sudo a2dismod md && sudo systemctl restart apache2` ; mais l'en-tête HSTS dit aux navigateurs passés entre-temps de refuser pendant un an un certificat qui n'est pas valide.

## 4. Courriels, file de tâches, sauvegardes

La file de tâches enverra aussitôt les courriels des tâches restées en attente dans le dump : un courriel d'essai d'abord, qui doit arriver à `MAILER_DIVERT_TO` et à personne d'autre. `vm-worker` refuse si `MAILER_DIVERT_TO` est vide : sans lui, `MAILER_ALLOWED_RECIPIENTS` est ignoré et tout part aux vrais destinataires.

```
sudo make vm-mail-test
sudo make vm-worker
sudo make vm-backup
```

Les tâches restées « en cours » dans le dump passent alors en « incomplètes », relançables. Les sauvegardes partent chaque nuit dans `/var/backups/bdoh/` (base) et `/var/backups/bdoh-files/` (`.env`, dont la clé Theia, et fichiers déposés), gardées 8 jours ; une sauvegarde qui échoue ne prévient personne : `systemctl --failed`. Une sauvegarde ne vaut qu'une fois restaurée : l'essayer une fois dans une base à part, où `inspect.sql` doit donner les mêmes comptes, puis la supprimer, seule base qu'on supprime. La base restaurée prend presque autant que la production (45 Go pour 46, le 24 novembre) : `df -h /var` d'abord. Le tout dure plusieurs dizaines de minutes, dans `tmux`, après `sudo -v` pour que le mot de passe ne soit pas demandé au milieu :

```
sudo runuser -u postgres -- createdb -T template0 -E UTF8 --locale=en_US.UTF-8 bdoh_essai
sudo runuser -u postgres -- pg_restore -d bdoh_essai --no-owner --no-privileges /var/backups/bdoh/prod-AAAA-MM-JJ.dump
sudo runuser -u postgres -- psql -qAt -F '|' -d bdoh_prod < dev/reference/inspect.sql > ~/inspect-prod.txt
sudo runuser -u postgres -- psql -qAt -F '|' -d bdoh_essai < dev/reference/inspect.sql > ~/inspect-essai.txt
wc -l ~/inspect-prod.txt ~/inspect-essai.txt
diff ~/inspect-prod.txt ~/inspect-essai.txt && echo "identiques"
sudo runuser -u postgres -- dropdb bdoh_essai
```

## 5. Vérifier

- `sudo make vm-status` : version, services, et l'accueil qui répond 401 (derrière le mot de passe d'accès).
- L'accueil, dans un navigateur ; les logos sont là ; OTHU parmi les observatoires.
- Un lien de connexion arrive sur une adresse INRAE et sur une adresse extérieure, et le bouton connecte. Une messagerie qui ouvre les liens pour les analyser ne doit pas le consommer.
- Un courriel vers une adresse hors de la liste arrive à `MAILER_DIVERT_TO`, et pas à son destinataire, son objet commençant par `[ESSAIS : NON ENVOYÉ À …]` (0.9.8). Celui d'une adresse de la liste arrive aux deux, l'objet inchangé.
- Un export, un import, et un calcul qui se relance : `systemctl status bdoh-job-worker@prod`, et la page des tâches.
- Arrêter PostgreSQL 30 s, le relancer : la file repart seule.
- Depuis Internet, le port : l'API de check-host.net, `https://check-host.net/check-tcp?host=ADRESSE-IP:443&max_nodes=4` avec l'en-tête `Accept: application/json`, puis `https://check-host.net/check-result/<request_id>` quelques secondes après. "Connection timed out" : bloqué en chemin ; "Connection refused" : arrivé à la VM, rien n'écoute. Un navigateur à qui l'on tape le nom seul se rabat sur `http`, donc sur le port 80, tant que le certificat n'est pas valide : il ne dit rien du 443.
- Depuis l'extérieur, un téléphone en 4G : l'accueil s'ouvre, et `sudo tail -n 5 /var/log/apache2/bdoh-prod-access.log` montre en tête de ligne l'adresse du téléphone. Une adresse privée d'INRAE veut dire qu'un équipement du réseau remplace celle des visiteurs : les limites par adresse vaudraient alors pour tout le site, et le compteur des consultations ne verrait qu'un visiteur. Voir s'il la transmet dans l'en-tête `X-Forwarded-For` (`SYMFONY_TRUSTED_PROXIES`, dans `.env`), sinon le demander à l'infra (`dev/plans/2026-10-26_deployment.md`, Suivi du 23 novembre).
- Anubis : un navigateur qui arrive voit une fois la page de défi ("Making sure you're not a bot", ou en français selon le navigateur), puis le site ; `sudo tail -n 5 /var/log/apache2/bdoh-prod-app-access.log` montre en tête de ligne l'adresse des visiteurs qu'il laisse entrer ; `systemctl status anubis@bdoh-prod`. Le lien de connexion d'un courriel et le lien d'un export passent aussi par le défi.
- Un import en plusieurs étapes, en redémarrant Apache entre deux : le service Apache d'Ubuntu a `PrivateTmp=true`, à voir ce que ça donne.

Ensuite, la liste de Lou : [dev/test-kit/lou-checklist.md](../test-kit/lou-checklist.md).

## Installer une nouvelle version

```
sudo make vm-update V=vX.Y.Z
```

La mise à jour part du script de la version installée : depuis la 0.7.0, elle demande encore `sudo make vm-update NOM=prod V=…`, une dernière fois ; ensuite, `NOM` n'est plus à donner.

Une instance qui passe à la 0.9.2 ou après : `sudo make vm-apache` après la mise à jour, qui donne son nom au site Apache, puis `sudo make vm-certificate` (section 3). De même pour la 0.9.7, qui met le magasin de `mod_md` sous `/var` : `vm-certificate` refuse un site Apache qui ne l'a pas. Pour la 0.9.8, `sudo make vm-apache` seul, qui ajoute le renvoi du port 80 vers `https`. Pour la 0.10.0, `sudo make vm-anubis` puis `sudo make vm-apache`, qui met Anubis devant BDOH. Pour la 0.10.1, `sudo make vm-anubis`, qui réécrit ses réglages (son journal réduit à ses avertissements).

Une instance installée avant la 0.9.1 qui passe à la 0.9.1 ou après : déballer d'abord les logos des partenaires, que sa migration demande (section 2), dans `/var/www/bdoh-prod` :

```
sudo tar -xzf /var/backups/bdoh-sources/bdoh-uploads-partners.tar.gz --strip-components=1 -C public web/uploads
sudo chown -R www-data:www-data public/uploads
```

Chaque étape s'annonce à l'heure ; la sauvegarde et les deux contrôles de la base, muets, prennent quelques minutes chacun sur la base complète. Arrête la file, sauvegarde la base sous un nom à part (`/var/backups/bdoh-kept/prod-avant-…`, que la sauvegarde de la nuit ne vide pas ; jusqu'à la 0.6.0, `/var/backups/bdoh/`, vidé au bout de 8 jours), passe au tag, puis laisse la suite au script de la version installée : il reconstruit, applique les migrations entre deux contrôles, recharge Apache et relance la file. Les thèmes compilés se recompilent avec la nouvelle version. Pour revenir en arrière : la version précédente par la même commande, et, si les migrations ont touché la base, ce dump.

## Mesurer les exports

Les seuils de volume d'un export (`dev/differences.md`, « Le volume d'un export ») ont été posés d'après le poste de développement : le second est provisoire. Sur la VM, mesurer ce que durent une lecture et un calcul, et la place du dossier temporaire, pour le régler. Ils se règlent dans le `.env` de l'instance, sans nouvelle version ni redémarrage : `EXPORT_READ_WITHOUT_ACCOUNT`, `EXPORT_READ_MAX`, `EXPORT_COMPUTED_WITHOUT_ACCOUNT`, `EXPORT_COMPUTED_MAX` (`.env.example`) ; une ligne absente garde la valeur par défaut. La sortie des commandes est jetée : rien ne s'écrit, la file n'est pas touchée, et seuls s'affichent des durées, un nombre de lignes et un nombre d'octets. À un moment calme, dans le dossier de l'instance :

```
df -h /tmp
sudo runuser -u postgres -- psql -d bdoh_prod -Atc "SELECT id, nbmesures, datedebutmesures::date, datefinmesures::date FROM chronique WHERE estvisible AND dtype = 'continue' ORDER BY nbmesures DESC NULLS LAST LIMIT 1"
```

Avec l'identifiant de cette chronique à la place de `ID`, et une année où elle a des mesures :

```
mesure() { time sudo runuser -u www-data -- php bin/console --env=prod --no-interaction "$@" --format csv --timezone '+00:00' 2>/dev/null | wc -lc; }
AN="2015-01-01T00:00:00+00:00 2016-01-01T00:00:00+00:00"
mesure bdoh:export:identical $AN ID
mesure bdoh:export:instantaneous 1 $AN ID
mesure bdoh:export:mean 5 $AN ID
mesure bdoh:export:mean 1 $AN ID
mesure bdoh:export:cumulative 1 $AN ID
```

Sur le poste, le 16 novembre : 1,3 s (128 000 lignes), 6,1 s (526 000), 15 s (9 500), 164 s (106 000) et 163 s. Le second seuil des valeurs lues (50 millions) vaut la place de `/tmp` : 56 octets par valeur en CSV, que l'export écrit sans compression avant l'archive, à côté des imports. Celui des valeurs calculées (1 200 000) vaut une durée de file : la dernière commande, multipliée par onze, donne celle d'un export au seuil (une demi-heure sur le poste). Il reste au-dessus de 1 157 112, ce que l'ancien acceptait ; si la mesure le montre déraisonnable sur la VM, on en reparle avec Lou (`dev/decisions.md`, "Ouverture des données").

## À l'ouverture au public

Retirer le bloc `<Location />` du site (`config/deploy/apache-site.conf`) ; vider `MAILER_DIVERT_TO` et `MAILER_ALLOWED_RECIPIENTS` ; le certificat du nom public (`make vm-certificate`, section 3), s'il n'est pas déjà fait ; `SYMFONY_TRUSTED_HOSTS` réduit au nom, fait le 26 novembre. Le nom est dans `DEFAULT_URI` et `SYMFONY_TRUSTED_HOSTS` depuis le 16 novembre (`dev/plans/2026-10-26_deployment.md`, Suivi) : `vm-env` ne réécrit pas un `.env` existant, ces deux lignes se changent à la main, points échappés dans `SYMFONY_TRUSTED_HOSTS` (`'^(nom\.public|adresse\.ip)$'`), puis la file se relance ; le flux vers Theia/OZCAR ; le reste dans `dev/plans/2026-10-26_deployment.md`.

Le compteur des consultations a compté les essais : le vider, pour que le compte public parte de la réouverture (la page d'une chronique dit "depuis" la première date du compteur) :

```
sudo runuser -u postgres -- psql -d bdoh_prod -c 'TRUNCATE bdoh.series_views'
```
