# Essais de l'interface

`check.py` essaie le nouveau BDOH dans un vrai navigateur (Playwright, Chromium sans fenêtre) : ce que `dev/compare/compare.py`, qui ne lance pas de JavaScript, ne voit pas. Il tourne sur une copie de la base réduite, et sur un observatoire sans aucune chronique cachée.

```
python3 -m venv .python_env
.python_env/bin/pip install -r dev/browser/requirements.txt
.python_env/bin/playwright install chromium
make start
make mail-start
make browser-check
```

Les captures vont dans `../bdoh_reference/browser/`, hors du dépôt. Les requêtes vers l'extérieur qui échouent sont ignorées : le réseau du développement n'en a pas besoin.

`check.py` joue les pages une à une, puis `journeys.py` les parcours de bout en bout : un nouveau compte, du formulaire au lien reçu par courriel ; un droit donné puis retiré sur une chronique cachée ; un import jusqu'au bout ; une période vidée ; une station créée puis supprimée ; un calcul relancé. Puis il parcourt tous les liens, sans compte et en gestionnaire, et ouvre et enregistre chaque fiche de chaque administration. Environ 12 minutes ; `make mail-start` d'abord : les parcours lisent leurs courriels dans Mailpit.

Ce que ça voit et que `compare.py` ne voit pas : le JavaScript, et ce qu'un navigateur envoie vraiment. Le 5 novembre, un parcours a ainsi trouvé la page « Pas trouvé » qui suivait toute suppression dans l'administration, alors que tous les modes passaient (`dev/decisions.md`).
