# Le kit des essais

Ce qu'on envoie aux gestionnaires pour les essais, une fois que Lou a fait les siens sur la VM : un message court et un tableur par observatoire. Les gestionnaires INRAE essaient le site, sous VPN, et valident leur liste ; les autres valident leur liste. Une réponse par observatoire suffit. Le choix des cases `estvisible` et ses raisons sont dans `dev/decisions.md`, « Ouverture des données ».

```
make test-contacts SOURCE=…brut.dump     # Lou, dans son terminal : production, puis OTHU
make test-kit ARGS="--site https://… --deadline 'lundi 23 novembre'"
```

- `make test-kit` écrit dans `../bdoh_reference/test-kit/<OBSERVATOIRE>/` le tableur et le message, depuis les bases anonymisées. Il ne reste à compléter à la main que le mot de passe d'accès.
- Lou relit la liste des destinataires de chaque observatoire, puis envoie les messages.
- `make test-contacts` écrit dans `../bdoh_reference/private-test-contacts/` les adresses par observatoire, et `allowed-recipients.env`, celles à laisser passer pendant les essais (la ligne `MAILER_ALLOWED_RECIPIENTS` du `.env` de la VM). Claude ne lit pas ce dossier : les bases qu'il ouvre n'ont que des adresses anonymisées, et ce script n'affiche que des comptes.
- Les réponses se suivent dans `dev/local/test-tracking.md`, ignoré par Git et sans aucune adresse. Une chronique à changer se change dans l'administration de la VM, qui deviendra la production.
- Les constats propres à une station ou une chronique viennent de `dev/local/test-kit-notes.csv`, hors de Git parce qu'il cite de vrais codes.

## Tranché (Lou, 10 novembre)

1. L'objet : « BDOH rouvre : ce qu'il me faut pour {observatoire} », sans durée, qu'on ne maîtrise pas.
2. La date limite : deux semaines après l'envoi du 30 novembre (Lou, 16 novembre ; une semaine le 10 novembre) ; elle s'allonge si des problèmes sérieux apparaissent.
3. Les destinataires : les gestionnaires de l'observatoire et de ses sites, son contact, le responsable et les gestionnaires de données Theia (ce que tire `contacts.py`). Lou relit la liste avant d'envoyer.
4. Sans réponse : une relance, puis `estvisible` fait foi, et c'est noté au suivi ; la Licence Ouverte s'applique alors sans validation explicite. Le message le dit.

## Le message

`messages/message.txt`, que `kit.py` complète pour chaque observatoire ; un seul message par observatoire, à tous ses destinataires, INRAE ou non (Lou, 15 novembre). Revu avec Lou élément par élément le 10 novembre (« nickel »), puis dans ses mises à jour du 13 et du 15 ; le tableur (ses deux onglets) et les destinataires, relus par Lou le 15 novembre (le tableur : « parfait »). Il :

- commence par « Bonjour », dit que « normalement ça colle », et annonce les changements : la connexion par lien, les données ouvertes, la carte et l'apparence, les conditions communes avec l'aide et les gestionnaires dans un onglet, les blocs de citation à regarder, l'export CSV par défaut (0.7.1) ;
- donne l'identifiant et le mot de passe d'accès au site sur deux lignes, prévient de l'avertissement du navigateur (certificat auto-signé tant que le site n'a pas son nom), et que c'est la vraie base : une modification destructrice se signale, pour repartir à l'ouverture sur le dump propre (Lou, 10 novembre) ;
- dit que le site n'est accessible pour l'instant que depuis le réseau d'INRAE, sous le VPN ou en filaire depuis un bureau (Lou, 16 novembre) : chaque observatoire a au moins un destinataire INRAE, qui l'essaie dès l'envoi ; les autres font le tableau, et Lou leur écrit quand le site s'ouvre sans VPN (`ROADMAP.md`, « Le site d'essai sans VPN ») ;
- porte des paragraphes propres à certains observatoires (`kit.py`) : Theia, seulement pour ceux qui ont des fiches sur le portail Theia/OZCAR (Draix, OHM-CV, ORACLE, Real Collobrier, Yzeron, vérifié le 15 novembre) : leurs fiches publiées ont des coordonnées décalées, corrigées au prochain envoi, et le mot de passe Theia/OZCAR est à ressaisir, la VM ayant une clé neuve ; et, dans `SPECIAL`, OTHU (la fusion, les libellés rapprochés, ses trois questions, l'accord de ses producteurs ; il a un code Theia mais aucune fiche publiée, `NOT_ON_THEIA`), ORACLE (la fin de ses conditions propres) et Arc-Isère (ses chroniques ouvertes d'EDF).

Mis à jour pour la 0.7.1 le 16 novembre ; à revoir pour la réouverture (la visio, deux semaines, l'ouverture expliquée, les photographies : `dev/plans/2026-11-16_reopening.md`, point 8), puis à relire par Lou. Le second onglet des tableurs attend la passe de relecture du contenu (même plan, point 7).
