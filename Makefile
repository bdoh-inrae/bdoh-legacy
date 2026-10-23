# Commandes du projet ; `make` seul les liste.

OUT ?= ../bdoh_reference

.PHONY: help anonymize

help: ## Liste les commandes
	@grep -E '^[a-z-]+:.*## ' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*## "} {printf "  %-10s %s\n", $$1, $$2}'

anonymize: ## Anonymise un dump de production : make anonymize SOURCE=chemin/du.dump [OUT=dossier]
	@test -n "$(SOURCE)" || { echo "Indiquer le dump : make anonymize SOURCE=chemin/du.dump"; exit 1; }
	dev/anonymize/anonymize.sh "$(SOURCE)" "$(OUT)"
