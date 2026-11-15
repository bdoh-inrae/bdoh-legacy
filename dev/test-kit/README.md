# Le kit des essais

Ce qu'on envoie aux gestionnaires pour les essais, une fois que Lou a fait les siens sur la VM : un message court et un tableur par observatoire. Les gestionnaires INRAE essaient le site, sous VPN, et valident leur liste ; les autres valident leur liste. Une réponse par observatoire suffit. Le choix des cases `estvisible` et ses raisons sont dans `dev/decisions.md`, « Ouverture des données ».

```
make test-contacts SOURCE=…brut.dump     # Lou, dans son terminal : production, puis OTHU
make test-kit ARGS="--site https://… --deadline 'dimanche 15 novembre'"
```

- `make test-kit` écrit dans `../bdoh_reference/test-kit/<OBSERVATOIRE>/` le tableur et le message, depuis les bases anonymisées. Il ne reste à compléter à la main que le mot de passe d'accès.
- Lou relit la liste des destinataires de chaque observatoire, puis envoie les messages.
- `make test-contacts` écrit dans `../bdoh_reference/private-test-contacts/` les adresses par observatoire, et `allowed-recipients.env`, celles à laisser passer pendant les essais (la ligne `MAILER_ALLOWED_RECIPIENTS` du `.env` de la VM). Claude ne lit pas ce dossier : les bases qu'il ouvre n'ont que des adresses anonymisées, et ce script n'affiche que des comptes.
- Les réponses se suivent dans `dev/local/test-tracking.md`, ignoré par Git et sans aucune adresse. Une chronique à changer se change dans l'administration de la VM, qui deviendra la production.
- Les constats propres à une station ou une chronique viennent de `dev/local/test-kit-notes.csv`, hors de Git parce qu'il cite de vrais codes.

## Tranché (Lou, 10 novembre)

1. L'objet : « BDOH rouvre : ce qu'il me faut pour {observatoire} », sans durée, qu'on ne maîtrise pas.
2. La date limite : une semaine après l'envoi, qui attendait la carte des stations, faite le 10 novembre (Lou : les gestionnaires l'essaient aussi) ; elle s'allonge si des problèmes sérieux apparaissent.
3. Les destinataires : les gestionnaires de l'observatoire et de ses sites, son contact, le responsable et les gestionnaires de données Theia (ce que tire `contacts.py`). Lou relit la liste avant d'envoyer.
4. Sans réponse : une relance, puis `estvisible` fait foi, et c'est noté au suivi ; la Licence Ouverte s'applique alors sans validation explicite. Le message le dit.

Revue avec Lou, élément par élément, montré dans la discussion (10 novembre) : le message INRAE est validé (« nickel »), avec les changements ci-dessous et la carte des stations présentée. Restent à revoir de la même façon : le message hors INRAE, le tableur (ses deux onglets) et la liste des destinataires, que Lou regarde de son côté.

Le message commence par « Bonjour », dit que « normalement ça colle », donne l'identifiant et le mot de passe d'accès au site sur deux lignes, et prévient que c'est la vraie base : une modification destructrice se signale, pour repartir à l'ouverture sur le dump propre (Lou, 10 novembre).

Mis à jour le 13 novembre, à relire par Lou : un seul site, OTHU compris ; un quatrième changement, les conditions d'utilisation communes ; le post-scriptum sur le courriel de chaque nouveau compte retiré, ce courriel l'étant depuis la 0.5.0 ; un paragraphe propre à OTHU (la fusion, les libellés rapprochés, ses trois questions, l'accord de ses producteurs), à ORACLE (la fin de ses conditions propres) et à Arc-Isère (ses chroniques ouvertes d'EDF), tenu dans `SPECIAL` de `kit.py`.

Un seul message par observatoire, à tous ses destinataires (Lou, 15 novembre) : chaque observatoire a au moins un destinataire INRAE, qui essaie le site sous VPN dès l'envoi ; les autres font le tableau, et Lou leur écrit quand le site s'ouvre sans VPN (`ROADMAP.md`, « Le site d'essai sans VPN »). `messages/message.txt` reprend le message INRAE, avec la connexion par lien dite pour tous et l'accès sans VPN à venir. Mis à jour le 15 novembre : le message INRAE prévient de l'avertissement du navigateur (certificat auto-signé tant que le site n'a pas son nom) ; le paragraphe Theia demande de ressaisir le mot de passe Theia/OZCAR dans la fiche de l'observatoire, la VM ayant une clé neuve ; le message hors INRAE dit que l'adresse suivra si le site s'ouvre sans VPN (phrase validée par Lou). Contacts tirés et relus par Lou le 15 novembre ; chaque observatoire a au moins un destinataire INRAE.
