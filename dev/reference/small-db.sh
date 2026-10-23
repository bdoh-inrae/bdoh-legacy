#!/usr/bin/env bash
# Crée la base de travail réduite, tirée d'une base locale : tout, sauf les mesures,
# gardées sur une fenêtre de temps, plus celles d'avant 1900 (cas à garder pour la comparaison).
# Les plages et les points de contrôle sont gardés en entier. Le nombre de mesures et les dates des
# chroniques sont recalculés, les taux de remplissage hors de la fenêtre retirés : la base reste
# cohérente avec elle-même.
#
#   [SERVER=new] dev/reference/small-db.sh [SOURCE] [CIBLE] [DÉBUT] [FIN]
#
# Par défaut : bdoh vers bdoh_small, mesures de l'année 2024, sur le serveur de référence ; avec
# SERVER=new, sur celui du nouveau BDOH. Le script refuse d'écraser une base.
set -euo pipefail

if [[ $# -gt 4 ]]; then
    sed -n '2,11p' "$0" | sed 's/^# \{0,1\}//'
    exit 1
fi

source=${1:-bdoh}
target=${2:-bdoh_small}
begin=${3:-2024-01-01}
end=${4:-2025-01-01}
case ${SERVER:-reference} in
    reference) container=bdoh-reference-db ;;
    new) container=bdoh-db ;;
    *) echo "Serveur inconnu : $SERVER (reference ou new)" >&2; exit 1 ;;
esac
psql() { docker exec -i "$container" psql -U postgres -v ON_ERROR_STOP=1 -q "$@"; }

if psql -Atc "SELECT 1 FROM pg_database WHERE datname = '$target'" | grep -q 1; then
    echo "La base $target existe déjà." >&2
    exit 1
fi

echo "Structure et données de $source, sans les mesures…"
psql -c "CREATE DATABASE $target TEMPLATE template0 ENCODING 'UTF8'"
docker exec "$container" pg_dump -U postgres -Fc --exclude-table-data=bdoh.mesure -f /tmp/small.dump "$source"
docker exec "$container" pg_restore -U postgres -d "$target" --no-owner --no-privileges \
    --section=pre-data --section=data /tmp/small.dump

echo "Mesures du $begin au $end, et d'avant 1900…"
docker exec "$container" sh -c "psql -U postgres -d $source -v ON_ERROR_STOP=1 -c \"
    COPY (SELECT * FROM bdoh.mesure
           WHERE (date >= '$begin' AND date < '$end') OR date < '1900-01-01') TO STDOUT\" \
  | psql -U postgres -d $target -v ON_ERROR_STOP=1 -c 'COPY bdoh.mesure FROM STDIN'"

echo "Index et contraintes…"
docker exec "$container" pg_restore -U postgres -d "$target" --no-owner --no-privileges -j 8 \
    --section=post-data /tmp/small.dump
docker exec "$container" rm /tmp/small.dump

echo "Métadonnées des chroniques et taux de remplissage…"
psql -d "$target" <<SQL
UPDATE bdoh.chronique c
   SET nbmesures = a.n, datedebutmesures = a.premiere, datefinmesures = a.derniere
  FROM (SELECT chronique_id, count(*) AS n, min(date) AS premiere, max(date) AS derniere
          FROM bdoh.mesure GROUP BY chronique_id) a
 WHERE a.chronique_id = c.id;
UPDATE bdoh.chronique c
   SET nbmesures = NULL, datedebutmesures = NULL, datefinmesures = NULL
 WHERE c.dtype <> 'discontinue'
   AND NOT EXISTS (SELECT 1 FROM bdoh.mesure m WHERE m.chronique_id = c.id);
DELETE FROM bdoh.tauxremplissage
 WHERE NOT (debut < '$end' AND fin > '$begin') AND fin > '1900-01-01';
VACUUM ANALYZE;
SQL
echo "Base $target prête."
