#!/usr/bin/env bash
# Installe et met à jour BDOH sur la VM (Ubuntu 26.04, sans Docker). Appelé par les cibles vm-… du
# Makefile, en root, dans le dossier d'une instance : /var/www/bdoh-prod ou /var/www/bdoh-othu, chacun un
# clone du dépôt. L'ordre des étapes et ce qu'il faut regarder entre deux : dev/deploy/README.md.
# Chaque étape s'arrête à la première erreur. Aucune ne supprime une base, ni ne restaure dans une base
# qui existe déjà.
#
# Tout est dans des fonctions, appelées par la dernière ligne : bash a ainsi lu le script entier avant
# de l'exécuter, et une mise à jour (git checkout) peut le remplacer en cours de route.
set -euo pipefail

sources=/var/backups/bdoh-sources   # les dumps bruts et les fichiers déposés, copiés par Lou
backups=/var/backups/bdoh           # les sauvegardes des bases (bdoh-backup@.timer, mises à jour)

die() { echo "$*" >&2; exit 1; }
pg() { runuser -u postgres -- psql -v ON_ERROR_STOP=1 "$@"; }
console() { runuser -u www-data -- php bin/console --env=prod --no-interaction "$@"; }

instance() {
    case ${NOM:-} in
        prod) port=443 ;;
        othu) port=8443 ;;
        *) die "Préciser l'instance : NOM=prod ou NOM=othu" ;;
    esac
    [[ $PWD == /var/www/bdoh-$NOM ]] || die "À lancer dans /var/www/bdoh-$NOM"
    base=bdoh_$NOM role=bdoh_$NOM
}

# Les comptes d'inspect.sql et l'empreinte au bit près d'une base, lus par root (postgres n'entre pas
# dans le dossier). Une sortie vide s'arrête : elle passerait sinon pour « rien n'a changé ».
controle() {
    local f
    for f in inspect fingerprint; do
        pg -d "$base" -qAt -F '|' < "dev/reference/$f.sql" > "/root/$f-$NOM-$1.txt"
        [[ -s /root/$f-$NOM-$1.txt ]] || die "Contrôle $f vide ou en erreur"
    done
}

# Ce qu'ont changé les migrations. inspect.sql : seulement la ligne « séquences en retard ». L'empreinte
# ne lit que les quatre tables de mesures, qu'aucune migration ne touche : aucune chronique attendue.
changes() {
    local changed
    echo "--- inspect.sql, avant et après :"
    diff "/root/inspect-$NOM-avant.txt" "/root/inspect-$NOM-apres.txt" || true
    echo "--- empreinte des mesures, chroniques changées (attendu : aucune) :"
    changed=$(diff "/root/fingerprint-$NOM-avant.txt" "/root/fingerprint-$NOM-apres.txt" \
        | sed -n 's/^[<>] //p' | cut -d'|' -f1-2 | sort -u || true)
    echo "${changed:-aucune}"
}

cmd_deps() {
    [[ -f config/deploy/postgresql.conf ]] || die "À lancer dans un clone du dépôt"
    apt-get update
    DEBIAN_FRONTEND=noninteractive apt-get install -y --no-install-recommends \
        apache2 apache2-utils libapache2-mod-php8.5 php8.5-cli \
        php8.5-apcu php8.5-curl php8.5-gd php8.5-intl php8.5-mbstring php8.5-pgsql php8.5-xml php8.5-zip \
        postgresql-18 postgresql-18-postgis-3 postgis locales openssl ssl-cert rsync unattended-upgrades \
        git composer nodejs npm unzip
    locale-gen en_US.UTF-8 fr_FR.UTF-8
    printf 'APT::Periodic::Update-Package-Lists "1";\nAPT::Periodic::Unattended-Upgrade "1";\n' \
        > /etc/apt/apt.conf.d/20auto-upgrades
    install -m 644 config/deploy/postgresql.conf /etc/postgresql/18/main/conf.d/bdoh.conf
    systemctl restart postgresql
    a2enmod -q ssl headers
    a2dissite -q 000-default || true
    grep -q '^Listen 8443' /etc/apache2/ports.conf || echo 'Listen 8443 https' >> /etc/apache2/ports.conf
    systemctl restart apache2
    install -m 644 config/deploy/bdoh-job-worker@.service config/deploy/bdoh-backup@.service \
        config/deploy/bdoh-backup@.timer /etc/systemd/system/
    systemctl daemon-reload
    install -d -m 750 -o postgres -g postgres "$backups"
    install -d -m 700 /var/backups/bdoh-files
    # Le compte d'administration y copie les dumps bruts ; ils contiennent les comptes : lui et root seuls
    install -d -m 700 -o "${SUDO_USER:-root}" "$sources"
    echo "Prêt. /tmp : $(findmnt -no FSTYPE,SIZE /tmp) (un tmpfs serait à éviter : dev/deploy/README.md)"
}

