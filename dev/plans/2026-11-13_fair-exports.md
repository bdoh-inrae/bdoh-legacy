# Des exports plus FAIR

État : en cours, ouvert le 2026-11-13. Point « Des exports plus FAIR » de `ROADMAP.md`, avancé par Lou avant l'envoi des essais aux gestionnaires (lundi 16 novembre) : « on ne fait pas tester un truc qui va changer autant après ».

## Choix de Lou (13 novembre)

- Un format CSV à côté des formats BDOH et Qtvar, gardés tels quels pour les utilisateurs actuels : virgule entre les colonnes, point décimal.
- Des CSV de métadonnées à côté des données, comme dans MEANDRE (`data.csv` et ses `meta_*.csv`).
- Pas de Parquet dans ce dépôt (`ROADMAP.md`, la raison).
- CSVW, la description des CSV que recommande le W3C : à essayer et à montrer.
- Un README plutôt qu'un rapport : c'est l'usage (le guide de Cornell, que suivent Dryad et le modèle « RDG x RiverLy » de Lou), et l'export doit être propre dès maintenant.
- Le nom de l'archive dit ce qu'elle contient ; le nommage de MEANDRE en donne l'idée.

## Ce que font les projets de Lou (pistes, pas une norme)

- **curation-RDG et dataverseuR** (`create_datasets_README`) : un `README.txt` en texte brut, une bannière, « créé le » et « dernière mise à jour », puis des sections en capitales soulignées : informations générales (titre, DOI, citation, contact, publication), informations méthodologiques, aperçu des fichiers (arborescence, convention de nommage), informations propres aux données, fichier par fichier : colonnes décrites par `displayed_name`, `long_name`, `description`, `type`, `unit`, `allowed_values`, puis « Missing data codes: NA ». Les CSV : virgule, point décimal, champs texte entre guillemets (`write.csv` de R, `ASHE::write_tibble`).
- **MEANDRE** : `MEANDRE-export+var-QA+H-2041_2070+n-4+chain-….zip`, des couples clé-valeur joints par `+` ; dans l'archive, `data.csv`, `meta_point.csv`, `meta_variable.csv`, `meta_projection.csv`, `README.txt` (bannière, date de génération, adresse et contact, informations générales, méthodologiques, paramètres de l'analyse, arborescence, puis chaque colonne : nom, description, unité, valeurs autorisées) et les deux textes de la Licence Ouverte.

## Ce qui se fait

