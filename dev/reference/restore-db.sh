#!/usr/bin/env bash
# Restaure un dump anonymisé dans un serveur de base local, sur un réseau Docker interne : le
# serveur de référence (PostgreSQL 11 et PostGIS 2.5, comme la production, dev/reference/compose.yaml)
# ou, avec SERVER=new, celui du nouveau BDOH (PostgreSQL 17 et PostGIS 3.5, comme Debian 13,
# compose.yaml).
#
#   [SERVER=new] dev/reference/restore-db.sh DUMP_ANONYMISÉ [BASE]
#
# N'accepte qu'un dump sorti de dev/anonymize (nom en *-anon.dump), rangé dans REFERENCE_DIR
# (../bdoh_reference par défaut). BASE vaut bdoh par défaut ; un même serveur accueille plusieurs
# bases (production, OTHU). Les fichiers des serveurs vivent dans REFERENCE_DIR/postgres et
# REFERENCE_DIR/postgres-17, et appartiennent à l'utilisateur courant. Le script refuse d'écraser
# une base existante. Pour repartir de zéro, par exemple sur le serveur de référence :
#   make reference-stop && docker rm bdoh-reference-db && rm -rf REFERENCE_DIR/postgres
set -euo pipefail

if [[ $# -lt 1 || $# -gt 2 ]]; then
    sed -n '2,14p' "$0" | sed 's/^# \{0,1\}//'
    exit 1
fi

here=$(cd "$(dirname "$0")" && pwd)
export REFERENCE_DIR=${REFERENCE_DIR:-$(realpath -m "$here/../../../bdoh_reference")}
export HOST_UID=${HOST_UID:-$(id -u)} HOST_GID=${HOST_GID:-$(id -g)}
dump=$(realpath "$1")
base=${2:-bdoh}
case ${SERVER:-reference} in
    reference) compose=$here/compose.yaml container=bdoh-reference-db data=postgres ;;
    new) compose=$here/../../compose.yaml container=bdoh-db data=postgres-17 ;;
    *) echo "Serveur inconnu : $SERVER (reference ou new)" >&2; exit 1 ;;
esac

[[ -f $dump ]] || { echo "Dump introuvable : $dump" >&2; exit 1; }
[[ $dump == *-anon.dump ]] || { echo "N'accepte qu'un dump anonymisé (*-anon.dump)." >&2; exit 1; }
[[ $(dirname "$dump") == "$REFERENCE_DIR" ]] || { echo "Le dump doit être dans $REFERENCE_DIR." >&2; exit 1; }

[[ -d $REFERENCE_DIR/$data ]] || mkdir -m 700 "$REFERENCE_DIR/$data"
docker compose -f "$compose" up -d --wait db

# L'application se connecte sous le rôle bdoh, dont le nom la mène aussi au schéma bdoh (chemin de
# recherche "$user"). Il est superutilisateur parce que la restauration laisse tous les objets à
# postgres ; les droits du rôle en production ne sont pas connus.
docker exec "$container" psql -U postgres -q -c \
    'DO $$ BEGIN CREATE ROLE bdoh LOGIN SUPERUSER; EXCEPTION WHEN duplicate_object THEN NULL; END $$'

if docker exec "$container" psql -U postgres -Atc "SELECT 1 FROM pg_database WHERE datname = '$base'" | grep -q 1; then
    echo "La base $base existe déjà." >&2
    exit 1
fi

echo "Restauration de $(basename "$dump") dans la base $base…"
docker exec "$container" createdb -U postgres -T template0 -E UTF8 "$base"
docker exec "$container" pg_restore -U postgres -d "$base" --no-owner --no-privileges -j 8 "/in/$(basename "$dump")"
docker exec "$container" psql -U postgres -d "$base" -q -c 'VACUUM ANALYZE'
echo "Base $base prête : docker exec -it $container psql -U postgres $base"
