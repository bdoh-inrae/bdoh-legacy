# La base de référence : état des lieux du 23 octobre 2026

Audit daté : il ne change plus une fois écrit. Ses suites sont dans [ROADMAP.md](../../ROADMAP.md).

Base de production du 21 octobre 2026, anonymisée (`dev/anonymize/`), restaurée sous PostgreSQL 11 et PostGIS 2.5 (`make reference-db`). Contrôles de `dev/reference/inspect.sql`, qui ne sortent que des comptes, complétés de quelques requêtes ciblées.

## En bref

- La base est saine là où le portage compte : chaque mesure a une qualité du jeu de son observatoire, aucune plage ne chevauche une autre, et les compteurs des chroniques (nombre de mesures, première et dernière date) correspondent exactement aux mesures.
- Cinq compteurs automatiques d'identifiants sont en retard sur leur table : ajouter un élément à ces listes depuis l'administration échoue. Bogue de l'ancien BDOH, à corriger au passage.
- Deux chroniques remontent à 1816 : les dates anciennes sont un cas à inclure dans la comparaison.
- Aucune colonne raster : le passage à PostGIS 3 n'a pas de données raster à reprendre.

## Ce qu'elle contient

| Élément                         | Nombre                                                                       |
|---------------------------------|------------------------------------------------------------------------------|
| Mesures                         | 257 025 612, dont 42 913 009 calculées                                       |
| Plages, points de contrôle      | 60 895 et 15 672                                                             |
| Chroniques                      | 3 012 : 1 514 continues, 896 discontinues, 450 calculées, 152 converties     |
| Chroniques non visibles         | 768                                                                          |
| Chroniques sans aucune mesure   | 97                                                                           |
| Taux de remplissage             | 256 775                                                                      |
| Comptes                         | 781, dont 5 administrateurs et 257 dont l'accès a pris fin                   |
| Droits                          | 81 584 sur des chroniques, puis sur sites et observatoires (quelques centaines) |
| Tables laissées par des imports | 72, dans le schéma `temp`                                                    |

La base occupe 35 Go, presque entièrement pour les mesures.

## Ce qui est sain

Aucune mesure sans qualité ; aucune dont la qualité n'appartient pas au jeu de son observatoire ; aucune datée dans le futur ; aucun point de contrôle en double ; aucune plage dont la fin précède le début ni qui chevauche la précédente ; aucun taux de remplissage hors de 0 à 1 ni orphelin ; aucun nom à l'encodage abîmé ; aucune chronique sans station ni sans observatoire. Les métadonnées des chroniques (`nbmesures`, `datedebutmesures`, `datefinmesures`) correspondent toutes aux mesures.

Aucune mesure n'a de millisecondes : le format le permet, la production ne s'en sert pas.

## Ce qui sort de l'ordinaire

- **Compteurs d'identifiants en retard.** Les séquences de `criteriageology`, `criteriaclimat`, `milieu`, `topiccategory` et `typefunding` sont restées à 1, alors que ces tables vont jusqu'à 6 ou 8 : leurs lignes ont été insérées avec des identifiants explicites. Un ajout depuis l'administration tire l'identifiant 2, déjà pris, et échoue ; chaque échec avance la séquence, si bien que l'ajout finit par passer après plusieurs essais. À corriger en recalant ces séquences (`setval`), sans autre effet.
- **Lacunes avec une valeur.** 3 910 714 mesures en qualité de lacune portent une valeur, toutes égales à -9999, toutes dans 222 chroniques calculées. C'est la convention des fonctions de calcul, pas une anomalie ; la comparaison doit la retrouver telle quelle.
- **Dates anciennes.** 82 720 mesures de deux chroniques datent de 1816 à 1899, deux par jour. Avant 1911, l'heure légale de Paris était l'heure moyenne de Paris (UTC+0:09:21) : toute conversion par le fuseau nommé `Europe/Paris` y produit ce décalage. Les deux chroniques entrent dans la comparaison des exports.
- **Configurations incomplètes.** 28 chroniques calculées n'ont pas de première transformation, et une chronique convertie n'a pas de conversion. Le nouveau BDOH doit les afficher sans erreur, comme l'ancien, et refuser proprement de les recalculer.
- **Qualité des données, pour les gestionnaires plutôt que pour le portage.** 902 485 mesures de qualité valide sortent des seuils de validité de leur chronique (le contrôle à l'import est désactivé dans le code) ; 115 875 ont une valeur hors de leur propre minimum et maximum ; 14 mesures valides n'ont pas de valeur ; une a un minimum supérieur à son maximum. Le portage ne change rien à ces mesures.

## Ce que ça appelle

- Recaler les cinq séquences, comme bogue corrigé au passage (`dev/differences.md`).
- Inclure dans la comparaison les deux chroniques antérieures à 1900, les chroniques calculées et leurs lacunes à -9999, et les chroniques à configuration incomplète.
- Repasser `inspect.sql` sur la base d'OTHU, puis après chaque changement de version de PostgreSQL et à la bascule : les comptes doivent rester égaux.
