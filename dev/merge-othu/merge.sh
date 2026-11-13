#!/usr/bin/env bash
# Fond OTHU dans une base du serveur du nouveau BDOH, pour éprouver la fusion avant la VM
# (dev/plans/2026-11-13_othu-merge.md). La base cible, qui doit avoir les migrations du nouveau BDOH, est modifiée.
#
#   dev/merge-othu/merge.sh BASE_CIBLE SOURCE
#
# SOURCE : le dump anonymisé d'OTHU (*-anon.dump, dans REFERENCE_DIR), sans migrations, comme sur la VM ; ou une
# base du même serveur (bdoh_othu_small). Les bases anonymisées tirent l'adresse d'un compte de son identifiant
# (utilisateur-12@example.invalid) : un compte d'OTHU et un compte de la cible de même identifiant auraient la même,
# et la fusion les rapprocherait. Le script les distingue, puis donne à deux comptes d'OTHU, dont un administrateur,
# l'adresse d'un compte de la cible (la seconde en majuscules), pour éprouver le rapprochement.
set -euo pipefail

if [[ $# -ne 2 ]]; then
    sed -n '2,11p' "$0" | sed 's/^# \{0,1\}//'
    exit 1
fi

here=$(cd "$(dirname "$0")" && pwd)
REFERENCE_DIR=${REFERENCE_DIR:-$(realpath -m "$here/../../../bdoh_reference")}
target=$1 source=$2 tmp=bdoh_othu_fusion
psql() { docker exec -i bdoh-db psql -U postgres -v ON_ERROR_STOP=1 -q "$@"; }

# Les bases restaurées des dumps servent à tout le reste : la fusion se fait dans une copie
case $target in
    bdoh | bdoh_small | bdoh_othu | bdoh_othu_small)
        echo "La fusion modifie sa base cible : en faire d'abord une copie (CREATE DATABASE merge_small TEMPLATE $target)." >&2
        exit 1 ;;
esac

psql -c "DROP DATABASE IF EXISTS $tmp WITH (FORCE)"
if [[ $source == *.dump ]]; then
    dump=$(realpath "$source")
    [[ $dump == *-anon.dump && $(dirname "$dump") == "$REFERENCE_DIR" ]] \
        || { echo "N'accepte qu'un dump anonymisé (*-anon.dump) de $REFERENCE_DIR." >&2; exit 1; }
    echo "[$(date +%T)] Restauration de $(basename "$dump")"
    docker exec bdoh-db createdb -U postgres -T template0 -E UTF8 "$tmp"
    # Sans index ni contraintes (post-data) : la fusion ne fait que lire ces tables, d'un bout à l'autre
    docker exec bdoh-db pg_restore -U postgres -d "$tmp" --no-owner --no-privileges --section=pre-data --section=data \
        -j 8 "/in/$(basename "$dump")"
else
    psql -c "CREATE DATABASE $tmp TEMPLATE $source"
fi
psql -d "$tmp" -c 'ALTER SCHEMA bdoh RENAME TO othu'

mapfile -t emails < <(psql -d "$target" -At -c \
    "SELECT email FROM bdoh.utilisateur WHERE roleadmin IS NULL ORDER BY id LIMIT 2")
psql -d "$tmp" <<SQL
UPDATE othu.utilisateur SET email = 'othu-' || email;
UPDATE othu.utilisateur SET email = '${emails[0]}'
 WHERE id = (SELECT min(id) FROM othu.utilisateur WHERE roleadmin IS NULL);
UPDATE othu.utilisateur SET email = upper('${emails[1]}')
 WHERE id = (SELECT min(id) FROM othu.utilisateur WHERE roleadmin IS NOT NULL);
SQL

echo "[$(date +%T)] Chargement du schéma othu dans $target"
docker exec bdoh-db sh -c "pg_dump -U postgres -n othu --section=pre-data --section=data $tmp \
    | psql -U postgres -q -v ON_ERROR_STOP=1 $target"
psql -c "DROP DATABASE $tmp"
echo "[$(date +%T)] Fusion"
psql -d "$target" < "$here/merge.sql"
# compare.py merge lit la correspondance sous le rôle de l'application
psql -d "$target" -c 'GRANT USAGE ON SCHEMA fusion_othu TO bdoh; GRANT SELECT ON ALL TABLES IN SCHEMA fusion_othu TO bdoh'
echo "[$(date +%T)] Fusion faite dans $target"
