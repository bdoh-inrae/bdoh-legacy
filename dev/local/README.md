# Notes locales

Ce dossier porte ce que le portage doit garder sans le publier : les points de sécurité sensibles, failles de l'ancien BDOH comprises. Git l'ignore, sauf ce fichier : chaque session le lit dans le dépôt, mais rien n'en part sur GitHub, et rien n'en est sauvegardé par Git. On le garde donc court, et on en sort ce qui peut devenir public.

- `security.md` : les failles relevées, avec leur correction et leur vérification ; ce qui reste à faire. À la mise en service du nouveau BDOH, les failles corrigées passent dans `dev/differences.md` (« Corrections de sécurité »).

Ce qui ne relève pas de la sécurité va dans les fichiers publics : écarts avec l'ancien dans `dev/differences.md`, tâches de la mise en service dans `dev/plans/2026-10-26_deployment.md`, choix dans `dev/decisions.md`.
