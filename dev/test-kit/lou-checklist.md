# Les essais de Lou, avant les gestionnaires

Sur la VM, une fois installée (`dev/deploy/README.md`). Ce que la comparaison et les parcours du navigateur ne jugent pas : l'œil, les vrais courriels, et les doutes qui restent. Un constat qui cloche s'écrit dans `dev/differences.md`, ou dans `dev/plans/2026-10-26_deployment.md` s'il relève de l'installation.

La base de la VM deviendra la production : un essai qui écrit se fait sur une chronique d'essai, ou se défait ensuite.

## D'abord, l'installation

- [ ] Les vérifications de la section 5 du guide (`dev/deploy/README.md`, « Vérifier ») : la file qui repart après un arrêt de PostgreSQL, un import en plusieurs étapes avec Apache redémarré entre deux, un courriel hors de la liste qui arrive à `MAILER_DIVERT_TO`. L'import avec Apache redémarré entre deux : fait le 24 novembre, il va au bout (`dev/plans/2026-10-26_deployment.md`, Suivi).
- [x] La première sauvegarde de la nuit, restaurée une fois dans une base à part (même guide, section 4). Faite le 24 novembre sur le point zéro : mêmes comptes que la production (`dev/plans/2026-10-26_deployment.md`, Suivi).

## À l'œil

- [ ] Deux ou trois observatoires aux thèmes différents : couleurs, logo, mise en page, comme l'ancien.
- [ ] Les boutons de la boîte à outils d'une page de chronique : ils ont désormais un contour et des couleurs au survol et au focus (`dev/differences.md`, « Thèmes des observatoires »). Ça passe ?
- [ ] Le graphique d'une chronique continue, d'une discontinue et d'une calculée : zoom, choix de la période, points de contrôle.
- [ ] Le visualiseur avec plusieurs chroniques.
- [ ] L'interface modernisée (0.4.0 et 0.4.1, `CHANGELOG.md`) : le portail et ses fiches, l'accueil de deux ou trois observatoires aux couleurs différentes, la page d'une chronique avec son graphique dès l'ouverture, la bande de toute la chronique (glisser, tirer, Ctrl + molette), et les lacunes en bandes. Recharger de force (Ctrl + Maj + R) après la mise à jour : le navigateur garde sinon les anciennes feuilles de style.
- [ ] La carte des stations, sur l'accueil du portail et sur celui de deux ou trois observatoires, dont un sans station placée : points, bulles, liens vers les stations, fond de l'IGN (`dev/differences.md`, « Carte des stations »).
- [ ] La page des conditions d'utilisation, au lien du pied de chaque page, la même pour tout BDOH (`/terms_of_use`, 0.7.1) ; l'ancienne adresse d'un observatoire y renvoie, et celle d'ORACLE ne télécharge plus son ancien PDF (`dev/audits/2026-11-13_terms-of-use.md`).
- [ ] Les favoris (0.5.0, `dev/differences.md`, « Favoris et étiquettes ») : l'étoile sur la page d'une chronique et dans la liste d'une station, « Mes favoris » depuis le portail et depuis un observatoire, une étiquette qui coche son groupe, des favoris de deux observatoires. La page se comprend-elle sans explication ?
- [ ] Les barres (0.7.0) : en haut l'observatoire et « Recherche », en bas « Mes favoris » puis le compte ; avec un compte d'administration, la barre du dessous tient sur une ligne, et le nom ouvre le menu du compte.
- [ ] La 0.7.1 : le « ? » et la ligne de liens du pied de page (aide, mentions légales avec la déclaration d'accessibilité) ; l'onglet « Gestionnaires » d'un accueil, et `/{observatoire}/contact` qui y mène ; le type après le nom dans les titres.
- [ ] Les blocs de citation (0.7.1), sur l'accueil de Draix ou d'Arc-Isère (DOI principal) et la page d'un de leurs jeux : sur la VM, la citation vient vraiment de doi.org, ce que le poste, sans accès à l'extérieur, n'a pu essayer qu'avec un faux. Si doi.org ne répond pas depuis la VM, la citation composée par BDOH reste : le dire, pour voir avec l'infra.
- [x] Un export CSV (0.7.0) : le nom de l'archive, le README (licence, comment citer, fichiers, colonnes), un CSV ouvert dans R ou Python, et dans un tableur par son import ; puis un export au format BDOH, dont le `.txt` n'a pas changé et dont le README remplace `Report.txt`. Essayé par Lou sur la VM le 15 novembre : « parfait ».

## Les vrais courriels

- [x] Se connecter par le lien reçu dans la messagerie INRAE. Ces messageries ouvrent les liens pour les analyser ; le lien ne sert qu'une fois, mais il ne se consomme que par le bouton de la page qu'il ouvre (`dev/decisions.md`, « Connexion par lien ») : il doit encore marcher quand on clique. Fait par Lou sur la VM (10 novembre, puis avec la 0.7.0) : ça marche.
- [ ] La même chose depuis une adresse hors INRAE : le site s'ouvre sans VPN depuis le 26 novembre, la messagerie d'un partenaire peut donc ouvrir le lien pour l'analyser, et le lien doit encore marcher quand on clique.
- [ ] Une demande d'accès à une chronique cachée : le courriel aux gestionnaires, puis le droit donné, puis l'export.
- [ ] Un export lourd, avec un compte : le lien arrive par courriel.
- [ ] L'export de plusieurs favoris d'un coup, depuis « Mes favoris » : le lien arrive par courriel, l'archive a un fichier par chronique.
- [ ] Aucun de ces courriels ne finit en indésirables. Sinon, voir avec l'équipe infra l'envoi depuis la VM.

## OTHU, après la fusion

- [x] `make vm-merge-othu` (`dev/deploy/README.md`, section 2 bis) : à la fin, aucune chronique de la production dans l'empreinte changée. Fait le 13 novembre (`dev/plans/2026-11-13_othu-merge.md`, Suivi).
- [ ] L'accueil d'OTHU, ses stations sur la carte, une chronique et son graphique, un export ; ses libellés rapprochés (« m3/s », « Puissance de rayonnement solaire »).
- [ ] Un ancien administrateur d'OTHU : gestionnaire d'OTHU, et de lui seul.

## Les doutes

- [ ] Un import d'un gestionnaire, de bout en bout, avec un fichier qui a déjà servi en production.
- [ ] Sur le vrai dump, ouvrir et enregistrer sans rien changer quelques fiches d'observatoire et de site. Dans nos bases, l'anonymisation vide téléphone et adresse, et le navigateur refuse alors une fiche : sur les vraies données, ça doit passer.
- [ ] `DocumentationBDOH.pdf` cite l'ancienne adresse de contact : le remplacer dans l'administration (`dev/plans/2026-10-26_deployment.md`, « Textes à changer »).
