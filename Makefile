# Commandes du projet ; `make` seul les liste.
# SERVER choisit le serveur de base : reference (PostgreSQL 11, comme la production, par défaut) ou
# new (PostgreSQL 17, comme Debian 13, celui du nouveau BDOH).

OUT ?= ../bdoh_reference
DUMP ?= $(OUT)/bdoh-205-2026-10-21-anon.dump
BASE ?= bdoh
export SERVER ?= reference

# Lus par les fichiers compose et les scripts de dev/reference/.
export REFERENCE_DIR := $(abspath $(OUT))
export HOST_UID := $(shell id -u)
export HOST_GID := $(shell id -g)
REFERENCE = docker compose -f dev/reference/compose.yaml
NEW = docker compose -f compose.yaml
# Composer a besoin du réseau : il tourne dans un conteneur jetable, hors du réseau interne.
COMPOSER = docker run --rm --user $(HOST_UID):$(HOST_GID) -e HOME=/tmp -v $(CURDIR):/var/www/bdoh bdoh-app composer
NPM = docker run --rm --user $(HOST_UID):$(HOST_GID) -e HOME=/tmp -v $(CURDIR):/var/www/bdoh bdoh-app npm
# Encore 0.15 (webpack 3) de l'ancien BDOH, sous le Node 20 de Debian 13 : il lui faut les algorithmes
# de hachage qu'OpenSSL 3 range à part (dev/decisions.md, « JavaScript compilé comme l'ancien »).
ENCORE = docker run --rm --network none --user $(HOST_UID):$(HOST_GID) -e HOME=/tmp -e NODE_OPTIONS=--openssl-legacy-provider \
	-v $(CURDIR):/var/www/bdoh bdoh-app node_modules/.bin/encore
DB_CONTAINER = $(if $(filter new,$(SERVER)),bdoh-db,bdoh-reference-db)

.PHONY: help app-install assets start stop composer phpstan anonymize db db-small inspect reference-app reference-start reference-stop reference-base reference-17-start mail-start worker-start

help: ## Liste les commandes
	@grep -E '^[a-z0-9-]+:.*## ' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*## "} {printf "  %-19s %s\n", $$1, $$2}'

app-install: ## Installe le nouveau BDOH : image, dépendances, .env copié de .env.example
	$(NEW) build app
	test -f .env || cp .env.example .env
	$(COMPOSER) install --no-interaction
	mkdir -p public/css/themes public/uploads
	$(MAKE) assets

assets: ## Compile le JavaScript et les feuilles de style dans public/assets
	$(NPM) ci --no-audit --no-fund
	mkdir -p public/assets
	$(ENCORE) production

start: ## Lance le nouveau BDOH : http://172.30.26.10/
	$(NEW) up -d --wait db app

stop: ## Arrête le nouveau BDOH
	$(NEW) --profile worker --profile mail --profile pg17 stop

worker-start: ## Lance la file de tâches du nouveau BDOH, sur la base qu'il lit
	$(NEW) --profile worker up -d worker

composer: ## Lance Composer pour le nouveau BDOH : make composer ARGS="require un/paquet"
	$(COMPOSER) $(ARGS)

phpstan: ## Analyse statique du nouveau BDOH
	docker exec bdoh-app php -d memory_limit=-1 vendor/bin/phpstan analyse --no-progress

anonymize: ## Anonymise un dump de production : make anonymize SOURCE=chemin/du.dump [OUT=dossier]
	@test -n "$(SOURCE)" || { echo "Indiquer le dump : make anonymize SOURCE=chemin/du.dump"; exit 1; }
	dev/anonymize/anonymize.sh "$(SOURCE)" "$(OUT)"

db: ## Restaure un dump anonymisé : make db [DUMP=… BASE=… SERVER=…]
	dev/reference/restore-db.sh "$(DUMP)" "$(BASE)"

db-small: ## Tire de la base bdoh la base de travail réduite bdoh_small (mesures de 2024) [SERVER=…]
	dev/reference/small-db.sh

inspect: ## Contrôles d'intégrité d'une base, en comptes : make inspect [BASE=… SERVER=…]
	@docker exec -i $(DB_CONTAINER) psql -U postgres -d "$(BASE)" -v ON_ERROR_STOP=1 -At -F '|' -q < dev/reference/inspect.sql | grep '|' | column -t -s '|'

reference-app: ## Installe l'ancien BDOH, au commit en production, à côté des dumps (app/)
	dev/reference/install-app.sh

reference-start: ## Lance la référence (base, pages, file de tâches) : http://172.30.25.10/
	$(REFERENCE) up -d --wait

reference-stop: ## Arrête la référence
	$(REFERENCE) stop

reference-base: ## Branche la référence sur une autre base : make reference-base BASE=bdoh_small
	dev/reference/use-base.sh "$(BASE)"

reference-17-start: ## L'ancien code sur PostgreSQL 17, pour l'étape de la base : http://172.30.26.17/
	test -d $(REFERENCE_DIR)/app-17 || cp -a $(REFERENCE_DIR)/app $(REFERENCE_DIR)/app-17
	$(NEW) --profile pg17 up -d reference-app-17

mail-start: ## Retient les courriels du développement, ancien et nouveau : http://172.30.26.25:8025/
	$(NEW) --profile mail up -d mailpit
