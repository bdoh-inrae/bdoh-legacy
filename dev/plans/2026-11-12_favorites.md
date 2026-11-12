# Les favoris et leurs étiquettes

État : en cours, ouvert le 2026-11-12 ; écrit, comparé, relu, constats de la relecture corrigés (Suivi) ; restent `make browser-check` et les fautes de `mutants.py` (« Pour reprendre »). Point « Favoris et étiquettes » de `ROADMAP.md`. La forme est celle que Lou a choisie le 12 novembre au soir ; ce fichier dit comment on la fait.

## Ce que voit un compte

- Une étoile sur la page d'une chronique et dans les listes de chroniques (station, « Nos données »), pour un compte connecté : un clic l'allume ou l'éteint, sans quitter la page. Toute chronique peut être mise en favori, cachée comprise, puisque sa fiche est publique.
- « Mes favoris », dans le menu du compte, sur le portail comme sur un observatoire : une seule page, `/favorites`, parce que les favoris d'un compte traversent les observatoires. Elle est rangée par observatoire, puis par station.
- Des étiquettes posées sur les favoris cochés (« thèse Yzeron »), et retirées de même ; une chronique peut en avoir plusieurs. En tête de page, chaque étiquette, avec son nombre de favoris : un clic coche exactement son groupe.
- « Exporter la sélection », un bouton par observatoire : un export ne mêle pas deux observatoires (sa route, ses requêtes et le nom de l'archive sont ceux d'un observatoire). Il ouvre la fenêtre d'export qui existe déjà, pour les chroniques cochées, avec les rééchantillonnages que toutes permettent (une discontinue n'en permet aucun) ; le lien arrive par courriel, comme pour tout export d'un compte. Une chronique cachée sans droit se coche, mais l'export la refuse, comme aujourd'hui : la page le signale et renvoie à la demande d'accès.
- « Retirer des favoris », pour les cochés.

## Comment

- Une table, `favorite` : `id`, `utilisateur_id`, `chronique_id` (supprimés avec le compte ou la chronique), `labels` (`jsonb`, une liste de noms), `created` ; un favori par compte et par chronique. Une seule table, et non deux comme annoncé à Lou : les étiquettes d'un compte sont quelques noms, que la page relit tous. Le type `textarray` de BDOH n'échappe pas ses valeurs et ne se relit pas : le `json` de Doctrine, si. Migration écrite à la main, avec son retour arrière, essayée sur la base complète.
- Noms en anglais (table, classes, routes, adresses), selon la règle des identifiants ; les colonnes de jointure gardent le nom des tables de l'ancien.
- `FavoriteController` (BdohSecurityBundle) : la page (`/favorites`, déclarée dans `config/routes.yaml`, comme les pages du compte du portail), ses actions par formulaire (étiquettes, retrait), l'étoile (`/{observatoire}/favorites/{id}`, en POST, jeton CSRF, réponse JSON) et la fenêtre d'export (`/{observatoire}/favorites/export`, le gabarit `Modal/export.html.twig` pour les chroniques cochées).
- `LoginEntryPoint` renvoie à la page de connexion du portail quand il n'y a pas d'observatoire (`AccountRoutes`) : `/favorites` sans compte, sinon, ne saurait pas où envoyer.
- Icônes : `star` et `star-fill` de Material Symbols 0.48.1, comme les autres (`public/images/icons/`).

## Vérification

- `compare.py pages` : seuls changent, pour un compte connecté, l'étoile et l'entrée du menu ; règle au registre.
- `compare.py accounts`, « nouveau seul » : étoile posée puis retirée, jeton exigé, chronique d'un autre observatoire refusée, page rangée par observatoire, étiquettes posées et retirées, favoris d'un autre compte invisibles, fenêtre d'export avec les rééchantillonnages communs, export d'une sélection (la tâche et ses arguments ; l'archive de plusieurs chroniques, comparée à l'ancien, reste à ajouter à `run_exports` : constat 9), chronique cachée sans droit refusée, favoris partis avec le compte.
- `make browser-check` : l'étoile, la page, une étiquette qui coche son groupe, la fenêtre d'export qui s'ouvre sur la sélection.
- Une relecture par un second Claude, en lecture seule, avant de clore.

