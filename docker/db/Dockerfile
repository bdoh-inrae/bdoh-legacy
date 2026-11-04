# La base du nouveau BDOH en développement, sur les paquets de Debian 13, ceux de la production :
# PostgreSQL, PostGIS, PROJ, GEOS et glibc de la distribution, et non ceux de l'image postgis/postgis.
# plpgsql_check donne la couverture des fonctions de calcul (shared_preload_libraries, compose.yaml).
FROM debian:trixie

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        postgresql-17 postgresql-17-postgis-3 postgresql-17-plpgsql-check postgis locales \
    && rm -rf /var/lib/apt/lists/* \
    && sed -i 's/^# en_US.UTF-8/en_US.UTF-8/; s/^# fr_FR.UTF-8/fr_FR.UTF-8/' /etc/locale.gen && locale-gen \
    && chmod 1777 /var/run/postgresql \
    && chmod a+w /etc/passwd

# Les vrais programmes, et non les aiguillages de Debian (pg_wrapper) : docker exec les trouve sans autre réglage
ENV PATH=/usr/lib/postgresql/17/bin:$PATH

COPY entrypoint.sh /usr/local/bin/entrypoint.sh
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
