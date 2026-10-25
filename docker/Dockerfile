# Le nouveau BDOH en développement, sur les paquets de Debian 13, ceux de la production : PHP 8.4
# sous Apache (mod_php) pour les pages, en ligne de commande pour la file de tâches. Le code n'est
# pas dans l'image : il est monté depuis le dépôt (compose.yaml). shp2pgsql et psql servent à
# l'import des shapefiles ; Node, à la compilation du JavaScript et des feuilles de style ; la
# langue fr_FR.UTF-8, à la translittération des codes (IrsteaBdohDataBundle::slugify).
FROM debian:trixie

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        apache2 libapache2-mod-php8.4 php8.4-cli php8.4-opcache \
        php8.4-apcu php8.4-curl php8.4-gd php8.4-intl php8.4-mbstring php8.4-pgsql php8.4-xml \
        php8.4-zip \
        composer git unzip nodejs npm locales \
        postgis postgresql-client-17 \
    && rm -rf /var/lib/apt/lists/* \
    && sed -i 's/^# fr_FR.UTF-8/fr_FR.UTF-8/' /etc/locale.gen && locale-gen

# Comme sur les serveurs actuels : la ligne de commande, donc la file de tâches, en Europe/Paris ;
# les pages en UTC, défaut de PHP (dev/differences.md, « Deux fuseaux horaires »).
RUN echo 'date.timezone = Europe/Paris' > /etc/php/8.4/cli/conf.d/90-bdoh.ini

# Apache tourne aux droits de l'utilisateur du poste (compose.yaml), qui possède ce qu'il écrit ;
# ses journaux vont sur la sortie du conteneur.
RUN chmod 1777 /var/run/apache2 /var/lock/apache2 /var/log/apache2 \
    && ln -sf /dev/stdout /var/log/apache2/access.log \
    && ln -sf /dev/stderr /var/log/apache2/error.log \
    && ln -sf /dev/stdout /var/log/apache2/other_vhosts_access.log
COPY apache.conf /etc/apache2/sites-available/000-default.conf

WORKDIR /var/www/bdoh
CMD ["sh", "-c", ". /etc/apache2/envvars && exec apache2 -D FOREGROUND"]
