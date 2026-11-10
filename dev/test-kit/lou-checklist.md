# Les essais de Lou, avant les gestionnaires

Sur la VM, sous VPN, une fois installée (`dev/deploy/README.md`). Ce que la comparaison et les parcours du navigateur ne jugent pas : l'œil, les vrais courriels, et les doutes qui restent. Un constat qui cloche s'écrit dans `dev/differences.md`, ou dans `dev/plans/2026-10-26_deployment.md` s'il relève de l'installation.

La base de la VM deviendra la production : un essai qui écrit se fait sur une chronique d'essai, ou se défait ensuite.

## D'abord, l'installation

- [ ] Les vérifications de la section 5 du guide (`dev/deploy/README.md`, « Vérifier ») : la file qui repart après un arrêt de PostgreSQL, un import en plusieurs étapes avec Apache redémarré entre deux, un courriel hors de la liste qui arrive à `MAILER_DIVERT_TO`.
- [ ] La première sauvegarde de la nuit, restaurée une fois dans une base à part (même guide, section 4).

## À l'œil

- [ ] Deux ou trois observatoires aux thèmes différents : couleurs, logo, mise en page, comme l'ancien.
- [ ] Les boutons de la boîte à outils d'une page de chronique : ils ont désormais un contour et des couleurs au survol et au focus (`dev/differences.md`, « Thèmes des observatoires »). Ça passe ?
- [ ] Le graphique d'une chronique continue, d'une discontinue et d'une calculée : zoom, choix de la période, points de contrôle.
- [ ] Le visualiseur avec plusieurs chroniques. (Le site n'a pas de carte : elle est prévue à part, `ROADMAP.md`, « Modernisations ».)

## Les vrais courriels

- [ ] S'inscrire, puis se connecter par le lien reçu dans la messagerie INRAE. Ces messageries ouvrent les liens pour les analyser ; le lien ne sert qu'une fois, mais il ne se consomme que par le bouton de la page qu'il ouvre (`dev/decisions.md`, « Connexion par lien ») : il doit encore marcher quand on clique.
- [ ] La même chose depuis une adresse hors INRAE.
- [ ] Une demande d'accès à une chronique cachée : le courriel aux gestionnaires, puis le droit donné, puis l'export.
- [ ] Un export lourd, avec un compte : le lien arrive par courriel.
- [ ] Aucun de ces courriels ne finit en indésirables. Sinon, voir avec l'équipe infra l'envoi depuis la VM.

## Les deux instances

- [ ] Une fois OTHU installée : se connecter à l'une, puis à l'autre, les sessions ne se mêlent pas (`dev/plans/2026-10-26_deployment.md`, Suivi du 5 novembre).

## Les doutes

- [ ] Un import d'un gestionnaire, de bout en bout, avec un fichier qui a déjà servi en production.
- [ ] Sur le vrai dump, ouvrir et enregistrer sans rien changer quelques fiches d'observatoire et de site. Dans nos bases, l'anonymisation vide téléphone et adresse, et le navigateur refuse alors une fiche : sur les vraies données, ça doit passer.
- [ ] `DocumentationBDOH.pdf` cite l'ancienne adresse de contact : le remplacer dans l'administration (`dev/plans/2026-10-26_deployment.md`, « Textes à changer »).
