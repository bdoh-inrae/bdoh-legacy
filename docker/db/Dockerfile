# La base du nouveau BDOH en développement, sur les paquets d'Ubuntu 26.04, ceux de la production (la VM) :
# PostgreSQL, PostGIS, PROJ, GEOS et glibc de la distribution, et non ceux de l'image postgis/postgis.
# plpgsql_check donne la couverture des fonctions de calcul (shared_preload_libraries, compose.yaml).
FROM ubuntu:26.04

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        postgresql-18 postgresql-18-postgis-3 postgresql-18-plpgsql-check postgis locales \
    && rm -rf /var/lib/apt/lists/* \
    && locale-gen en_US.UTF-8 fr_FR.UTF-8 \
    && chmod 1777 /var/run/postgresql \
    && chmod a+w /etc/passwd

# Les vrais programmes, et non les aiguillages de la distribution (pg_wrapper) : docker exec les trouve sans autre réglage
ENV PATH=/usr/lib/postgresql/18/bin:$PATH

COPY entrypoint.sh /usr/local/bin/entrypoint.sh
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
