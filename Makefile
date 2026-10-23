# Commandes du projet ; `make` seul les liste.

OUT ?= ../bdoh_reference
DUMP ?= $(OUT)/bdoh-205-2026-10-21-anon.dump
BASE ?= bdoh

# Lus par dev/reference/compose.yaml et ses scripts.
export REFERENCE_DIR := $(abspath $(OUT))
export HOST_UID := $(shell id -u)
export HOST_GID := $(shell id -g)
COMPOSE = docker compose -f dev/reference/compose.yaml

.PHONY: help anonymize reference-db reference-app reference-start reference-stop inspect

help: ## Liste les commandes
	@grep -E '^[a-z-]+:.*## ' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*## "} {printf "  %-16s %s\n", $$1, $$2}'

anonymize: ## Anonymise un dump de production : make anonymize SOURCE=chemin/du.dump [OUT=dossier]
	@test -n "$(SOURCE)" || { echo "Indiquer le dump : make anonymize SOURCE=chemin/du.dump"; exit 1; }
	dev/anonymize/anonymize.sh "$(SOURCE)" "$(OUT)"

reference-db: ## Restaure un dump anonymisé dans le serveur de référence : make reference-db [DUMP=… BASE=…]
	dev/reference/restore-db.sh "$(DUMP)" "$(BASE)"

reference-app: ## Installe l'ancien BDOH, au commit en production, à côté des dumps (app/)
	dev/reference/install-app.sh

reference-start: ## Lance la référence (base, pages, file de tâches) : http://172.30.25.10/
	$(COMPOSE) up -d --wait

reference-stop: ## Arrête la référence
	$(COMPOSE) stop

inspect: ## Contrôles d'intégrité d'une base du serveur de référence, en comptes : make inspect [BASE=…]
	@docker exec -i bdoh-reference-db psql -U postgres -d "$(BASE)" -v ON_ERROR_STOP=1 -At -F '|' -q < dev/reference/inspect.sql | grep '|' | column -t -s '|'
