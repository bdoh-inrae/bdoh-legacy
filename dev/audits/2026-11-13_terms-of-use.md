# Les conditions d'utilisation des données : passe juridique

Audit du 13 novembre 2026, à la demande de Lou : donner une cohérence juridique aux conditions d'utilisation des treize observatoires (OTHU compris), autour de la Licence Ouverte, la plupart des données étant publiques. Fait en lecture seule par un second Claude (le code au commit `5ec7640`, l'ancien code figé, les bases anonymisées, l'archive des fichiers déposés de la production, les textes en ligne), puis relu. Ce n'est pas l'avis d'un juriste : les points marqués DAJ (direction des affaires juridiques d'INRAE) ou DPO (sa déléguée à la protection des données) leur reviennent.

Ce qui était tranché ne se rediscute pas (`dev/decisions.md`, « Ouverture des données ») : la case `chronique.estvisible` est la seule frontière ; une chronique visible est sous Licence Ouverte, une cachée sous les conditions générales de BDOH, sur demande.

## Ce qui a été trouvé

- **Le lien « Conditions d'utilisation »** de l'accueil d'un observatoire (`/{observatoire}/terms_of_use`) servait en téléchargement le fichier du champ `observatoire.conditionsutilisation` s'il était rempli, sinon les conditions générales de BDOH, qui ne valent plus que pour les chroniques en accès restreint depuis la 0.3.0. Les fiches Theia/OZCAR citent cette adresse (`urlDataPolicy`, `Mapper.php`). La case des exports et des demandes d'accès mène à `/{observatoire}/conditions`, même fichier.
- **ORACLE**, le seul observatoire dont le champ est rempli : son fichier est une copie mot pour mot des conditions de BDOH de 2014 (Irstea, compte obligatoire, usage non commercial, interdiction de transmettre, « déclaration auprès de la CNIL »), sans aucune clause propre à ORACLE. Le lien le servait pour ses 520 chroniques visibles, alors que ses trois DOI déclarent la Licence Ouverte. Ses gestionnaires ne pouvaient pas le retirer : le champ a quitté l'administration en mars 2021 (commit `4ee500478` de l'ancien code).
- **Les jeux de données** (28 en production, 9 dans OTHU) avaient tous la même contrainte d'accès, « Conditions Generales Utilisation (voir pdf) », affichée sur leur page sous le libellé anglais « Data Constraint » et envoyée à Theia. Deux ont des chroniques en accès restreint (Arc-Isère, Rhône). Les 18 DOI de jeux que cite BDOH déclarent tous `etalab 2.0` sur Recherche Data Gouv (API consultée le 13 novembre) : la mise à jour prévue de leur licence est déjà faite.
- **La fenêtre d'export** renvoyait la licence vers l'ancienne adresse d'Etalab, qui redirige vers l'accueil de data.gouv.fr ; la version 2.0 n'était pas dite, ni l'usage commercial permis.
- **Les données personnelles** : aucune information à l'inscription ; le courriel de bienvenue citait les articles 39 et 40 de la loi Informatique et Libertés, abrogés par sa réécriture de 2018 ; téléphone et adresse postale obligatoires, sans usage dans BDOH.
- **Les conditions générales** renvoyaient à l'article 3 pour la citation (c'est l'article 4) et définissaient la force majeure par des articles du code civil remplacés par l'article 1218 en 2016.
- **OTHU** : ses 119 chroniques visibles viennent de l'INSA (DEEP, 117) et de l'UCBL (LEHNA, 2) ; aucune trace de ses anciennes mentions légales dans les copies dont on dispose.
- **Producteurs extérieurs de chroniques visibles** : surtout des établissements publics (CEREGE, ASNR, IGE…) ; une entreprise, EDF, pour 4 chroniques visibles d'Arc-Isère.

## Le droit (textes consultés le 13 novembre 2026)

