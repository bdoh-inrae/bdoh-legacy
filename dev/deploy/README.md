# Installer BDOH sur la VM

Les étapes, à lire et à lancer une par une sur la VM, en root. **Rien de ceci n'a encore été essayé** (choix de Lou, 2026-11-05 : pas d'essai sur le poste) : à corriger ici pendant la première installation, en notant le temps de chaque étape. Le pourquoi de chaque choix est dans [dev/plans/2026-10-26_deployment.md](../plans/2026-10-26_deployment.md).

Aucune de ces étapes ne supprime une base ni un serveur. Une erreur en route s'arrête et se lit : ne pas relancer à l'aveugle.

## 1. Sur le poste : l'archive de la version

La VM n'a ainsi besoin ni de Composer, ni de Node, ni d'accès à Packagist, npm ou GitHub.

```
version=$(git describe --tags --always)
work=$(realpath -m ../bdoh_reference/releases/build-$version)
mkdir -p "$work" && git archive HEAD | tar -x -C "$work"
docker run --rm --user "$(id -u):$(id -g)" -e HOME=/tmp -e APP_ENV=prod -v "$work":/var/www/bdoh bdoh-app sh -c '
    cp .env.example .env
    composer install --no-dev --no-scripts --no-interaction --classmap-authoritative
    mkdir -p public/assets public/css/themes public/uploads
    php bin/console assets:install public --no-debug
    npm ci --no-audit --no-fund
    NODE_OPTIONS=--openssl-legacy-provider node_modules/.bin/encore production
    rm -rf node_modules .env var'
tar -C "$work" -czf ../bdoh_reference/releases/bdoh-legacy-$version.tar.gz .
```

Lou copie sur la VM, dans `/root/` : cette archive, les deux dumps bruts, et `uploads/bdoh-uploads-prod.tar.gz`.

## 2. La machine, une fois

```
apt-get update
apt-get install --no-install-recommends \
    apache2 apache2-utils libapache2-mod-php8.4 php8.4-cli php8.4-opcache \
    php8.4-apcu php8.4-curl php8.4-gd php8.4-intl php8.4-mbstring php8.4-pgsql php8.4-xml php8.4-zip \
    postgresql-17 postgresql-17-postgis-3 postgis locales openssl ssl-cert rsync unattended-upgrades

sed -i 's/^# en_US.UTF-8/en_US.UTF-8/; s/^# fr_FR.UTF-8/fr_FR.UTF-8/' /etc/locale.gen && locale-gen
systemctl restart postgresql
```

À regarder :

- `findmnt /tmp` : si c'est un `tmpfs`, `systemctl mask tmp.mount`, puis redémarrer la VM. Les imports et les exports y écrivent.
- `open-vm-tools`, si l'infra l'exige.
- `dpkg-reconfigure -plow unattended-upgrades` : répondre oui.

Réglages de PostgreSQL, à adapter à la mémoire de la VM (16 Go ici), dans `/etc/postgresql/17/main/conf.d/bdoh.conf`, puis `systemctl restart postgresql` :

```
shared_buffers = 4GB
effective_cache_size = 8GB
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

```
NOM=prod PORT=443 ADRESSE=… DUMP=/root/….dump ARCHIVE=/root/bdoh-legacy-….tar.gz
DIR=/var/www/bdoh-$NOM ROLE=bdoh_$NOM BASE=bdoh_$NOM

mkdir -p $DIR && tar -xzf $ARCHIVE -C $DIR
mkdir -p $DIR/var $DIR/public/uploads $DIR/public/css/themes
chown -R root:www-data $DIR && chmod -R g-w,o-rwx $DIR
chown -R www-data:www-data $DIR/var $DIR/public/uploads $DIR/public/css/themes
```

La production seulement, ses logos et images, et le fichier d'Oracle (`dev/plans/2026-10-26_deployment.md`) :

```
tar -xzf /root/bdoh-uploads-prod.tar.gz --strip-components=1 -C $DIR/public web/uploads
chown -R www-data:www-data $DIR/public/uploads
```

Le `.env`, à partir de `.env.example`, lisible par le serveur web seul (`chown root:www-data`, `chmod 640`) :

```
APP_ENV=prod
APP_SECRET=                     # openssl rand -hex 32, propre à l'instance
DATABASE_URL="postgresql://bdoh_NOM:MOT_DE_PASSE@127.0.0.1:5432/bdoh_NOM?serverVersion=17&charset=utf8"
DEFAULT_URI=https://ADRESSE:PORT
SYMFONY_TRUSTED_HOSTS='^ADRESSE$'    # les points de l'adresse écrits \.
THEIA_PASSWORD_SECRET_KEY=      # openssl rand -hex 32, propre à l'instance
MAILER_DSN=smtp://…             # le relais de l'infra
MAILER_DIVERT_TO=               # l'adresse de Lou, pendant les essais
MAILER_ALLOWED_RECIPIENTS=      # la ligne de allowed-recipients.env (dev/test-kit/README.md)
```

La base. Le rôle de l'instance n'est pas superuser, et son nom ne le mène pas seul au schéma `bdoh` :

```
MOT_DE_PASSE=$(openssl rand -hex 24)     # le même que dans le .env
runuser -u postgres -- psql -c "CREATE ROLE $ROLE LOGIN PASSWORD '$MOT_DE_PASSE'"
runuser -u postgres -- psql -c "ALTER ROLE $ROLE SET search_path = bdoh, public"
runuser -u postgres -- createdb -T template0 -E UTF8 --locale=en_US.UTF-8 $BASE
runuser -u postgres -- psql -c "REVOKE CONNECT ON DATABASE $BASE FROM PUBLIC"

