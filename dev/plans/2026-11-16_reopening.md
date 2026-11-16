# La réouverture : pré-essai, essais, ouverture à tous et toutes

État : en cours, ouvert le 2026-11-16. Le plan que Lou a rapporté de son point du 16 novembre avec sa responsable : plus de temps pour les gestionnaires, plus d'accompagnement de l'ouverture des données, une réouverture mi-décembre. Il prend la suite de [2026-11-16_before-kit.md](2026-11-16_before-kit.md), dont la 0.7.1 est publiée et installée ; ses points 10 (relecture du contenu) et 12 (photographies) passent ici, avec leurs décisions là-bas.

## Le calendrier (Lou, 16 novembre)

- **16 novembre** : Lou invite la liste large des utilisateurs et utilisatrices de BDOH à une visio de présentation de la nouvelle plateforme, sur un sondage des deux semaines qui viennent.
- **Avant la visio** : un pré-essai par deux gestionnaires d'observatoire bien plus présents que les autres (noms dans `dev/local/test-tracking.md`) ; leurs retours avant ceux des autres testeurs et testeuses, et beaucoup de cas couverts d'emblée. L'une d'eux a fait les remarques de ce plan : le pré-essai vient une fois ce lot corrigé, avec le kit de leur observatoire, pour qu'ils voient aussi ce qui partira aux autres gestionnaires et les corrections qu'on leur propose (Lou, 16 novembre).
- **30 novembre** : les essais pour tous et toutes, deux semaines, avec le kit (message et tableur par observatoire).
- **Mi-décembre** : la réouverture à tous et toutes.
- **En parallèle** (Lou) : contacter OTHU et OHGE, une autre instance de BDOH, pas encore fondue, pour discuter de tout réunir dans l'instance actuelle : la preuve que BDOH porte toutes ces données, une gestion du service plus simple, et une seule base pour une future migration du modèle de données.

## Les versions (Lou, 16 novembre)

Pas de découpage en versions avec ou sans : tout ce qui est ici se fait avant le 30 novembre, pour les essais. Une version pour le pré-essai, dès que le lot de remarques (points 1 à 5, 7, 8) est fait ; puis celle des essais, avec les retours du pré-essai et le reste. Seules les listes de diffusion par observatoire peuvent attendre, si elles dépendent d'échanges avec l'administration d'INRAE.

## Les points

### 1. Les chroniques à accès restreint (Lou, 16 novembre)

Ce ne sont pas des données qu'on demande : ce sont les données intermédiaires des observatoires, réservées à leurs membres, dont BDOH ne peut pas assurer la diffusion en l'état ; d'où l'ancien mot « cachées ». Les textes ne doivent pas laisser croire que n'importe qui peut en demander l'accès, sans fermer la porte : qui en a besoin s'adresse aux partenaires responsables de leur diffusion.
- Aujourd'hui, la page des conditions (`Core/terms.html.twig`, « Les chroniques en accès restreint ») dit « leurs valeurs sont fournies sur demande : créez un compte, puis […] faites une demande d'accès ». À réécrire, en français et en anglais, comme la page de la demande d'accès (`BesoinController`) et ce que voit un visiteur sans droit sur une telle chronique.
- La demande d'accès reste, pour les membres d'un observatoire (un nouveau membre la fait encore) ; son texte le dit.
- Le producteur d'une chronique est renseigné pour la moitié d'entre elles (1 502 sur 3 012, `dev/plans/2026-11-04_open-data.md`). La liste des chroniques d'une station le montre déjà, chroniques réservées comprises (`Station/_chronique-inList.html.twig`) ; la page de la chronique aussi, mais un visiteur sans droit sur une chronique réservée y reçoit un refus (403). À creuser : dire, sur ce refus ou dans la liste, que la chronique est réservée à l'observatoire, et à qui s'adresser.

### 2. L'export

