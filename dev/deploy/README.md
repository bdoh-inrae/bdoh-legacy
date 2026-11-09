# Installer BDOH sur la VM

Les étapes, à lire et à lancer une par une sur la VM, en root. **Rien de ceci n'a encore été essayé** (choix de Lou, 2026-11-05 : pas d'essai sur le poste) : à corriger ici pendant la première installation, en notant le temps de chaque étape. Le pourquoi de chaque choix est dans [dev/plans/2026-10-26_deployment.md](../plans/2026-10-26_deployment.md).

**Écrit pour Ubuntu 26.04**, le système de la VM depuis le 9 novembre (section 0) : PHP 8.5, PostgreSQL 18, PostGIS 3.6. La liste des paquets de la section 2, les chemins et les noms des services ont été essayés le même jour dans l'image `ubuntu:26.04`, pas encore sur la VM.

Aucune de ces étapes ne supprime une base ni un serveur. Une erreur en route s'arrête et se lit : ne pas relancer à l'aveugle.

Chaque session root commence par `set -u` : une variable oubliée (une nouvelle session, un `NOM` changé sans le reste) arrête alors la commande au lieu de devenir un chemin vide. Les commandes qui effacent ou changent des droits en masse écrivent en plus `"${DIR:?}"`, que bash refuse si la variable est vide. Relu par un second Claude le 5 novembre : ce texte en tient compte.

## 0. La VM livrée : le disque et le système

La VM est livrée sous Ubuntu 22.04, avec un disque de 200 Go dont 19 seulement sont attribués, en LVM (groupe `vg1`) : `/` n'a plus que 1 Go libre, et `/var`, qui portera les bases, les exports et les sauvegardes, fait 3,7 Go (relevé et calcul des tailles : `dev/plans/2026-10-26_deployment.md`, Suivi du 9 novembre). Les volumes s'agrandissent d'abord, sans arrêter la machine ; le reste du groupe sert de réserve. Les tailles sont absolues : une ligne relancée ne grandit pas deux fois.

Avec le compte d'administration de la VM. Si `vgs` montre moins de 160 Go dans la colonne `VFree`, s'arrêter là :

```
sudo vgs
sudo lvextend -r -L 15G  /dev/vg1/root
sudo lvextend -r -L 140G /dev/vg1/var
sudo lvextend -r -L 8G   /dev/vg1/tmp
sudo lvextend -r -L 5G   /dev/vg1/varlog
df -h / /var /var/log /tmp /boot
```

Puis le système, une version LTS à la fois, jusqu'à Ubuntu 26.04, en redémarrant après chacune :

```
sudo apt update && sudo apt full-upgrade && sudo apt autoremove --purge
sudo apt install usrmerge                     # si ls -ld /bin montre un dossier, et non un lien vers usr/bin
sudo reboot
sudo do-release-upgrade                       # vers 24.04
sudo apt update && sudo apt full-upgrade
sudo do-release-upgrade                       # vers 26.04
cat /etc/os-release
```

Ubuntu 24.04 refuse un système dont `/usr` n'est pas fusionné (`/bin`, `/sbin`, `/lib` en dossiers et non en liens), ce qu'a la VM livrée : `usrmerge` le convertit. Les questions de `do-release-upgrade` sont en français sur la VM : « [oN] » attend `o`, et tout autre lettre, `y` compris, vaut non. Aux questions : garder la version installée d'un fichier de configuration (réponse par défaut), surtout pour ssh, que l'infra a réglé ; redémarrer les services sans demander ; retirer les paquets obsolètes. Si la connexion tombe en route, ne rien relancer à l'aveugle : se reconnecter et lire ce que l'outil a écrit.

## 1. Sur le poste : l'archive de la version

La VM n'a ainsi besoin ni de Composer, ni de Node, ni d'accès à Packagist, npm ou GitHub.

```
version=$(git describe --tags --always)
work=$(realpath -m ../bdoh_reference/releases/build-$version)
mkdir -p "$work" && git archive HEAD | tar -x -C "$work"
docker run --rm --user "$(id -u):$(id -g)" -e HOME=/tmp -e APP_ENV=prod -v "$work":/var/www/bdoh bdoh-app sh -ec '
    cp .env.example .env
    composer install --no-dev --no-scripts --no-interaction --classmap-authoritative
    mkdir -p public/assets public/css/themes public/uploads
    php bin/console assets:install public --no-debug
    npm ci --no-audit --no-fund
    NODE_OPTIONS=--openssl-legacy-provider node_modules/.bin/encore production
    test -f vendor/autoload.php && test -n "$(ls public/assets)"
    find node_modules -mindepth 1 -maxdepth 1 ! -name bootstrap -exec rm -rf {} +
    rm -rf .env var' \
  && tar -C "$work" -czf ../bdoh_reference/releases/bdoh-legacy-$version.tar.gz .
```

