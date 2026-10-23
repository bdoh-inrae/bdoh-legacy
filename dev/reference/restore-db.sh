#!/usr/bin/env bash
# Restaure un dump anonymisé dans le serveur de référence local : PostgreSQL 11 et PostGIS 2.5,
# comme la production, sur un réseau Docker interne, sans accès à l'extérieur.
#
#   dev/reference/restore-db.sh DUMP_ANONYMISÉ [BASE]
#
# N'accepte qu'un dump sorti de dev/anonymize (nom en *-anon.dump). BASE vaut bdoh par défaut ;
# un même serveur accueille plusieurs bases (production, OTHU). Les fichiers du serveur vivent à
# côté des dumps, dans postgres/, et appartiennent à l'utilisateur courant. Le script refuse
# d'écraser une base existante. Pour repartir de zéro :
#   docker rm -f bdoh-reference-db && rm -rf DOSSIER_DES_DUMPS/postgres
set -euo pipefail

if [[ $# -lt 1 || $# -gt 2 ]]; then
    sed -n '2,13p' "$0" | sed 's/^# \{0,1\}//'
    exit 1
fi

dump=$(realpath "$1")
base=${2:-bdoh}
dumps=$(dirname "$dump")
data=$dumps/postgres
image=postgis/postgis:11-2.5
network=bdoh-reference
container=bdoh-reference-db

[[ -f $dump ]] || { echo "Dump introuvable : $dump" >&2; exit 1; }
[[ $dump == *-anon.dump ]] || { echo "N'accepte qu'un dump anonymisé (*-anon.dump)." >&2; exit 1; }

if ! docker container inspect "$container" >/dev/null 2>&1; then
    fresh=no
    [[ -d $data ]] || { mkdir -m 700 "$data"; fresh=yes; }
    docker network inspect "$network" >/dev/null 2>&1 || docker network create --internal "$network" >/dev/null
    docker run -d --name "$container" --network "$network" \
        --user "$(id -u):$(id -g)" -v /etc/passwd:/etc/passwd:ro \
        -e POSTGRES_HOST_AUTH_METHOD=trust \
        -v "$data":/var/lib/postgresql/data \
        -v "$dumps":/in:ro \
        "$image" >/dev/null
    for _ in $(seq 120); do
        if { [[ $fresh == no ]] || docker logs "$container" 2>&1 | grep -q 'init process complete'; } \
            && docker exec "$container" pg_isready -U postgres -q; then
            break
        fi
        sleep 1
    done
fi
docker start "$container" >/dev/null
docker exec "$container" pg_isready -U postgres -q || { echo "PostgreSQL ne répond pas." >&2; exit 1; }
if [[ $(docker inspect -f '{{range .Mounts}}{{if eq .Destination "/in"}}{{.Source}}{{end}}{{end}}' "$container") != "$dumps" ]]; then
    echo "Le conteneur $container ne voit pas $dumps : le recréer (docker rm -f $container)." >&2
    exit 1
fi
if docker exec "$container" psql -U postgres -Atc "SELECT 1 FROM pg_database WHERE datname = '$base'" | grep -q 1; then
    echo "La base $base existe déjà." >&2
    exit 1
fi

echo "Restauration de $(basename "$dump") dans la base $base…"
docker exec "$container" createdb -U postgres -T template0 -E UTF8 "$base"
docker exec "$container" pg_restore -U postgres -d "$base" --no-owner --no-privileges -j 8 "/in/$(basename "$dump")"
docker exec "$container" psql -U postgres -d "$base" -q -c 'VACUUM ANALYZE'
echo "Base $base prête : docker exec -it $container psql -U postgres $base"
