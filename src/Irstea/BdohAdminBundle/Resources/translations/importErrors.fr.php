<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

use Irstea\BdohAdminBundle\Errors\ImportErrors as Errors;

/*************************************************
 * ERRORS OF MEASURES IMPORT
 ************************************************/

$lineError = "Ligne %numLine% (date = «\u{a0}%date%\u{a0}», valeur = «\u{a0}%valeur%\u{a0}») :";

return [
    Errors::TOO_MANY_ERRORS                    => "<b>Trop d'erreurs ; arrêt de lecture du fichier</b>",
    Errors::NO_FILE                            => "Le fichier n'a pu être chargé",
    Errors::BAD_MIME_TYPE                      => "Le type MIME du fichier («\u{a0}%mimeType%\u{a0}») est invalide",
    Errors::BAD_ZIP                            => "Aucun fichier n'a pu être extrait de l'archive zip envoyée",
    Errors::BAD_SHAPE                          => "Aucun fichier de l'archive zip envoyée n'a pu être exploité pour les raisons suivantes :<br/>%errors%",
    Errors::ZIP_FIRST_BAD_MIME_TYPE            => "Le type MIME du fichier de l'archive zip («\u{a0}%mimeType%\u{a0}») est invalide",
    Errors::BAD_FORMAT                         => "Le format de fichier «\u{a0}%format%\u{a0}» n'existe pas",
    Errors::BAD_STATION                        => "Le code «\u{a0}%station%\u{a0}» ne correspond à aucune station de cet observatoire",
    Errors::BAD_CHRONIQUE_CONTINUE             => "Le code «\u{a0}%chronique%\u{a0}» ne correspond à aucune <b>chronique continue</b> de la station «\u{a0}%station%\u{a0}»",
    Errors::BAD_CHRONIQUE_DISCONTINUE          => "Le code «\u{a0}%chronique%\u{a0}» ne correspond à aucune <b>chronique discontinue</b> de la station «\u{a0}%station%\u{a0}»",
    Errors::BAD_UNIT_CHRONIQUE                 => "L'unité «\u{a0}%unite%\u{a0}» ne correspond pas à la chronique «\u{a0}%chronique%\u{a0}»",
    Errors::CHRONIQUE_ALREADY_EXISTS           => "La chronique «\u{a0}%chronique%\u{a0}» ne doit être utilisée que pour une seule colonne",
    Errors::NO_CHRONIQUE_CONTINUE_AVAILABLE    => "Aucune <b>chronique continue</b> disponible pour la station «\u{a0}%station%\u{a0}»",
    Errors::NO_CHRONIQUE_DISCONTINUE_AVAILABLE => "Aucune <b>chronique discontinue</b> disponible pour la station «\u{a0}%station%\u{a0}»",
    Errors::INVALID_TIMEZONE                   => "«\u{a0}%timezone%\u{a0}» n'est pas un fuseau horaire valide",
    Errors::BAD_LINE_SIZE                      => 'Ligne %numLine% : %expected% colonne(s) attendue(s) ; %found% colonne(s) trouvée(s)',
    Errors::BAD_MIN_LINE_SIZE                  => 'Ligne %numLine% : au moins %expected% colonne(s) attendue(s) ; %found% colonne(s) trouvée(s)',
    Errors::BAD_CHAR_NUMBER                    => 'Ligne %numLine% : %expected% caractère(s) attendu(s) ; %found% caractère(s) lu(s)',
    Errors::BAD_LINE_BEGIN                     => "La ligne %numLine% doit commencée par «\u{a0}%expected%\u{a0}»",
    Errors::BAD_HEADER_NO_CHRONIQUE            => "L'entête ne contient aucun code chronique",
    Errors::BAD_HEADER_FIRST_TWO_LINES         => "Les 2 premières lignes d'entête n'ont pas le même nombre de colonnes",
    Errors::BAD_HEADER_SYNTAX                  => "L'entête du fichier ne respecte pas la syntaxe attendue",
    Errors::BAD_HEADER_UNIT                    => "L'unité présente dans l'entête du fichier («\u{a0}%present%\u{a0}») ne correspond pas à celle attendue («\u{a0}%expected%\u{a0}»)",
    Errors::DATE_REDUNDANCY                    => "La chronique «\u{a0}%chronique%\u{a0}» a %count% valeurs pour la date «\u{a0}%date%\u{a0}», aux lignes [%numLines%], veuillez n'en laisser qu'une.",
    Errors::RANGE_OVERLAP                      => "La chronique «\u{a0}%chronique%\u{a0}» a un chevauchement de plages aux dates [%plages%], aux lignes [%numLines%]",
    Errors::NOT_IN_SOURCE_JEU                  => "$lineError «\u{a0}%qualite%\u{a0}» n'est pas une qualité valide pour ce format",
    Errors::FORBIDDEN_VALUE_LIMIT              => "$lineError limite de détection/quantification «\u{a0}%qualite%\u{a0}» interdite dans cette chronique",
    Errors::VALUE_LIMIT_IN_BAREME              => "Ligne %numLine% : limite de détection/quantification «\u{a0}%qualite%\u{a0}» interdite dans les barèmes",
    Errors::QUALITY_NOT_FOR_CHECKPOINT         => "$lineError qualité «\u{a0}%qualite%\u{a0}» non autorisée pour les points de contrôle",
    Errors::NO_RIGHT_ON_STATION                => "Vous n'avez pas les droits nécessaires pour importer des données pour la station «\u{a0}%station%\u{a0}»",
    Errors::NO_TRANS_IN_OBSERVATOIRE_JEU       => "$lineError la qualité «\u{a0}%qualite%\u{a0}» n'a pas de correspondance dans le jeu de qualités de l'observatoire",
    Errors::BAD_CODE_QUALITE                   => "Ligne %numLine% : la qualité «\u{a0}%qualite%\u{a0}» n'existe pas dans le jeu de qualités de l'observatoire",
    Errors::VALEUR_NOT_NUMERIC                 => "$lineError la valeur n'est pas de type numérique",
    Errors::VALID_NO_VALUE                     => "Ligne %numLine% (date «\u{a0}%date%\u{a0}») : le code qualité «\u{a0}%qualite%\u{a0}» exige la présence d'une valeur",
    Errors::MINIMUM_NOT_NUMERIC                => "$lineError le minimum («\u{a0}%minimum%\u{a0}») n'est pas de type numérique",
    Errors::MAXIMUM_NOT_NUMERIC                => "$lineError le maximum («\u{a0}%maximum%\u{a0}») n'est pas de type numérique",
    Errors::DATE_INVALID_FORMAT                => "$lineError la date n'a pas un format valide",
    Errors::DATE_AFTER_NOW                     => "$lineError date postérieure à la date de l'import en cours",
    Errors::INCOHERENT_GAP                     => "$lineError valeur et qualité («\u{a0}%qualite%\u{a0}») contradictoires",
    Errors::INCOHERENT_DATES                   => "Ligne %numLine% (valeur = «\u{a0}%valeur%\u{a0}») : la date de début est postérieure à la date de fin",
    Errors::BAD_UNIT_INPUT_BAREME              => "Unité d'entrée «\u{a0}%unite%\u{a0}» non reconnue",
    Errors::BAD_UNIT_OUTPUT_BAREME             => "Unité de sortie «\u{a0}%unite%\u{a0}» non reconnue",
    Errors::VARIABLE_NOT_NUMERIC               => "Ligne %numLine% : la valeur «\u{a0}%valeur%\u{a0}» du champ «\u{a0}%nomValeur%\u{a0}» n'est pas de type numérique",
    Errors::NOT_ENOUGH_DATA                    => 'Le fichier doit contenir au moins %minDataLines% lignes de données',
    Errors::NOT_STRICTLY_ASCENDING             => "Ligne %numLine% : la valeur «\u{a0}%valeur%\u{a0}» est inférieure ou égale à la valeur précédente «\u{a0}%valeurPrec%\u{a0}»",
    Errors::INCOHERENT_SHAPE                   => "Le shape ne correspond pas au format attendu : «\u{a0}%format%\u{a0}»
    <li><b>Vérifiez que : </b></li>
        <ul>
            <li>Le <b>nom des colonnes</b> de votre fichier correspond à la même orthographe que <b>le format : «\u{a0}%format%\u{a0}»</b>;</li>
            <li>Le zip contient au moins les fichiers <b>shx</b>, <b>shp</b> et <b>dbf</b>;</li>
            <li>Les valeurs dans les colonnes correspondent au format : «\u{a0}%format%\u{a0}»;</li>
        </ul>",
    Errors::PHP_UPLOAD_ERROR                   => "Une erreur système (%errorCode%) a empeché l'envoi du fichier.<br/><u>Veuillez vérifier que le fichier envoyé fait moins de 2Mo.</u>",
    Errors::NO_IMPORT_ON_CONVERTED             => "L'import de mesures est interdit sur une chronique convertie («\u{a0}%chronique%\u{a0}»).",
    Errors::NO_CHECKPOINT_ON_CONVERTED         => "L'import de points de contrôle est interdit sur une chronique convertie («\u{a0}%chronique%\u{a0}»).",
    Errors::UNDEFINED_PARAM_CONVERSION         => "L'intervalle pour les lacunes n'est pas renseigné.",
    Errors::BAD_PARAM_CONVERSION               => "L'intervalle pour les lacunes («\u{a0}%param%\u{a0}») doit être un entier supérieur ou égale à 1.",
    Errors::BAD_PARENT_TIME_SERIES             => "La chronique mère n'est pas définie ou ne correspond pas à une chronique discontinue de la même station et ayant la même unité.",
];
