# L'interface modernisée

État : en cours, ouvert le 2026-11-11 ; publié en 0.4.0, puis 0.4.1 après le second regard de Lou. Restent les points de « Pour reprendre ». Né du point « Les deux accueils autour de la carte » de `ROADMAP.md`, élargi par Lou le même jour. Les raisons : `dev/decisions.md`, « L'apparence se modernise, le visage reste ».

## Choix de Lou (2026-11-11)

Après trois maquettes (A : même page sans bascule ; B : liste et carte côte à côte ; C : carte en tête), puis une seconde série sur C avec l'habillage :

- **Accueils en C.** Sur le portail : la carte en tête, la présentation en un seul bloc, puis les observatoires en fiches ; plus de logo INRAE à côté de la présentation. Sur un observatoire : la carte en bandeau, et la colonne de droite, « interminable », séparée en trois (sites, paramètres, jeux de données) pour tenir dans une fenêtre.
- **Charte d'INRAE** (septembre 2020, version 3) : Raleway pour les titres (p. 16) ; Inter pour le texte, Avenir Next Pro n'étant pas libre ; des bleus moins criards, ceux de la charte (p. 14 et 15).
- **Une couleur par observatoire, appliquée partout**, et non plus des liens restés bleus sur un thème vert. Les observatoires sur la couleur par défaut prennent le bleu pétrole d'INRAE ; la couleur secondaire ne sert plus.
- **Icônes sobres**, d'un seul trait (Material Symbols), à la place des icônes en couleur de Silk.
- **Logo INRAE de l'en-tête plus grand.**
- **Graphique d'une chronique** affiché dès l'ouverture, avec dessous une bande de toute la chronique où l'on glisse la fenêtre affichée ; molette pour zoomer. Les lacunes en bandes très pâles sur toute la hauteur, au lieu d'un trait horizontal.
- **Le kit des essais attend la fin de ce chantier**, sans presse : « on finit ça déjà proprement en prenant le temps ».

## Les lots, dans l'ordre

Un lot à la fois, chacun comparé à part (`compare.py pages` et `make browser-check`) et inscrit dans `dev/differences.md`.

1. **Accueils.**
   - Portail : carte en tête, cadrée sur les stations de France métropolitaine ; présentation ; fiches des observatoires (logo s'il y en a un, nom, début de la description sans mise en forme, coupé par le serveur, nombre de stations placées). Survoler ou atteindre au clavier une fiche allume ses stations ; rien ne bouge. Plus d'onglets au survol (`home/index.js`), ni d'aperçus cachés.
   - Observatoire : carte en bandeau, avec un bouton par site qui allume ses stations ; présentation et photos ; puis sites, paramètres et jeux de données en trois colonnes, les longues descriptions repliées ; la recherche avancée en bouton. Sans station placée : une phrase au lieu d'une carte vide.
   - `StationRepository::findForMap` donne aussi les sites de chaque station.
2. **Habillage.**
   - Polices Raleway et Inter, fichiers woff2 servis par le site, avec leur licence (SIL OFL) ; texte à 14 px au lieu de 12.
   - Couleurs : `CssManager` calcule les déclinaisons de la couleur primaire (texte et liens au contraste de 4,5, teinte pâle, filet) ; barres plates, sans dégradé ; défaut bleu pétrole. Tous les thèmes d'observatoire recompilés.
   - Logo INRAE recadré : son SVG a une toile de 106 × 146 pour un dessin d'environ 100 × 27, d'où sa petite taille.
   - Icônes : SVG de Material Symbols (Apache 2.0) copiés avec leur licence, posés en CSS sur les classes `fam-*` existantes : aucun gabarit ne change. Inventaire des icônes employées par le parcours de tous les liens de `make browser-check`.
   - L'administration (Sonata) reste hors champ.
   - Vérification : `compare.py pages` ne doit pas bouger, puisque seule l'apparence change ; captures avant et après de chaque sorte de page.
3. **Graphique.**
   - Affiché dès l'ouverture sur une période par défaut. Bande de toute la chronique, résumée par le serveur (minimum et maximum par tranche de temps) ; fenêtre qu'on glisse ou dont on tire les bords, clic pour recentrer, molette sur le graphique ; champs de dates synchronisés ; limite de 5 ans gardée pour le graphique du haut.
   - Lacunes en bandes pâles sur toute la hauteur.
   - Vérification : étapes du graphique et des lacunes de `make browser-check` revues ; la route de la bande, en « nouveau seul » dans `compare.py`.

## Pour reprendre

Retours de Lou du 11 novembre au soir, sur la 0.4.0 :

1. **Faits en 0.4.1** : retour aux trois colonnes (sites, paramètres, jeux de données), tassées en haut, la recherche avancée gardée au-dessus ; les deux colonnes de paramètres coupaient mal les listes courtes, et la grille laissait des vides à gauche. Paragraphes vides des descriptions masqués (`p:empty`), d'où les grands blancs (TPG) : le reste des blancs vient de la saisie, à signaler aux gestionnaires au cas par cas. Carte nette : zoom par niveaux entiers, un niveau fractionnaire agrandissant les tuiles.
2. **Le fond de carte** ne plaît pas encore à Lou : le plan de l'IGN atténué est terne ; le réseau hydrographique lui plaît. Piste : un fond de relief (`ELEVATION.ELEVATIONGRIDCOVERAGE.SHADOW`, estompage en gris, Géoplateforme) très clair, l'hydrographie par-dessus, et le plan de l'IGN en transparence pour les noms ; essayer deux ou trois mélanges en captures, et les montrer à Lou.
3. **Le sélecteur de dates** du graphique est vieillot : un calendrier moderne, sans bibliothèque lourde (un champ `datetime-local`, ou un petit sélecteur), synchronisé avec la bande. Et l'avertissement « 5 ans maximum » devient obscur avec la bande : la limite tient pour le graphique du haut (les cumuls n'y sont pas réduits en nombre de points) ; la lever pour les autres échantillonnages, ou la dire autrement, dans la bande.
4. **Feuilles de style en cache** après une mise à jour : `dev/plans/2026-10-26_deployment.md`, à faire avant l'ouverture.

