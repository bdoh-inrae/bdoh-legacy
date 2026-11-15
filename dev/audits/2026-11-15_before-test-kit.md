# Avant l'envoi du kit des essais

État des lieux du 15 novembre 2026 au soir, demandé par Lou : ce qui bloque l'envoi du kit aux gestionnaires (au plus tard le 16 au soir), ce qui peut attendre la semaine des essais sans changer ce que voient les testeurs, et l'accès au site sans le VPN. Fait en lecture : plans, guide de la VM, scripts, rapports de la passe du 13 novembre. Ses tâches partent dans `ROADMAP.md`.

## Où on en est

- **La fusion d'OTHU est finie** sur la VM (`dev/plans/2026-11-13_othu-merge.md`, Suivi du 13 novembre). La transaction est validée ; seule la dernière étape de `vm-merge-othu`, la liste des chroniques changées, s'est arrêtée (tri selon la langue, corrigé pour la 0.7.0). Ce qu'elle aurait fait ensuite, Lou l'a fait à la main : contrôle de l'empreinte (aucune chronique de la production changée), cache vidé, file relancée. Rien à refaire ; le schéma `fusion_othu` et les sauvegardes de `/var/backups/bdoh-kept/` restent pour le retour arrière, et partent après la bascule (`dev/plans/2026-10-26_deployment.md`, « Après »).
- **La VM est à la 0.6.0.** La 0.7.0 (exports CSV, README, nom de l'archive, barres rangées) est écrite, relue, commitée, et annoncée par les messages du kit ; sa comparaison n'est pas propre.
- **Les écarts de la passe du 13 novembre, lus le 15** (rapports dans `../bdoh_reference/compare/`) :
  - `exports`, 285 différents : l'ancien `Report.txt` écrit les apostrophes de la généalogie en `&#039;`, le README les écrit telles quelles (286 lignes, toutes identiques une fois les entités décodées). C'est un bogue de l'ancien : la macro `genealogie` de `report.text.twig` échappe en HTML, le bloc `autoescape false` ne s'étendant pas aux macros. S'y ajoutent 10 cas sans mesure sur la période, où l'ancien écrit « exportées 0 » et le README ne dit rien.
  - `state`, 3 différents : « courriels », la même généalogie ; « étapes », la nouvelle barre, que ce mode ne réécrit pas comme `pages` ; « jms_jobs », l'export en moyenne d'une chronique discontinue, tâche en échec, qui sort sur le code 1 au lieu de 255 (ancien et nouveau jusqu'à la 0.6.0) : à comprendre avant de l'admettre.
  - `accounts` : la vérification de la fenêtre d'export des favoris attend le seul format BDOH, alors que le CSV y est proposé.
  - Pas encore faits : le CSV relu par R et par pandas, le CSVW validé, `make browser-check` (`dev/plans/2026-11-13_fair-exports.md`, « Vérification »).

## Ce qui bloque l'envoi

Claude, le 15 au soir et le 16 au matin :

1. Corriger ce qui précède : décoder les entités de l'ancien rapport dans `report_facts`, avec son entrée dans `dev/differences.md` ; dire « Valeurs exportées : 0 » ; comprendre le code de sortie de l'export en échec ; étendre la règle de la barre à `state` ; mettre à jour la vérification d'`accounts`. Puis rejouer `exports`, `state`, `accounts`, `pages` si le registre change, lire le CSV dans R et pandas, valider le CSVW, `make browser-check`.
2. Publier la 0.7.0 : `CHANGELOG.md`, tag, release.

Lou, sur la VM et pour le kit :

3. `vm-update` vers la v0.7.0.
4. **Les contacts** : `make test-contacts` sur les deux dumps bruts, la production puis OTHU (le fichier des adresses autorisées réunit les deux), relire les destinataires, puis la ligne `MAILER_ALLOWED_RECIPIENTS` dans le `.env` de la VM. Sans elle, les liens de connexion des gestionnaires partent à `MAILER_DIVERT_TO`, et personne d'autre ne peut se connecter.
5. **Un vrai lien de connexion** reçu dans une boîte INRAE et dans une boîte extérieure, puis suivi : une messagerie qui ouvre les liens pour les analyser ne doit pas les consommer (la page du bouton est faite pour cela, jamais essayée avec un vrai courriel ; `dev/plans/2026-10-26_deployment.md`, « La machine »).
6. **Un collègue INRAE** ouvre le site sous son VPN : seul Lou l'a fait jusqu'ici, et le guide laisse ouverte la question de l'identifiant d'annuaire que demande le VPN.
7. De sa liste (`dev/test-kit/lou-checklist.md`), au moins : la restauration d'une sauvegarde de la nuit (les testeurs vont écrire dans la future production), un regard sur deux ou trois observatoires dont OTHU, un export CSV. Le reste peut se faire pendant la semaine.
8. Le kit : relire le message hors INRAE, le tableur et les destinataires ; `make test-kit` avec l'adresse et la date limite ; le mot de passe d'accès à la main.

Pas bloquant, une commande : retirer le PDF des conditions d'ORACLE (`ROADMAP.md`, « Suites de la passe juridique »).

## L'accès sans VPN

Aujourd'hui, seuls les gestionnaires INRAE essaient le site ; les autres valident leur tableur sans le voir (`dev/test-kit/messages/outside.txt`). Ouvrir le port HTTPS de la VM à Internet, en gardant le mot de passe d'accès sur tout le site et le détournement des courriels, laisserait tous les gestionnaires l'essayer. Ce n'est pas l'ouverture au public : le site reste fermé, et les préalables de l'ouverture au public (déléguée à la protection des données, direction des affaires juridiques, accord des producteurs extérieurs) ne s'y appliquent pas.

Ce que cela demande :

- de l'infra et du RSSI : le port 443 ouvert depuis Internet, et lui seul (pas SSH) ; un nom et un certificat reconnu. Avec l'adresse IP et le certificat auto-signé d'aujourd'hui, un partenaire extérieur tombe sur un avertissement du navigateur, ce qui fait mauvaise impression et apprend à passer outre ;
- sur la VM : le certificat dans le site Apache, `DEFAULT_URI` et `SYMFONY_TRUSTED_HOSTS` au nouveau nom (`vm-env` y a mis l'adresse IP), un mot de passe d'accès long et tiré au hasard, puisqu'il part par courriel à des dizaines de personnes, et une prison fail2ban sur l'authentification d'Apache ;
- le choix du nom. Soit le nom définitif tout de suite, derrière le mot de passe jusqu'à l'ouverture, qui se réduit alors à retirer ce mot de passe : un seul changement de nom, et des archives d'export qui portent déjà les bonnes adresses ; mais les visiteurs qui suivent un DOI tombent sur la demande de mot de passe. Soit un nom d'essai, à changer à l'ouverture.

Derrière le mot de passe, Internet ne voit qu'Apache et son module d'authentification ; l'application ne répond qu'ensuite. Pour une période d'essais, sur un système tenu à jour, c'est un risque raisonnable. Mais la décision revient à l'infra et au RSSI, après l'incident d'octobre : à demander tôt, sans en faire dépendre l'envoi du kit.

## Pendant la semaine des essais

Rien de ce que voient les testeurs ne change pendant leurs essais : seuls des correctifs partent sur la VM (0.7.x).

- **Validation**, dans l'ordre de `dev/plans/2026-11-04_validation.md` : passe de couverture, invariants (C4), pannes provoquées (C5), relecture en lecture seule du code qui écrit et de ce qui a changé depuis la 0.3.0 ; le code gelé (C6) vient après les retours.
- **Sécurité des dépendances** : `composer audit` et les versions correctives de Symfony 7.4 parues depuis le 5 novembre, avis lus un à un comme le 4 novembre ; relevé, sans rien changer, des failles connues des bibliothèques servies aux navigateurs (jQuery 2.2, Bootstrap 3, moment) qui toucheraient l'usage qu'en fait BDOH, la mise à jour de la chaîne restant pour après la bascule.
- **La VM** : `unattended-upgrades` actif, `ServerTokens Prod` et `ServerSignature Off` dans Apache, `systemctl --failed` et `inspect.sql` chaque jour la première semaine, les sauvegardes hors de la VM (encore à voir avec l'infra), la charge des pages des plus longues chroniques et `random_page_cost` si le disque est un SSD.
- **PHP dans Apache ou à part** : la raison principale de PHP-FPM, séparer deux instances, est partie avec la fusion d'OTHU ; garder `mod_php`, ce qui a été comparé, est à noter dans `dev/decisions.md`.
- **Mots de passe Theia** : la VM a une clé neuve (`vm-env`), si bien que ceux de la base ne se déchiffrent plus ; chaque observatoire qui envoie à Theia ressaisit le sien dans sa fiche avant le prochain envoi, au plus tard à l'ouverture au public.
- **Le dépôt** : supprimer la branche `interface`, fusionnée ; clore les plans finis ; sortir de `ROADMAP.md` ce qui est fait.