**a. « Le pas de temps est trop faible par rapport à la période sélectionnée. »** Une règle de l'ancien, dans le navigateur seulement (`export-modal.js`, `ALLOWED_YEARS_PER_MINUTE = 2`) : pour un export rééchantillonné (instantané, moyenne, cumul), la période ne dépasse pas 2 ans par minute de pas, soit 30 ans au pas de 15 minutes, environ un million de valeurs calculées par chronique. Une limite technique, sans raison scientifique ; le serveur ne la vérifie pas. La période entière par défaut (0.7.1) la rend visible dès l'ouverture de la fenêtre, sur toute chronique plus longue (Yzeron, dont un débit commence en 1969 : relevé de Lou, 16 novembre), avec un message qui ne dit ni la limite ni quoi faire, et un anglais tronqué. **Décidé (Lou, 16 novembre)** : on peut faire tout export que la plateforme permet scientifiquement ; cette règle part. Restent les règles scientifiques de chaque type : les pas permis pour une moyenne ou un cumul, un pas d'interpolation d'une minute à un jour, la moyenne et le cumul aux seuls formats BDOH et CSV. Le reste est une question de volume (b).

**b. Une seule règle de volume, quel que soit le type.** Aujourd'hui, sans compte, une demande de plus de 5 millions de mesures lues demande un compte, et une chronique seule passe toujours (`ExportController::isTooHeavyWithoutAccount`) ; avec un compte, aucune garde (`dev/differences.md`, « Ces gardes ne jouent pas pour un compte »). Sans la règle de a, une chronique seule interpolée à la minute sur 50 ans écrirait 26 millions de lignes, sans compte. Le volume d'une demande compte donc les valeurs écrites, pas seulement les mesures lues : pour un export à l'identique, les mesures de la période ; pour un rééchantillonnage, le nombre de pas de la période, par chronique. Au-dessus d'un premier seuil, un compte est demandé (le lien arrive par courriel) ; au-dessus d'un second, plus haut, l'export est refusé même avec un compte, pour qu'une demande sur tout BDOH ne bloque pas la file des heures, ni ne remplisse `/tmp` (8 Go sur la VM), où passent aussi les imports. Le temps, lui, dépend du type : sur le poste, la plus grosse chronique (2,4 millions de mesures) s'exporte en 43 s à l'identique et en 422 s en moyenne horaire (`dev/plans/2026-11-04_open-data.md`, « Les choix de Lou ») ; les seuils se règlent sur le cas le plus lent, mesuré sur la VM. Sans compte, la page de suivi donne le lien et son adresse le garde 7 jours, mais rien ne le dit : le dire, et proposer un compte (4). L'écart avec l'ancien s'inscrit dans `dev/differences.md`.

**c. Les noms des colonnes du CSV** (Lou, 16 novembre) : en anglais, les mêmes quelle que soit la langue, pour qu'un script lise tous les exports ; ce qui s'explique se donne dans les deux langues : le README, et les titres des colonnes dans la description CSVW, qui permet un titre par langue. Le détail (un README par langue ou un seul pour les deux) en passe de convergence avec Lou.

**d. Les métadonnées dans le format long** (Lou) : l'unité de la chronique dans chaque fichier de données, absolument ; d'autres (position de la station…) à peser, pour un équilibre gérable entre fichiers séparés et colonnes répétées. Un entre-deux pour les essais, ajusté sur les retours.

### 3. L'historique : « Utilisateur supprimé depuis » pour un export sans compte

