# L'interface modernisée

État : en cours, ouvert le 2026-11-11. Né du point « Les deux accueils autour de la carte » de `ROADMAP.md`, élargi par Lou le même jour. Les raisons : `dev/decisions.md`, « L'apparence se modernise, le visage reste ».

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

## Suivi

- 2026-11-11, soir, **lot 1, les accueils, fait** (76b9b56, 2adda28). `compare.py pages` 1237/0/55/282/0 contre `reference-17` : seuls les accueils diffèrent, admis par la règle « Accueils autour de la carte », et « nouveau seul : fiches des observatoires » relit chaque fiche ; deux fautes vues (une station dans un seul de ses sites ; le texte entier dans une fiche). `make browser-check` 67 étapes sur 67, dont le survol qui allume la carte, sur le portail et sur un observatoire, et le dépliage d'une description. Une description collée depuis Word (commentaires conditionnels) a montré que la vérification doit retirer les commentaires entiers, comme `strip_tags`. Pour travailler pendant une passe sans toucher à `src/` : une copie de travail (`git worktree`, `../bdoh-legacy-interface`, branche `interface`) et une seconde instance de l'application dessus (`bdoh-app-ui`, 172.30.26.11, sur `bdoh_small`), qui monte `vendor/`, `node_modules/` et `public/uploads/` de la copie principale.
- 2026-11-11, **maquettes**, sur les vraies pages, par du CSS et du JavaScript injectés dans le navigateur, sans toucher au code. Mesure pour la bande du graphique : la plus longue chronique de la base complète (2,4 millions de mesures, de 2000 à 2025) se résume en 1 454 tranches, minimum et maximum, en 1,6 s, par l'index `(chronique_id, date)`, le serveur étant occupé par une passe. Le graphique actuel ne réduit pas le nombre de points des chroniques en cumul (`seriesCumulativeSampling` ne reçoit pas `maxValidPoints`), d'où la limite de 5 ans : la bande doit venir d'une route à elle.
