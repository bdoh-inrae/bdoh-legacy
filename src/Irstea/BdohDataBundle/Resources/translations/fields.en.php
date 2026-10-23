<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

return [
    '_action'                          => 'Action',
    /* Common fields */
    'code'                             => 'Code',
    'chronique(s)'                     => 'Time series',
    'description'                      => 'Description',
    'descriptionEn'                    => 'Description (english)',
    'file'                             => 'File',
    'fileLogo'                         => 'Logo',
    'format'                           => 'Format',
    'id'                               => 'Id.',
    'libelle'                          => 'Label',
    'libelleEn'                        => 'Label (english)',
    'lien'                             => 'Web site',
    'nom'                              => 'Name',
    'nomEn'                            => 'Name (english)',
    'timezone'                         => 'Time zone',
    'stationExutoire'                  => 'Outlet station',
    'noboby'                           => 'N.A.',
    'utilisateur'                      => 'BDOH User',

    /* Baremes */
    'uniteEntree'                      => 'Input unit',
    'uniteSortie'                      => 'Output unit',

    /* Bassins */
    'surface'                          => 'Area (m²)',

    /* CoursEau */
    'observatoires'                    => 'Observatories',
    'codeHydro'                        => 'Hydro code',
    'classifica'                       => 'Strahler number',

    /* Theia categories*/
    'milieu'                           => 'Environment',
    'familleParametre'                 => 'Parameter categories',
    'uriOzcarTheia'                    => 'Theia/Ozcar thesaurus',

    /* Dataset */
    'dataSet'                          => 'Dataset',
    'dataset'                          => 'Dataset',
    'dataSets'                         => 'Datasets',
    'chroniques'                       => 'Chronicles',

    /* Observatoire */
    'fileConditionsUtilisation'                  => 'Terms of use',
    'partenaires'                                => 'Partners',
    'partenaire'                                 => 'Partner',
    'jeu'                                        => 'Quality codes',
    'financeur'                                  => 'Fundings',
    'couleurPrimaire'                            => 'Primary color',
    'couleurSecondaire'                          => 'Secondary color',
    'filePhotoPrincipale'                        => 'Picture n°1',
    'filePhotoSecondaire'                        => 'Picture n°2',
    'theiaCode'                                  => 'Theia/OZCAR Identifier',
    'theiaPassword'                              => 'Theia/OZCAR Password',

    /* Partenaire */
    'typeFundings'                      => 'Type of Fundings/Organisations',
    'scanR'                             => 'ScanR Identifier (if Country = FRANCE)',
    'iso'                               => 'Country',
    'estFinanceur'                      => 'He is a funding ?',
    'roleOrgansation'                   => 'Organisation role',
    'Pays'                              => 'Country',

    /* DOI */
    'identifiant'                      => 'Identifier',

    /* SiteExperimental */
    'observatoire'                     => 'Observatory',

    /* DataSet */
    'titre'                               => 'Title',
    'titreEn'                             => 'Title (english)',
    'dataConstraint'                      => 'Data Constraint',
    'topicCategories'                     => 'INSPIRE Topic Categories',
    'inspireTheme'                        => 'INSPIRE Theme',
    'portailSearchCriteriaGeology'        => 'Criteria Geology',
    'portailSearchCriteriaClimat'         => 'Criteria Climat',
    'dataManagers'                        => 'Data Manager',
    'principalInvestigators'              => 'Principal Investigator',
    'dataCollectors'                      => 'Data Collector',
    'projectMembers'                      => 'Project Member',
    'doi'                                 => 'DOI',
    'keywords'                            => 'KeyWords',
    'contact'                             => 'Contact',
    'info'                                => 'Major Information',

    /* Station */
    'codeAlternatif'                     => 'Alternative code',
    'altitude'                           => 'Altitude (m)',
    'commune'                            => 'Town',
    'sites'                              => 'Experimental sites',
    'commentaire'                        => 'Comment',
    'commentaireEn'                      => 'Comment (english)',
    'bassin'                             => 'Basin',
    'sousBassin'                         => 'Sub-basin',
    'coursEau'                           => 'River',
    'onBassin'                           => 'Basin',
    'onCoursEau'                         => 'River',
    'estActive'                          => 'Active?',
    'station.estActive'                  => 'Active station',

    /* Chronique */
    'chronique.code'                   => 'Time series code',
    'estVisible'                       => 'Is visible?',
    'minimumValide'                    => 'Valid minimum',
    'maximumValide'                    => 'Valid maximum',
    'parametre'                        => 'Parameter',
    'station'                          => 'Station',
    'stations'                         => 'Stations',
    'genealogie'                       => 'Genealogy',
    'genealogieEn'                     => 'Genealogy (english)',
    'unite'                            => 'Unit',
    'producteur'                       => 'Producer (Partners)',
    'measures'                         => 'Data',
    'measuresShort'                    => 'Data',
    'allowValueLimits'                 => 'This time series may contain limits of quantification / detection (lq/ld)',
    'valueLimitTransformationType'     => 'Transformation of quantification / detection limits',
    'valueLimitPlaceholder'            => 'Placeholder value',
    'chronique.lq_ld'                  => [
        'forced_allow_already_present' => 'This field cannot be edited because this time series already contains lq/ld.',
        'legacy_parent_time_series'    => 'This field cannot be edited on converted time series, it is derectly inherited from the parent time series during the conversion.',
        'legacy_and_already_present'   => 'This field cannot be edited on converted time series, it is derectly inherited from the parent time series during the conversion. Besides, this time series already contains lq/ld.',
        'options'                      => [
            'true_value'               => 'Use the original values',
            'half_value'               => 'Divide the values by 2',
            'placeholder'              => 'Use a placeholder value',
            'gap'                      => 'Process as invalid data',
        ],
    ],

    /* ChroniqueContinue */
    'echantillonnage'                  => 'Sampling',
    'exportsLicites'                   => 'Allowed download types',
    'directionMesure'                  => 'Time ranges covered by data',
    'period.ahead'                     => 'ahead',
    'period.backward'                  => 'past',
    'echantillonnagesSortieLicites'    => 'Allowed download types at regular time step',
    'optionsEchantillonnageSortie'     => 'Allowed time steps for download of mean and cumulative values',
    'uniteCumul'                       => 'Unit for acccumulations',

    /* ChroniqueCalculee */
    'CalculChroniques'                 => 'Time series calculation',

    /* ChroniqueConvertie */
    'ConversionChroniques'             => 'Time series conversion',

    /* Commune */
    'departement'                      => 'County',
    'codePostal'                       => 'Zip code',
    'codeInsee'                        => 'INSEE code',
    'latitude'                         => 'Geo. coord. (X)',
    'longitude'                        => 'Geo. coord. (Y)',

    /* FamilleParametres */
    'prefix'                           => 'Prefix',
    'familleParente'                   => 'Parent category',

    /* TypeParametre */
    'familleParametres'                => 'Parameter category',
    'unites'                           => 'Valid units',

    /* JeuQualite */
    'estAffectable'                    => 'Assignable to an observatory',
    'qualites'                         => 'Qualities',

    /* Mesure and Plage */
    'date'                             => 'Date',
    'debut'                            => 'Start',
    'fin'                              => 'End',
    'valeur'                           => 'Value',
    'qualite'                          => 'Quality',
    'minimum'                          => 'Minimum',
    'maximum'                          => 'Maximum',

    /* Transformation */
    'coefficientMultiplicateur'        => 'Multiplying coefficient',
    'miseAJour'                        => 'Update',
    'dateCreation'                     => 'Creation date',

    /* Ponctuation */
    'deux_points'                      => ':',
    'point_virgule'                    => ';',
    'point_interogation'               => '?',
    'point_exclamation'                => '!',
    'open_quote'                       => "“\u{a0}",
    'close_quote'                      => "\u{a0}”",

    /* Date Formats */
    'formatDate'                       => 'd-m-Y',
    'formatDateTime'                   => 'd-m-Y H:i:s',
    'formatDateTimeJS'                 => 'DD-MM-YYYY HH:mm:ss',
    'dateSeparator'                    => '-',
    'dateDayShort'                     => 'd',
    'oneHour'                          => '1 hour',
    'oneDay'                           => '1 day',
    'nSeconds(%n%)'                    => '%n% seconds',
    'jour'                             => 'day|days',
    'heure'                            => 'hour|hours',
    'minute'                           => 'minute|minutes',
    'seconde'                          => 'second|seconds',
];
