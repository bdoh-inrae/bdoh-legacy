# L'image du nouveau BDOH, plus PCOV : la couverture du PHP, le temps d'une passe (dev/coverage/README.md).
# À part, pour ne pas changer l'image qui décrit la production.
FROM bdoh-app

USER root
RUN apt-get update \
    && apt-get install -y --no-install-recommends php8.4-pcov \
    && rm -rf /var/lib/apt/lists/*
