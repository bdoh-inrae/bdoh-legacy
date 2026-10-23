<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

use Irstea\BdohAdminBundle\Errors\ImportErrors as Errors;

/*************************************************
 * ERRORS OF MEASURES IMPORT
 ************************************************/

$lineError = "Line %numLine% (date = “\u{a0}%date%\u{a0}”, value = “\u{a0}%valeur%\u{a0}”):";

return [
    Errors::TOO_MANY_ERRORS                    => '<b>Too many errors; File analysis canceled</b>',
    Errors::NO_FILE                            => 'The file could not be read',
    Errors::BAD_MIME_TYPE                      => "File mime type (“\u{a0}%mimeType%\u{a0}”) is not valid",
    Errors::BAD_ZIP                            => 'No File could be extracted from the provided zip file.',
    Errors::BAD_SHAPE                          => 'No file from the provided zip file could be exploited for the following reasons:<br/>%errors%',
    Errors::ZIP_FIRST_BAD_MIME_TYPE            => "File mime type from the zip file (“\u{a0}%mimeType%\u{a0}”) is not valid",
    Errors::BAD_FORMAT                         => "File format “\u{a0}%format%\u{a0}” does not exists",
    Errors::BAD_STATION                        => "Station code “\u{a0}%station%\u{a0}” does not match any station in this observatory",
    Errors::BAD_CHRONIQUE_CONTINUE             => "Time series code “\u{a0}%chronique%\u{a0}” does not match any <b>continuous time series</b> in station “\u{a0}%station%\u{a0}”",
    Errors::BAD_CHRONIQUE_DISCONTINUE          => "Time series code “\u{a0}%chronique%\u{a0}” does not match any <b>discontinuous time series</b> in station “\u{a0}%station%\u{a0}”",
    Errors::BAD_UNIT_CHRONIQUE                 => "Unit “\u{a0}%unite%\u{a0}” does not match that of time series “\u{a0}%chronique%\u{a0}”",
    Errors::CHRONIQUE_ALREADY_EXISTS           => "Time Series “\u{a0}%chronique%\u{a0}” should only be used in one column",
    Errors::NO_CHRONIQUE_CONTINUE_AVAILABLE    => "No <b>continuous time series</b> available on station “\u{a0}%station%\u{a0}”",
    Errors::NO_CHRONIQUE_DISCONTINUE_AVAILABLE => "No <b>discontinuous time series</b> available on station “\u{a0}%station%\u{a0}”",
    Errors::INVALID_TIMEZONE                   => "“\u{a0}%timezone%\u{a0}” is not a valid time zone",
    Errors::BAD_LINE_SIZE                      => 'Line %numLine%: %expected% column(s) expected; %found% column(s) found',
    Errors::BAD_MIN_LINE_SIZE                  => 'Line %numLine%: at least %expected% column(s) expected; %found% column(s) found',
    Errors::BAD_CHAR_NUMBER                    => 'Line %numLine%: %expected% caracter(s) expected; %found% caracter(s) read',
    Errors::BAD_LINE_BEGIN                     => "Line %numLine% must begin with “\u{a0}%expected%\u{a0}”",
    Errors::BAD_HEADER_NO_CHRONIQUE            => 'No time series code found in header',
    Errors::BAD_HEADER_FIRST_TWO_LINES         => "The first 2 header's lines do not match the number of columns",
    Errors::BAD_HEADER_SYNTAX                  => 'The header does not fulfil the expected syntax',
    Errors::BAD_HEADER_UNIT                    => "The unit declared in the header (“\u{a0}%present%\u{a0}”) does not match the expected one (“\u{a0}%expected%\u{a0}”)",
    Errors::DATE_REDUNDANCY                    => "Time Series “\u{a0}%chronique%\u{a0}” contains %count% values for the date “\u{a0}%date%\u{a0}”, at lines [%numLines%], please leave only one.",
    Errors::RANGE_OVERLAP                      => "Time Series “\u{a0}%chronique%\u{a0}” contains overlapping ranges for the date [%plages%], at lines [%numLines%]",
    Errors::NOT_IN_SOURCE_JEU                  => "$lineError “\u{a0}%qualite%\u{a0}” is not a valid quality for this format",
    Errors::FORBIDDEN_VALUE_LIMIT              => "$lineError detection/quantification limit “\u{a0}%qualite%\u{a0}” not allowed in this time series",
    Errors::VALUE_LIMIT_IN_BAREME              => "Line %numLine%: detection/quantification limit “\u{a0}%qualite%\u{a0}” not allowed in scales",
    Errors::QUALITY_NOT_FOR_CHECKPOINT         => "$lineError quality “\u{a0}%qualite%\u{a0}” not allowed for checkpoints",
    Errors::NO_RIGHT_ON_STATION                => "You do not have the required rights to upload data in station “\u{a0}%station%\u{a0}”",
    Errors::NO_TRANS_IN_OBSERVATOIRE_JEU       => "$lineError quality “\u{a0}%qualite%\u{a0}” does not match the observatory's quality set",
    Errors::BAD_CODE_QUALITE                   => "Line %numLine%: quality “\u{a0}%qualite%\u{a0}” does not exist in the observatory's quality set",
    Errors::VALEUR_NOT_NUMERIC                 => "$lineError value must be numeric",
    Errors::VALID_NO_VALUE                     => "Line %numLine% (date “\u{a0}%date%\u{a0}”): value is absent in contradiction with the quality code (“\u{a0}%qualite%\u{a0}”)",
    Errors::MINIMUM_NOT_NUMERIC                => "$lineError minimum (“\u{a0}%minimum%\u{a0}”) must be numeric",
    Errors::MAXIMUM_NOT_NUMERIC                => "$lineError maximum (“\u{a0}%maximum%\u{a0}”) must be numeric",
    Errors::DATE_INVALID_FORMAT                => "$lineError date format is not valid",
    Errors::DATE_AFTER_NOW                     => "$lineError date later than the current upload date",
    Errors::INCOHERENT_GAP                     => "$lineError value and quality (“\u{a0}%qualite%\u{a0}”) do not match",
    Errors::INCOHERENT_DATES                   => "Line %numLine% (value = “\u{a0}%valeur%\u{a0}”): beginning date is greater than end date",
    Errors::BAD_UNIT_INPUT_BAREME              => "Unknown input unit “\u{a0}%unite%\u{a0}”",
    Errors::BAD_UNIT_OUTPUT_BAREME             => "Unknown output unit “\u{a0}%unite%\u{a0}”",
    Errors::VARIABLE_NOT_NUMERIC               => "Line %numLine%: value “\u{a0}%valeur%\u{a0}” from field “\u{a0}%nomValeur%\u{a0}” must be numeric",
    Errors::NOT_ENOUGH_DATA                    => 'File has to contain at last %minDataLines% data lines',
    Errors::NOT_STRICTLY_ASCENDING             => "Line %numLine%: value “\u{a0}%valeur%\u{a0}” is less than or equal to previous value “\u{a0}%valeurPrec%\u{a0}”",
    Errors::INCOHERENT_SHAPE                   => "Shape does not match expected format: “\u{a0}%format%\u{a0}”
    <li><b>Check : </b></li>
        <ul>
            <li>The <b>column names</b> of your file must be with the same spelling that <b>the format : «\u{a0}%format%\u{a0}»</b>;</li>
            <li>Provide a full shapefile containing the geographic data (at least <b>shx</b>, <b>shp</b> and <b>dbf</b> files).;</li>
            <li>The <b>column values</b> are the same that the <b>format : «\u{a0}%format%\u{a0}»</b>;</li>
        </ul>
    ",
    Errors::PHP_UPLOAD_ERROR                   => 'A system error (%errorCode%) prevented the file from being uploaded.<br/><u>Please verify that the uploaded file is less than 2MB.</u>',
    Errors::NO_IMPORT_ON_CONVERTED             => "Upload data is forbidden for converted time series (“\u{a0}%chronique%\u{a0}”).",
    Errors::NO_CHECKPOINT_ON_CONVERTED         => "Upload checkpoint is forbidden for converted time series (“\u{a0}%chronique%\u{a0}”).",
    Errors::UNDEFINED_PARAM_CONVERSION         => 'The interval for the gaps is undefiend.',
    Errors::BAD_PARAM_CONVERSION               => "The interval for the gaps (“\u{a0}%param%\u{a0}”) must be an integer greater than or equal to 1.",
    Errors::BAD_PARENT_TIME_SERIES             => 'The parent time series is undefined or does not match a discontinuous time series of the same station and with the same unit.',
];
