<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

$errors = [
    'Error.notFile(%file%)'                  => "“\u{a0}%file%\u{a0}”: This path is not that of a valid file",
    'Error.zip.cantCreate'                   => 'Unable to create the ZIP archive',
    'Error.file.cantRead(%file%)'            => "Unable to read file “\u{a0}%file%\u{a0}”",
    'Error.file.cantCreate(%file%)'          => "Unable to create file “\u{a0}%file%\u{a0}”",
    'Error.file.chmodFailed(%file%,%mode%)'  => "Unable to assign permissions “\u{a0}%mode%\u{a0}” to file “\u{a0}%file%\u{a0}”",
    'Error.baseLessFile.cantCompile(%file%)' => "Unable to compile the LESS base file: “\u{a0}%file%\u{a0}”",
];

/*************************************************
 * OBSERVATOIRE
 ************************************************/

$observatoire = [
    'Observatoire' => [
        'selector'                     => 'Observatories',
        'notExist(%observatoireSlug%)' => "Observatory “\u{a0}%observatoireSlug%\u{a0}” does not exist!",
        'cssFile.cantCreate(%file%)'   => "Unable to create the CSS file for the observatory: “\u{a0}%file%\u{a0}”",
        'cssFile.cantRemove(%file%)'   => "Unable to remove the CSS file for the observatory: “\u{a0}%file%\u{a0}”",
        'Tous'                         => 'All the observatories',
    ],
];

/*************************************************
 * ABOUT DATES
 ************************************************/

$dates = [
    'Year'  => 'Year',
    'Month' => 'Month',
    'Jan'   => 'Jan',
    'Feb'   => 'Feb',
    'Mar'   => 'Mar',
    'Apr'   => 'Apr',
    'May'   => 'May',
    'Jun'   => 'Jun',
    'Jul'   => 'Jul',
    'Aug'   => 'Aug',
    'Sep'   => 'Sep',
    'Oct'   => 'Oct',
    'Nov'   => 'Nov',
    'Dec'   => 'Dec',
];

/*************************************************
 * VARIOUS MESSAGES
 ************************************************/

