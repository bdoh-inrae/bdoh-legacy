# Le kit des essais

Ce qu'on envoie aux gestionnaires pour les essais, une fois que Lou a fait les siens sur la VM : un message court et un tableur par observatoire. Les gestionnaires essaient le site, ouvert à Internet derrière le mot de passe d'accès, et valident leur liste. Une réponse par observatoire suffit. Le choix des cases `estvisible` et ses raisons sont dans `dev/decisions.md`, « Ouverture des données ».

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
2. La date limite : deux semaines après l'envoi du 1er décembre (Lou, 16 et 19 novembre, d'abord le 30 novembre ; une semaine le 10 novembre) ; elle s'allonge si des problèmes sérieux apparaissent.
3. Les destinataires : les gestionnaires de l'observatoire et de ses sites, son contact, le responsable et les gestionnaires de données Theia (ce que tire `contacts.py`). Lou relit la liste avant d'envoyer.
4. Sans réponse : une relance, puis `estvisible` fait foi, et c'est noté au suivi ; la Licence Ouverte s'applique alors sans validation explicite. Le message ne le dit plus (Lou, 19 novembre, "trop violent") : la relance se fait sans l'annoncer, et on avise si elle reste sans réponse.

## Le message

`messages/message.txt`, que `kit.py` complète pour chaque observatoire ; un seul message par observatoire, à tous ses destinataires, INRAE ou non (Lou, 15 novembre). Revu avec Lou élément par élément le 10 novembre (« nickel »), puis dans ses mises à jour du 13 et du 15 ; le tableur (ses deux onglets) et les destinataires, relus par Lou le 15 novembre (le tableur : « parfait »). Il :

- commence par « Bonjour », dit que « normalement ça colle », et annonce les changements : la connexion par lien, les données ouvertes, la carte et l'apparence, les conditions communes avec l'aide et les gestionnaires dans un onglet, les blocs de citation à regarder, l'export CSV par défaut (0.7.1) ;
- donne l'identifiant et le mot de passe d'accès au site sur deux lignes, prévient de l'avertissement du navigateur (certificat auto-signé tant que le site n'a pas son nom), et que c'est la vraie base : les corrections restent, une modification destructrice se signale, et se répare depuis la sauvegarde faite avant les essais (Lou, 10 et 16 novembre ; `dev/plans/2026-11-16_before-kit.md`, « Les données après les essais ») ;
- ne parle pas du VPN : le site est ouvert à Internet pour l'envoi du 1er décembre, derrière le mot de passe d'accès (Lou, 19 novembre ; `ROADMAP.md`, "Le site d'essai sans VPN") ;
- porte des paragraphes propres à certains observatoires (`kit.py`) : Theia, seulement pour ceux qui ont des fiches sur le portail Theia/OZCAR (Draix, OHM-CV, ORACLE, Real Collobrier, Yzeron, vérifié le 15 novembre) : leurs fiches publiées ont des coordonnées décalées, corrigées au prochain envoi, et le mot de passe Theia/OZCAR est à ressaisir, la VM ayant une clé neuve ; et, dans `SPECIAL`, OTHU (la fusion, les libellés rapprochés, ses trois questions, l'accord de ses producteurs ; il a un code Theia mais aucune fiche publiée, `NOT_ON_THEIA`), ORACLE (la fin de ses conditions propres) et Arc-Isère (ses chroniques ouvertes d'EDF).

Mis à jour pour la 0.7.1 le 16 novembre, puis pour la réouverture le 19, avec Lou : l'ouverture en une phrase (la politique de science ouverte d'INRAE, et des choses plus simples pour tous), la chronique cachée réservée aux membres de l'observatoire, les photographies ouvertes comme les données, une porte ouverte aux questions. La visio a lieu avant les essais : le message n'y renvoie pas. Le second onglet des tableurs est complété par la relecture du 19 novembre (`dev/plans/2026-11-19_review.md`).

Écrire aux gestionnaires (Lou, 19 novembre) : simple et sans insister. Pas de texte de loi, qui sonnerait comme une menace : la visio explique l'ouverture, et le message reste ouvert à la discussion. On demande plutôt qu'on impose (« dites-moi si ça vous va », jamais « retirez-les ») : ce sont leurs données et leur observatoire.

## Le pré-essai

`messages/pre-test.txt` : quelques lignes que Lou met au-dessus du kit de leur observatoire, pour les deux gestionnaires du pré-essai (`dev/local/test-tracking.md`), des collègues proches : le tutoiement collectif, le but et le contexte en deux phrases, sans justification. Le pré-essai part au plus tard le lundi 24 novembre (Lou, 19 novembre) ; `{date}`, celle de leur retour, avant le 1er décembre, se remplit à l'envoi. `kit.py` ne l'écrit pas : Lou le colle à la main.
