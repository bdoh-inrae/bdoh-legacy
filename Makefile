# Commandes du projet ; `make` seul les liste.

OUT ?= ../bdoh_reference
DUMP ?= $(OUT)/bdoh-205-2026-10-21-anon.dump
BASE ?= bdoh

.PHONY: help anonymize reference-db inspect

help: ## Liste les commandes
	@grep -E '^[a-z-]+:.*## ' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*## "} {printf "  %-13s %s\n", $$1, $$2}'

anonymize: ## Anonymise un dump de production : make anonymize SOURCE=chemin/du.dump [OUT=dossier]
	@test -n "$(SOURCE)" || { echo "Indiquer le dump : make anonymize SOURCE=chemin/du.dump"; exit 1; }
	dev/anonymize/anonymize.sh "$(SOURCE)" "$(OUT)"

reference-db: ## Restaure un dump anonymisé dans le serveur de référence : make reference-db [DUMP=… BASE=…]
	dev/reference/restore-db.sh "$(DUMP)" "$(BASE)"

inspect: ## Contrôles d'intégrité d'une base du serveur de référence, en comptes : make inspect [BASE=…]
	@docker exec -i bdoh-reference-db psql -U postgres -d "$(BASE)" -v ON_ERROR_STOP=1 -At -F '|' -q < dev/reference/inspect.sql | grep '|' | column -t -s '|'