## Étapes

1. [x] Migration, entité, dépôt ; appliquée à `bdoh_small`, `bdoh`, `bdoh_othu`, `bdoh_othu_small`, retour arrière essayé.
2. [x] Contrôleur, gabarits, traductions, icônes, styles, JavaScript.
3. [x] Cas de `compare.py` ; passes `accounts`, `pages`, `admin`, `imports` (Suivi). [ ] `make browser-check` (étapes écrites dans `dev/browser/journeys.py`, `favorites`, pas encore jouées).
4. [x] Relecture faite, `dev/differences.md` et `CHANGELOG.md` écrits. [x] Ses constats, plus bas, chacun corrigé et éprouvé par un cas (Suivi du 12 novembre, nuit). [ ] Leurs fautes dans `mutants.py`, vues.

## Pour reprendre

Restent : les fautes des favoris dans `mutants.py` (`--only favoris`), puis `make browser-check`, puis la version 0.5.0.

Les constats de la relecture du 12 novembre (un second Claude, en lecture seule), du plus grave au moins grave, tous corrigés le soir même ; ce qui les éprouve est entre crochets.

1. **Étiquette réinjectée en HTML dans le message flash** (`FavoriteController::apply`, messages `labelAdded` et `labelRemoved`) : toastr insère ses messages sans échappement (`escapeHtml: false`), si bien qu'une étiquette `<img src=x onerror=alert(1)>` s'exécute après la redirection, sur son propre compte seulement (le jeton CSRF empêche d'en viser un autre). Échapper `%label%` (`htmlspecialchars`), comme `SecurityController` ; piège déjà dans `dev/decisions.md`. Cas : une étiquette avec du HTML, qui revient échappée dans `data-notices`. [Corrigé ; « étiquette en HTML échappée dans le message ».]
2. **Vérification à vide** : « favoris d'un autre compte invisibles et intouchables » lit le jeton du second compte sur sa page des favoris, vide faute de favori, donc sans formulaire : la requête tombe sur le contrôle CSRF, et rien n'est vérifié. Faire poser une étoile au second compte d'abord, exiger un jeton non vide, et inclure son propre favori dans l'envoi, qui doit recevoir l'étiquette « intrus » quand les deux autres ne changent pas. [Corrigé : le second compte a son favori, qui seul reçoit « intrus ».]
3. **Vérification à vide** : dans « page rangée par observatoire », `lines.index('A /SLUG/home')` trouve d'abord le menu des observatoires, présent sur toute page : « chaque chronique sous le sien » passe toujours. Prendre les dernières occurrences (les titres de groupe) et exiger titre i < lien < titre i+1. [Corrigé : chaque case cochable entre le titre de son observatoire et le suivant.]
4. Un identifiant au-delà de l'entier 32 bits (`chroniques[]=99999999999` à l'export, `favorites[]=…` au formulaire) donne une erreur 500 (« value out of range for type integer ») ; la route de l'étoile s'en garde déjà (`\d{1,9}`). Filtrer `0 < id <= 2147483647` aux deux endroits. [Corrigé (`FavoriteController::ids`) ; « identifiant hors des entiers et étiquette à caractère de contrôle, sans erreur ».]
5. Une étiquette avec le caractère nul donne une erreur 500 (`jsonb` refuse `\u0000`) : `Favorite::normalizeLabel` doit remplacer aussi les caractères de contrôle (`[\s\p{Cc}]+`). [Corrigé ; même vérification.]
6. Le bouton « Aide » de la fenêtre d'export ne fait rien sur la page des favoris : inclure `@IrsteaBdohConsult/Chronique/_export-help.html.twig` dans `list.html.twig`. [Corrigé ; à voir dans `make browser-check`.]
7. Sans session (déconnecté dans un autre onglet), « Exporter la sélection » insère la page de connexion entière dans la page : dans `favorites/index.js`, recharger la page si la réponse n'a pas `data-favorites-export-report`, comme l'étoile. [Corrigé ; à voir dans `make browser-check`.]
8. La page des favoris montre le lien vers une chronique cachée sans droit, qui répond 403 : le conditionner à `is_granted('CONSULT_TIMESERIES', chronique)`, comme les listes des stations (ce test juge d'après l'observatoire de la chronique, il marche sur le portail) ; adapter alors la vérification 3, qui cherche ce lien. [Corrigé ; « page : lien d’une chronique cachée seulement avec le droit de la consulter », le droit donné puis repris dans la copie.]
9. **Ce qui n'est pas éprouvé** : l'export de plusieurs chroniques n'a aucun cas dans `run_exports` (coupé dans l'interface de l'ancien) ; l'intersection des rééchantillonnages passerait aussi avec les options d'une seule chronique si les deux choisies ont les mêmes ; les branches « une discontinue » (attendu : `identical` seul, format BDOH seul) et « sans mesure » ne se jouent pas. Ajouter un ou deux exports à deux chroniques (à l'identique, en moyenne) comparés à l'ancien, et une discontinue et une chronique d'autres options dans la fenêtre. [Fait : `run_exports` joue deux continues et une continue avec une discontinue (`several_export_cases`) ; la fenêtre, une paire dont chacune a une option que l'autre n'a pas, cherchée sur leurs pages, et une discontinue.]
10. `dev/browser/journeys.py`, `favorites` : `user.problems.clear()` efface toutes les erreurs de l'étape d'export, pas seulement le téléchargement abandonné ; retenir leur nombre avant le téléchargement et tronquer à ce nombre. [Corrigé, et de même aux deux autres étapes qui téléchargent.]
11. `dev/anonymize/anonymize.sql` n'a pas de règle pour `favorite.labels` (texte libre) : le script refusera le premier dump de la nouvelle production. Ajouter `('bdoh', 'favorite', 'labels', 'replace', $$'[]'$$)` (la colonne est `NOT NULL`). [Corrigé.]

