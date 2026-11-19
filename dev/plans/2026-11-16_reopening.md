# La réouverture : pré-essai, essais, ouverture à tous et toutes

État : en cours, ouvert le 2026-11-16. Le plan que Lou a rapporté de son point du 16 novembre avec sa responsable : plus de temps pour les gestionnaires, plus d'accompagnement de l'ouverture des données, une réouverture mi-décembre. Il prend la suite de [2026-11-16_before-kit.md](2026-11-16_before-kit.md), terminé avec la 0.7.1 (publiée et installée sur la VM, au nom `bdoh.inrae.fr`) ; les décisions de ses points 10 (relecture du contenu) et 12 (photographies) y restent, leur réalisation se suit ici (point 7).

Fait sur `main` dans la nuit du 16 au 17 novembre, sans version : les points 1 à 4, l'unité du point 2d et le point 7 bis, toutes les passes comprises (Suivi). Ce qui y attend Lou est dans `dev/local/notes-for-lou.md`. « Pour reprendre », en bas, donne l'ordre de travail.

## Le calendrier (Lou, 16 novembre)

- **16 novembre** : Lou invite la liste large des utilisateurs et utilisatrices de BDOH à une visio de présentation de la nouvelle plateforme, sur un sondage des deux semaines qui viennent.
- **Avant les essais**, pas forcément avant la visio (Lou, 18 novembre) : un pré-essai par deux gestionnaires d'observatoire bien plus présents que les autres (noms dans `dev/local/test-tracking.md`). Les remarques des points 1 à 4 viennent de l'une des deux personnes du pré-essai : il vient une fois ce lot corrigé, avec le kit de leur observatoire, pour qu'ils voient aussi ce qui partira aux autres gestionnaires et les corrections qu'on leur propose. Leurs retours viennent avant ceux des autres, et couvrent déjà beaucoup de cas.
- **30 novembre** : les essais pour tous et toutes, deux semaines, avec le kit (message et tableur par observatoire).
- **Mi-décembre** : la réouverture à tous et toutes.
- **En parallèle** (Lou) : contacter OTHU et OHGE (une autre instance de BDOH, pas encore fondue) pour discuter de tout réunir dans l'instance actuelle : la preuve que BDOH porte toutes ces données, une gestion du service plus simple, une seule base pour une future migration du modèle de données. OTHU est déjà fondu sur la VM (0.6.0) ; reste à en parler avec ses gestionnaires.

## Les versions

Comme d'habitude (`~/.claude/rules/project.md`, « Les versions ») : une version se publie quand « Non publié » forme un ensemble utilisable, sans numéro fixé d'avance (Lou, 16 novembre : on ne sait pas encore à quelle vitesse on avancera). Deux repères seulement :
- le pré-essai demande une version installée sur la VM avec au moins les points 1 à 4, 7 et 8 ;
- les essais du 30 novembre, tout ce qu'on peut du reste, avec les retours du pré-essai. Seules les listes de diffusion par observatoire (5) peuvent attendre, si elles dépendent d'échanges avec l'administration d'INRAE.

## Les points

Chaque point dit ce qui est décidé, et ce qui attend Lou avant d'être codé.

### 1. Les chroniques réservées à l'observatoire

