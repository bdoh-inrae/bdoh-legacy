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
DB_CONTAINER = $(if $(filter new,$(SERVER)),bdoh-db,bdoh-reference-db)

.PHONY: help anonymize db db-small inspect reference-app reference-start reference-stop reference-base

help: ## Liste les commandes
	@grep -E '^[a-z-]+:.*## ' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*## "} {printf "  %-16s %s\n", $$1, $$2}'

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