$various = [
    'account.create'                          => 'Register',
    'account.edit'                            => 'Modify your account',
    'account.login'                           => 'Sign in',
    'account.logout'                          => 'Sign out',
    'accueil'                                 => 'Home',
    'accueilMessage'                          => "The Hydrology Observatory Database (Base de Données des Observatoires en Hydrologie, BDOH) aims at managing, banking and providing hydrological and biochemical data from long-term observatories managed by or where Irstea (formerly Cemagref) is strongly involved. These observatories consist of experimental sites in which data are collected continuously or during recurring campaigns: rainfall, water height and river flow rates, groundwater levels, suspended matter flow, concentration of various substances, etc. The oldest observatory has been operating since 1962.\n\n" .
        "Those data are used for research by scientists of Irstea and their partners, as well as the public or private operational community for environment (State services, territory authorities, design offices, industry). The data are accessible to everyone as long as the users register on the application and observe the terms of uses.\n\n" .
        'In BDOH, data are naturally organised by Observatories, which are independently managed by the research units in charge.',
    'accueilObservatoires'                    => 'Observatories main page',
    'administration'                          => 'Administration',
    'advancedSearch'                          => 'Advanced search',
    'astuceDate'                              => 'Tip : You can write the date with the number(example : 130495 -> 13/04/1995). If you write 1304 the current year will be automatically add',
    'applyDates'                              => 'Write dates located',
    'atBeginning'                             => 'at the beginning',
    'atEnd'                                   => 'at the end',
    'ofMeasureRanges'                         => 'of data time ranges',
    'askDataAccess'                           => 'Ask for data access',
    'askManager'                              => 'Contact a manager',
    'backToHome'                              => 'Home',
    'begin'                                   => 'Start',
    'beingCanceled'                           => 'Cancelling',
    'betterStartAtExistingTime'               => "Choosing “\u{a0}from a data point\u{a0}” will usually lead to a more accurate interpolation for this kind of time series",
    'cancel'                                  => 'Cancel',
    'captcha'                                 => 'Rewrite the code',
    'captcha.info'                            => 'Please type the following characters',
    'captcha.security'                        => 'Security check',
    'chroniquesBox'                           => 'Time series box',
    'chroniquesBox(%station%)'                => "Time series in station “\u{a0}%station%\u{a0}”",
    'close'                                   => 'Close',
    'dataset'                                 => 'Jeu de données',
    'contactHelp'                             => 'Help',
    'ContinueBox(%station%)'                  => "Continuous and calculated time series in station “\u{a0}%station%\u{a0}”",
    'cumulative'                              => 'Cumulative',
    'cumulRefHour'                            => 'Start time of accumulations (in the selected time zone)',
    'DiscontinueBox(%station%)'               => "Discontinuous time series in station “\u{a0}%station%\u{a0}”",
    'dataAccess'                              => 'See our data',
    'complexViewer'                           => 'Display several time series',
    'TextLinkToCV'                            => 'Display several time series on the same page',
    'date(UTC)'                               => 'UTC Date',
    'date.firstMeasure'                       => 'Date of the first data',
    'date.lastMeasure'                        => 'Date of the last data',
    'date.selectFirst'                        => 'Select the minimum date of all listed time series',
    'date.selectLast'                         => 'Select the maximum date of all listed time series',
    'date.selectFirst_empty'                  => 'Select the minimum date of the time series',
    'date.selectLast_empty'                   => 'Select the minimum date of the time series',
    'displayData'                             => 'Display data',
    'displayOnlyInPeriod'                     => 'Display only time series that have data:',
    'selectStartEndDates'                     => 'Select the start date and end date',
    'doNotSelect'                             => 'Do not select',
    'downloadedRangeInTimezone'               => 'Period in this time zone',
    'editChronique'                           => 'Edit this time series',
    'editObservatoire'                        => 'Edit this observatory',
    'editStation'                             => 'Edit this station',
    'empty'                                   => 'N.A.',
    'envoialljson'                            => 'Send all json by producer',
    'end'                                     => 'End',
    'enSavoirPlus'                            => 'Go to the observatory',
    'errors'                                  => 'Errors',
    'errorsMayComeFromEncoding'               => 'Some errors may occur when the uploaded file contains special characters (e.g. µ).
                                      In such case please make sure this file is UTF-8 encoded.',
    'existing'                                => 'Existing',
    'existingMeasureTime'                     => 'from a data point',
    'export'                                  => 'Download',
    'exporter'                                => 'Download',
    'exportType'                              => 'Download type (variable or regular time step)',
    'exportTypeShort'                         => 'Download type',
    'fillings'                                => 'Fillings',
    'fillingRate'                             => 'Data completeness',
    'from'                                    => 'from',
    'gaps'                                    => 'Gaps',
    'home'                                    => 'Home',
    'home.alt'                                => 'Irstea',
    'home.choice'                             => 'Choice your observatory',
    'home.title'                              => "“\u{a0}Base de Données des Observatoires en Hydrologie\u{a0}”",
    'hour(s)(utc)'                            => 'hour(s) [UTC]',
    'IAgreeWith'                              => 'I agree with',
    'identical'                               => 'Identical',
    'identification'                          => 'Specifications',
    'ignoreColumn'                            => 'Ignore this column',
    'illustration'                            => 'Illustration photograph',
    'import'                                  => 'Upload',
    'import(the)'                             => 'the upload',
    'information'                             => 'Information',
    'instantaneous'                           => 'Instantaneous',
    'loading'                                 => 'Loading',
    'maximum.small'                           => 'Max.',
    'mean'                                    => 'Mean',
    'measures.table'                          => 'Table',
    'measures.viewer'                         => 'Viewer',
    'measuresNumber'                          => 'Data',
    'checkpoints'                             => 'Checkpoints',
    'minimum.small'                           => 'Min.',
    'minutes'                                 => 'minutes',
    'no'                                      => 'No',
    'noDescription'                           => 'No description available',
    'noGenalogy'                              => 'No genealogy available',
    'none'                                    => 'N.A.',
    'noneSmall'                               => '-',
    'noOne'                                   => 'No one',
    'none'                                    => 'None',
    'noboby'                                  => 'N.A.',
    'noneFeminine'                            => 'None',
    'notActive'                               => 'inactive',
    'or'                                      => 'or',
    'overlap'                                 => 'Overlap',
    'parametersStudied'                       => 'Parameters',
    'partners'                                => 'Partners',
    'cgu'                                     => 'Terms of use',
    'period'                                  => 'Period',
    'presentation'                            => 'Presentation',
    'reset'                                   => 'Reset',
    'restart'                                 => 'Restart',
    'roundTime'                               => 'from a round hour',
    'searchPeriod'                            => 'Search period',
    'startInterpAt'                           => 'Start interpolation',
    'submit'                                  => 'Submit',
    'survolez'                                => 'Hover the name of the desired observatory',
    'step'                                    => 'Time step',
    'TermsOfUses'                             => 'Terms of uses',
    'TermsOfUses.none'                        => 'No terms of uses are defined.',
    'TermsOfUses(the)'                        => 'The terms of uses',
    'to'                                      => 'to',
    'toLearnMore'                             => 'To learn more',
    'citeWithDoi'                             => 'To cite these data you may refer to the following DOI:',
    'citeWithDois'                            => 'To cite these data you may refer to the following DOIs:',
    'moreAboutDoi'                            => 'More about this DOI',
    'type'                                    => 'Type',
    'UTC'                                     => 'UTC',
    'UTC(in)'                                 => 'in UTC',
    'validate'                                => 'Validate',
    'validateurJSON'                          => 'Dataset validation by Theia/OZCAR',
    'viewer'                                  => 'Viewer',
    'viewChronique'                           => 'Show this time series',
    'viewStation'                             => 'Show this station',
    'viewObservatoire'                        => 'Show this observatory',
    'welcome'                                 => 'Welcome',
    'yes'                                     => 'Yes',
];

/*************************************************
 * RETURNED MESSAGES
 ************************************************/

return array_merge($errors, $observatoire, $dates, $various);