`sh -e` et le `&&` : une étape qui échoue (npm ou GitHub injoignable) ne donne pas d'archive. `node_modules/bootstrap` reste : `CssManager` compile les thèmes des observatoires sur la VM, à la première vue, avec le LESS de Bootstrap (`src/Irstea/BdohBundle/Resources/config/services.yml`).

Lou copie sur la VM, dans `/var/backups/bdoh-sources/` : cette archive, les deux dumps bruts, et `uploads/bdoh-uploads-prod.tar.gz`. Pas dans `/root/`, sur `/` (15 Go), ni dans le dossier personnel du compte, sur `/home` (1 Go) : les dumps bruts pèsent environ 4 Go. Le dossier se crée, avec le compte d'administration, avant la copie, et passe à root ensuite (les dumps bruts contiennent les comptes) :

```
sudo install -d -m 700 -o "$USER" /var/backups/bdoh-sources
sudo chown -R root:root /var/backups/bdoh-sources
```

## 2. La machine, une fois

```
apt-get update
apt-get install --no-install-recommends \
    apache2 apache2-utils libapache2-mod-php8.5 php8.5-cli \
    php8.5-apcu php8.5-curl php8.5-gd php8.5-intl php8.5-mbstring php8.5-pgsql php8.5-xml php8.5-zip \
    postgresql-18 postgresql-18-postgis-3 postgis locales openssl ssl-cert rsync unattended-upgrades

locale-gen en_US.UTF-8 fr_FR.UTF-8
systemctl restart postgresql
```

OPcache fait partie de PHP depuis la 8.5 : il n'a plus de paquet.

À regarder :

- `findmnt /tmp` : sur la VM, un volume ext4 de 8 Go (section 0), rien à faire. Si c'était un `tmpfs`, `systemctl mask tmp.mount`, puis redémarrer. Les imports et les exports y écrivent.
- `open-vm-tools`, si l'infra l'exige.
- `dpkg-reconfigure -plow unattended-upgrades` : répondre oui.

Réglages de PostgreSQL, à adapter à la mémoire de la VM (11 Gio visibles ici), dans `/etc/postgresql/18/main/conf.d/bdoh.conf`, puis `systemctl restart postgresql` :

```
shared_buffers = 3GB
effective_cache_size = 7GB
work_mem = 64MB
maintenance_work_mem = 512MB
```

Apache :

```
a2enmod ssl headers
a2dissite 000-default
```

Le certificat : celui de l'infra s'il y en a un, sinon celui, auto-signé, de `ssl-cert` (`/etc/ssl/certs/ssl-cert-snakeoil.pem`, avec sa clé dans `/etc/ssl/private/`).

Les services : créer `/etc/systemd/system/bdoh-job-worker@.service` (section 5) et ceux de la sauvegarde (section 6), puis `systemctl daemon-reload`.

## 3. Une instance : le code et la base

Pour la production, `NOM=prod PORT=443`, et pour OTHU, `NOM=othu PORT=8443`, avec `ADRESSE` l'adresse IP de la VM ou son nom, et `DUMP` le dump brut de l'instance.

Une seule ligne pour toutes les variables, à recopier entière à chaque session, et pour passer à l'autre instance :

```
set -u; NOM=prod PORT=443 ADRESSE=… DUMP=/var/backups/bdoh-sources/….dump ARCHIVE=/var/backups/bdoh-sources/bdoh-legacy-….tar.gz; DIR=/var/www/bdoh-$NOM ROLE=bdoh_$NOM BASE=bdoh_$NOM

mkdir -p "${DIR:?}" && tar -xzf $ARCHIVE -C "${DIR:?}"
mkdir -p $DIR/var $DIR/public/uploads $DIR/public/css/themes
chown -R root:www-data "${DIR:?}" && chmod -R g-w,o-rwx "${DIR:?}"
chown -R www-data:www-data $DIR/var $DIR/public/uploads $DIR/public/css/themes
```

La production seulement, ses logos et images, et le fichier d'Oracle (`dev/plans/2026-10-26_deployment.md`) :

