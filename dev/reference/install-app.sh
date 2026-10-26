#!/usr/bin/env bash
# Installe l'application de référence : le code de l'ancien BDOH au commit en production, extrait
# du dépôt d'origine sans le modifier, et ses dépendances, installées comme le faisait le
# déploiement (deploy.php : composer install --no-dev, puis composer install-assets).
#
#   dev/reference/install-app.sh [DÉPÔT_D_ORIGINE]
#
# Le dépôt d'origine est ../bdoh par défaut ; le code va dans REFERENCE_DIR/app. L'installation a
# besoin du réseau (paquets PHP et JavaScript) : elle tourne dans des conteneurs jetables, hors du
# réseau interne. Le script refuse d'écraser une installation ; pour repartir de zéro :
#   make reference-stop && rm -rf REFERENCE_DIR/app
set -euo pipefail

if [[ $# -gt 1 ]]; then
    sed -n '2,11p' "$0" | sed 's/^# \{0,1\}//'
    exit 1
fi

commit=523cf8e32  # version 5.0.3, en production depuis février 2025
node_image=node:12-buster  # la version de Node de l'image de développement d'origine
here=$(cd "$(dirname "$0")" && pwd)
legacy=$(realpath "${1:-$here/../../../bdoh}")
export REFERENCE_DIR=${REFERENCE_DIR:-$(realpath -m "$here/../../../bdoh_reference")}
export HOST_UID=${HOST_UID:-$(id -u)} HOST_GID=${HOST_GID:-$(id -g)}
app=$REFERENCE_DIR/app

[[ ! -e $app ]] || { echo "$app existe déjà." >&2; exit 1; }
git -C "$legacy" cat-file -e "$commit^{commit}" || { echo "Commit $commit absent de $legacy." >&2; exit 1; }

echo "Extraction du code au commit $commit…"
mkdir -p "$app"
git -C "$legacy" archive "$commit" | tar -x -C "$app"
cp "$here/app/parameters.yml" "$app/app/config/parameters.yml"
# Le code qui passe outre le captcha, pour que la comparaison remplisse les formulaires qui en ont un
# (dev/compare/compare.py, CAPTCHA_BYPASS). Le nouveau BDOH n'a plus de captcha.
cat >> "$app/app/config/config_prod.yml" <<'EOF'

# Ajouté par dev/reference/install-app.sh, pour la comparaison seulement.
gregwar_captcha:
    bypass_code: comparaison
EOF
mkdir -p "$app"/{app/imports,app/exports,web/css/themes,web/uploads}  # writable_dirs de deploy.php

docker compose -f "$here/compose.yaml" build app
in_php() { docker run --rm --user "$HOST_UID:$HOST_GID" -e HOME=/tmp -v "$app":/var/www/bdoh bdoh-reference-app "$@"; }

echo "Dépendances PHP…"
in_php composer install --no-dev --optimize-autoloader --prefer-dist --no-interaction --no-progress
echo "Traductions pour le JavaScript…"
in_php php app/console bazinga:js-translation:dump -n src/Irstea/BdohInternationalisationBundle/Resources/assets --merge-domains
in_php php app/console bazinga:js-translation:dump
echo "JavaScript et feuilles de style…"
docker run --rm --user "$HOST_UID:$HOST_GID" -e HOME=/tmp -v "$app":/app -w /app "$node_image" \
    sh -c 'npm ci --no-audit --no-fund && node_modules/.bin/encore production'

echo "Application installée dans $app : make reference-start"
