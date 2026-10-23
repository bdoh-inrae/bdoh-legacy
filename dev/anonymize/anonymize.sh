#!/usr/bin/env bash
# Anonymise un dump de production de BDOH sans que ses données ne quittent le poste.
#
#   dev/anonymize/anonymize.sh SOURCE.dump [DOSSIER_DE_SORTIE]
#
# Le dump source n'est que lu : monté en lecture seule dans un conteneur PostgreSQL sans réseau,
# restauré dans une base temporaire, anonymisé par anonymize.sql, puis exporté. La base temporaire
# (un volume Docker) est détruite à la fin, même en cas d'échec ; les anciennes valeurs, qui
# restent dans ses fichiers jusqu'à leur nettoyage, partent avec elle.
#
# Ne sortent que le dump anonymisé et un rapport fait de comptes. Si le rapport signale une fuite
# possible, aucun dump n'est produit. Le dossier de sortie (par défaut ../bdoh_reference, à côté du
# dépôt) reste hors de git : certaines séries ne sont pas publiques.
set -euo pipefail

if [[ $# -lt 1 ]]; then
    sed -n '2,15p' "$0" | sed 's/^# \{0,1\}//'
    exit 1
fi

here=$(cd "$(dirname "$0")" && pwd)
source_dump=$(realpath "$1")
out_dir=$(realpath -m "${2:-$here/../../../bdoh_reference}")
image=postgis/postgis:11-2.5  # même version majeure de PostgreSQL et de PostGIS que la production
container=bdoh-anonymize
volume=bdoh-anonymize-data
name=$(basename "$source_dump" .dump)-anon
anon_dump=$out_dir/$name.dump
report=$out_dir/$name.txt

[[ -f $source_dump ]] || { echo "Dump introuvable : $source_dump" >&2; exit 1; }
if docker container inspect "$container" >/dev/null 2>&1; then
    echo "Le conteneur $container existe déjà (essai précédent ?) : docker rm -f $container" >&2
    exit 1
fi

cleanup() {
    docker rm -f "$container" >/dev/null 2>&1 || true
    docker volume rm "$volume" >/dev/null 2>&1 || true
}
trap cleanup EXIT

umask 077
mkdir -p "$out_dir"
chmod 700 "$out_dir"

echo "Empreinte du dump source…"
sum_before=$(sha256sum "$source_dump" | cut -d' ' -f1)
sums=$(dirname "$source_dump")/SHA256SUMS
if [[ -f $sums ]]; then
    expected=$(awk -v f="$(basename "$source_dump")" '$2 == f || $2 == "*" f {print $1}' "$sums")
    if [[ -n $expected && $expected != "$sum_before" ]]; then
        echo "L'empreinte du dump ne correspond pas à $sums : arrêt." >&2
        exit 1
    fi
fi

echo "Démarrage de PostgreSQL, sans réseau…"
docker volume create "$volume" >/dev/null
docker run -d --name "$container" --network none \
    -e POSTGRES_HOST_AUTH_METHOD=trust \
    -v "$source_dump":/in/source.dump:ro \
    -v "$here":/in/sql:ro \
    -v "$volume":/var/lib/postgresql/data \
    "$image" >/dev/null
for _ in $(seq 120); do
    if docker logs "$container" 2>&1 | grep -q 'init process complete' \
        && docker exec "$container" pg_isready -U postgres -q; then
        break
    fi
    sleep 1
done
docker exec "$container" pg_isready -U postgres -q || { echo "PostgreSQL ne démarre pas." >&2; exit 1; }

echo "Restauration dans une base temporaire (longue : la table des mesures est grosse)…"
docker exec "$container" createdb -U postgres -T template0 -E UTF8 bdoh
# Le journal de restauration reste dans le conteneur : il peut citer des valeurs. En cas d'échec,
# n'en sortent que les lignes de pg_restore et les messages d'erreur, guillemets masqués.
if ! docker exec "$container" sh -c \
    'pg_restore -U postgres -d bdoh --no-owner --no-privileges -j 4 /in/source.dump 2> /tmp/restore.log'; then
    echo "La restauration a échoué : arrêt, aucun dump produit." >&2
    docker exec "$container" grep -E '^pg_restore:|ERROR:' /tmp/restore.log \
        | sed -E '/ERROR:/ s/"[^"]*"/"…"/g' | head -40 >&2
    exit 1
fi

echo "Anonymisation…"
docker exec "$container" psql -U postgres -d bdoh -v ON_ERROR_STOP=1 --single-transaction -q \
    -f /in/sql/anonymize.sql

echo "Rapport…"
counts=$(docker exec "$container" psql -U postgres -d bdoh -At -F $'\t' -c 'SELECT item, n FROM anon.report()')
{
    echo "Anonymisation de $(basename "$source_dump"), le $(date '+%Y-%m-%d %H:%M')"
    echo "Empreinte du dump source : $sum_before"
    echo "Mot de passe de tous les comptes : bdoh-test (base de test locale, à ne jamais exposer)"
    echo
    echo "$counts" | column -t -s $'\t'
} > "$report"
problems=$(echo "$counts" | awk -F'\t' '$1 ~ /^(fuite|contrôle) / && $2 > 0' | wc -l)

if [[ $problems -gt 0 ]]; then
    cat "$report"
    echo
    echo "Fuite possible ou contrôle en échec ($problems ligne(s) ci-dessus) : aucun dump produit." >&2
    exit 1
fi

echo "Export du dump anonymisé…"
docker exec "$container" psql -U postgres -d bdoh -q \
    -c 'SET client_min_messages = warning' -c 'DROP SCHEMA anon CASCADE'
docker exec "$container" pg_dump -U postgres -Fc --no-owner --no-privileges bdoh > "$anon_dump.part"
mv "$anon_dump.part" "$anon_dump"
echo "Empreinte du dump anonymisé : $(sha256sum "$anon_dump" | cut -d' ' -f1)" >> "$report"

sum_after=$(sha256sum "$source_dump" | cut -d' ' -f1)
if [[ $sum_after != "$sum_before" ]]; then
    echo "L'empreinte du dump source a changé pendant l'opération !" >&2
    exit 1
fi

cat "$report"
echo
echo "Fait : $anon_dump"
echo "Rapport : $report"