```
tar -xzf /var/backups/bdoh-sources/bdoh-uploads-prod.tar.gz --strip-components=1 -C $DIR/public web/uploads
chown -R www-data:www-data $DIR/public/uploads
```

Le `.env`, à partir de `.env.example`, lisible par le serveur web seul (`chown root:www-data`, `chmod 640`) :

```
APP_ENV=prod
APP_SECRET=                     # openssl rand -hex 32, propre à l'instance
DATABASE_URL="postgresql://bdoh_NOM:MOT_DE_PASSE@127.0.0.1:5432/bdoh_NOM?serverVersion=18&charset=utf8"
DEFAULT_URI=https://ADRESSE:PORT
SYMFONY_TRUSTED_HOSTS='^ADRESSE$'    # les points de l'adresse écrits \.
THEIA_PASSWORD_SECRET_KEY=      # openssl rand -hex 32, propre à l'instance
MAILER_DSN=smtp://…             # le relais de l'infra
MAILER_DIVERT_TO=               # l'adresse de Lou, pendant les essais
MAILER_ALLOWED_RECIPIENTS=      # la ligne de allowed-recipients.env (dev/test-kit/README.md)
```

La base. Le rôle de l'instance n'est pas superuser, et son nom ne le mène pas seul au schéma `bdoh`. La restauration tient en une commande, qui ne va pas plus loin que `createdb` si la base existe déjà : restaurer dans une base vivante ajouterait des lignes et ramènerait ses séquences en arrière.

```
MOT_DE_PASSE=$(openssl rand -hex 24)     # le même que dans le .env
runuser -u postgres -- psql -c "CREATE ROLE $ROLE LOGIN PASSWORD '$MOT_DE_PASSE'"
runuser -u postgres -- psql -c "ALTER ROLE $ROLE SET search_path = bdoh, public"

runuser -u postgres -- createdb -T template0 -E UTF8 --locale=en_US.UTF-8 $BASE \
  && runuser -u postgres -- psql -c "REVOKE CONNECT ON DATABASE $BASE FROM PUBLIC" \
  && install -o postgres -m 600 $DUMP /var/lib/postgresql/restore.dump \
  && runuser -u postgres -- pg_restore -d $BASE --no-owner --no-privileges -j 4 /var/lib/postgresql/restore.dump
rm -f /var/lib/postgresql/restore.dump

runuser -u postgres -- psql -c "ALTER DATABASE $BASE OWNER TO $ROLE"
sed "s/OWNER TO bdoh/OWNER TO $ROLE/" $DIR/dev/reference/own-by-bdoh.sql | runuser -u postgres -- psql -d $BASE -v ON_ERROR_STOP=1 -q
runuser -u postgres -- psql -d $BASE -c 'VACUUM ANALYZE'
```

Les contrôles, les migrations, le cache. Les comptes d'`inspect.sql` doivent rester les mêmes avant et après, sauf la ligne « séquences en retard », qui passe à 0 ; l'empreinte (`fingerprint.sql`, quelques minutes) ne doit changer que sur les tables que visent les migrations. Le fichier SQL est lu par root (`<`) : `postgres` n'a pas le droit d'entrer dans `$DIR`. `controle avant` et `controle apres` s'arrêtent sur une sortie vide, qui passerait sinon pour « rien n'a changé » :

```
controle() { cd /root && for f in inspect fingerprint; do
    runuser -u postgres -- psql -d $BASE -v ON_ERROR_STOP=1 -qAt -F '|' < $DIR/dev/reference/$f.sql > /root/$f-$NOM-$1.txt \
      && test -s /root/$f-$NOM-$1.txt || { echo "contrôle $f vide ou en erreur"; return 1; }; done; }

controle avant \
  && cd $DIR && runuser -u www-data -- php bin/console doctrine:migrations:migrate --env=prod --no-interaction \
  && runuser -u www-data -- php bin/console cache:clear --env=prod \
  && controle apres
diff /root/inspect-$NOM-avant.txt /root/inspect-$NOM-apres.txt
diff /root/fingerprint-$NOM-avant.txt /root/fingerprint-$NOM-apres.txt | cut -d'|' -f1-2 | sort -u
```

