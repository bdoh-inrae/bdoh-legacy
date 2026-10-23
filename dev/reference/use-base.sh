#!/usr/bin/env bash
# Branche l'application de référence sur une autre base du serveur de référence.
#
#   dev/reference/use-base.sh BASE
#
# Change database_name dans app/config/parameters.yml de l'application installée, refait son cache
# (le cache de l'environnement prod contient les paramètres) et relance la file de tâches.
set -euo pipefail

if [[ $# -ne 1 ]]; then
    sed -n '2,7p' "$0" | sed 's/^# \{0,1\}//'
    exit 1
fi

here=$(cd "$(dirname "$0")" && pwd)
export REFERENCE_DIR=${REFERENCE_DIR:-$(realpath -m "$here/../../../bdoh_reference")}
export HOST_UID=${HOST_UID:-$(id -u)} HOST_GID=${HOST_GID:-$(id -g)}
base=$1

docker exec bdoh-reference-db psql -U postgres -Atc "SELECT 1 FROM pg_database WHERE datname = '$base'" \
    | grep -q 1 || { echo "La base $base n'existe pas." >&2; exit 1; }
sed -i "s/^\(    database_name: *\).*/\1$base/" "$REFERENCE_DIR/app/app/config/parameters.yml"
docker exec bdoh-reference-app php app/console cache:clear --no-warmup
docker exec bdoh-reference-app php app/console cache:warmup
docker compose -f "$here/compose.yaml" restart worker
echo "La référence lit maintenant la base $base."