cmd_env() {
    instance
    [[ -n ${ADRESSE:-} ]] || die "Préciser ADRESSE= : l'adresse IP de la VM, ou son nom"
    [[ ! -e .env ]] || die ".env existe déjà : rien n'est changé"
    local password hosts
    password=$(openssl rand -hex 24)
    hosts=$(printf '%s' "$ADRESSE" | sed 's/\./\\./g')
    pg -qc "DO \$\$ BEGIN CREATE ROLE $role LOGIN; EXCEPTION WHEN duplicate_object THEN NULL; END \$\$"
    pg -qc "ALTER ROLE $role PASSWORD '$password'"
    pg -qc "ALTER ROLE $role SET search_path = bdoh, public"
    (umask 027; cat > .env <<EOF
APP_ENV=prod
APP_SECRET=$(openssl rand -hex 32)
DATABASE_URL="postgresql://$role:$password@127.0.0.1:5432/$base?serverVersion=18&charset=utf8"
DEFAULT_URI=https://$ADRESSE:$port
SYMFONY_TRUSTED_HOSTS='^$hosts\$'
THEIA_PASSWORD_SECRET_KEY=$(openssl rand -hex 32)
# Le relais de courrier de l'infra, par exemple smtp://hote:25 ; null://null n'envoie rien.
MAILER_DSN=null://null
# Pendant les essais, tant que le site n'est pas ouvert : tout courriel part à MAILER_DIVERT_TO, sauf vers
# les adresses de MAILER_ALLOWED_RECIPIENTS (la ligne de allowed-recipients.env, dev/test-kit/README.md).
MAILER_DIVERT_TO=
MAILER_ALLOWED_RECIPIENTS=
EOF
    )
    chgrp www-data .env
    echo ".env écrit. À compléter avant de lancer la file : MAILER_DSN, MAILER_DIVERT_TO, MAILER_ALLOWED_RECIPIENTS."
}

# Les bibliothèques et le JavaScript, en root : le code appartient à root, et le serveur web n'écrit que
# dans var/, public/uploads/ et public/css/themes/ (thèmes compilés par CssManager avec node_modules/bootstrap).
build() {
    [[ -f .env ]] || die "D'abord : make vm-env"
    export COMPOSER_ALLOW_SUPERUSER=1 HOME=/root
    composer install --no-dev --no-scripts --no-interaction --classmap-authoritative
    npm ci --no-audit --no-fund
    NODE_OPTIONS=--openssl-legacy-provider node_modules/.bin/encore production
    mkdir -p var public/uploads public/css/themes
    php bin/console assets:install public --env=prod --no-debug
    chown -R root:www-data . && chmod -R g-w,o-rwx .
    chown -R www-data:www-data var public/uploads public/css/themes
    console cache:clear
}

cmd_build() {
    instance
    build
    echo "Construit : $(git describe --tags --always)"
}