Ce que la relecture a vérifié et trouvé correct : filtrage par compte de toutes les requêtes (pas d'IDOR), CSRF avant tout, chroniques limitées à l'observatoire de la page (404 sinon), aucun export sans droit `DOWNLOAD` (refait par `ExportController`), intersection de même forme que `ChroniqueController`, échappement dans Twig et le JavaScript, portail (contexte de l'observatoire chargé avant le pare-feu, `LoginEntryPoint`, retour à `/favorites` après connexion, `ROLE_USER` sans observatoire), migration, pièges de DBAL 4, compatibilité avec Babel 6, double clic.

## Suivi

- 2026-11-12, soir : écrit. Essai à la main sur une copie de `bdoh_small` : étoile, refus d'une chronique d'un autre observatoire (404), étiquettes, favoris d'un autre compte intouchés, chronique cachée sans droit nommée à part, fenêtre d'export (rééchantillonnages communs). `compare.py accounts` 44/0/0/17/0, dont les dix vérifications « nouveau seul : favoris », toutes conformes. Icônes `star` et `star-fill` de Material Symbols 0.48.1, prises sur jsDelivr après avoir vérifié que la même source redonne `search.svg` à l'identique. Les jetons CSRF portés par un attribut `data-*` se lisent `<jeton>` dans `compare.py`. Relecture par un second Claude lancée.
- 2026-11-12, nuit : les onze constats corrigés, chacun éprouvé par une vérification de `compare.py accounts` (« Pour reprendre ») ; `compare.py accounts` 49/0/0/17/0 sur `bdoh_small`. Ce que la vérification des deux options a appris : les options d'export d'une chronique sont souvent les siennes, pas celles par défaut, et une paire prise au hasard a des options emboîtées (l'intersection est alors celle de l'une) ; la vérification cherche donc sur leurs pages une paire dont chacune a une option que l'autre n'a pas. En passant, le rapport de `compare.py` nommait pour chaque écart admis la première règle dont le motif couvre le cas, si bien que la règle du menu des favoris, qui vaut pour toute page connectée, nommait aussi les courriels et l'état des tables : `admitted` rend désormais les seules règles qui expliquent une ligne de l'écart (`test_compare.py`). Huit fautes des favoris ajoutées à `mutants.py`.