install -o postgres -m 600 $DUMP /var/lib/postgresql/restore.dump
runuser -u postgres -- pg_restore -d $BASE --no-owner --no-privileges -j 4 /var/lib/postgresql/restore.dump
rm /var/lib/postgresql/restore.dump

runuser -u postgres -- psql -c "ALTER DATABASE $BASE OWNER TO $ROLE"
sed "s/OWNER TO bdoh/OWNER TO $ROLE/" $DIR/dev/reference/own-by-bdoh.sql | runuser -u postgres -- psql -d $BASE -v ON_ERROR_STOP=1 -q
runuser -u postgres -- psql -d $BASE -c 'VACUUM ANALYZE'
```

Les contrôles, les migrations, le cache. Les comptes doivent rester les mêmes avant et après, sauf la ligne « séquences en retard », qui passe à 0 :

```
cd $DIR
runuser -u postgres -- psql -d $BASE -At -F '|' -f $DIR/dev/reference/inspect.sql > /root/inspect-$NOM-avant.txt
runuser -u www-data -- php bin/console doctrine:migrations:migrate --env=prod
runuser -u www-data -- php bin/console cache:clear --env=prod
runuser -u postgres -- psql -d $BASE -At -F '|' -f $DIR/dev/reference/inspect.sql > /root/inspect-$NOM-apres.txt
diff /root/inspect-$NOM-avant.txt /root/inspect-$NOM-apres.txt
```

OTHU seulement : ses trois fonctions SQL, à aligner sur la production après comparaison (`ROADMAP.md`).

## 4. Une instance : le site

`/etc/apache2/sites-available/bdoh-NOM.conf`. Debian écoute déjà sur 443 une fois `ssl` activé ; pour une autre instance, ajouter `Listen 8443 https` à `/etc/apache2/ports.conf`.

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
Requires=postgresql@17-main.service
After=postgresql@17-main.service

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
WantedBy=multi-user.target
```

`systemctl enable --now bdoh-job-worker@$NOM`. Les tâches restées « en cours » dans le dump passent alors en « incomplètes », relançables.

## 6. Une instance : les sauvegardes

`/var/backups/bdoh/`, à l'emplacement que l'infra sauvegarde hors de la VM (`install -d -m 750 -o postgres -g postgres`).

`/etc/systemd/system/bdoh-backup@.service` :

```
[Unit]
Description=Sauvegarde de la base de BDOH, %i
After=postgresql@17-main.service

[Service]
Type=oneshot
User=postgres
ExecStart=/bin/sh -c 'pg_dump -Fc -f /var/backups/bdoh/%i-$(date +%%F).dump bdoh_%i && find /var/backups/bdoh -name "%i-*.dump" -mtime +7 -delete'
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

`systemctl enable --now bdoh-backup@$NOM.timer`. Une sauvegarde ne vaut qu'une fois restaurée : en restaurer une dans une base à part, une fois.

## 7. Vérifier

- L'accueil de chaque instance répond, derrière le mot de passe d'accès ; les logos sont là.
- Un lien de connexion arrive sur une adresse INRAE et sur une adresse extérieure, et le bouton connecte. Une messagerie qui ouvre les liens pour les analyser ne doit pas le consommer.
- Un courriel vers une adresse hors de la liste arrive à `MAILER_DIVERT_TO`, et pas à son destinataire.
- Un export, un import, et un calcul qui se relance : `systemctl status bdoh-job-worker@$NOM`, et la page des tâches.
- Arrêter PostgreSQL 30 s, le relancer : la file repart seule.
- Un import en plusieurs étapes, en redémarrant Apache entre deux : le service Apache de Debian a `PrivateTmp=true`, à voir ce que ça donne.

## Installer une nouvelle version

```
mkdir /root/version && tar -xzf /root/bdoh-legacy-….tar.gz -C /root/version
systemctl stop bdoh-job-worker@$NOM
rsync -a --delete --exclude=.env --exclude=/var --exclude=/public/uploads --exclude=/public/css/themes /root/version/ $DIR/
chown -R root:www-data $DIR && chmod -R g-w,o-rwx $DIR
chown -R www-data:www-data $DIR/var $DIR/public/uploads $DIR/public/css/themes
cd $DIR && runuser -u www-data -- php bin/console doctrine:migrations:migrate --env=prod
runuser -u www-data -- php bin/console cache:clear --env=prod
systemctl reload apache2 && systemctl start bdoh-job-worker@$NOM
rm -rf /root/version
```

## À l'ouverture au public

Retirer le bloc `<Location />` ; vider `MAILER_DIVERT_TO` et `MAILER_ALLOWED_RECIPIENTS` ; le nom public, son certificat et `SYMFONY_TRUSTED_HOSTS` ; le flux vers Theia/OZCAR ; le reste dans `dev/plans/2026-10-26_deployment.md`.
