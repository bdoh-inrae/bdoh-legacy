# Couverture de la comparaison

Quelles lignes du nouveau BDOH la comparaison exécute-t-elle ? En PHP, par PCOV ; en SQL, où vivent les calculs, par `plpgsql_check`. Une ligne jamais exécutée n'a rien prouvé. Une ligne exécutée peut rester non vérifiée : c'est ce que mesurent les fautes de `dev/compare/mutants.py`. Le but est donc une liste à lire, ce qui écrit d'abord, pas un pourcentage.

```
dev/coverage/coverage.py start
BDOH_COVERAGE=1 dev/compare/compare.py MODE ...   # les modes, comme d'habitude (dev/plans/2026-11-04_validation.md)
dev/coverage/coverage.py report                   # écrit php.txt et sql.txt dans ../bdoh_reference/coverage/
dev/coverage/coverage.py stop
```

- **PHP.** `start` recrée le conteneur `bdoh-app` avec `coverage.yaml`, sur une image à part (`Dockerfile`), pour ne pas changer celle qui décrit la production. Chaque processus écrit à sa fin les lignes de `src/` qu'il a exécutées (`prepend.php`). Mesuré le 5 novembre : PCOV écrit `1` pour une ligne exécutée, `-1` pour une ligne vue et pas exécutée, y compris dans les fonctions jamais appelées, et rien pour un fichier jamais chargé. Opcache est coupé, ce qui ralentit les pages.
- **SQL.** `start` met `plpgsql_check.profiler` sur le rôle `bdoh`, sans redémarrer le serveur. Mesuré le 5 novembre : un profil est propre à une base, ne suit pas sa copie, et se collecte sans l'extension, qu'il suffit de créer pour le lire. `compare.py` relève donc le profil d'une copie avant de la détruire (`BDOH_COVERAGE=1`), et `report` relève les bases restantes. Les réglages d'une base (`ALTER DATABASE`) ne passent pas à ses copies, d'où le réglage par rôle. Les numéros d'instruction ne valent que pour un texte de fonction, et OTHU en a d'autres versions : chaque relevé porte l'empreinte du texte, et seules les versions de la base réduite se fusionnent dans la liste ; les autres se comptent à part (6 novembre).
