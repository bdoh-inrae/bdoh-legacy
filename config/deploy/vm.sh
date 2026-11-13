#!/usr/bin/env bash
# Installe et met à jour BDOH sur la VM (Ubuntu 26.04, sans Docker). Appelé par les cibles vm-… du
# Makefile, en root, dans le dossier de l'instance : /var/www/bdoh-prod, un clone du dépôt. OTHU y est un
# observatoire, fondu par vm-merge-othu (dev/plans/2026-11-13_othu-merge.md). L'ordre des étapes et ce qu'il faut regarder entre deux : dev/deploy/README.md.
# Chaque étape s'arrête à la première erreur. Aucune ne supprime une base, ni ne restaure dans une base
# qui existe déjà.
#
# Tout est dans des fonctions, appelées par la dernière ligne : bash a ainsi lu le script entier avant
# de l'exécuter, et une mise à jour (git checkout) peut le remplacer en cours de route.
set -euo pipefail

sources=/var/backups/bdoh-sources   # les dumps bruts et les fichiers déposés, copiés par Lou
backups=/var/backups/bdoh           # les sauvegardes de chaque nuit, gardées 8 jours (bdoh-backup@.timer)
kept=/var/backups/bdoh-kept         # celles d'avant une mise à jour ou la fusion, gardées jusqu'à ce qu'on les supprime

die() { echo "$*" >&2; exit 1; }
# Ce que fait le script, à l'heure : les étapes longues (sauvegarde, empreinte) n'écrivent rien d'elles-mêmes
step() { echo "[$(date +%T)] $*"; }
pg() { runuser -u postgres -- psql -v ON_ERROR_STOP=1 "$@"; }
console() { runuser -u www-data -- php bin/console --env=prod --no-interaction "$@"; }