Un export sans compte s'écrit sans auteur, et `historique/base.html.twig` affiche alors « Utilisateur supprimé depuis ». Mesuré sur la base complète anonymisée : avant l'ouverture, aucun export n'a d'auteur vide (une seule ligne de tout l'historique en a un, une administration de compte). Un export sans auteur est donc un téléchargement sans compte : l'afficher ainsi. Limite : la clé étrangère vide l'auteur à la suppression d'un compte (`ON DELETE SET NULL`) ; aucun compte n'est supprimé aujourd'hui.

### 4. Inviter à créer un compte (Lou)

Dans la fenêtre d'export sans compte, et sur sa page de suivi : ce qu'un compte apporte, les favoris et leurs étiquettes, le lien de l'export par courriel, les nouvelles des observatoires (5).

### 5. Des listes de diffusion (Lou, pour la confiance après l'ouverture)

Une liste technique des utilisateurs et utilisatrices du service, et une par observatoire, où ses gestionnaires envoient à la main les nouvelles de sa vie scientifique ; une page pour s'y inscrire (au pied de page), et la proposition à la création d'un compte et dans la fenêtre d'export (4).
- Côté BDOH : un champ par observatoire, l'adresse d'inscription à sa liste, vide tant qu'elle n'existe pas ; la page liste la liste technique et celles des observatoires qui en ont une. Rien d'autre à envoyer ni à garder : faisable pour les essais.
- Les listes elles-mêmes, à creuser par Lou : celles d'INRAE (`listes.inrae.fr`, où vivent déjà `bdoh.support` et la liste large des utilisateurs et utilisatrices), une par observatoire, créées à la main à la demande (une nouvelle liste est rare : un nouvel observatoire), avec ses gestionnaires pour propriétaires ; un observatoire hors INRAE (OTHU, OHGE) peut préférer la sienne, que le champ accepte aussi. L'autre voie, que BDOH envoie lui-même les messages écrits par les gestionnaires, demanderait plus de code et un traitement de données personnelles de plus ; écartée tant que les listes d'INRAE suffisent.

### 6. Les statistiques d'usage

`ROADMAP.md`, « Suivi de l'usage » : en public, consultations et téléchargements par chronique ; pour les gestionnaires, la vie de l'observatoire.

### 7. La relecture du contenu et les photographies

[2026-11-16_before-kit.md](2026-11-16_before-kit.md), points 10 et 12 : une migration globale pour les erreurs de forme (descriptions d'observatoire, DOI principaux évidents), les captures des accueils montrées à Lou d'abord ; les photographies sous Licence Ouverte.

### 8. Les messages

- Le pré-essai : le kit de leur observatoire (message et tableur, avec les corrections qu'on leur propose), précédé de quelques lignes qui disent ce qu'est le pré-essai.
- Le kit des essais : la visio, deux semaines, et un court paragraphe sur l'ouverture (la loi, ce qui ne change pas, la case « visible » qui reste aux gestionnaires, les chroniques réservées à l'observatoire) ; les photographies à vérifier ; la date limite.

### 9. Ce que demande la réouverture

Ce qui était « avant l'ouverture au public » tombe d'ici mi-décembre :
- le port 443 ouvert à Internet, la nouvelle adresse IP et le certificat (Lou, avec l'infra ; `dev/plans/2026-10-26_deployment.md`, Suivi du 16 novembre) ;
- les trois défauts relevés dans le JavaScript le 15 novembre (`dev/local/security.md`, « À corriger ») ;
- le juridique, en partie couvert par les essais : l'accord des gestionnaires sur leurs chroniques ouvertes vient avec les tableurs ; reste à savoir si l'accord d'un gestionnaire vaut celui d'un producteur extérieur (EDF pour Arc-Isère, INSA et UCBL pour OTHU), question que le kit leur pose déjà ; et les durées de conservation des comptes, que la page des mentions légales dit « à fixer » avec la déléguée à la protection des données, sans bloquer la réouverture (`ROADMAP.md`, « Suites de la passe juridique ») ;
- le mot de passe d'accès retiré, `MAILER_DIVERT_TO` et `MAILER_ALLOWED_RECIPIENTS` vidés (`dev/deploy/README.md`) ;
- la validation : invariants, pannes provoquées, relecture, puis le code gelé et toutes les passes ([2026-11-04_validation.md](2026-11-04_validation.md)).

## Suivi

- 2026-11-16 : ouvert, sur le plan de Lou ; les relevés des points 2 et 3 faits le même jour. Revu avec Lou le soir : plus de découpage en versions, le pré-essai après le lot de remarques et avec le kit, la limite période et pas retirée au profit d'une règle de volume, des colonnes en anglais, des listes de diffusion qui ne sont qu'un champ par observatoire côté BDOH.
