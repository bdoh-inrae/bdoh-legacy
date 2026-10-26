# L'ancien BDOH tel qu'il tourne en production : PHP 7.1 sous Apache pour les pages, en ligne de
# commande pour la file de tâches. Le code n'est pas dans l'image : il est monté depuis
# REFERENCE_DIR/app (install-app.sh).
FROM php:7.1.33-apache-buster

# Debian 10 n'est plus servie que par ses archives.
RUN sed -i -e 's|deb.debian.org|archive.debian.org|' -e 's|security.debian.org|archive.debian.org|' \
        /etc/apt/sources.list

# Extensions demandées par composer.json, plus APCu, dont le cache de Doctrine a besoin en
# environnement prod (apc_* par apcu_bc). gd avec FreeType et JPEG, qui dessinent l'image du
# captcha. shp2pgsql et psql servent à l'import des shapefiles ; la langue fr_FR.UTF-8, au
# setlocale() de slugify.
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        libfreetype6-dev libicu-dev libjpeg62-turbo-dev libpng-dev libpq-dev libsodium-dev \
        libxslt1-dev libzip-dev locales postgis postgresql-client unzip \
    && rm -rf /var/lib/apt/lists/* \
    && sed -i 's/^# fr_FR.UTF-8/fr_FR.UTF-8/' /etc/locale.gen && locale-gen \
    && docker-php-ext-configure gd --with-freetype-dir=/usr/include/ --with-jpeg-dir=/usr/include/ \
        --with-png-dir=/usr/include/ \
    && docker-php-ext-install -j"$(nproc)" gd intl pcntl pdo_pgsql pgsql xsl zip \
    && pecl install libsodium-2.0.23 apcu-5.1.21 apcu_bc-1.0.5 \
    && docker-php-ext-enable opcache sodium apcu \
    && echo 'extension=apc.so' > "$PHP_INI_DIR/conf.d/docker-php-ext-zz-apc.ini"

# Comme sur les serveurs de production : les pages sans fuseau réglé (donc en UTC), la ligne de
# commande en Europe/Paris et sans limite de mémoire (php.ini de Debian pour la ligne de
# commande). PHP lit php-cli.ini à la place de php.ini en ligne de commande.
RUN cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
    && sed -e 's|^;date.timezone =.*|date.timezone = Europe/Paris|' \
           -e 's|^memory_limit = .*|memory_limit = -1|' \
           "$PHP_INI_DIR/php.ini-production" > "$PHP_INI_DIR/php-cli.ini"

COPY --from=composer:2.2 /usr/bin/composer /usr/local/bin/composer
COPY apache.conf /etc/apache2/sites-available/000-default.conf

ENV SYMFONY_ENV=prod
WORKDIR /var/www/bdoh