instance() {
    [[ ${NOM:-} == prod ]] || die "Préciser l'instance : NOM=prod"
    port=443
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
    step "Bibliothèques PHP (Composer)"
    composer install --no-dev --no-scripts --no-interaction --classmap-authoritative
    step "Bibliothèques JavaScript (npm)"
    npm ci --no-audit --no-fund
    step "Compilation du JavaScript et des styles"
    NODE_OPTIONS=--openssl-legacy-provider node_modules/.bin/encore production
    mkdir -p var public/uploads public/css/themes
    # Les thèmes compilés de la version précédente : CssManager ne recompile un thème que s'il manque, à la
    # première page de chaque observatoire, avec les feuilles et les couleurs de cette version.
    rm -f public/css/themes/*.css
    php bin/console assets:install public --env=prod --no-debug
    step "Droits et cache"
    # Le code est celui du dépôt public : lisible par tous, pour qu'on y lise le Makefile ; fermés aux autres
    # comptes, les secrets (.env) et ce que le site écrit (var/ : exports, dont des séries cachées, journaux, sessions)
    chown -R root:www-data . && chmod -R g-w,o-rwx .
    find . -path ./var -prune -o ! -name '.env*' -exec chmod o+rX {} +
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
    step "Contrôle de la base avant les migrations (comptes et empreinte des mesures, quelques minutes)"
    controle avant
    step "Migrations"
    console doctrine:migrations:migrate
    console cache:clear
    step "Contrôle de la base après les migrations (quelques minutes)"
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
    [[ -f $access ]] || { echo "Mot de passe d'accès des essais, compte « test » :"; htpasswd -c "$access" test; }
    chgrp www-data "$access" && chmod 640 "$access"
    a2ensite -q "bdoh-$NOM"
    apache2ctl configtest
    systemctl reload apache2
    echo "Site en ligne sur le port $port, derrière le mot de passe d'accès."
}

# Une ligne du .env remplacée, la valeur lue sur l'entrée standard : rien ne passe par la ligne de commande,
# et un caractère spécial de la valeur ne compte pas pour le shell. La ligne doit exister, une fois.
env_set() {
    php -r '$k = $argv[1]; $v = stream_get_contents(STDIN);
        $t = preg_replace_callback("/^" . preg_quote($k, "/") . "=.*$/m", fn() => "$k=$v", file_get_contents(".env"), 1, $n);
        if ($n !== 1) { fwrite(STDERR, "$k introuvable dans .env : aucun changement\n"); exit(1); }
        file_put_contents(".env", $t);' "$1"
}

# Les courriels d'une instance, dans cet ordre, pour qu'aucun ne parte ailleurs que chez Lou : l'adresse
# où tout part pendant les essais (MAILER_DIVERT_TO), puis le relais de l'infra, avec le compte de
# l'ancienne production, dont l'identifiant et le mot de passe se lisent au clavier, le mot de passe sans
# s'afficher, et s'écrivent encodés dans MAILER_DSN. Le relais se donne à la commande : le dépôt ne le nomme pas.
cmd_mail() {
    instance
    [[ -n ${RELAIS:-} ]] || die "Préciser RELAIS= : le relais de courrier, hôte:port"
    if [[ -n ${RENVOI:-} ]]; then
        printf '%s' "$RENVOI" | env_set MAILER_DIVERT_TO
    fi
    grep -q '^MAILER_DIVERT_TO=.' .env || die "Préciser RENVOI= : l'adresse où partent les courriels pendant les essais"
    local user pass
    read -rp 'Identifiant du relais : ' user
    read -rsp 'Mot de passe du relais : ' pass; echo
    printf '%s\n%s\n%s' "$RELAIS" "$user" "$pass" | php -r '[$r, $u, $p] = explode("\n", stream_get_contents(STDIN), 3);
        echo "smtp://" . rawurlencode($u) . ":" . rawurlencode($p) . "@" . $r;' | env_set MAILER_DSN
    unset user pass
    # La file lit .env à son démarrage
    systemctl try-restart "bdoh-job-worker@$NOM" || true
    echo "MAILER_DIVERT_TO et MAILER_DSN écrits. Puis : sudo make vm-mail-test NOM=$NOM TO=…"
}

# Avant la file de tâches, qui enverra les courriels des tâches restées en attente dans le dump : ce
# courriel doit arriver à MAILER_DIVERT_TO, et à personne d'autre.
cmd_mail_test() {
    instance
    # L'expéditeur de BDOH : celui de Symfony par défaut, from@example.org, serait refusé par le relais
    console mailer:test --from=bdoh@inrae.fr "${TO:-quelquun@example.org}"
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
    step "Arrêt de la file de tâches (elle reprend à la fin ; un arrêt en route la laisse arrêtée)"
    systemctl stop "$worker"
    step "Sauvegarde de la base $base dans $kept (quelques minutes)"
    install -d -m 750 -o postgres -g postgres "$kept"
    runuser -u postgres -- pg_dump -Fc -f "$kept/$NOM-avant-$V-$(date +%F-%H%M).dump" "$base"
    step "Contrôle de la base avant les migrations (comptes et empreinte des mesures, quelques minutes)"
    controle avant
    step "Passage à $V"
    git -c advice.detachedHead=false checkout --quiet "$V"
    # Le script qui tourne est celui de la version d'avant, lu avant le checkout : la suite (construction,
    # migrations, contrôles) est celle de la version installée, relancée depuis son propre fichier. Le
    # passage de la 0.3.2 à la 0.5.0 a ainsi gardé les thèmes de la 0.3.2, que seule la 0.5.0 supprimait.
    if grep -q '^cmd_update_installed()' "$0"; then
        exec env UPDATE_WORKER="$was_active" UPDATE_SECONDS="$SECONDS" "$0" update-installed
    fi
    update_installed "$was_active"
}

cmd_update_installed() {
    instance
    [[ -n ${V:-} && -n ${UPDATE_WORKER:-} ]] || die "Étape interne de make vm-update"
    SECONDS=${UPDATE_SECONDS:-0}
    update_installed "$UPDATE_WORKER"
}

update_installed() {
    local worker=bdoh-job-worker@$NOM was_active=$1
    build
    step "Migrations"
    console doctrine:migrations:migrate
    console cache:clear
    step "Contrôle de la base après les migrations (quelques minutes)"
    controle apres
    changes
    step "Rechargement d'Apache et reprise de la file"
    systemctl reload apache2
    [[ $was_active == yes ]] && systemctl start "$worker"
    step "Installée : $(git describe --tags --always), en $((SECONDS / 60)) min"
}

# La fusion d'OTHU dans la production (dev/plans/2026-11-13_othu-merge.md) : le dump brut d'OTHU restauré dans une
# base temporaire, son schéma renommé othu et chargé à part dans la base de production, puis dev/merge-othu/merge.sql,
# en une seule transaction. Site et file arrêtés pendant ce temps, pour que rien d'autre n'écrive dans la base ; base
# sauvegardée avant, dans un dossier que la sauvegarde de la nuit ne vide pas. Aucune chronique de la production ne doit
# changer. Une heure environ : à lancer dans tmux, une coupure du VPN arrêtant sinon tout en route.
cmd_merge_othu() {
    instance
    [[ -f ${DUMP:-} ]] || die "Préciser DUMP= : le dump brut d'OTHU, dans $sources"
    local worker=bdoh-job-worker@$NOM was_active=no tmp=bdoh_othu_fusion free
    [[ -z $(pg -Atc "SELECT 1 FROM pg_database WHERE datname = '$tmp'") ]] \
        || die "La base $tmp reste d'une fusion inachevée : sudo -u postgres dropdb $tmp, puis relancer"
    [[ -z $(pg -d "$base" -Atc "SELECT 1 FROM pg_namespace WHERE nspname = 'othu'") ]] \
        || die "Le schéma othu reste d'une fusion inachevée : sudo -u postgres psql -d $base -c 'DROP SCHEMA othu CASCADE', puis relancer"
    [[ -z $(pg -d "$base" -Atc "SELECT 1 FROM pg_namespace WHERE nspname = 'fusion_othu'") ]] \
        || die "OTHU est déjà fondu dans $base (schéma fusion_othu)"
    # Au plus fort : la base temporaire, OTHU à part dans la production, ses mesures ajoutées, leur journal et la sauvegarde
    free=$(df --output=avail -BG /var/lib/postgresql | tail -1 | tr -dc 0-9)
    (( free >= 60 )) || die "Moins de 60 Go libres pour PostgreSQL ($free Go) : la fusion en demande autant au plus fort"
    systemctl is-active -q "$worker" && was_active=yes
    step "Site et file de tâches arrêtés jusqu'à la fin (le site rouvre même en cas d'échec ; la file, seulement en cas de succès)"
    trap 'rm -f /var/lib/postgresql/othu.dump; systemctl start apache2' EXIT
    systemctl stop apache2 "$worker"
    step "Sauvegarde de la base $base dans $kept (quelques minutes)"
    install -d -m 750 -o postgres -g postgres "$kept"
    runuser -u postgres -- pg_dump -Fc -f "$kept/$NOM-avant-fusion-othu-$(date +%F-%H%M).dump" "$base"
    step "Contrôle de la base avant la fusion (quelques minutes)"
    controle avant
    step "Restauration de $(basename "$DUMP") dans une base temporaire"
    runuser -u postgres -- createdb -T template0 -E UTF8 --locale=en_US.UTF-8 "$tmp"
    install -o postgres -m 600 "$DUMP" /var/lib/postgresql/othu.dump
    # Sans index ni contraintes (post-data) : la fusion ne fait que lire ces tables
    runuser -u postgres -- pg_restore -d "$tmp" --no-owner --no-privileges --section=pre-data --section=data \
        -j 4 /var/lib/postgresql/othu.dump
    pg -d "$tmp" -qc 'ALTER SCHEMA bdoh RENAME TO othu'
    step "Chargement d'OTHU dans $base, à part (schéma othu)"
    runuser -u postgres -- pg_dump -n othu --section=pre-data --section=data "$tmp" | pg -d "$base" -q
    runuser -u postgres -- dropdb "$tmp"
    step "Fusion (une seule transaction : une erreur, et aucune donnée n'a changé)"
    if ! pg -d "$base" < dev/merge-othu/merge.sql; then
        if [[ -n $(pg -d "$base" -Atc "SELECT 1 FROM pg_namespace WHERE nspname = 'fusion_othu'") ]]; then
            die "La fusion est validée (schéma fusion_othu), seule sa fin a échoué : lire l'erreur. La file reste arrêtée."
        fi
        pg -d "$base" -qc 'DROP SCHEMA IF EXISTS othu CASCADE'
        die "Fusion arrêtée : aucune donnée n'a changé dans $base (les compteurs d'identifiants ont avancé, sans effet). La file reste arrêtée."
    fi
    step "Contrôle de la base après la fusion (quelques minutes)"
    controle apres
    echo "--- inspect.sql, avant et après (les lignes d'OTHU s'ajoutent aux comptes) :"
    diff "/root/inspect-$NOM-avant.txt" "/root/inspect-$NOM-apres.txt" || true
    local changed
    # Le même ordre pour sort et comm, celui des octets : selon la langue du système, ils ne rangeaient pas pareil, et
    # comm s'arrêtait sur une erreur (fusion du 13 novembre sur la VM)
    changed=$(LC_ALL=C comm -23 <(LC_ALL=C sort "/root/fingerprint-$NOM-avant.txt") <(LC_ALL=C sort "/root/fingerprint-$NOM-apres.txt") | cut -d'|' -f1-2)
    echo "--- empreinte des mesures, chroniques de la production changées (attendu : aucune) :"
    echo "${changed:-aucune}"
    console cache:clear
    [[ $was_active == yes ]] && systemctl start "$worker"
    step "OTHU est un observatoire de la production, en $((SECONDS / 60)) min ; le site rouvre"
}

# Le retour arrière de la fusion, tant que personne n'a écrit sur OTHU (un export n'y écrit rien) et que rien dans la
# production ne cite ce qu'elle a ajouté ; sinon il le dit sans rien changer, et le retour se fait par la sauvegarde
# d'avant la fusion, dans $kept. Une sauvegarde d'abord, là aussi.
cmd_unmerge_othu() {
    instance
    local worker=bdoh-job-worker@$NOM was_active=no
    systemctl is-active -q "$worker" && was_active=yes
    trap 'systemctl start apache2' EXIT
    systemctl stop apache2 "$worker"
    step "Sauvegarde de la base $base dans $kept (quelques minutes)"
    install -d -m 750 -o postgres -g postgres "$kept"
    runuser -u postgres -- pg_dump -Fc -f "$kept/$NOM-avant-retrait-othu-$(date +%F-%H%M).dump" "$base"
    step "Retrait d'OTHU (une seule transaction)"
    pg -d "$base" < dev/merge-othu/unmerge.sql || die "Retour arrière arrêté : rien n'a changé. La file reste arrêtée."
    console cache:clear
    [[ $was_active == yes ]] && systemctl start "$worker"
    step "OTHU est retiré de la production ; le site rouvre"
}

cmd_status() {
    instance
    echo "Version : $(git describe --tags --always)"
    systemctl is-active postgresql@18-main apache2 "bdoh-job-worker@$NOM" || true
    curl -sk -o /dev/null -w "Accueil : %{http_code} (401 : en ligne, derrière le mot de passe d'accès)\n" "https://127.0.0.1:$port/"
}

[[ $(id -u) == 0 ]] || die "En root : sudo make vm-…"
[[ $# -eq 1 && $(type -t "cmd_${1//-/_}") == function ]] || die "Étapes : deps env build db apache mail mail-test worker backup update merge-othu unmerge-othu status"
"cmd_${1//-/_}"; exit