## Suivi

- 2026-11-11, soir, **fin, version 0.4.0** (fed426a) : `compare.py pages` 1180/0/55/343/0 et `make browser-check` 69 étapes sur 69, retouches comprises ; l'essai des bandes compte désormais les morceaux de lacunes, une série du serveur en portant plusieurs. La copie de travail et la seconde instance sont retirées. Restent hors de ce chantier : mesurer sur la VM la durée des pages de chronique et de leur bande (`dev/plans/2026-10-26_deployment.md`), régler `random_page_cost` si le disque est un SSD, les conditions d'ORACLE (`ROADMAP.md`) ; l'administration (Sonata) et l'affichage sur téléphone (le site n'a pas de balise `viewport`) attendent la mise à jour de Bootstrap (`ROADMAP.md`, « Chaîne de compilation du JavaScript à jour »).
- 2026-11-11, soir, **lot 3 vérifié** (1e92808) : `compare.py pages` 1180/0/55/343/0, les pages de chronique gagnant l'élément de la bande (règle « Graphique d'une chronique ») ; « nouveau seul : bande de toute la chronique » pour une continue, une calculée et une discontinue, et 403 pour une cachée ; deux fautes vues (lacunes et invalides comptés ; droits retirés).
- 2026-11-11, soir, **retouches après le regard de Lou** (e2a40d7, sur la branche `interface`) : graphique sur fond blanc (une seconde clé `grid` des options de jqPlot écrasait la première), lacunes plus pâles, couleurs des qualités dessinées dans la plus proche d'une palette lisible (Tableau 10) ; carte : plan de l'IGN atténué en CSS, réseau hydrographique de l'IGN (`HYDROGRAPHY.HYDROGRAPHY`) par-dessus à partir du zoom 9, plus dense il couvre la carte, zoom au pincement ou avec Ctrl et la molette ; fiches du portail, titre et logo sur une ligne ; observatoire : photos flottantes dans la présentation, grille des listes (recherche avancée en tête, sites et jeux de données d'un côté, paramètres sur deux colonnes de l'autre), l'ordre de la page inchangé. Les conditions d'utilisation : celles par défaut sont à jour depuis la 0.3.0 ; ORACLE a les siennes, à revoir (`ROADMAP.md`).
- 2026-11-11, soir, **lot 2, l'habillage** (08b5d59). `compare.py pages` ne bouge pas (1237/0/55/282/0) : seule l'apparence change. `compare.py admin` 665/0/0/13/0 : le thème recompilé ne se compare plus règle à règle à l'ancien, mais « nouveau seul : thème aux couleurs choisies » vérifie les couleurs tirées de la nouvelle, calculées à part en Python ; elles concordent avec `CssManager::palette` sur quatre couleurs. Fichiers ajoutés : `public/fonts/` (Inter et Raleway variables, alphabet latin, de Fontsource 5.2.8, licences SIL OFL), `public/images/icons/` (23 symboles de Material Symbols 0.48.1, licence Apache 2.0), empreintes des polices dans le commit. Le logo d'INRAE est recadré dans son SVG (`viewBox`). `config/deploy/vm.sh` efface les thèmes compilés à chaque construction.
- 2026-11-11, soir, **lot 3, le graphique**, écrit (0aafaa4 sur la branche `interface`) : route `bdoh_data_chronique_overview`, bande sous le graphique (`widgets/overview/`), graphique dès l'ouverture, lacunes et invalides en bandes (`gap-band-renderer.js`). Une lacune sur une période sans valeur valide n'a pas de hauteur (le serveur ne trouve pas de plus petite valeur) : la bande ne dépend que des dates. La requête de la bande abaisse `random_page_cost` pour elle seule (`getOverview`, et le plan de déploiement).
- 2026-11-11, soir, **lot 1, les accueils, fait** (76b9b56, 2adda28). `compare.py pages` 1237/0/55/282/0 contre `reference-17` : seuls les accueils diffèrent, admis par la règle « Accueils autour de la carte », et « nouveau seul : fiches des observatoires » relit chaque fiche ; deux fautes vues (une station dans un seul de ses sites ; le texte entier dans une fiche). `make browser-check` 67 étapes sur 67, dont le survol qui allume la carte, sur le portail et sur un observatoire, et le dépliage d'une description. Une description collée depuis Word (commentaires conditionnels) a montré que la vérification doit retirer les commentaires entiers, comme `strip_tags`. Pour travailler pendant une passe sans toucher à `src/` : une copie de travail (`git worktree`, `../bdoh-legacy-interface`, branche `interface`) et une seconde instance de l'application dessus (`bdoh-app-ui`, 172.30.26.11, sur `bdoh_small`), qui monte `vendor/`, `node_modules/` et `public/uploads/` de la copie principale.
- 2026-11-11, **maquettes**, sur les vraies pages, par du CSS et du JavaScript injectés dans le navigateur, sans toucher au code. Mesure pour la bande du graphique : la plus longue chronique de la base complète (2,4 millions de mesures, de 2000 à 2025) se résume en 1 454 tranches, minimum et maximum, en 1,6 s, par l'index `(chronique_id, date)`, le serveur étant occupé par une passe. Le graphique actuel ne réduit pas le nombre de points des chroniques en cumul (`seriesCumulativeSampling` ne reçoit pas `maxValidPoints`), d'où la limite de 5 ans : la bande doit venir d'une route à elle.
