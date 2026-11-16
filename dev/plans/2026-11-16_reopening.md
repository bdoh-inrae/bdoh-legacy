# La réouverture : pré-essai, essais, ouverture à tous et toutes

État : en cours, ouvert le 2026-11-16. Le plan que Lou a rapporté de son point du 16 novembre avec sa responsable : plus de temps pour les gestionnaires, plus d'accompagnement de l'ouverture des données, une réouverture mi-décembre. Il prend la suite de [2026-11-16_before-kit.md](2026-11-16_before-kit.md), dont la 0.7.1 est publiée et installée ; ses points 10 (relecture du contenu) et 12 (photographies) passent ici, avec leurs décisions là-bas.

## Le calendrier (Lou, 16 novembre)

- **16 novembre** : Lou invite la liste large des utilisateurs et utilisatrices de BDOH à une visio de présentation de la nouvelle plateforme, sur un sondage des deux semaines qui viennent.
- **Avant la visio** : un pré-essai par deux gestionnaires d'observatoire bien plus présents que les autres (noms dans `dev/local/test-tracking.md`) ; leurs retours avant ceux des autres testeurs et testeuses, et beaucoup de cas couverts d'emblée.
- **30 novembre** : les essais pour tous et toutes, deux semaines, avec le kit (message et tableur par observatoire).
- **Mi-décembre** : la réouverture à tous et toutes.
- **En parallèle** (Lou) : contacter OTHU et OHGE, une autre instance de BDOH, pas encore fondue, pour discuter de tout réunir dans l'instance actuelle : la preuve que BDOH porte toutes ces données, une gestion du service plus simple, et une seule base pour une future migration du modèle de données.

## Les versions (proposition de Claude, à confirmer par Lou)

- **Pré-essai sur la 0.7.1**, telle qu'elle est sur la VM, dès que Lou l'envoie : elle a tout l'essentiel, et les deux gestionnaires ont ainsi le plus de temps.
- **0.8.0, la version des essais**, installée au plus tard le 27 novembre : les points 1 à 4, 7 et 8 ; 5 si les listes sont celles d'INRAE (de simples liens).
- **0.9.0, la réouverture** : les retours des essais, 5 si les listes sont dans BDOH, 6, et ce que demande l'ouverture (9).

## Les points

### 1. Les chroniques à accès restreint (Lou, 16 novembre)

Ce ne sont pas des données qu'on demande : ce sont les données intermédiaires des observatoires, réservées à leurs membres, dont BDOH ne peut pas assurer la diffusion en l'état ; d'où l'ancien mot « cachées ». Les textes ne doivent pas laisser croire que n'importe qui peut en demander l'accès, sans fermer la porte : qui en a besoin s'adresse aux partenaires responsables de leur diffusion.
- Aujourd'hui, la page des conditions (`Core/terms.html.twig`, « Les chroniques en accès restreint ») dit « leurs valeurs sont fournies sur demande : créez un compte, puis […] faites une demande d'accès ». À réécrire, en français et en anglais, comme la page de la demande d'accès (`BesoinController`) et ce que voit un visiteur sans droit sur une telle chronique.
- La demande d'accès reste, pour les membres d'un observatoire (un nouveau membre la fait encore) ; son texte le dit.
- Le producteur d'une chronique est renseigné pour la moitié d'entre elles (1 502 sur 3 012, `dev/plans/2026-11-04_open-data.md`) : le montrer sur sa page dirait à qui s'adresser.

### 2. L'export

**a. « Le pas de temps est trop faible par rapport à la période sélectionnée. »** Une règle de l'ancien, dans le navigateur seulement (`export-modal.js`, `ALLOWED_YEARS_PER_MINUTE = 2`) : pour un export rééchantillonné (instantané, moyenne, cumul), la période ne dépasse pas 2 ans par minute de pas, soit 30 ans au pas de 15 minutes, environ un million de valeurs calculées par chronique. Ni le compte ni le seuil de 5 millions n'y sont pour rien ; le serveur ne la vérifie pas. La période entière par défaut (0.7.1) la rend visible dès l'ouverture de la fenêtre, sur toute chronique plus longue (Yzeron, dont un débit commence en 1969 : relevé de Lou, 16 novembre). Le message ne dit ni la limite ni quoi faire, et l'anglais est tronqué (« The time step is too with regard to the selected period »). À faire : un message qui dit la limite et quoi faire (« au pas de 15 min, 30 ans au plus : réduisez la période ou augmentez le pas ») ; revoir la règle elle-même en nombre de valeurs, cohérente avec le seuil, une fois mesurée (c).

**b. Le seuil de 5 millions de mesures sans compte.** Choisi le 4 novembre sur une mesure du poste (`dev/plans/2026-11-04_open-data.md`, « Les choix de Lou ») : la plus grosse chronique, 2,4 millions de mesures, s'exporte en 43 s à l'identique et en 422 s en moyenne horaire ; au pire une quinzaine de minutes de file. Une chronique seule passe toujours, quelle que soit sa taille. À mesurer sur la VM : un export d'environ 5 millions de mesures, à l'identique et en moyenne, pour régler le seuil. Sans compte, la page de suivi donne le lien ; son adresse le garde 7 jours, mais rien ne le dit : le dire, et proposer un compte, qui reçoit le lien par courriel (4).