**Décidé (Lou, 16 novembre).** Les chroniques à accès restreint (`estvisible = false`, « cachées » dans l'ancien) ne sont pas des données qu'on demande : ce sont les données intermédiaires des observatoires, réservées à leurs membres, dont BDOH ne peut pas assurer la diffusion en l'état. Les textes ne doivent pas laisser croire que n'importe qui peut en demander l'accès, sans fermer la porte. Texte retenu par Lou, à décliner en français et en anglais : elles sont réservées aux membres de l'observatoire pour son travail interne ; BDOH n'en assure pas la diffusion : pour les obtenir, s'adresser aux partenaires qui la portent.
- À réécrire : la page des conditions (`Core/terms.html.twig`, section « Les chroniques en accès restreint », qui dit aujourd'hui « leurs valeurs sont fournies sur demande : créez un compte, puis […] faites une demande d'accès »), la page de la demande d'accès (`BesoinController`), et ce que voit un visiteur sans droit sur une telle chronique.
- La demande d'accès reste, pour les membres d'un observatoire (un nouveau membre la fait encore) ; son texte le dit.
- **À proposer à Lou, en captures** : la page d'une chronique réservée répond aujourd'hui par un refus sec (403) à un visiteur sans droit. Le producteur, quand il est renseigné (1 502 chroniques sur 3 012), se voit déjà dans la liste des chroniques d'une station, chroniques réservées comprises (`Station/_chronique-inList.html.twig`). Proposer, sur ce refus ou dans la liste, de dire que la chronique est réservée à l'observatoire, et à qui s'adresser.

### 2. L'export

**a. La limite entre période et pas part.** Décidé (Lou, 16 novembre) : on peut faire tout export que la plateforme permet scientifiquement. « Le pas de temps est trop faible par rapport à la période sélectionnée » vient d'une règle de l'ancien, dans le navigateur seulement (`export-modal.js`, `ALLOWED_YEARS_PER_MINUTE = 2`) : pour un export rééchantillonné (instantané, moyenne, cumul), la période ne dépasse pas 2 ans par minute de pas, soit 30 ans au pas de 15 minutes. Une garde technique, sans raison scientifique, que le serveur ne vérifie pas. La période entière par défaut (0.7.1) la rend visible dès l'ouverture de la fenêtre sur toute chronique plus longue (un débit d'Yzeron qui commence en 1969 : relevé de Lou). Restent les règles scientifiques de chaque type : les pas permis pour une moyenne ou un cumul, un pas d'interpolation d'une minute à un jour, la moyenne et le cumul aux seuls formats BDOH et CSV.

**b. Une seule règle de volume, quel que soit le type.** Décidé (Lou, 16 novembre) dans son principe ; les seuils attendent une mesure sur la VM.
- Aujourd'hui : sans compte, une demande de plus de 5 millions de mesures lues demande un compte, et une chronique seule passe toujours (`ExportController::isTooHeavyWithoutAccount`, `MAX_MEASURES_WITHOUT_ACCOUNT`) ; avec un compte, aucune garde (`dev/differences.md`, « Ces gardes ne jouent pas pour un compte »), si bien que des favoris sur tout BDOH partent sans plafond, un export par observatoire.
- Le volume d'une demande compte les valeurs écrites : pour un export à l'identique, les mesures de la période ; pour un rééchantillonnage, le nombre de pas de la période ; la somme sur les chroniques de la demande. Sans la limite de a, une chronique seule interpolée à la minute sur 50 ans écrirait 26 millions de lignes : une chronique seule n'est donc plus exemptée.
- Deux seuils : au-dessus du premier, un compte est demandé (le lien arrive par courriel) ; au-dessus du second, plus haut, l'export est refusé même avec un compte, pour qu'une demande ne bloque pas la file des heures, ni ne remplisse `/tmp` (8 Go sur la VM), où passent aussi les imports. Le message de refus dit le volume demandé, le seuil, et quoi faire (réduire la période, le nombre de chroniques, ou augmenter le pas).
- Les seuils se règlent sur le type le plus lent : sur le poste, la plus grosse chronique (2,4 millions de mesures) s'exporte en 43 s à l'identique et en 422 s en moyenne horaire (`dev/plans/2026-11-04_open-data.md`, « Les choix de Lou »). Jusqu'à la mesure sur la VM (Lou, avec une marche à suivre que Claude prépare), le premier reste à 5 millions, et le second se pose provisoirement d'après les mesures du poste, dit comme tel dans `dev/differences.md`.
- Sans compte, la page de suivi donne le lien, et son adresse le garde 7 jours, mais rien ne le dit : le dire, et proposer un compte (point 4).
- Écart avec l'ancien : entrée dans `dev/differences.md` ; cas de `compare.py` pour chaque seuil, avec et sans compte, et pour une chronique seule rééchantillonnée au-delà du premier.

