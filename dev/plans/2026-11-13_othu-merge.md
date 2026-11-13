# La fusion d'OTHU dans BDOH

État : prêt pour la VM le 2026-11-13 (étape 6, Lou), ouvert le même jour. Point « Fusion d'OTHU dans BDOH » de `ROADMAP.md` ; la décision et ses raisons dans `dev/decisions.md`, « OTHU, un observatoire de BDOH ».

## Ce que fait la fusion

OTHU devient le treizième observatoire de la base de production, avec toutes ses données, sans rien changer à celles des douze autres. Le script est `dev/merge-othu/merge.sql`, son retour arrière `dev/merge-othu/unmerge.sql`.

- La base d'OTHU, telle que l'ancien serveur l'a laissée (le dump brut du 21 octobre, sans les migrations du nouveau BDOH), est restaurée dans une base temporaire, son schéma renommé `othu`, puis chargée dans la base de production. Le script lit `othu.*` et écrit dans `bdoh.*`, en une seule transaction : s'il s'arrête, rien n'a changé.
- **Listes communes** (unités, paramètres et leurs familles, qualités et leurs jeux, communes, listes de Theia, échantillonnages…) : chaque ligne d'OTHU est rapprochée de celle de la production qui dit la même chose, sinon ajoutée. Les règles, écrites dans le script, sont relevées plus bas. Une ligne d'une liste qui ne devrait pas changer (qualités, échantillonnages, listes de Theia) et qui n'a pas de pareil arrête la fusion.
- **Ce qui est propre à OTHU** (l'observatoire, ses sites, stations, chroniques, mesures, jeux de données, partenaires, rôles, demandes, historique…) : recopié sous de nouveaux identifiants, pris à la suite des compteurs de la production. La correspondance de chaque ancien identifiant au nouveau reste dans le schéma `fusion_othu` (`id_map`), avec les plages d'identifiants ajoutées (`ranges`) : c'est ce que lit le retour arrière, et ce qui répond à « quelle était cette chronique dans l'ancien OTHU ».
- **Comptes** : un compte d'OTHU dont l'adresse existe déjà en production (à la casse près) est rapproché de ce compte, qui reste tel quel ; les autres sont ajoutés. Les administrateurs d'OTHU (`roleadmin`) deviennent gestionnaires de l'observatoire OTHU, et ne gardent aucun rôle sur l'instance (`Utilisateur::isGestionnaireDesObservatoires` vaut pour tous les observatoires).
- **Points des stations** : ceux qu'OTHU n'avait pas, alors que la station a des coordonnées, sont calculés comme par la migration `Version20261103120000`, et notés dans `station_point_rebuilt` pour son retour arrière.
- Ne sont pas repris : l'historique des tâches d'OTHU (`jms_*`, que les dumps anonymisés n'ont pas : le script en donne le compte), ses trois fonctions SQL (celles de la production s'appliquent), ses tables de migration.

Avant de valider la transaction, le script vérifie que chaque table a reçu toutes ses lignes, et que l'empreinte de chaque chronique d'OTHU (mesures, plages, points de contrôle, taux de remplissage, qualités rapprochées) est la même des deux côtés.

## Rapprochement des listes (relevé du 13 novembre, bases anonymisées ; ces listes ne le sont pas)

Les 14 qualités et 21 types de paramètres que le relevé du 12 novembre donnait « sans pareil » ne différaient que par l'identifiant de leur jeu ou de leur famille.

- Qualités : les deux jeux d'OTHU (`VALIDE`, `Hydro2`) ont leur pareil en production, chaque qualité, libellés, ordre et couleur compris ; leurs traductions aussi. Le jeu `VALIDE` de la production a en plus le style des points de contrôle et des plages, qu'OTHU n'emploie pas.
- Familles de paramètres : par nom ; « Chimie » a le préfixe 003 dans OTHU, 004 en production : celui de la production reste. Ajoutée : « Grandeurs physiques ».
- Types de paramètres : par nom, puis par nom anglais. 16 sur 21 ont leur pareil, dont 4 avec un autre nom anglais ou une autre famille, ceux de la production restant. « Ensoleillement » (`SLR`) devient « Puissance de rayonnement solaire » (`PRAY`), de même nom anglais, « Solar radiation ». Ajoutés : Masse, Teneur en eau volumétrique, Flux de sève, Potentiel matriciel, Évapotranspiration de référence.
- Unités : par libellé, puis par libellé anglais, puis trois écritures d'une même unité : `m³` est `m3`, `cm3/cm3` est `m3.m-3`, `mm/jour` est `mm/j`. Ajoutées : `L/h`, `m NGF`, `kW/m²`, `mS/cm`, `kPa`.
- Communes : par code postal et code INSEE, puis par code postal et nom. La Villeurbanne d'OTHU porte le code INSEE 54321, qui n'existe pas : elle rejoint celle de la production (69266).
- Le reste (catégories de comptes, objectifs et types de travaux des demandes, échantillonnages, pas de temps, options de rééchantillonnage, listes de Theia) : identique.

OTHU voit donc changer quelques libellés : à lui dire avec le kit des essais (« m3/s » au lieu de « m³/s », « Puissance de rayonnement solaire » au lieu de « Ensoleillement », quelques noms anglais).

## Sur la VM

`sudo make -C /var/www/bdoh-prod vm-merge-othu NOM=prod DUMP=/var/backups/bdoh-sources/bdoh-othu-2026-10-21.dump` : file arrêtée, base sauvegardée sous un nom à part, contrôles avant et après (aucune chronique de la production ne doit changer), puis la file relancée. Retour arrière, tant que personne n'a écrit sur OTHU : `vm-unmerge-othu` ; sinon, la sauvegarde.

## Vérification

1. Base réduite : `bdoh_small` et `bdoh_othu_small`, pour mettre le script au point.
2. Base complète : la production anonymisée et le dump anonymisé d'OTHU, sans migrations, comme sur la VM ; durée mesurée.
3. Aller et retour : fusion puis retour arrière rendent une base identique, table par table, à celle d'avant.
4. Ce que montre le site : le nouveau BDOH sur l'ancienne base d'OTHU, contre le nouveau BDOH sur la base fusionnée (`compare.py merge`) : pages publiques d'OTHU et exports de ses chroniques. Une seule chose change ainsi : la base, pas le code, déjà comparé à l'ancien sur OTHU.
5. Relecture du script par un second Claude, en lecture seule.

## Étapes

1. [x] Relevé des tables, des listes et des collisions (13 novembre).
2. [x] `merge.sql` et `unmerge.sql`, sur la base réduite (13 novembre).
3. [x] Base complète, aller et retour, durée (13 novembre).
4. [x] `compare.py merge` (13 novembre).
5. [x] Cibles de la VM, guide, relecture et ses constats (13 novembre).
6. [ ] Sur la VM, avec la 0.6.0 : `make vm-merge-othu` (Lou).

## Suivi

- 2026-11-13, fin de matinée : **relecture** par un second Claude, en lecture seule, de la fusion, de la passe juridique et du mode `merge`. Ses constats, tous traités :
  1. la sauvegarde d'avant la fusion, nommée `prod-…` dans `/var/backups/bdoh`, aurait été effacée au bout de 8 jours par la sauvegarde de la nuit, comme celle d'avant une mise à jour : elles vont désormais dans `/var/backups/bdoh-kept/`, que rien ne vide ;
  2. le retour arrière aurait emporté en cascade des lignes de la production écrites depuis la fusion et qui citent ce qu'elle a ajouté (un rôle donné à un ancien compte d'OTHU, une demande qui cite un paramètre ajouté) : il parcourt maintenant toutes les clés étrangères vers les tables touchées, et s'arrête en nommant ce qu'il trouve ; il tolère un export fait sur OTHU, qui n'écrit rien dans ses données, et sauvegarde la base avant. Éprouvé sur la base réduite : un rôle ajouté l'arrête (« role.utilisateur_id (1) »), un import aussi, un export non, et l'aller et retour reste identique au bit près ;
  3. le site restait en ligne pendant l'heure de la fusion (un import en même temps aurait passé pour un changement de la production, ou pris un identifiant réservé) : `vm-merge-othu` et `vm-unmerge-othu` ferment le site jusqu'à la fin, et le rouvrent même en cas d'échec ;
  4. une fusion coupée en route laissait le schéma `othu` et un message faux : la cible refuse de repartir sur un reste d'une fusion inachevée en donnant la commande de nettoyage, le schéma `othu` part dans la transaction, et le message d'échec dit si la fusion était déjà validée ; le guide dit de lancer la fusion dans `tmux` ;
  5. l'espace disque : 60 Go libres exigés au départ ;
  6. le mot de passe Theia d'OTHU, chiffré par la clé de son ancien serveur, n'est plus recopié (le script dit s'il y en avait un, à ressaisir) ; une adresse vide ne rapproche plus deux comptes ; une chronique cachée dans OTHU arrêterait la fusion, le rapprochement de sa contrainte d'accès supposant qu'il n'y en a pas ;
  7. dans `compare.py merge`, la règle des contacts n'admet plus que les noms des anciens administrateurs d'OTHU ; les valeurs de ses exports instantanés ne sont jugées par aucune comparaison, l'ancien n'en ayant pas (écrit dans `dev/differences.md`) ;
  8. la date à mentionner sous Licence Ouverte est celle de la dernière mise à jour (licence, et CRPA L322-1) ; BDOH n'en garde pas par chronique, la date du téléchargement en tient lieu : la fenêtre d'export et la page des conditions le disent ;
  9. l'accord des producteurs extérieurs (INSA et UCBL pour OTHU, EDF pour Arc-Isère) devient une condition de l'ouverture au public, avec une question à la direction des affaires juridiques (`ROADMAP.md`) ; le message à OTHU ne dit plus que ses comptes sont repris « tels quels ».
  Laissé tel quel : les identifiants des pages masqués plutôt que traduits dans `compare.py merge` (ce qu'ils désignent reste comparé, et les données sont vérifiées par l'empreinte de chaque chronique) ; les compteurs d'identifiants qu'une fusion échouée laisse avancés.
- 2026-11-13, matinée : **`compare.py merge`** sur la base réduite (`bdoh_othu_small` contre `merge_small`, migrée comme elle) : 38 identiques, 1 autre ordre, 67 écarts admis, aucun autre. Ce qu'il a fallu admettre, et pourquoi, est dans `merge_rules` (`dev/compare/expected.py`) : libellés rapprochés, là où ils s'affichent seuls ; les douze autres observatoires dans le choix d'un observatoire ; l'ordre des listes triées par identifiant, et les identifiants que portent les pages ; deux paramètres qui changent de famille ; les anciens administrateurs parmi les contacts ; le code Qtvar de « m3/s ». Et un bogue d'OTHU : ses exports instantanés sortaient toujours vides, sa table `interp_qualite` l'étant ; avec la fusion, ils prennent les règles de la production (`dev/differences.md`, « Fusion d'OTHU dans BDOH »). La comparaison est exacte, sans tolérance d'arrondi : des lignes de séries différentes, mises en face par un ordre changé, passaient sinon pour des arrondis.
- 2026-11-13, matin : **base complète**, la production anonymisée (31 Go, migrée) et le dump anonymisé d'OTHU sans migrations, comme sur la VM : 48 min en tout (restauration d'OTHU 4 min, chargement 5, fusion et vérifications 39), 106 006 788 mesures versées, mêmes rapprochements que sur la base réduite. Toutes les tables d'avant identiques hors ajouts ; retour arrière en 3 min 43, puis base identique au bit près, compteurs compris.
- 2026-11-13, matin : sur la base réduite (`bdoh_small` et `bdoh_othu_small`), la fusion passe du premier coup, en 3 min 20 vérifications comprises : 119 chroniques et 11 millions de mesures, empreintes identiques ; 17 comptes ajoutés et 2 rapprochés (les doublons voulus par `merge.sh`, dont une adresse en majuscules), 4 administrateurs devenus gestionnaires d'OTHU ; 5 unités, 5 types de paramètres et une famille ajoutés, les autres lignes des listes rapprochées. L'empreinte de toutes les tables (`checksum.sql`) est identique hors ajouts après la fusion, et au bit près, compteurs compris, après le retour arrière (10 s). `inspect.sql` sur la base fusionnée : chaque compte est la somme de ceux des deux bases, sauf les quatre attendus (comptes rapprochés, administrateurs d'OTHU sans rôle sur l'instance, deux rôles de gestionnaire ajoutés). OTHU se charge sans index ni contraintes (`--section=pre-data --section=data`) : la fusion ne fait que lire ces tables. La migration `Version20261113120000` renomme la contrainte d'accès des jeux : la fusion rapproche l'ancien libellé d'OTHU du nouveau, dans les deux ordres possibles. Pendant une passe, ne pas modifier `merge.sh` : bash relit un script en cours à partir d'une position en octets, et un premier essai complet, ainsi faussé, a été arrêté et repris d'une copie des scripts.
- 2026-11-13 : ouvert. Relevé des listes ci-dessus. Aucune collision de nom ni de code (stations, sites, observatoire). OTHU : 1 observatoire, 4 sites, 25 stations, 119 chroniques, 106 millions de mesures (base complète), 19 comptes dont 4 administrateurs (2 `FCT`, 2 `TECH`), aucune transformation ni conversion, aucune plage ni aucun point de contrôle, aucun envoi à Theia dans son historique.
