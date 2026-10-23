# Repères du portage

Les choix techniques du portage, avec leur raison : ce qu'un prochain Claude déferait ou referait sinon. La méthode générale est dans `CLAUDE.md` ; les écarts de comportement avec l'ancien BDOH, dans `dev/differences.md`.

- **Structure gardée.** Le nouveau code garde les bundles et les espaces de noms de l'ancien (`src/Irstea/Bdoh*Bundle/`), fichier pour fichier : chaque fichier porté se compare à son original, et la différence ne montre que la montée de version. Un dépôt voué à l'archive n'a pas besoin d'être réorganisé. (2026-10-23)
- **Correspondance Doctrine en XML**, exportée de l'ancienne par Doctrine lui-même (`doctrine:mapping:convert xml` dans la référence). Tables, colonnes et séquences y sont nommées en toutes lettres, si bien que la stratégie de nommage ne joue pas, et les classes d'entités restent telles quelles, traits partagés compris. Doctrine ORM 3 lit le XML, plus le YAML. Vérifiée par la comparaison des métadonnées chargées par l'ancien et par le nouveau. (2026-10-23)
- **Dates des mesures en texte.** Le type `datetimems` de l'ancien ne convertit rien : les dates arrivent en PHP comme le texte de PostgreSQL, millisecondes comprises. Le nouveau garde ce comportement. (2026-10-23)
- **Deux serveurs de base.** La référence reste sur PostgreSQL 11, comme la production ; le nouveau tourne sur PostgreSQL 17, comme Debian 13. Chacun a son fichier compose et son réseau interne (`dev/reference/compose.yaml`, `compose.yaml`). (2026-10-23)
- **Comparer ce que les pages montrent**, pas leur balisage : texte, liens, formulaires, attributs `data-*` (`dev/compare/compare.py`). Sonata 4 et Bootstrap changent le balisage de l'administration sans changer ce qu'elle montre. (2026-10-23)