**c. Avec un compte, aucune garde** (`dev/differences.md`, « Ces gardes ne jouent pas pour un compte ») : des favoris sur tout BDOH partent en un export par observatoire, sans plafond. La file les fait un à un, après les calculs des gestionnaires, qui ne sont donc pas bloqués ; mais les exports des autres attendent des heures, et `/tmp` (8 Go sur la VM) peut se remplir pendant la construction d'une archive, ce qui ferait échouer aussi les imports. À faire : un plafond par demande, même avec un compte, plus haut que celui sans compte (nombre tiré des mesures de b).

**d. Les noms des colonnes du CSV** (Lou) : en français ou en anglais selon la langue de BDOH, les français proches de ceux de l'ancien format. Passe de convergence avec Lou, avant la 0.8.0. À peser : un script qui lit les colonnes par leur nom ne lit plus un export fait dans l'autre langue ; le README (lecture en R et en Python) et la description CSVW suivent.

**e. Les métadonnées dans le format long** (Lou) : l'unité de la chronique dans chaque fichier de données, absolument ; d'autres (position de la station…) à peser, pour un équilibre gérable entre fichiers séparés et colonnes répétées. Un entre-deux pour les essais, ajusté sur les retours.

### 3. L'historique : « Utilisateur supprimé depuis » pour un export sans compte

Un export sans compte s'écrit sans auteur, et `historique/base.html.twig` affiche alors « Utilisateur supprimé depuis ». Mesuré sur la base complète anonymisée : avant l'ouverture, aucun export n'a d'auteur vide (une seule ligne de tout l'historique en a un, une administration de compte). Un export sans auteur est donc un téléchargement sans compte : l'afficher ainsi. Limite : la clé étrangère vide l'auteur à la suppression d'un compte (`ON DELETE SET NULL`) ; aucun compte n'est supprimé aujourd'hui.

### 4. Inviter à créer un compte (Lou)

Dans la fenêtre d'export sans compte, et sur sa page de suivi : ce qu'un compte apporte, les favoris et leurs étiquettes, le lien de l'export par courriel, les nouvelles des observatoires (5).

### 5. Des listes de diffusion (Lou, pour la confiance après l'ouverture)

Une liste technique des utilisateurs et utilisatrices du service, et une par observatoire, où ses gestionnaires envoient leurs nouvelles ; une page pour s'y inscrire (au pied de page), et la proposition à la création d'un compte. Deux voies, à trancher par Lou :
- **les listes d'INRAE** (`listes.inrae.fr`, où vit déjà `bdoh.support`) : une par observatoire, créée à la demande par la DSI, ses gestionnaires propriétaires ; BDOH ne fait que montrer le lien d'inscription (un champ par observatoire). Peu de code, l'envoi, les archives et la désinscription tenus par le serveur de listes.
- **dans BDOH** : un bouton « Suivre » par observatoire, les nouvelles écrites dans l'administration et envoyées par la file, un lien de désinscription dans chaque courriel. Automatique pour chaque observatoire, lié au compte ; mais du code, des envois en nombre par le relais d'INRAE, et un traitement de données personnelles de plus à déclarer.

### 6. Les statistiques d'usage

`ROADMAP.md`, « Suivi de l'usage » : en public, consultations et téléchargements par chronique ; pour les gestionnaires, la vie de l'observatoire.

### 7. La relecture du contenu et les photographies

[2026-11-16_before-kit.md](2026-11-16_before-kit.md), points 10 et 12 : une migration globale pour les erreurs de forme (descriptions d'observatoire, DOI principaux évidents), les captures des accueils montrées à Lou d'abord ; les photographies sous Licence Ouverte.

### 8. Les messages

- Le pré-essai : un message court aux deux gestionnaires, sur la 0.7.1.
- Le kit des essais : la visio, deux semaines, et un court paragraphe sur l'ouverture (la loi, ce qui ne change pas, la case « visible » qui reste aux gestionnaires, les chroniques réservées à l'observatoire) ; les photographies à vérifier ; la date limite.

### 9. Ce que demande la réouverture

Ce qui était « avant l'ouverture au public » tombe d'ici mi-décembre :
- le port 443 ouvert à Internet, la nouvelle adresse IP et le certificat (Lou, avec l'infra ; `dev/plans/2026-10-26_deployment.md`, Suivi du 16 novembre) ;
- les trois défauts relevés dans le JavaScript le 15 novembre (`dev/local/security.md`, « À corriger ») ;
- la déléguée à la protection des données (durées de conservation des comptes) et la direction des affaires juridiques (l'accord des producteurs extérieurs, INSA, UCBL, EDF) : `ROADMAP.md`, « Suites de la passe juridique » ;
- le mot de passe d'accès retiré, `MAILER_DIVERT_TO` et `MAILER_ALLOWED_RECIPIENTS` vidés (`dev/deploy/README.md`) ;
- la validation : invariants, pannes provoquées, relecture, puis le code gelé et toutes les passes ([2026-11-04_validation.md](2026-11-04_validation.md)).

## Suivi

- 2026-11-16 : ouvert, sur le plan de Lou ; les relevés des points 2 et 3 faits le même jour.
