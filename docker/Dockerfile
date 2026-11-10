# Le nouveau BDOH en développement, sur les paquets d'Ubuntu 26.04, ceux de la production (la VM) : PHP 8.5
# sous Apache (mod_php) pour les pages, en ligne de commande pour la file de tâches. Le code n'est
# pas dans l'image : il est monté depuis le dépôt (compose.yaml). shp2pgsql et psql servent à
# l'import des shapefiles ; Node, à la compilation du JavaScript et des feuilles de style ; la
# langue fr_FR.UTF-8, à la translittération des codes (IrsteaBdohDataBundle::slugify). OPcache fait
# partie de PHP depuis la 8.5, sans paquet à part.
FROM ubuntu:26.04

RUN apt-get update \
    && DEBIAN_FRONTEND=noninteractive apt-get install -y --no-install-recommends \
        apache2 libapache2-mod-php8.5 php8.5-cli \
        php8.5-apcu php8.5-curl php8.5-gd php8.5-intl php8.5-mbstring php8.5-pgsql php8.5-xml \
        php8.5-zip \
        composer git unzip nodejs npm locales \
        postgis postgresql-client-18 \
    && rm -rf /var/lib/apt/lists/* \
    && locale-gen fr_FR.UTF-8

# Apache tourne aux droits de l'utilisateur du poste (compose.yaml), qui possède ce qu'il écrit ;
# ses journaux vont sur la sortie du conteneur.
RUN chmod 1777 /var/run/apache2 /var/lock/apache2 /var/log/apache2 \
    && ln -sf /dev/stdout /var/log/apache2/access.log \
    && ln -sf /dev/stderr /var/log/apache2/error.log \
    && ln -sf /dev/stdout /var/log/apache2/other_vhosts_access.log
COPY apache.conf /etc/apache2/sites-available/000-default.conf

WORKDIR /var/www/bdoh
CMD ["sh", "-c", ". /etc/apache2/envvars && exec apache2 -D FOREGROUND"]
