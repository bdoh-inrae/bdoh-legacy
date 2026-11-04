# Feuille de route

Porter BDOH vers Symfony 7.4 (version à support long, corrigée jusqu'en novembre 2029) et PHP 8.4, en gardant son comportement, ses adresses et ses données. Méthode et repères : `CLAUDE.md`.

Code de référence : commit `523cf8e32` de l'ancien dépôt (version 5.0.3, février 2025), celui qui tourne en production. L'instance OTHU tourne sur le même code applicatif ; seules la page des mentions légales et la configuration de déploiement diffèrent.

## En cours

Priorité (Lou, 3 novembre) : stabiliser ce BDOH au plus près de l'ancien et le faire tester vite par les gestionnaires ; les nouvelles fonctions attendent leur validation. La demande de VM : envoyée à l'équipe infra le 3 novembre, réponse attendue ([dev/plans/2026-10-26_deployment.md](dev/plans/2026-10-26_deployment.md)). Les sept modules du portage sont portés et comparés (version 0.1.0, `CHANGELOG.md`, plans dans `dev/plans/`) ; ce qui reste avant la mise en service est ci-dessous. Reprise : le plan de validation est consolidé ([dev/audits/2026-11-03_validation-plan.md](dev/audits/2026-11-03_validation-plan.md)) ; ses six décisions attendent Lou, sans bloquer les étapes 1 à 4 ci-dessous. Le comparateur est durci, on est en train de l'éprouver.

## À faire

- **Preuves de solidité à apporter**, dans cet ordre ; le détail, les raisons et les coûts sont dans [dev/audits/2026-11-03_validation-plan.md](dev/audits/2026-11-03_validation-plan.md), qui reprend [dev/audits/2026-11-03_migration-trust.md](dev/audits/2026-11-03_migration-trust.md). Ce qui doit précéder les essais des gestionnaires reste à décider (décision 3) ; recommandé : les étapes 1 à 4 ci-dessous. En cours, avec l'avancée et l'ordre retenu : [dev/plans/2026-11-04_validation.md](dev/plans/2026-11-04_validation.md).
  1. Durcir le comparateur : règle d'arrondi selon la nature du nombre, empreintes sans `concat_ws`, toutes les tables dont `plage`, rapport qui note commit et base lue, registre des écarts admis, code de sortie ;
  2. couverture pendant la comparaison, du PHP (PCOV) et du SQL (`plpgsql_check`), et lecture de ce qui n'est jamais exécuté ;
  3. sur les dumps actuels, tant que les vraies données sont là : recalcul contre la base des chroniques calculées et converties, les plus profondes d'abord, codes de retour comptés ; taux de remplissage ; temps et mémoire contre l'ancien ; OTHU restaurée sous PostgreSQL 17, empreinte et taux ;
  4. régénérer les dumps (`dev/local/hidden-series.md`, décisions 1 et 2) ;
  5. élargir les cas d'après la couverture et l'usage réel (`historique`) : « 1 événement », 5 et 15 minutes, fuseaux, plages, sortes de calcul, tirages au hasard ; adapter les modes à la forme d'OTHU ;
  6. invariants (`E(I(E(x))) = E(x)`, relations sur les calculs) ;
  7. pannes provoquées et relecture croisée de ce qui écrit des données, l'exécuteur de tâches en premier ;
  8. une base de développement sur les paquets de Debian 13, un rôle sans superuser ;
  9. code gelé, tous les modes sur la base complète et sur OTHU, une fois.
- **Proxy inverse** (Lou, question à l'équipe infra, dans sa réponse sur la VM) : y en aura-t-il un devant la VM, et quelle est son adresse ? Si oui, le nouveau doit la connaître (`SYMFONY_TRUSTED_PROXIES`), sinon les limites de fréquence valent pour tout le site (`dev/decisions.md`, « Connexion par lien »).
- **Chroniques cachées citées dans les listes** (Lou, avec sa cheffe) : sans compte, on voit leur code, leur libellé et leur unité, et 9 partent vers Theia (`dev/differences.md`). À décider ici, ou avec « Ouvrir les données » plus bas. Les autres bizarreries de l'ancien sont corrigées ou laissées, avec leur raison, dans le même fichier.
- **Affiliations des auteurs à confirmer** (Lou, auprès de Flora Branger) : `CITATION.cff` et `codemeta.json` ; les personnes en CDD sont à RiverLy dans le doute, et Degiuli, Gesche, Jamet et Crochemore à la DSI sans source officielle.
- **Question aux gestionnaires pendant les essais** : gardent-ils le courriel « nouvel utilisateur » (`dev/differences.md`) ?
- **Prévenir les gestionnaires** que les fiches publiées sur Theia/OZCAR par l'ancien BDOH ont des coordonnées décalées de 300 à 700 m, corrigées au prochain envoi par le nouveau (`dev/differences.md`, « Coordonnées envoyées à Theia/OZCAR ») ; à joindre au message sur la connexion par lien (`dev/plans/2026-10-26_deployment.md`). Leur signaler aussi ce qu'ils ont à corriger : l'inventaire est dans `dev/local/data-issues.md` (ignoré par Git) : une chronique qui bloque la fiche Theia de REAL-COLLOBRIER, 11 stations placées hors de France, 135 stations sans coordonnées.
- **Archivage et version 1** (Lou, 3 novembre). La 1.0.0 viendra avec la bascule ; Lou branchera alors le webhook de Software Heritage. Rien à faire d'ici là.
- **Essais dans un navigateur.** `make browser-check` les joue sur un observatoire (`dev/browser/`) : pages publiques, graphique, visualiseur, export, formulaires, choix d'une entité liée, onglets de l'historique. À étendre : chroniques discontinues et calculées, conversion, enregistrement d'un import jusqu'au bout. À l'œil (Lou) : les couleurs, le rendu du graphique et des thèmes (boutons de la boîte à outils, `dev/differences.md`). Déjà essayées (27 octobre) : l'inscription et la connexion par lien, courriels compris (façon de faire dans le `README.md`).
- **Fonctions SQL d'OTHU.** La base d'OTHU a trois fonctions qui diffèrent de la production : `bdoh_interp_cumul_to_cumul` et `bdoh_interp_to_mean_or_cumul`, faute de la migration `Version20241216124726` (correction du pas mensuel des exports), et `bdoh_chronique_calcul_dates_mesures`, qui y remet à `NULL` les dates et le nombre de mesures d'une chronique devenue vide, comme le code, quand la version de la production les laisse inchangés (relevé le 23 octobre). Les exports mensuels d'OTHU ont donc encore le bogue corrigé en production. À aligner sur la production à la bascule d'OTHU, après comparaison.
- **Déploiement** : ce qu'il demande, relevé module après module, est réuni dans [dev/plans/2026-10-26_deployment.md](dev/plans/2026-10-26_deployment.md). Sur une VM neuve, sans Docker, pour les deux instances, avec le PHP et le PostgreSQL de sa distribution, qui les corrige par ses mises à jour ordinaires : Debian 13 (choix du 23 octobre), ou Ubuntu 26.04, une fois la comparaison repassée dessus (PHP 8.5 et PostgreSQL 18 n'ont pas été essayés). Le formulaire de l'équipe infra ne donne qu'Ubuntu 22.04, trop ancienne : la demande envoyée le 3 novembre réclame l'un de ces deux systèmes. Puis déploiement de test sous VPN, reprise des données, vérifications par des gestionnaires d'observatoire, bascule.

## Modernisations

Ce qui améliore nettement l'expérience, la sécurité ou la maintenance pour peu de travail, au-delà de l'identique (règle dans `CLAUDE.md`). Chacune vient après l'identité de son module, comparée à part.

- **Effacer les hachages des anciens mots de passe**, qui ne servent plus depuis la connexion par lien : une migration (`UPDATE utilisateur SET password = '', salt = ''`), sans retour arrière possible, donc à faire à la bascule, une fois la connexion par lien éprouvée en production. Il n'y aurait alors plus rien à voler.
- **Se déconnecter de tous ses appareils.** Le cookie de 30 jours ne se révoque aujourd'hui qu'en changeant d'adresse. Ajouter `password` (colonne désormais inutilisée) aux `signature_properties` de `remember_me`, et un bouton de la page du compte qui y écrit une valeur aléatoire : tous les cookies du compte cessent de valoir. Une demi-journée, avec un cas de `compare.py accounts`.

## Après la validation par les testeurs

**Ouvrir les données.** Objectif minimum fixé par Lou le 3 novembre, à commencer une fois ce BDOH stabilisé et validé par les testeurs.

- **Données ouvertes** : le cas par défaut. Visibles et téléchargeables sans compte, comme sur Recherche Data Gouv, sous licence ouverte.
- **Données fermées** : ni visibles ni exportables sans les droits, y compris quand elles font partie d'un jeu de données.
- **Comptes** : ils ne servent plus qu'à ce qui demande des droits (accéder à une chronique fermée, gérer un observatoire). Un panier de stations et de chroniques favorites peut s'y ajouter, si c'est simple.
- **Téléchargement** : un lien temporaire, ou un export dans le navigateur. Au-dessus d'un certain poids, un compte est nécessaire et le lien arrive par courriel ; plus de pièce jointe de plusieurs Go.
- **À établir d'abord** (Lou) : quelles chroniques sont ouvertes ou fermées, ce qui reste ambigu aujourd'hui.

Constats du 3 novembre, sur les copies anonymisées :

- La base n'a ni catégorie « public » ou « privé », ni part de financement. `estvisible` est le seul interrupteur, et ne dit pas pourquoi une chronique est cachée : 356 chroniques calculées visibles ont une entrée cachée (donnée brute non montrée, résultat publié), et un observatoire entier est caché. Le producteur n'est renseigné que pour 1 502 chroniques sur 3 012, et un partenaire n'a qu'un type et une case « financeur », sans montant.
- Répartition de la production : 790 chroniques visibles dans un jeu avec DOI (74 millions de mesures), 286 dans un jeu sans DOI, 1 168 hors jeu, 759 cachées hors jeu (108 millions) et 9 cachées dans un jeu avec DOI. Une dizaine de chroniques visibles viennent d'un partenaire de type « Other » ou d'un nom de partenaire privé (CNR, EDF…), à revoir. Sur OTHU, les 119 chroniques sont visibles.
- Sans compte, une chronique cachée ne se visualise pas : sa page et ses valeurs répondent 403 (essai sur le nouveau, `canConsultTimeseries`, même règle dans l'ancien). Ses métadonnées, elles, se voient (`dev/differences.md`, « Chroniques cachées citées dans les listes »).
- Tout téléchargement demande aujourd'hui un compte et un droit explicite (`BdohRightVoter::canDownload`), et part en pièce jointe d'un courriel (`AbstractExportCommand`). Le visualiseur sert sans compte les valeurs d'une chronique visible, jusqu'à 50 000 points par fenêtre.
- Seul un observatoire de la production a un PDF de conditions, alors que les 28 jeux y renvoient : la licence des données reste à décider.
- Cadre légal : l'[article L533-4 du code de la recherche](https://www.legifrance.gouv.fr/codes/article_lc/LEGIARTI000033205794) rend libre la réutilisation des données publiées d'une recherche financée au moins pour moitié par des fonds publics, sauf droit ou réglementation particulière ; il ne dit pas lui-même ce qui doit être publié, à voir avec le service juridique et données d'INRAE.

## Plus tard, après la bascule

- **Envoi automatique des fiches vers Theia/OZCAR.** Peut-être à faire (Lou, 3 novembre). La colonne existe, mais la case est en commentaire et rien ne lance l'envoi (`dev/differences.md`, « Mise à jour automatique ») : il faudrait réactiver la case et ajouter une tâche planifiée.
- **Chaîne de compilation du JavaScript à jour.** Le JavaScript se compile avec les paquets de l'ancien (Encore 0.15, webpack 3, babel 6), sous le Node de Debian 13 grâce à l'option `--openssl-legacy-provider` (`dev/decisions.md`). Passer à un Encore récent, et mettre à jour les bibliothèques servies aux navigateurs (jQuery 2.2, Bootstrap 3, moment), changerait tous les fichiers compilés : un chantier à part, vérifié dans un navigateur.
- **Lacunes à -9999 dans la base.** Les fonctions de calcul écrivent -9999 comme valeur des lacunes (3,9 millions de mesures de chroniques calculées), alors que les imports y mettent `NULL` : la base a deux conventions, et une valeur sentinelle qu'un calcul fait hors de BDOH peut prendre pour une vraie mesure. Passer à `NULL` partout touche neuf fonctions de calcul et ces mesures : à faire une fois le nouveau BDOH identique à l'ancien, comme chantier à part, vérifié par la comparaison. -9999 reste la valeur des lacunes dans les fichiers d'import et d'export, format public que les utilisateurs emploient.
- **Page de l'historique plus légère.** Elle rend d'un coup tous ses onglets, chaque entrée deux fois (« tout » et sa sorte) : 11 Mo et 7 500 lignes de tableau pour l'année en cours de l'observatoire le plus fourni (mesure du 26 octobre, même poids et même temps dans l'ancien). Ne rendre que l'onglet ouvert, ou servir les tableaux par pages, la rendrait utilisable dans un navigateur modeste ; à faire après l'identité, comparée à part.
- **Critique des données, pour les gestionnaires.** L'inspection des bases relève des mesures valides hors des seuils de validité, et, sur OTHU, des lots de mesures au minimum et au maximum inversés ([dev/audits/2026-10-23_reference-db.md](dev/audits/2026-10-23_reference-db.md)). Hors du portage ; un retour aux gestionnaires d'observatoire est possible à partir de cette trace.