cmd_db() {
    instance
    [[ -f ${DUMP:-} ]] || die "Préciser DUMP= : le dump brut de l'instance, dans $sources"
    [[ -f .env ]] || die "D'abord : make vm-env"
    [[ -z $(pg -Atc "SELECT 1 FROM pg_database WHERE datname = '$base'") ]] \
        || die "La base $base existe déjà : rien n'est restauré"
    runuser -u postgres -- createdb -T template0 -E UTF8 --locale=en_US.UTF-8 "$base"
    pg -qc "REVOKE CONNECT ON DATABASE $base FROM PUBLIC"
    install -o postgres -m 600 "$DUMP" /var/lib/postgresql/restore.dump
    trap 'rm -f /var/lib/postgresql/restore.dump' EXIT
    echo "Restauration de $(basename "$DUMP") dans $base…"
    runuser -u postgres -- pg_restore -d "$base" --no-owner --no-privileges -j 4 /var/lib/postgresql/restore.dump
    pg -qc "ALTER DATABASE $base OWNER TO $role"
    sed "s/OWNER TO bdoh/OWNER TO $role/" dev/reference/own-by-bdoh.sql | pg -d "$base" -q
    pg -d "$base" -qc 'VACUUM ANALYZE'
    controle avant
    console doctrine:migrations:migrate
    console cache:clear
    controle apres
    changes
    # Les logos et images de la production (OTHU n'en a pas)
    if [[ $NOM == prod && -f $sources/bdoh-uploads-prod.tar.gz ]]; then
        tar -xzf "$sources/bdoh-uploads-prod.tar.gz" --strip-components=1 -C public web/uploads
        chown -R www-data:www-data public/uploads
        echo "Logos et images déballés : $(find public/uploads -type f | wc -l) fichiers."
    elif [[ $NOM == prod ]]; then
        echo "Pas de $sources/bdoh-uploads-prod.tar.gz : logos et images à déballer à part (dev/deploy/README.md)."
    fi
    echo "Base $base prête."
}

cmd_apache() {
    instance
    sed -e "s/@NOM@/$NOM/g" -e "s/@PORT@/$port/g" config/deploy/apache-site.conf \
        > "/etc/apache2/sites-available/bdoh-$NOM.conf"
    local access=/etc/apache2/bdoh-$NOM.htpasswd
    [[ -f $access ]] || { echo "Mot de passe d'accès des essais, compte « essais » :"; htpasswd -c "$access" essais; }
    chgrp www-data "$access" && chmod 640 "$access"
    a2ensite -q "bdoh-$NOM"
    apache2ctl configtest
    systemctl reload apache2
    echo "Site en ligne sur le port $port, derrière le mot de passe d'accès."
}

# Avant la file de tâches, qui enverra les courriels des tâches restées en attente dans le dump : ce
# courriel doit arriver à MAILER_DIVERT_TO, et à personne d'autre.
cmd_mail_test() {
    instance
    console mailer:test "${TO:-quelquun@example.org}"
}

cmd_worker() {
    instance
    grep -q '^MAILER_DIVERT_TO=.' .env \
        || die "MAILER_DIVERT_TO est vide dans .env : les courriels partiraient aux vrais destinataires"
    systemctl enable --now "bdoh-job-worker@$NOM"
    systemctl --no-pager status "bdoh-job-worker@$NOM" | head -5
}

cmd_backup() {
    instance
    systemctl enable --now "bdoh-backup@$NOM.timer"
    systemctl --no-pager list-timers "bdoh-backup@$NOM.timer"
}

# Une nouvelle version : un tag, jamais main. File arrêtée et base sauvegardée sous un nom à part avant
# tout changement ; pour revenir en arrière, la version précédente par la même commande, et ce dump.
cmd_update() {
    instance
    [[ -n ${V:-} ]] || die "Préciser V= : la version à installer, par exemple V=v0.3.2"
    git fetch --tags --quiet
    git rev-parse -q --verify "refs/tags/$V" > /dev/null || die "Version inconnue : $V"
    local worker=bdoh-job-worker@$NOM was_active=no
    systemctl is-active -q "$worker" && was_active=yes
    systemctl stop "$worker"
    runuser -u postgres -- pg_dump -Fc -f "$backups/$NOM-avant-$V-$(date +%F-%H%M).dump" "$base"
    controle avant
    git -c advice.detachedHead=false checkout --quiet "$V"
    build
    console doctrine:migrations:migrate
    console cache:clear
    controle apres
    changes
    systemctl reload apache2
    [[ $was_active == yes ]] && systemctl start "$worker"
    echo "Installée : $(git describe --tags --always)"
}

cmd_status() {
    instance
    echo "Version : $(git describe --tags --always)"
    systemctl is-active postgresql@18-main apache2 "bdoh-job-worker@$NOM" || true
    curl -sk -o /dev/null -w "Accueil : %{http_code} (401 : en ligne, derrière le mot de passe d'accès)\n" "https://127.0.0.1:$port/"
}

[[ $(id -u) == 0 ]] || die "En root : sudo make vm-…"
[[ $# -eq 1 && $(type -t "cmd_${1//-/_}") == function ]] || die "Étapes : deps env build db apache mail-test worker backup update status"
"cmd_${1//-/_}"; exit