**c. Les noms des colonnes du CSV.** Décidé (Lou, 16 novembre) : en anglais, les mêmes quelle que soit la langue de BDOH, pour qu'un script lise tous les exports. Ce qui s'explique se donne dans les deux langues : le README, et les titres des colonnes dans la description CSVW (un titre par langue). **À voir avec Lou** : un README par langue, ou un seul pour les deux.

**d. Les métadonnées dans le format long.** Décidé (Lou, 16 novembre) : l'unité de la chronique dans chaque fichier de données, absolument. **À voir avec Lou** : d'autres métadonnées dans les fichiers de données (position de la station…), pour un équilibre gérable entre fichiers séparés et colonnes répétées ; un entre-deux pour les essais, ajusté sur les retours. Ce qui change le CSV se répercute dans le README (lecture en R et en Python), la description CSVW, `compare.py` (mode `exports`) et `dev/browser/check.py`.

### 3. L'historique : un export sans compte

Décidé : un export sans compte s'écrit sans auteur, et `historique/base.html.twig` affiche alors « Utilisateur supprimé depuis », ce qui trompe. Mesuré sur la base complète anonymisée : avant l'ouverture, aucun export n'a d'auteur vide (une seule ligne de tout l'historique en a un, une administration de compte). Un export sans auteur est donc un téléchargement sans compte : l'afficher ainsi (« Sans compte »). Limite connue : la clé étrangère vide l'auteur à la suppression d'un compte (`ON DELETE SET NULL`) ; aucun compte n'est supprimé aujourd'hui.

### 4. Inviter à créer un compte

Décidé (Lou, 16 novembre) : dans la fenêtre d'export sans compte, et sur sa page de suivi, dire ce qu'un compte apporte : les favoris et leurs étiquettes, le lien de l'export par courriel, les nouvelles des observatoires (point 5, quand leurs listes existent).

### 5. Des listes de diffusion

Voulu par Lou (16 novembre), pour la confiance après l'ouverture : une liste technique des utilisateurs et utilisatrices du service, et une par observatoire, où ses gestionnaires envoient à la main les nouvelles de sa vie scientifique ; une page pour s'y inscrire (au pied de page), et la proposition à la création d'un compte et dans la fenêtre d'export. **À voir avec Lou avant tout code** (« on voit ensemble après »). La piste de Claude, à lui expliquer :
- côté BDOH, un champ par observatoire, l'adresse d'inscription à sa liste, vide tant qu'elle n'existe pas ; la page montre la liste technique et celles des observatoires qui en ont une ; BDOH n'envoie rien lui-même ;
- les listes elles-mêmes sur le serveur de listes d'INRAE (`listes.inrae.fr`, où vivent déjà `bdoh.support` et la liste large des utilisateurs et utilisatrices), créées à la main, une par observatoire, ses gestionnaires pour propriétaires ; un observatoire hors INRAE (OTHU, OHGE) peut avoir la sienne ailleurs, que le champ accepte aussi. Ce qu'INRAE permet est à creuser par Lou.
- Les listes d'observatoire et la liste technique sont à inscription : personne n'y est mis d'office (Lou, 19 novembre). Une exception voulue par Lou, si c'est possible : une liste des gestionnaires d'observatoire qui suit d'elle-même leurs droits dans BDOH, un gestionnaire y entrant quand il reçoit le rôle et en sortant quand il le perd. À regarder : Sympa (le logiciel de `listes.inrae.fr`) sait remplir une liste depuis une source extérieure, un fichier à une adresse web ou une requête (« sources de données » d'une liste) ; BDOH exposerait alors, derrière un accès réservé, la liste des adresses des gestionnaires. Ce qu'INRAE ouvre aux propriétaires de listes est à demander.

### 6. Les statistiques d'usage

Décidé (Lou, 16 novembre) : en public, les consultations et les téléchargements de chaque chronique ; pour les gestionnaires, la vie de l'observatoire. Un comptage à nous, Make Data Count laissé de côté pour cette version. Le détail, les mesures de l'historique et ses pièges (nomenclature changée le 20 novembre 2018) : `ROADMAP.md`, « Suivi de l'usage ». La présentation se montre à Lou en captures avant d'être finie.

### 7. La relecture du contenu et les photographies

En cours depuis le 19 novembre, élargie par Lou aux textes de l'interface, dans une session à part : [2026-11-19_review.md](2026-11-19_review.md), avec son registre.

Décidés dans [2026-11-16_before-kit.md](2026-11-16_before-kit.md) :
- point 10 : une passe de relecture du contenu de la base ; ses erreurs de forme (descriptions d'observatoire copiées de Word, paragraphes vides, DOI principaux évidents) dans une migration globale, écrite à la main, avec son retour arrière, essayée sur la base complète anonymisée ; **les captures des cinq accueils, avant et après, montrées à Lou avant d'écrire la migration** ; ses erreurs plus importantes au second onglet des tableurs du kit (`dev/local/test-kit-notes.csv`) ;
- point 12 : les photographies sous Licence Ouverte (aide des deux champs du formulaire d'un observatoire, page des conditions, mentions légales, une ligne du kit).

Ajouté par Lou le 16 novembre au soir, pour la migration globale : retirer le logo d'INRAE là où il sert de logo d'observatoire. INRAE n'est pas un observatoire mais un partenaire : son logo a sa place à l'onglet « Partenaires », pas à côté du nom d'un observatoire. À relever d'abord (quels observatoires, quel fichier), à montrer dans les captures des accueils, avec son retour arrière. Relevé le 16 novembre sur les fichiers de la production copiés sur le poste : les logos d'Arc-Isère et de Haute-Loire sont le fichier du logo du partenaire INRAE, octet pour octet (`uploads/ARC-ISERE-logo.jpg`, `uploads/HAUTE-LOIRE-logo.jpg`, `uploads/INRAE-logo.jpg`) ; celui du Site Atelier Ardières-Morcille est le bloc Marianne et INRAE (vu sur l'accueil de BDOH le 17 novembre) ; les autres logos d'observatoire sont à regarder un à un.

### 7 bis. Deux retouches de l'apparence (Lou, 16 novembre au soir)

- Sur l'accueil d'un observatoire, son logo passe à gauche du bloc de citation, au lieu d'être à côté du titre : il s'y intègre mieux.
- Sur l'accueil de BDOH, le logo de chaque observatoire à gauche de son titre, le titre centré en hauteur par rapport au logo.

À faire avec la première partie, et à montrer à Lou en captures.

### 8. Les messages

- Le pré-essai : le kit de leur observatoire (message et tableur, avec les corrections qu'on leur propose), précédé de quelques lignes qui disent ce qu'est le pré-essai. `make test-kit` écrit les kits de tous les observatoires ; Lou n'envoie que les leurs.
- Le kit des essais (`dev/test-kit/messages/message.txt`) : la visio, deux semaines à partir du 30 novembre, et un court paragraphe sur l'ouverture (la loi, ce qui ne change pas, la case « visible » qui reste aux gestionnaires, les chroniques réservées à l'observatoire) ; les photographies à vérifier ; la date limite. À relire par Lou. Avant l'envoi du 30 novembre : le point zéro de la base et la restauration éprouvée à part ([2026-11-16_before-kit.md](2026-11-16_before-kit.md), « Les données après les essais »), puis `make test-kit` avec `--site https://bdoh.inrae.fr` et la date limite.

### 9. Ce que demande la réouverture, mi-décembre

- Le port 443 ouvert à Internet, la nouvelle adresse IP de la VM et le certificat (Lou, avec l'infra ; `dev/plans/2026-10-26_deployment.md`, Suivi du 16 novembre).
- Les trois défauts relevés dans le JavaScript le 15 novembre, chacun éprouvé par un cas de `compare.py` (`dev/local/security.md`, « À corriger »).
- Le mot de passe d'accès retiré, `MAILER_DIVERT_TO` et `MAILER_ALLOWED_RECIPIENTS` vidés (`dev/deploy/README.md`, « À l'ouverture au public »).
- La validation, autant que le temps le permet : invariants, pannes provoquées, relecture, puis le code gelé et toutes les passes ([2026-11-04_validation.md](2026-11-04_validation.md)).
- Pas le juridique (Lou, 16 novembre) : l'accord des producteurs extérieurs est l'affaire des gestionnaires, qui savent y répondre ; la mise en conformité (durées de conservation des comptes, registre des traitements) attend la remise en route de BDOH (`ROADMAP.md`, « Plus tard »).

## Ce que fait Lou

Hors du code, et sans attendre Claude : l'invitation à la visio ; OTHU et OHGE ; l'infra (port, adresse, certificat) ; les listes d'INRAE (point 5) ; la restauration d'une sauvegarde éprouvée sur la VM (`dev/local/notes-for-lou.md`). Avec Claude : les points « à voir avec Lou » (2c, 2d, 5), les captures (1, 6, 7), la mesure des exports sur la VM (2b), la relecture des messages (8).

## Pour reprendre

Méthode : celle de [2026-11-16_before-kit.md](2026-11-16_before-kit.md), « Méthode » (les passes de `compare.py` à la fin, une fois les changements faits ; entre-temps, les tests de l'outil, `lint:twig`, `lint:container`, des captures ; ce qui se voit se montre à Lou avant d'aller plus loin). Chaque écart avec l'ancien s'inscrit dans `dev/differences.md` et, s'il est admis, dans `dev/compare/expected.py`. Le poste a dans `public/uploads/` les images de la production, pour les captures des accueils : les mettre de côté le temps de chaque passe d'`admin`, puis les remettre (copies dans `../bdoh_reference/uploads/`).

Ordre de travail proposé ; les étapes 1 à 4 sont faites (Suivi, nuit du 16 au 17 novembre), sauf ce qu'elles laissent à Lou (le refus d'une chronique réservée, les seuils, 2c et le reste de 2d). L'étape 5 est faite le 19 novembre dans une session à part, élargie aux textes de l'interface ([2026-11-19_review.md](2026-11-19_review.md)) : reste l'accord de Lou sur les captures pour fusionner la migration, et les logos des partenaires. La suite reprend à l'étape 6 ; une session par sujet, dans cet ordre (Lou, 19 novembre : chacune dans un contexte propre).
1. Point 1, les textes des chroniques réservées ; puis les captures de la proposition sur le refus.
2. Point 2a et 2b : la limite retirée, la règle de volume et ses deux seuils, les messages, la page de suivi ; la marche à suivre de la mesure sur la VM, pour Lou.
3. Point 3, l'historique ; point 4, l'invitation à créer un compte.
4. Point 2d, l'unité dans les fichiers de données ; 2c et le reste de 2d après en avoir vu le détail avec Lou.
5. Point 7 : les captures des accueils pour Lou, puis la migration globale et le second onglet des tableurs ; les photographies.
6. Point 8, les messages ; puis toutes les passes, `make browser-check`, `CHANGELOG.md`, une version ; Lou l'installe (`sudo make vm-update V=…`), puis envoie le pré-essai.
7. Points 6 et 5 (après en avoir parlé avec Lou), les retours du pré-essai, une version pour les essais du 30 novembre ; point 9 pour la réouverture.

Les sujets des sessions suivantes, relevés le 19 novembre, chacun dans sa session :
- **Point 8, les messages** : le pré-essai et le kit des essais (la visio, dont la date sort du sondage de Lou ; deux semaines ; l'ouverture expliquée ; une ligne pour que les gestionnaires vérifient les photographies de leur accueil, désormais sous Licence Ouverte ; leur second onglet, complété par la relecture). Puis les passes, une version, et Lou envoie le pré-essai.
- **Point 6, les statistiques** (priorité de Lou, 19 novembre) : les téléchargements par chronique tirés de l'historique, la vie de l'observatoire pour les gestionnaires, puis le compteur des consultations (une table, donc une migration à part), selon les règles de COUNTER ; captures avant de finir.
- **Point 5, les listes** : le code est court (un champ par observatoire, une page, la proposition à l'inscription et dans la fenêtre d'export) ; il attend ce que Lou apprend de `listes.inrae.fr`, et la liste des gestionnaires tenue à jour (point 5, plus haut).
- **Les trois défauts du JavaScript** relevés le 15 novembre (`dev/local/security.md`, « À corriger ») : avant l'ouverture du port à Internet.
- **Les versions correctives** de Doctrine (DBAL 4.5.1, ORM 3.7.7, DoctrineBundle 3.3.4 au 15 novembre), puis la suite de la validation (invariants, pannes provoquées : `2026-11-04_validation.md`).

## Suivi

- 2026-11-19, **point 7, la relecture**, dans sa propre session, élargie par Lou aux textes de l'interface : [2026-11-19_review.md](2026-11-19_review.md), avec son registre, ses questions pour Lou et sa reprise.
- 2026-11-17, nuit, **les passes sur le code de la nuit** (7682718), relancées par une session suivante : celle de la nuit s'était arrêtée à sa limite pendant la première. `pages` 491/0/0/1096/0 (deux de plus qu'au 16 novembre : les textes des chroniques réservées et la bulle du cadenas), `exports` 40/0/0/384/0, `queue` 11/0/0/0/0, `imports` 77/0/0/42/0, `accounts` 49/0/0/18/0 (la page de la demande d'accès), `admin` 448/0/0/230/0, `theia` 19/0/0/19/0 sur `bdoh` ; `make browser-check` 78 étapes sans problème. `state` 94/0/0/2/1 : le cas de l'historique attendait « Sans compte » deux fois ; BDOH l'écrit trois fois, à juste titre, un export étant dans trois onglets (tout, données, exports). L'outil corrigé (99af96b), `state` rejoué : 95/0/0/2/0. Aucune régression de BDOH. Deux fautes de `mutants.py` visaient du code disparu, ce qui l'empêchait de se lancer sans `--only` : l'ajout des conditions dans l'archive (réécrit le 13 novembre) et la garde d'un ensemble lourd (remplacée cette nuit). Remplacées, plus une sur le jeu de seuils d'une moyenne ou d'un cumul : 3 vues sur 3.
- 2026-11-17, nuit, **point 7 bis, les deux retouches de l'apparence** : le logo d'un observatoire à gauche de son bloc de citation (`Observatoire/show.html.twig`, `homes.css`, hors des marques du bloc, que la comparaison retire) ; sur l'accueil de BDOH, le logo de chaque fiche à gauche du nom, centré en hauteur. Captures pour Lou : `../bdoh_reference/browser/reopening/point-7bis/`. Elles montrent aussi un troisième logo d'INRAE, celui d'Ardières-Morcille (Marianne et INRAE), à ajouter au relevé du point 7.
- 2026-11-17, nuit, **point 2d, l'unité dans chaque fichier de données du CSV**, rien d'autre : une colonne `unit` entre `value` et `quality` (`Csv\Tables`, `Formatter\Csv`, qui reçoit la langue de l'export pour le libellé), celle des cumuls pour un export en cumul, la même que dans `meta_series.csv` ; le README et la description CSVW suivent d'eux-mêmes. Outil : `csv_checks` compare l'unité de chaque ligne à celle de `meta_series.csv`, ses neuf cas conformes sur `bdoh_small` ; l'en-tête attendu de `dev/browser/check.py`. Les autres métadonnées dans les fichiers de données, et 2c, restent à voir avec Lou ; aucune ébauche n'en est écrite.
- 2026-11-17, nuit, **point 4, l'invitation à créer un compte** : sans compte, la fenêtre d'export (sous la licence) et la page de suivi disent ce qu'un compte apporte, le lien de l'export par courriel et les favoris avec leurs étiquettes, et mènent à l'inscription et à la connexion (clé `measure.export.account`) ; les nouvelles des observatoires s'y ajouteront avec leurs listes (point 5). Outil : trois cas dans `open_data_checks` (la fenêtre et la page de suivi sans compte, la fenêtre d'un compte connecté, sans invitation). Captures : `../bdoh_reference/browser/reopening/points-2-4/`.
- 2026-11-17, nuit, **point 3, l'historique** : un export de mesures sans auteur s'affiche « Sans compte » (`historique/base.html.twig`, clé `historique.withoutAccount`), et se filtre sous ce nom ; les autres lignes sans auteur gardent « Utilisateur supprimé depuis ». Outil : un cas dans `open_data_checks` (deux fois « Sans compte » pour l'export sans compte, dans l'onglet de tout et dans celui des exports, aucune fois « Utilisateur supprimé depuis »), à jouer avec les passes.
- 2026-11-17, nuit, **points 2a et 2b, l'export** : la limite entre période et pas retirée du navigateur (`export-modal.js`, la clé `validation.invalid-time-span`) ; le volume d'une demande compté par le serveur en valeurs écrites, une chronique seule comprise (`ExportController::volume`), deux seuils, les messages `tooHeavy` et `tooLarge` (volume, limite, quoi faire), l'aide de la fenêtre, la page de suivi qui dit de garder son adresse. Mesuré sur le poste avant de poser les seuils : une valeur calculée (moyenne, cumul) coûte 140 fois une valeur lue (640 contre 90 000 par seconde), ce que le plan ne savait pas ; d'où deux jeux de seuils, 5 et 50 millions de valeurs lues, 100 000 et 1 million de valeurs calculées, le second provisoire (`dev/differences.md`, « Le volume d'un export »). À trancher par Lou (`dev/local/notes-for-lou.md`). La marche à suivre de la mesure sur la VM : `dev/deploy/README.md`, « Mesurer les exports ». Outil : 14 cas dans `open_data_checks` (chaque seuil, avec et sans compte, à l'identique, en valeurs interpolées, en moyenne et en cumul ; une chronique seule ; le pas « événement » ; le volume et la limite lus dans le message), la page de suivi ; `state` joué deux fois, ces cas conformes, et deux vérifications voisines corrigées (elles demandaient toute la chronique gonflée, désormais au-delà du premier seuil), à rejouer avec les passes.
- 2026-11-16, nuit, **point 1, les textes des chroniques réservées** : la page des conditions (tableau et section), l'accueil de BDOH, l'inscription et le courriel de bienvenue, l'aide, la fenêtre d'export des favoris, la bulle du cadenas et la page de la demande d'accès (titre, deux lignes en tête), en français et en anglais ; « en accès restreint » reste le nom de la catégorie, que les exports emploient. Outil : « nouveau seul : chroniques réservées, textes » et « … bulle du cadenas » dans `pages` (`reserved_checks`, conformes sur `bdoh_small`), la page de la demande comparée dans `accounts` (aucun compte de `pages` ne la voyait : `expected.RESERVED_REQUEST`), un test de l'outil. Le refus d'une chronique réservée n'est pas codé : maquettes pour Lou (`dev/local/notes-for-lou.md`). Sans passe (méthode).
- 2026-11-16, soir : ouvert sur le plan de Lou, revu avec Lou point par point ; relevés faits pour les points 2 et 3 (code, base complète anonymisée). Rien n'est commencé dans le code.
