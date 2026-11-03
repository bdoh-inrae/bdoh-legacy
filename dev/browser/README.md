# Essais de l'interface

`check.py` essaie le nouveau BDOH dans un vrai navigateur (Playwright, Chromium sans fenêtre) : ce que `dev/compare/compare.py`, qui ne lance pas de JavaScript, ne voit pas. Il tourne sur une copie de la base réduite, et sur un observatoire sans aucune chronique cachée.

```
python3 -m venv .python_env
.python_env/bin/pip install -r dev/browser/requirements.txt
.python_env/bin/playwright install chromium
make start
make browser-check
```

Les captures vont dans `../bdoh_reference/browser/`, hors du dépôt. Les requêtes vers l'extérieur qui échouent sont ignorées : le réseau du développement n'en a pas besoin.