- Code de la recherche, [L533-4](https://www.legifrance.gouv.fr/codes/article_lc/LEGIARTI000033205794) : les données d'une recherche financée au moins pour moitié sur fonds publics, rendues publiques et sans droit spécifique, sont de réutilisation libre ; toute clause contraire est « réputée non écrite », c'est-à-dire sans effet (IV). Une chronique cachée, communiquée sur demande et sous contrat, n'est pas rendue publique : ses conditions gardent leur effet.
- CRPA : [L321-2](https://www.legifrance.gouv.fr/codes/article_lc/LEGIARTI000033218992) c, ce sur quoi des tiers ont des droits n'est pas une information publique ; L321-3, le droit de producteur de base de données d'une administration ne fait pas obstacle à la réutilisation ; L322-1, mentionner la source et la date de dernière mise à jour ; L323-2, une licence ne restreint la réutilisation que pour un motif d'intérêt général, et pour une réutilisation gratuite elle se choisit dans la liste du décret : D323-2-1, la Licence Ouverte 2.0 et l'ODbL ; toute autre demande une homologation (D323-2-2, [data.gouv.fr](https://www.data.gouv.fr/pages/legal/licences)).
- Licence Ouverte 2.0 ([data.gouv.fr](https://www.data.gouv.fr/datasets/licence-ouverte-2-0), copies identiques dans `public/documents/`) : réutilisation libre, commerciale comprise ; seule condition, mentionner la source (au moins le concédant) et la date de dernière mise à jour ; le concédant garantit que des droits de tiers ne font pas obstacle ; compatible avec CC-BY.
- Code de l'environnement, L124-1 et suivants : une chronique cachée reste une information environnementale communicable sur demande, sauf motif de refus apprécié au cas par cas (L124-4 ; DAJ le jour venu).
- RGPD, [article 13](https://www.cnil.fr/fr/reglement-europeen-protection-donnees/chapitre3#Article13) : à la collecte, le responsable, les finalités, la base juridique, les destinataires, la durée de conservation, les droits et la CNIL ; article 5, ne collecter que le nécessaire.
- INRAE : politique de science ouverte (2021, « aussi ouverte que possible, aussi fermée que nécessaire ») ; note de 2022 sur la valorisation des données, qui privilégie la Licence Ouverte.

D'où le seul modèle cohérent : **ouvert sous Licence Ouverte, ou fermé sous contrat**. Un observatoire n'est pas une personne juridique ; une condition ajoutée à la licence d'une chronique visible serait sans effet et ferait une licence non homologuée.

## Ce qui est fait (version 0.6.0)

- Une page « Conditions d'utilisation » commune aux treize observatoires, à la même adresse (`/terms_of_use`), qui dit les deux régimes, la mention de source attendue et, comme simple recommandation, comment citer (les DOI de l'observatoire) ; `/conditions` reste le PDF des conditions générales qu'on accepte par une case. Les métadonnées de BDOH y sont mises sous la même licence, sauf logos, photographies et coordonnées des personnes (choix fait en écrivant la page, à défaire d'une phrase si Lou le juge prématuré).
- Une migration (`Version20261113120000`, avec retour arrière) : le fichier d'ORACLE n'est plus servi ; la contrainte des jeux devient « Licence Ouverte Etalab 2.0 (etalab-2.0) », et une seconde entrée nomme aussi les conditions générales pour les deux jeux qui ont des chroniques en accès restreint. La fusion d'OTHU rapproche sa contrainte de la première.
- La fenêtre d'export : lien vers le texte de la licence, version 2.0, usage commercial permis, lien vers la page des conditions.
- L'information sur les données personnelles à l'inscription et dans le courriel de bienvenue, sans les articles abrogés ; téléphone et adresse facultatifs.
- Les conditions générales corrigées (article 4, article 1218), le PDF refait ; l'accueil du portail (INRAE, Licence Ouverte 2.0), les fautes anglaises, le libellé de la contrainte en français, les liens des DOI enregistrés avec leur résolveur.

## Ce qui reste (reporté dans `ROADMAP.md`)

- DPO : la base juridique du traitement des comptes, la durée de conservation des comptes inactifs et de l'historique, à ajouter à l'information ; savoir si BDOH figure au registre des traitements d'INRAE.
- DAJ, sans urgence : la clause de juridiction des conditions générales (article 9) et le fondement de leur article 4.
- Lou, sur la VM : retirer le fichier d'ORACLE de `public/uploads/documents/`, encore servi à son adresse.
- Le kit des essais : l'accord des producteurs d'OTHU pour la diffusion libre de leurs chroniques, à garder par écrit ; les 4 chroniques visibles d'EDF signalées aux gestionnaires d'Arc-Isère.
- Au choix, plus tard : la licence dans l'objet `licence` des fiches Theia ; un bloc « licence et citation » dans le `Report.txt` des exports (modernisation « Des exports plus FAIR ») ; le vocabulaire « en accès restreint » partout ; `DocumentationBDOH.pdf`, de 2019, qui dit encore qu'il faut un compte pour télécharger.
