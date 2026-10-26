<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

$errors = [
    'Error.notFile(%file%)'                  => "«\u{a0}%file%\u{a0}» : ce chemin n'est pas celui d'un fichier valide",
    'Error.zip.cantCreate'                   => "Impossible de créer l'archive ZIP",
    'Error.file.cantRead(%file%)'            => "Impossible de lire le fichier «\u{a0}%file%\u{a0}»",
    'Error.file.cantCreate(%file%)'          => "Impossible de créer le fichier «\u{a0}%file%\u{a0}»",
    'Error.file.chmodFailed(%file%,%mode%)'  => "Impossible d'attribuer les permissions «\u{a0}%mode%\u{a0}» au fichier «\u{a0}%file%\u{a0}»",
    'Error.baseLessFile.cantCompile(%file%)' => "Impossible de compiler le fichier LESS de base : «\u{a0}%file%\u{a0}»",
];

/*************************************************
 * OBSERVATOIRE
 ************************************************/

$observatoire = [
    'Observatoire' => [
        'selector'                     => 'Observatoires',
        'notExist(%observatoireSlug%)' => "L'observatoire «\u{a0}%observatoireSlug%\u{a0}» n'existe pas !",
        'cssFile.cantCreate(%file%)'   => "Impossible de créer le fichier CSS de l'observatoire : «\u{a0}%file%\u{a0}»",
        'cssFile.cantRemove(%file%)'   => "Impossible de supprimer le fichier CSS de l'observatoire : «\u{a0}%file%\u{a0}»",
        'Tous'                         => 'Tous les observatoires',
    ],
];

/*************************************************
 * ABOUT DATES
 ************************************************/

$dates = [
    'Year'  => 'Année',
    'Month' => 'Mois',
    'Jan'   => 'Jan',
    'Feb'   => 'Fév',
    'Mar'   => 'Mar',
    'Apr'   => 'Avr',
    'May'   => 'Mai',
    'Jun'   => 'Juin',
    'Jul'   => 'Juil',
    'Aug'   => 'Aoû',
    'Sep'   => 'Sep',
    'Oct'   => 'Oct',
    'Nov'   => 'Nov',
    'Dec'   => 'Déc',
];

/*************************************************
 * VARIOUS MESSAGES
 ************************************************/