1. **Le nom de l'archive**, pour tous les formats, calculé au téléchargement d'après la tâche (`ExportController::fileAction`) : `BDOH-export+obs-{observatoire}+{chr-{station}_{chronique} | n-{nombre}}+type-{type}[+step-{pas}]+period-{début}_{fin}+on-{date de l'export}.zip`, en caractères sûrs.
2. **Le format CSV** (`Formatter/Csv.php`) : un fichier par chronique, `{station}_{chronique}.csv`, aux mêmes colonnes partout, pour que les mettre bout à bout donne le format long : `station`, `series`, `time`, `time_end`, `value`, `quality`, `value_min`, `value_max`. UTF-8, virgule, point décimal, une ligne d'en-tête, dates ISO 8601 avec le décalage du fuseau choisi, `NA` pour une valeur absente (une lacune garde sa ligne et son code de qualité).
3. **Les métadonnées en CSV** : `meta_series.csv` (une ligne par chronique : nom, paramètre, unité, nature, accès et licence, jeu de données et son DOI, producteur, fichier, nombre de valeurs et de lacunes, première et dernière date), `meta_station.csv` (nom, latitude et longitude en WGS 84 tirées du point, altitude, commune, sites), `meta_quality.csv` (les codes de qualité du jeu de l'observatoire, leur libellé et leur sorte).
4. **CSVW** : `csv-metadata.json` décrit ces tables et leurs colonnes, tiré de la même description que le README.
5. **`README.txt` à la place de `Report.txt`**, pour tous les formats : ce que disait le rapport (période, fuseau, rééchantillonnage, généalogie, barèmes des chroniques calculées), plus la licence, comment citer, l'arborescence et la description des colonnes ; pour BDOH et Qtvar, le renvoi à `FormatsExport.pdf`, qui reste. Les fichiers de données de ces deux formats ne changent pas d'un octet.
6. **La fenêtre d'export** propose « CSV », avec tous les rééchantillonnages.

## Pour reprendre (13 novembre, soir)

Tout est écrit et commité ; la passe complète (tous les modes de `compare.py`, puis `make browser-check`) tournait au moment du commit. Si elle passe : version 0.7.0 (`CHANGELOG.md`, « Non publié »), tag, release. Puis, sur la VM, une fois la fusion d'OTHU finie et son compte rendu noté (`dev/plans/2026-11-13_othu-merge.md`, étape 6) : `sudo make -C /var/www/bdoh-prod vm-update NOM=prod V=v0.7.0`, la liste de Lou (`dev/test-kit/lou-checklist.md`), et l'envoi des essais lundi 16 novembre. Si elle ne passe pas : lire les écarts dans `../bdoh_reference/compare/`, corriger, rejouer le mode.

## Vérification

- `compare.py exports` : les fichiers `.txt` identiques à l'ancien ; `Report.txt` devenu `README.txt` et le nom de l'archive, écarts admis (`dev/differences.md`) ; des cas « nouveau seul » pour le CSV (colonnes, `NA`, dates, une discontinue, une calculée, une cachée).
- Le CSV relu par R (`read.csv`) et par Python (`csv`, et `pandas` s'il est là) : types, valeurs manquantes, dates ; le CSVW validé.
- `make browser-check` : l'export CSV jusqu'au fichier.
- Relecture par un second Claude, en lecture seule.

## Étapes

1. [x] La barre d'abord (`dev/plans/2026-11-11_interface.md`, point 3).
2. [x] Nom de l'archive.
3. [x] Formateur CSV et fenêtre d'export.
4. [x] Métadonnées CSV et CSVW.
5. [x] README.
6. [ ] Comparaison, essais, relecture ; version 0.7.0 ; la VM avant lundi.

## Suivi

- 2026-11-13, après-midi : **relecture** par un second Claude, en lecture seule, de la barre, des exports et de l'outil. Quinze constats, tous traités :
  1. l'unité de `meta_series.csv` était celle de la chronique, même pour un cumul, dont les valeurs sont dans l'unité des cumuls (m3/s pour des m3) : elle vient de l'extraction, comme l'en-tête du format BDOH. Éprouvé : la faute remise est vue par `csv_checks`, une fois le cas tiré parmi les chroniques dont l'unité de cumul diffère (la première chronique à cumul était une pluie, en mm des deux côtés, et la faute passait) ;
  2. la lecture commune du rapport et du README échouait sur tous les cas (ordre, « UTC+01:00 », « 1 day » et « 1 jour », ligne repliée) et aurait lu « 1 mois » en « 1 moi » : faits rangés dans un même ordre, pas ramenés à « 1 month », README fait de champs courts (« Mesures présentes », « Valeurs exportées », « Rééchantillonnage », « Pas de temps »), et une classe de tests (`ReportFacts`) sur des textes écrits comme les écrivent les deux codes, avec des fautes qui doivent se voir ;
  3. la généalogie, le calcul (entrées, barèmes, limites) et le nombre de valeurs exportées sont désormais comparés, aux blancs près ; le README dit, comme l'ancien rapport, les mesures présentes et les valeurs exportées, et garde le retrait du calcul, qui dit quel barème va avec quelle chronique mère ;
  4. `csv_checks` joue neuf cas (continue à l'identique, en moyenne, en cumul, en interpolation à UTC-05 et en anglais ; discontinue, calculée, convertie, cachée ; une cachée et une ouverte ensemble) et compare au format BDOH les dates de début et de fin, les valeurs, les codes de qualité hors lacunes, le minimum et le maximum ; le README se vérifie ligne entière ;
  5. le CSVW donne sa licence à chaque table (conditions générales pour les valeurs d'une chronique cachée, Licence Ouverte pour le reste), au groupe seulement si elle est la même pour toutes ;
  6. `meta_quality.csv` marque une lacune comme le formateur (`JeuToJeu::isGapType`) ;
  7. `time_first` et `time_last` sont la première et la dernière ligne du fichier, relevées par le formateur ;
  8. un code de qualité vide s'écrit `NA` ;
  9. l'export ne joint plus le fichier de conditions propre à un observatoire, que plus rien ne nomme (`dev/decisions.md`, « Ouverture des données ») ;
  10. le décalage du fuseau se prend au 1er janvier 2001, comme le SQL des extractions, et non au jour de l'export ;
  11. `json_encode` lève une erreur au lieu d'écrire un CSVW vide ;
  12. le menu du compte se dit menu aux lecteurs d'écran (`role`, `aria-haspopup`, `aria-expanded`), et le nom coupé d'un compte ou d'un observatoire se lit au survol ;
  13. le titre d'une chronique dans le README tient sur une ligne (son nom passe en dessous) ;
  14. les adresses écrites dans une archive sont celles de `DEFAULT_URI` : à changer à l'ouverture au public (`dev/plans/2026-10-26_deployment.md`, « Après ») ;
  15. le README et l'aide de la fenêtre disent comment ouvrir le CSV dans un tableur.
- 2026-11-13, après-midi : écrit. `Formatter/Csv.php` ; `Csv/Tables.php`, la description unique des colonnes ; `Csv/Metadata.php`, les métadonnées et le CSVW ; `Reporter/Readme.php`, le README, en PHP plutôt qu'en Twig pour tenir les 80 colonnes (les entrées et barèmes d'une chronique calculée viennent de `transformations.text.twig`, la partie de l'ancien rapport qu'il reprend) ; le nom de l'archive dans `ExportController::archiveName`. Dans `compare.py` : `report_facts` lit le rapport de l'ancien et le README du nouveau sous une même forme, et `csv_checks` éprouve le CSV contre le format BDOH. Le nom de colonne `series` plutôt que `chronique` : ce qui se tape est en anglais, et c'est le mot de l'interface anglaise de BDOH (« time series »). `NA` pour une valeur absente, comme dans les curations de Lou (« Missing data codes: NA »), que R et pandas lisent d'eux-mêmes. Pas de clé dans le CSVW pour les valeurs : rien dans la base n'interdit deux valeurs à la même date d'une chronique discontinue.
- 2026-11-13 : ouvert. Relevé de l'export : `Exporter.php` écrit les licences, `FormatsExport.pdf`, un `.txt` par chronique et `Report.txt` (`report.text.twig`) ; l'archive se télécharge sous le nom `export.zip`. Un export ne mêle jamais deux observatoires (`dev/plans/2026-11-12_favorites.md`). Les colonnes `latitude` et `longitude` de la station portent en fait les coordonnées du point en Lambert 93 (EPSG:2154) : les métadonnées donnent le WGS 84 tiré du point.
