# BDOH, nouvelle base de code

Portage de BDOH vers Symfony 7.4 et PHP 8.4, dans un dépôt neuf destiné à être public. L'ancien code est dans `../bdoh/`, figé : c'est la référence de comportement, on ne le modifie pas pour les besoins du portage. (2026-10-23)

- Méthode : portage module par module dans un squelette neuf, pas de montée version par version de Symfony. Les versions intermédiaires (3.4 à 6.4) demanderaient chacune leur propre adaptation de Sonata, de la sécurité et de la file de tâches, réécrite à l'étape suivante ; la comparaison avec l'ancien BDOH localise les régressions par module aussi bien. Un module passe la comparaison avant qu'on attaque le suivant ; un écart est corrigé, ou tranché par Lou et noté. (2026-10-23)
- Une seule chose change à la fois : la version de PostgreSQL se teste avec l'ancien code, avant le portage. (2026-10-23)
- Les adresses publiques de l'ancien BDOH restent valides : des DOI et le portail Theia/OZCAR y renvoient. (2026-10-23)
- Aucune donnée dans le dépôt : ni dump, ni sortie de référence (certaines séries ne sont pas publiques). Elles vivent hors dépôt, dans `../bdoh_reference/`. Rien non plus qui nomme une machine, une adresse ou un accès, ni ce qui relève du suivi d'incident. (2026-10-23)
- Claude n'ouvre que des bases anonymisées. Un dump brut passe par le script d'anonymisation, que Lou lance ; Claude ne voit que son rapport, fait de comptes, et le dump qui en sort. (2026-10-23)
- Pas de dépôt distant pour l'instant : rien n'est poussé sans l'accord de Lou. (2026-10-23)
