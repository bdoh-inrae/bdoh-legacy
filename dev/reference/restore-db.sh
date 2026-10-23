#!/usr/bin/env bash
# Restaure un dump anonymisé dans la base de référence locale : PostgreSQL 11 et PostGIS 2.5, comme
# la production, sur un réseau Docker interne, sans accès à l'extérieur.
#
#   dev/reference/restore-db.sh DUMP_ANONYMISÉ
#
# N'accepte qu'un dump sorti de dev/anonymize (nom en *-anon.dump). Les fichiers de la base vivent
# à côté du dump, dans postgres/, et appartiennent à l'utilisateur courant : on les voit et on les
# supprime comme le reste du projet. Le script refuse d'écraser une base existante. Pour repartir
# de zéro :
#   docker rm -f bdoh-reference-db && rm -rf DOSSIER_DU_DUMP/postgres
set -euo pipefail

if [[ $# -ne 1 ]]; then
    sed -n '2,12p' "$0" | sed 's/^# \{0,1\}//'
    exit 1
fi

dump=$(realpath "$1")
data=$(dirname "$dump")/postgres
image=postgis/postgis:11-2.5
network=bdoh-reference
container=bdoh-reference-db

[[ -f $dump ]] || { echo "Dump introuvable : $dump" >&2; exit 1; }
[[ $dump == *-anon.dump ]] || { echo "N'accepte qu'un dump anonymisé (*-anon.dump)." >&2; exit 1; }
if [[ -e $data ]] || docker container inspect "$container" >/dev/null 2>&1; then
    echo "La base de référence existe déjà ($data, conteneur $container)." >&2
    exit 1
fi
mkdir -m 700 "$data"

docker network inspect "$network" >/dev/null 2>&1 || docker network create --internal "$network" >/dev/null
docker run -d --name "$container" --network "$network" \
    --user "$(id -u):$(id -g)" -v /etc/passwd:/etc/passwd:ro \
    -e POSTGRES_HOST_AUTH_METHOD=trust \
    -v "$data":/var/lib/postgresql/data \
    -v "$dump":/in/reference.dump:ro \
    "$image" >/dev/null
for _ in $(seq 120); do
    if docker logs "$container" 2>&1 | grep -q 'init process complete' \
        && docker exec "$container" pg_isready -U postgres -q; then
        break
    fi
    sleep 1
done
docker exec "$container" pg_isready -U postgres -q || { echo "PostgreSQL ne démarre pas." >&2; exit 1; }

echo "Restauration de $(basename "$dump")…"
docker exec "$container" createdb -U postgres -T template0 -E UTF8 bdoh
docker exec "$container" pg_restore -U postgres -d bdoh --no-owner --no-privileges -j 8 /in/reference.dump
docker exec "$container" psql -U postgres -d bdoh -q -c 'VACUUM ANALYZE'
echo "Base de référence prête : docker exec -it $container psql -U postgres bdoh"
