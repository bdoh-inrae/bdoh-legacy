<?php
// SPDX-FileCopyrightText: The BDOH authors
// SPDX-License-Identifier: AGPL-3.0-or-later

return [
    '_action'                          => 'Action',
    /* Common fields */
    'code'                             => 'Code',
    'chronique(s)'                     => 'Time series',
    'description'                      => 'Description',
    'descriptionEn'                    => 'Description (English)',
    'file'                             => 'File',
    'fileLogo'                         => 'Logo',
    'format'                           => 'Format',
    'id'                               => 'Id.',
    'libelle'                          => 'Label',
    'libelleEn'                        => 'Label (English)',
    'lien'                             => 'Website',
    'nom'                              => 'Name',
    'nomEn'                            => 'Name (English)',
    'timezone'                         => 'Time zone',
    'stationExutoire'                  => 'Outlet station',
    'noboby'                           => 'N.A.',
    'personneTheia'                    => 'Theia/OZCAR person',
    'rolePersonneTheia'                => 'Role of the Theia/OZCAR person',
    'utilisateur'                      => 'BDOH User',
    'chronique'                        => 'Time series',

    /* Baremes */
    'uniteEntree'                      => 'Input unit',
    'uniteSortie'                      => 'Output unit',

    /* Bassins */
    'surface'                          => 'Area (m²)',

    /* CoursEau */
    'observatoires'                    => 'Observatories',
    'isOrphan'                         => 'Orphan',
    'projectLeader'                    => 'Project Leader',
    'doiPrincipal'                     => 'Main DOI',
    'codeHydro'                        => 'Hydro code',
    'classifica'                       => 'Strahler number',

    /* Theia categories*/
    'milieu'                           => 'Environment',
    'familleParametre'                 => 'Parameter category',
    'uriOzcarTheia'                    => 'Theia/OZCAR thesaurus variable',

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
    'filePhotoPrincipale'                        => 'Photo 1',
    'filePhotoSecondaire'                        => 'Photo 2',
    'theiaCode'                                  => 'Theia/OZCAR Identifier',
    'theiaPassword'                              => 'Theia/OZCAR Password',

    /* Partenaire */
    'typeFundings'                      => 'Type of funder or organisation',
    'scanR'                             => 'ScanR Identifier (if Country = FRANCE)',
    'iso'                               => 'Country',
    'estFinanceur'                      => 'Is it a funder?',
    'roleOrgansation'                   => 'Organisation role',
    'Pays'                              => 'Country',

    /* DOI */
    'identifiant'                      => 'Identifier',

    /* SiteExperimental */
    'observatoire'                     => 'Observatory',

    /* DataSet */
    'titre'                               => 'Title',
    'titreEn'                             => 'Title (English)',
    'dataConstraint'                      => 'Access and use conditions',
    'topicCategories'                     => 'INSPIRE Topic Categories',
    'inspireTheme'                        => 'INSPIRE Theme',
    'portailSearchCriteriaGeology'        => 'Geological criteria',
    'portailSearchCriteriaClimat'         => 'Climatic criteria',
    'dataManagers'                        => 'Data Manager',
    'principalInvestigators'              => 'Principal Investigator',
    'dataCollectors'                      => 'Data Collector',
    'projectMembers'                      => 'Project Member',
    'doi'                                 => 'DOI',
    'keywords'                            => 'Keywords',
    'contact'                             => 'Contact',
    'info'                                => 'Main information',

    /* Station */
    'codeAlternatif'                     => 'Alternative code',
    'altitude'                           => 'Altitude (m)',
    'commune'                            => 'Town',
    'sites'                              => 'Experimental sites',
    'commentaire'                        => 'Comment',
    'commentaireEn'                      => 'Comment (English)',
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
    'dateDebutMesures'                 => 'Start of data',
    'dateFinMesures'                   => 'End of data',
    'parametre'                        => 'Parameter',
    'parametre.familleParametres'      => 'Parameter category',
    'station'                          => 'Station',
    'stations'                         => 'Stations',
    'station.sites'                    => 'Site',
    'genealogie'                       => 'Genealogy',
    'genealogieEn'                     => 'Genealogy (English)',
    'unite'                            => 'Unit',
    'producteur'                       => 'Producer (partner)',
    'measures'                         => 'Data',
    'measuresShort'                    => 'Data',
    'allowValueLimits'                 => 'This time series may contain limits of quantification / detection (lq/ld)',
    'valueLimitTransformationType'     => 'Transformation of quantification / detection limits',
    'valueLimitPlaceholder'            => 'Placeholder value',
    'chronique.lq_ld'                  => [
        'forced_allow_already_present' => 'This field cannot be edited because this time series already contains lq/ld.',
        'legacy_parent_time_series'    => 'This field cannot be edited on converted time series, it is directly inherited from the parent time series during the conversion.',
        'legacy_and_already_present'   => 'This field cannot be edited on converted time series, it is directly inherited from the parent time series during the conversion. Besides, this time series already contains lq/ld.',
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
    'uniteCumul'                       => 'Unit for accumulations',

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
    'oneMonth'                         => '1 month',
    'dateMonthShort'                   => 'month',
    'nSeconds(%n%)'                    => '%n% seconds',
    'jour'                             => 'day|days',
    'heure'                            => 'hour|hours',
    'minute'                           => 'minute|minutes',
    'seconde'                          => 'second|seconds',
];
