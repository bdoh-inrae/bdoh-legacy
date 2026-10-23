<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

return [
    '_action'                          => 'Action',
    /* Common fields */
    'code'                             => 'Code',
    'chronique(s)'                     => 'Chronique|Chroniques',
    'description'                      => 'Description',
    'descriptionEn'                    => 'Description (anglais)',
    'file'                             => 'Fichier',
    'fileLogo'                         => 'Logo',
    'format'                           => 'Format',
    'id'                               => 'Id.',
    'libelle'                          => 'Libellé',
    'libelleEn'                        => 'Libellé (anglais)',
    'lien'                             => 'Adresse web',
    'nom'                              => 'Nom',
    'nomEn'                            => 'Nom (anglais)',
    'timezone'                         => 'Fuseau horaire',
    'stationExutoire'                  => 'Station exutoire',
    'personneTheia'                    => 'Personne Theia',
    'rolePersonneTheia'                => 'Rôle de la Personne Theia',
    'utilisateur'                      => 'Utilisateur BDOH',
    'chronique'                        => 'Chronique',

    /* Partenaire */
    'typeFundings'                      => 'Type de financeur/organisation',
    'scanR'                             => 'Identifiant ScanR (si Pays = FRANCE)',
    'iso'                               => 'Pays',
    'estFinanceur'                      => 'Est-il un financeur ?',
    'roleOrgansation'                   => "Rôle de l'organisme",

    /* DataSet */
    'titre'                               => 'Titre',
    'titreEn'                             => 'Titre (anglais)',
    'dataConstraint'                      => 'Data Constraint',
    'topicCategories'                     => 'Catégories thématiques INSPIRE',
    'inspireTheme'                        => 'Thème INSPIRE',
    'portailSearchCriteriaGeology'        => 'Critères Géologiques',
    'portailSearchCriteriaClimat'         => 'Critères Climatiques',
    'dataManagers'                        => 'Data Managers',
    'principalInvestigators'              => 'Principal Investigators',
    'dataCollectors'                      => 'Data Collectors',
    'projectMembers'                      => 'Project Members',
    'doi'                                 => 'DOI',
    'keywords'                            => 'Mots Clés',
    'contact'                             => 'Contacts',
    'info'                                => 'Informations Principales',

    /* Baremes */
    'uniteEntree'                      => "Unité d'entrée",
    'uniteSortie'                      => 'Unité de sortie',
    'isOrphan'                         => 'Orphelin',

    /* Bassins */
    'surface'                          => 'Superficie (m²)',

    /* CoursEau */
    'observatoires'                    => 'Observatoires',
    'codeHydro'                        => 'Code hydro',
    'classifica'                       => 'Nombre de Strahler',

    /* Observatoire */
    'fileConditionsUtilisation'                  => "Conditions d'utilisation",
    'partenaires'                                => 'Partenaires',
    'partenaire'                                 => 'Partenaire',
    'jeu'                                        => 'Codes qualité',
    'couleurPrimaire'                            => 'Couleur primaire',
    'couleurSecondaire'                          => 'Couleur secondaire',
    'filePhotoPrincipale'                        => 'Photo n°1',
    'filePhotoSecondaire'                        => 'Photo n°2',
    'theiaCode'                                  => 'Identifiant Theia/OZCAR',
    'theiaPassword'                              => 'Mot de passe Theia/OZCAR',
    'projectLeader'                              => 'Project Leader',
    'dataSets'                                   => 'Jeux de données',
    'dataSet'                                    => 'Jeu(x) de données',
    'doiPrincipal'                               => 'DOI principal',

    /* Theia categories*/
    'milieu'                           => 'Milieu',
    'familleParametre'                 => 'Famille de paramètre',
    'uriOzcarTheia'                    => 'Variable du thesaurus Theia/Ozcar',

    /* DOI */
    'identifiant'                      => 'Identifiant',

    /* SiteExperimental */
    'observatoire'                     => 'Observatoire',

    /* Station */
    'codeAlternatif'                   => 'Code alternatif',
    'altitude'                         => 'Altitude (m)',
    'commune'                          => 'Commune',
    'sites'                            => 'Sites expérimentaux',
    'commentaire'                      => 'Commentaire',
    'commentaireEn'                    => 'Commentaire (anglais)',
    'bassin'                           => 'Bassin',
    'sousBassin'                       => 'Sous-bassin',
    'coursEau'                         => "Cours d'eau",
    'onBassin'                         => 'Bassin',
    'onCoursEau'                       => "Cours d'eau",
    'estActive'                        => 'En activité ?',
    'station.estActive'                => 'Station en activité',

    /* Chronique */
    'dataset'                                               => 'Jeu de données',
    'chronique.code'                                        => 'Code chronique',
    'estVisible'                                            => 'Est visible ?',
    'minimumValide'                                         => 'Minimum valide',
    'maximumValide'                                         => 'Maximum valide',
    'dateDebutMesures'                                      => 'Début des mesures',
    'dateFinMesures'                                        => 'Fin des mesures',
    'parametre'                                             => 'Paramètre',
    'parametre.familleParametres'                           => 'Famille Paramètre',
    'station'                                               => 'Station',
    'stations'                                              => 'Stations',
    'station.sites'                                         => 'Site',
    'genealogie'                                            => 'Généalogie',
    'genealogieEn'                                          => 'Généalogie (en anglais)',
    'unite'                                                 => 'Unité',
    'producteur'                                            => 'Producteur (Partenaire)',
    'measures'                                              => 'Nb mesures',
    'measuresShort'                                         => 'Mesures',
    'allowValueLimits'                                      => 'Cette chronique peut contenir des des limites de quantification et de détection (lq/ld)',
    'valueLimitTransformationType'                          => 'Transformation des limites de quantification / détection',
    'valueLimitPlaceholder'                                 => 'Valeur de remplacement',
    'chronique.lq_ld'                                       => [
        'forced_allow_already_present' => 'Ce champ ne peut pas être édité car cette chronique contient déjà des lq/ld.',
        'legacy_parent_time_series'    => "Pour les chroniques converties ce champ n'est pas éditable, il est directement hérité de la chronique mère à chaque conversion.",
        'legacy_and_already_present'   => "Pour les chroniques converties ce champ n'est pas éditable, il est directement hérité de la chronique mère à chaque conversion. De plus cette chronique contient déjà des lq/ld.",
        'options'                      => [
            'true_value'               => "Utiliser les valeurs d'origine",
            'half_value'               => 'Diviser les valeurs par 2',
            'placeholder'              => 'Remplacer par une valeur constante',
            'gap'                      => 'Traiter comme des mesures invalides',
        ],
    ],

    /* ChroniqueContinue */
    'echantillonnage'                  => 'Échantillonnage',
    'exportsLicites'                   => 'Exports licites',
    'directionMesure'                  => 'Période couverte par les mesures',
    'period.ahead'                     => 'à venir',
    'period.backward'                  => 'écoulée',
    'echantillonnagesSortieLicites'    => "Types d'export permis à pas de temps fixe",
    'optionsEchantillonnageSortie'     => 'Pas de temps permis pour exports de moyennes et cumuls',
    'uniteCumul'                       => 'Unité pour les cumuls',

    /* ChroniqueCalculee */
    'CalculChroniques'                 => 'Calcul de chroniques',

    /* ChroniqueConvertie */
    'ConversionChroniques'             => 'Conversion de chroniques',

    /* Commune */
    'departement'                      => 'Département',
    'codePostal'                       => 'Code postal',
    'codeInsee'                        => 'Code INSEE',
    'latitude'                         => 'Coord. géo. (X)',
    'longitude'                        => 'Coord. géo. (Y)',

    /* FamilleParametres */
    'prefix'                           => 'Préfixe',
    'familleParente'                   => 'Famille mère',

    /* TypeParametre */
    'familleParametres'                => 'Famille de paramètres',
    'unites'                           => 'Unités valides',

    /* JeuQualite */
    'estAffectable'                    => 'Affectable à un observatoire',
    'qualites'                         => 'Qualités',

    /* Mesure and Plage */
    'date'                             => 'Date',
    'debut'                            => 'Début',
    'fin'                              => 'Fin',
    'valeur'                           => 'Valeur',
    'qualite'                          => 'Qualité',
    'minimum'                          => 'Minimum',
    'maximum'                          => 'Maximum',

    /* Transformation */
    'coefficientMultiplicateur'        => 'Coefficient multiplicateur',
    'miseAJour'                        => 'Mise à jour',
    'dateCreation'                     => 'Date de création',

    /* Ponctuation */
    'deux_points'                      => ' :',
    'point_virgule'                    => ' ;',
    'point_interogation'               => ' ?',
    'point_exclamation'                => ' !',
    'open_quote'                       => "«\u{a0}",
    'close_quote'                      => "\u{a0}»",

    /* Date Formats */
    'formatDate'                       => 'd/m/Y',
    'formatDateTime'                   => 'd/m/Y H:i:s',
    'formatDateTimeJS'                 => 'DD/MM/YYYY HH:mm:ss',
    'dateSeparator'                    => '/',
    'dateDayShort'                     => 'j',
    'oneHour'                          => '1 heure',
    'oneDay'                           => '1 jour',
    'nSeconds(%n%)'                    => '%n% secondes',
    'jour'                             => 'jour|jours',
    'heure'                            => 'heure|heures',
    'minute'                           => 'minute|minutes',
    'seconde'                          => 'seconde|secondes',
];