$various = [
    'account.create'                          => 'Créer un compte',
    'account.edit'                            => 'Modifier votre compte',
    'account.login'                           => 'Se connecter',
    'account.logout'                          => 'Déconnexion',
    'accueil'                                 => 'Accueil',
    'accueilMessage'                          => "La Base de Données pour les Observatoires en Hydrologie (BDOH) a pour vocation de permettre la gestion, la bancarisation et la mise à disposition des données hydrologiques et biogéochimiques issues des observatoires de long terme gérés par ou dans lesquels est fortement impliqué Irstea (anciennement Cemagref). Il s'agit de sites expérimentaux de terrain sur lesquels sont réalisées en continu ou lors de campagnes récurrentes des mesures de pluviométrie, hauteurs d'eau et débits dans les cours d'eau, niveaux de nappes, flux de matières en suspension, concentrations en diverses substances etc. Le plus ancien observatoire fonctionne depuis 1962.\n\n" .
        "Ces données sont utilisées à des fins scientifiques par les chercheurs d'Irstea et ses partenaires, ainsi que par la communauté opérationnelle de l'environnement publique ou privée (services de l'État, collectivités territoriales, bureaux d'études, industriels). Les données sont accessibles à tous gratuitement moyennant une inscription sur le site et le respect des conditions d'utilisation.\n\n" .
        'Dans BDOH, les données sont naturellement organisées par Observatoires, qui sont administrés indépendamment par les unités de recherche qui en ont la charge.',
    'accueilObservatoires'                    => 'Accueil des Observatoires',
    'administration'                          => 'Administration',
    'advancedSearch'                          => 'Recherche avancée',
    'applyDates'                              => 'Exporter les dates situées',
    'atBeginning'                             => 'en début',
    'atEnd'                                   => 'en fin',
    'ofMeasureRanges'                         => 'des intervalles de mesures',
    'askDataAccess'                           => 'Demander un accès aux données',
    'askManager'                              => 'Contacter un gestionnaire',
    'backToHome'                              => "Retour à l'accueil",
    'begin'                                   => 'Début',
    'beingCanceled'                           => 'Annulation en cours',
    'betterStartAtExistingTime'               => "Choisir «\u{a0}sur une mesure\u{a0}» aboutira habituellement à une interpolation plus précise pour ce type de chroniques",
    'cancel'                                  => 'Annuler',
    'chroniquesBox'                           => 'Boîte à chroniques',
    'chroniquesBox(%station%)'                => "Chroniques de la station «\u{a0}%station%\u{a0}»",
    'close'                                   => 'Fermer',
    'contactHelp'                             => 'Aide',
    'ContinueBox(%station%)'                  => "Chroniques continues et calculées de la station «\u{a0}%station%\u{a0}»",
    'cumulative'                              => 'Cumul',
    'cumulRefHour'                            => 'Heure de départ des cumuls (dans le fuseau sélectionné)',
    'DiscontinueBox(%station%)'               => "Chroniques discontinues de la station «\u{a0}%station%\u{a0}»",
    'dataAccess'                              => 'Consultation des données',
    'complexViewer'                           => 'Afficher plusieurs chroniques',
    'TextLinkToCV'                            => 'Afficher plusieurs chroniques sur la même page',
    'date(UTC)'                               => 'Date en UTC',
    'date.firstMeasure'                       => 'Date de première mesure',
    'date.lastMeasure'                        => 'Date de dernière mesure',
    'date.selectFirst'                        => "Sélectionne la date minimale de l'ensemble des chroniques listées",
    'date.selectLast'                         => "Sélectionne la date maximale de l'ensemble des chroniques listées",
    'date.selectFirst_empty'                  => 'Sélectionne la date minimale de la chronique',
    'date.selectLast_empty'                   => 'Sélectionne la date maximale de la chronique',
    'displayData'                             => 'Afficher les données',
    'displayOnlyInPeriod'                     => "N'afficher que les chroniques ayant des mesures :",
    'astuceDate'                              => "Astuce : La date peut s'écrire en tapant seulement les chiffres (exemple : 130495 -> 13/04/1995). Si vous écrivez 1304 l'année en cours sera ajoutée automatiquement.",
    'selectStartEndDates'                     => 'Sélectionner les dates de début et fin',
    'doNotSelect'                             => 'Ne pas sélectionner',
    'downloadedRangeInTimezone'               => 'Période dans ce fuseau :',
    'editChronique'                           => 'Éditer la chronique',
    'editObservatoire'                        => "Éditer l'observatoire",
    'editStation'                             => 'Éditer la station',
    'editDataset'                             => 'Éditer le jeu de données',
    'empty'                                   => 'N.A.',
    'end'                                     => 'Fin',
    'enSavoirPlus'                            => "Accéder à l'observatoire",
    'envoialljson'                            => 'Envoyer toutes les données pour Theia/OZCAR',
    'errors'                                  => 'Erreurs',
    'errorsMayComeFromEncoding'               => 'Certaines erreurs peuvent se produire lorsque le fichier importé contient des caractères spéciaux (p. ex. µ).
                                      Dans ce cas veuillez vous assurer que ce fichier est encodé en UTF-8.',
    'existing'                                => 'Existant',
    'existingMeasureTime'                     => 'sur une mesure',
    'export'                                  => 'Exporter',
    'exporter'                                => 'Exporter',
    'exportType'                              => "Type d'export (pas de temps variable ou fixe)",
    'exportTypeShort'                         => "Type d'export",
    'fillings'                                => 'Remplissages',
    'fillingRate'                             => 'Taux de remplissage',
    'from'                                    => 'de',
    'gaps'                                    => 'Lacunes',
    'home'                                    => 'Accueil',
    'home.alt'                                => 'Irstea',
    'home.choice'                             => 'Choisissez votre observatoire',
    'home.title'                              => "«\u{a0}Base de Données des Observatoires en Hydrologie\u{a0}»",
    'hour(s)(utc)'                            => 'heure(s) [UTC]',
    'IAgreeWith'                              => "J'accepte",
    'identical'                               => "À l'identique",
    'identification'                          => 'Caractéristiques',
    'ignoreColumn'                            => 'Ignorer cette colonne',
    'illustration'                            => "Photographie d'illustration",
    'import'                                  => 'Import',
    'import(the)'                             => "l'import",
    'information'                             => 'Informations',
    'instantaneous'                           => 'Instantané',
    'loading'                                 => 'Chargement',
    'maximum.small'                           => 'Max.',
    'mean'                                    => 'Moyenne',
    'measures.table'                          => 'Tableau',
    'measures.viewer'                         => 'La visualisation',
    'measuresNumber'                          => 'Nombre de mesures',
    'checkpoints'                             => 'Points de contrôle',
    'minimum.small'                           => 'Min.',
    'minutes'                                 => 'minutes',
    'no'                                      => 'Non',
    'noDescription'                           => 'Pas de description disponible',
    'noGenalogy'                              => 'Pas de généalogie disponible',
    'none'                                    => 'N.A.',
    'noneSmall'                               => '-',
    'noOne'                                   => 'Aucun',
    'none'                                    => 'Aucun',
    'noneFeminine'                            => 'Aucune',
    'notActive'                               => 'inactive',
    'or'                                      => 'ou',
    'overlap'                                 => 'Chevauchement',
    'parametersStudied'                       => 'Paramètres étudiés',
    'partners'                                => 'Partenaires',
    'cgu'                                     => "Conditions d'utilisation",
    'period'                                  => 'Période',
    'presentation'                            => 'Présentation',
    'reset'                                   => 'Effacer',
    'restart'                                 => 'Recommencer',
    'roundTime'                               => 'à une heure ronde',
    'searchPeriod'                            => 'Période de recherche',
    'startInterpAt'                           => "Débuter l'interpolation",
    'submit'                                  => 'Envoyer',
    'survolez'                                => "Survolez le nom de l'observatoire désiré",
    'step'                                    => 'Pas de temps',
    'TermsOfUses'                             => "Conditions d'utilisation",
    'TermsOfUses.none'                        => "Aucune condition d'utilisation n'est définie.",
    'TermsOfUses(the)'                        => "les conditions d'utilisation",
    'to'                                      => 'à',
    'toLearnMore'                             => 'Pour en savoir plus',
    'citeWithDoi'                             => 'Pour citer ces données vous pouvez vous référer au DOI suivant :',
    'citeWithDois'                            => 'Pour citer ces données vous pouvez vous référer aux DOI suivants :',
    'moreAboutDoi'                            => "Plus d'informations",
    'type'                                    => 'Type',
    'UTC'                                     => 'UTC',
    'UTC(in)'                                 => 'en UTC',
    'validate'                                => 'Valider',
    'validateurJSON'                          => 'Validation jeu de données pour Theia/OZCAR',
    'viewer'                                  => 'La visualisation',
    'viewChronique'                           => 'Voir la fiche de la chronique',
    'viewStation'                             => 'Voir la fiche de la station',
    'viewDataset'                             => 'Voir la fiche du jeu de données',
    'viewObservatoire'                        => 'Voir cet observatoire',
    'welcome'                                 => 'Bienvenue',
    'yes'                                     => 'Oui',
];

/*************************************************
 * RETURNED MESSAGES
 ************************************************/

return array_merge($errors, $observatoire, $dates, $various);