Les courriels, avant que rien ne puisse en envoyer (l'exécuteur, en section 5, enverra ceux des tâches restées en attente dans le dump). `MAILER_DIVERT_TO` doit être rempli : sans lui, `MAILER_ALLOWED_RECIPIENTS` est ignoré, et tout part aux vrais destinataires (`vendor/symfony/mailer/EventListener/EnvelopeListener.php`). Ce courriel doit arriver à Lou, et à personne d'autre :

```
cd $DIR && runuser -u www-data -- php bin/console mailer:test --env=prod quelquun@example.org
```

OTHU seulement : ses trois fonctions SQL, à aligner sur la production après comparaison (`ROADMAP.md`).

## 4. Une instance : le site

`/etc/apache2/sites-available/bdoh-NOM.conf`. Apache écoute déjà sur 443 une fois `ssl` activé ; pour une autre instance, ajouter `Listen 8443 https` à `/etc/apache2/ports.conf`.

```
<VirtualHost *:PORT>
    DocumentRoot /var/www/bdoh-NOM/public

    SSLEngine on
    SSLCertificateFile /etc/ssl/certs/ssl-cert-snakeoil.pem
    SSLCertificateKeyFile /etc/ssl/private/ssl-cert-snakeoil.key

    # Un cookie ne dépend pas du port : chaque instance a le sien
    php_admin_value session.name BDOHNOM

    Header always set Strict-Transport-Security "max-age=31536000"
    Header always set X-Content-Type-Options nosniff
    Header always set Referrer-Policy strict-origin-when-cross-origin

    <Directory /var/www/bdoh-NOM/public>
        AllowOverride None
        Require all granted
        FallbackResource /index.php
    </Directory>

    # Le mot de passe d'accès des essais, sur tout le site : à retirer à l'ouverture au public
    <Location />
        AuthType Basic
        AuthName "BDOH, essais"
        AuthUserFile /etc/apache2/bdoh-NOM.htpasswd
        Require valid-user
    </Location>

    ErrorLog ${APACHE_LOG_DIR}/bdoh-NOM-error.log
    CustomLog ${APACHE_LOG_DIR}/bdoh-NOM-access.log combined
</VirtualHost>
```

```
htpasswd -c /etc/apache2/bdoh-$NOM.htpasswd essais
chgrp www-data /etc/apache2/bdoh-$NOM.htpasswd && chmod 640 /etc/apache2/bdoh-$NOM.htpasswd
a2ensite bdoh-$NOM && apache2ctl configtest && systemctl reload apache2
```

## 5. Une instance : la file de tâches

`/etc/systemd/system/bdoh-job-worker@.service`, pour toutes les instances. Ne jamais lancer l'exécuteur hors de ce service : tué, il laisserait sa tâche tourner et libérerait la file.

```
[Unit]
Description=Exécuteur des tâches de BDOH, %i
Requires=postgresql@18-main.service
After=postgresql@18-main.service

[Service]
Type=simple
WorkingDirectory=/var/www/bdoh-%i
ExecStart=/usr/bin/php bin/console bdoh:jobs:run --max-concurrent-jobs=2 --max-runtime=3600 --env=prod --no-interaction
Nice=10
Restart=always
RestartSec=30
User=www-data
KillMode=mixed
TimeoutStopSec=5min

[Install]
WantedBy=multi-user.target postgresql@18-main.service
```

`Requires=` arrête l'exécuteur avant PostgreSQL, proprement ; mais une mise à jour de `postgresql-18` arrête puis redémarre PostgreSQL, et rien ne relancerait l'exécuteur (`Restart=` ne joue pas après un arrêt demandé). Le `WantedBy=` sur PostgreSQL le redémarre avec lui.

`systemctl enable --now bdoh-job-worker@$NOM`. Les tâches restées « en cours » dans le dump passent alors en « incomplètes », relançables.

## 6. Une instance : les sauvegardes

Deux dossiers, à l'emplacement que l'infra sauvegarde hors de la VM : `/var/backups/bdoh/` pour les dumps (`install -d -m 750 -o postgres -g postgres`), et `/var/backups/bdoh-files/` pour le `.env` et les fichiers déposés, que le dump ne contient pas (`install -d -m 700`). Sans le `.env`, sa clé `THEIA_PASSWORD_SECRET_KEY` est perdue, et les mots de passe Theia d'une base restaurée ne se lisent plus.

`/etc/systemd/system/bdoh-backup@.service` (en root, pour lire le `.env`) :

```
[Unit]
Description=Sauvegarde de BDOH, %i
After=postgresql@18-main.service

[Service]
Type=oneshot
ExecStart=/bin/sh -ec 'd=$(date +%%F); runuser -u postgres -- pg_dump -Fc -f /var/backups/bdoh/%i-$d.dump bdoh_%i; tar -czf /var/backups/bdoh-files/%i-$d.tar.gz -C /var/www/bdoh-%i .env public/uploads; find /var/backups/bdoh /var/backups/bdoh-files -name "%i-*" -mtime +7 -delete'
```

`/etc/systemd/system/bdoh-backup@.timer` :

```
[Unit]
Description=Sauvegarde quotidienne de la base de BDOH, %i

[Timer]
OnCalendar=*-*-* 02:00:00
RandomizedDelaySec=30min
Persistent=true

[Install]
WantedBy=timers.target
```

`systemctl enable --now bdoh-backup@$NOM.timer`. Une sauvegarde qui échoue ne prévient personne : `systemctl --failed` la montre, à regarder chaque jour la première semaine (`dev/plans/2026-10-26_deployment.md`, « Après »).

Une sauvegarde ne vaut qu'une fois restaurée. L'essayer une fois, dans une base à part (`bdoh_essai`), où `inspect.sql` doit donner les mêmes comptes que dans la base vivante ; c'est la seule base qu'on supprime ensuite (`dropdb bdoh_essai`) :

```
runuser -u postgres -- createdb -T template0 -E UTF8 --locale=en_US.UTF-8 bdoh_essai \
  && runuser -u postgres -- pg_restore -d bdoh_essai --no-owner --no-privileges /var/backups/bdoh/$NOM-AAAA-MM-JJ.dump
```

Une vraie restauration, sur une VM neuve, reprend la section 3 avec ce dump à la place du dump brut (le rôle, son `search_path` et les propriétaires ne sont pas dans le dump), et le `.env` et `public/uploads/` de `/var/backups/bdoh-files/`.

## 7. Vérifier

- L'accueil de chaque instance répond, derrière le mot de passe d'accès ; les logos sont là.
- Un lien de connexion arrive sur une adresse INRAE et sur une adresse extérieure, et le bouton connecte. Une messagerie qui ouvre les liens pour les analyser ne doit pas le consommer.
- Un courriel vers une adresse hors de la liste arrive à `MAILER_DIVERT_TO`, et pas à son destinataire.
- Un export, un import, et un calcul qui se relance : `systemctl status bdoh-job-worker@$NOM`, et la page des tâches.
- Arrêter PostgreSQL 30 s, le relancer : la file repart seule.
- Un import en plusieurs étapes, en redémarrant Apache entre deux : le service Apache d'Ubuntu a `PrivateTmp=true`, à voir ce que ça donne.

## Installer une nouvelle version

Instance par instance. Une sauvegarde juste avant les migrations, sous un nom à part (celle de la nuit garderait le retour arrière au matin, et la suivante du même jour l'écraserait), puis les mêmes contrôles qu'à l'installation, avec la fonction `controle` de la section 3. Les thèmes compilés ne sont pas gardés : ils se recompilent avec la nouvelle version.

```
set -u; NOM=prod ARCHIVE=/var/backups/bdoh-sources/bdoh-legacy-….tar.gz; DIR=/var/www/bdoh-$NOM BASE=bdoh_$NOM

mkdir /root/version && tar -xzf $ARCHIVE -C /root/version
systemctl stop bdoh-job-worker@$NOM
runuser -u postgres -- pg_dump -Fc -f /var/backups/bdoh/$NOM-avant-$(date +%F-%H%M).dump $BASE && controle avant
rsync -a --delete --exclude=/.env --exclude=/var --exclude=/public/uploads /root/version/ "${DIR:?}/"
chown -R root:www-data "${DIR:?}" && chmod -R g-w,o-rwx "${DIR:?}"
chown -R www-data:www-data $DIR/var $DIR/public/uploads $DIR/public/css/themes
cd $DIR && runuser -u www-data -- php bin/console doctrine:migrations:migrate --env=prod --no-interaction \
  && runuser -u www-data -- php bin/console cache:clear --env=prod \
  && controle apres
diff /root/inspect-$NOM-avant.txt /root/inspect-$NOM-apres.txt
systemctl reload apache2 && systemctl start bdoh-job-worker@$NOM
rm -rf /root/version
```

## À l'ouverture au public

Retirer le bloc `<Location />` ; vider `MAILER_DIVERT_TO` et `MAILER_ALLOWED_RECIPIENTS` ; le nom public, son certificat et `SYMFONY_TRUSTED_HOSTS` ; le flux vers Theia/OZCAR ; le reste dans `dev/plans/2026-10-26_deployment.md`.
