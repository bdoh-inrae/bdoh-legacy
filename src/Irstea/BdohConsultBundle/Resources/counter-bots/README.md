# Les robots de COUNTER

`counter_bots.txt` : la liste des robots de COUNTER, une expression régulière par ligne, copiée telle quelle de
https://github.com/Project-Counter/counter-bots (commit 91f998d278 du 15 octobre 2026), sous licence MIT (`LICENSE`).
COUNTER la publie au plus une fois par trimestre et au moins une fois par an : la reprendre une fois par an.

Le compteur des consultations (`Statistics/ViewCounter.php`) écarte les visites qu'elle reconnaît, sauf les outils
dont se servent les chercheurs (python, curl, wget, java) : le Code of Practice for Research Data de COUNTER (version
1.0.1, section 10.5) demande de les compter. Le filtre est dans le code, pour que le fichier reste celui de COUNTER.
