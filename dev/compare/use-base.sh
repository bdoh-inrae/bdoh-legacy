#!/usr/bin/env bash
# Branche les trois instances que compare dev/compare/compare.py sur la même base : la référence
# (PostgreSQL 11), l'ancien code sur PostgreSQL 17 et le nouveau BDOH. La base doit exister sur les
# deux serveurs (make db, make db-small, avec et sans SERVER=new).
#
#   dev/compare/use-base.sh BASE
#
# Le nouveau BDOH lit sa base dans .env.local, que ce script écrit (Symfony le lit après .env) ;
# pour revenir à .env : rm .env.local.
set -euo pipefail

if [[ $# -ne 1 ]]; then
    sed -n '2,10p' "$0" | sed 's/^# \{0,1\}//'
    exit 1
fi

here=$(cd "$(dirname "$0")" && pwd)
root=$(cd "$here/../.." && pwd)
export REFERENCE_DIR=${REFERENCE_DIR:-$(realpath -m "$root/../bdoh_reference")}
base=$1

docker exec bdoh-db psql -U postgres -Atc "SELECT 1 FROM pg_database WHERE datname = '$base'" | grep -q 1 \
    || { echo "La base $base n'existe pas sur le serveur PostgreSQL 17." >&2; exit 1; }

"$root/dev/reference/use-base.sh" "$base"

# L'ancien code sur PostgreSQL 17 : sa copie a ses propres paramètres et son propre cache
sed -i "s/^\(    database_name: *\).*/\1$base/" "$REFERENCE_DIR/app-17/app/config/parameters.yml"
docker exec bdoh-reference-app-17 php app/console cache:clear --no-warmup
docker exec bdoh-reference-app-17 php app/console cache:warmup

echo "DATABASE_URL=\"postgresql://bdoh@db:5432/$base?serverVersion=17&charset=utf8\"" > "$root/.env.local"
echo "Les trois instances lisent la base $base."
