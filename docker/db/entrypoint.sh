#!/bin/sh
# Crée le serveur à son premier démarrage, dans le dossier monté, aux droits de l'utilisateur du poste ;
# en développement seulement : confiance à tout le réseau interne, comme POSTGRES_HOST_AUTH_METHOD=trust.
set -eu
export PATH="/usr/lib/postgresql/18/bin:$PATH"
data=/var/lib/postgresql/data

# Le compte du poste peut n'être connu que de son annuaire : PostgreSQL veut lui trouver un nom
getent passwd "$(id -u)" >/dev/null || echo "bdoh-dev:x:$(id -u):$(id -g)::/var/lib/postgresql:/bin/sh" >> /etc/passwd

if [ ! -s "$data/PG_VERSION" ]; then
    initdb -D "$data" -U postgres -E UTF8 --locale=en_US.UTF-8 --auth=trust >/dev/null
    echo "listen_addresses = '*'" >> "$data/postgresql.conf"
    echo 'host all all all trust' >> "$data/pg_hba.conf"
fi

exec postgres -D "$data" "$@"
