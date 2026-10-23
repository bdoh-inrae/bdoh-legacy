<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohAdminBundle\Errors;

/**
 * Manages the errors of measures import process.
 *
 * Note : error translations are in "ImportErrors" catalog
 * (eg: src/Irstea/BdohAdminBundle/Resources/translations/ImportErrors.fr.php)
 */
class ImportErrors extends Errors
{
    const ERRORS_CATALOG = 'importErrors';

    /**
     * Error types.
     */
    const TOO_MANY_ERRORS = 'tooManyErrors';

    const NO_FILE = 'noFile';

    const BAD_MIME_TYPE = 'badMimeType(%mimeType%)';

    const BAD_ZIP = 'badZip';

    const BAD_SHAPE = 'badShape(%errors%)';

    const ZIP_FIRST_BAD_MIME_TYPE = 'zipFirst.badMimeType(%mimeType%)';

    const BAD_FORMAT = 'badFormat(%format%)';

    const BAD_STATION = 'badStation(%station%)';

    const BAD_CHRONIQUE_CONTINUE = 'badChroniqueContinue(%chronique%, %station%)';

    const BAD_CHRONIQUE_DISCONTINUE = 'badChroniqueDiscontinue(%chronique%, %station%)';

    const BAD_UNIT_CHRONIQUE = 'badUnitForThisChronique(%chronique%, %unite%)';

    const CHRONIQUE_ALREADY_EXISTS = 'chroniqueAlreadyExists';

    const NO_CHRONIQUE_CONTINUE_AVAILABLE = 'noChroniqueContinueAvailable(%station%)';

    const NO_CHRONIQUE_DISCONTINUE_AVAILABLE = 'noChroniqueDiscontinueAvailable(%station%)';

    const INVALID_TIMEZONE = 'invalidTimezone(%timezone%)';

    const BAD_LINE_SIZE = 'badLineSize(%numLine%, %found%, %expected%)';

    const BAD_MIN_LINE_SIZE = 'badMinLineSize(%numLine%, %found%, %expected%)';

    const BAD_CHAR_NUMBER = 'badCharNumber(%numLine%, %found%, %expected%)';

    const BAD_LINE_BEGIN = 'badLineBegin(%numLine%, %expected%)';

    const BAD_HEADER_NO_CHRONIQUE = 'badHeaderNoChronique';

    const BAD_HEADER_FIRST_TWO_LINES = 'badHeaderFirstTwoLines';

    const BAD_HEADER_SYNTAX = 'badHeaderSyntax';

    const BAD_HEADER_UNIT = 'badHeaderUnit(%present%, %expected%)';

    const DATE_REDUNDANCY = 'dateRedundancy(%chronique%, %date%, %count%, %numLines%)';

    const RANGE_OVERLAP = 'rangeOverlap(%chronique%, %plages%, %numLines%)';

    const NOT_IN_SOURCE_JEU = 'notInSourceJeu(%numLine%, %date%, %valeur%, %qualite%)';

    const FORBIDDEN_VALUE_LIMIT = 'forbiddenValueLimit(%numLine%, %date%, %valeur%, %qualite%)';

    const VALUE_LIMIT_IN_BAREME = 'valueLimitInBareme(%numLine%, %qualite%)';

    const QUALITY_NOT_FOR_CHECKPOINT = 'qualityNotForCheckpoint(%numLine%, %date%, %valeur%, %qualite%)';

    const NO_RIGHT_ON_STATION = 'noRightOnStation(%station%)';

    const NO_TRANS_IN_OBSERVATOIRE_JEU = 'noTransInObservatoireJeu(%numLine%, %date%, %valeur%, %qualite%)';

    const BAD_CODE_QUALITE = 'badCodeQualite(%numLine%, %qualite%)';

    const VALEUR_NOT_NUMERIC = 'valeurNotNumeric(%numLine%, %date%, %valeur%)';

    const VALID_NO_VALUE = 'validNoValue(%numLine%, %date%, %codeQualite%)';

    const MINIMUM_NOT_NUMERIC = 'minimumNotNumeric(%numLine%, %date%, %valeur%, %minimum%)';

    const MAXIMUM_NOT_NUMERIC = 'maximumNotNumeric(%numLine%, %date%, %valeur%, %maximum%)';

    const DATE_INVALID_FORMAT = 'dateInvalidFormat(%numLine%, %date%, %valeur%)';

    const DATE_AFTER_NOW = 'dateAfterNow(%numLine%, %date%, %valeur%)';

    const INCOHERENT_GAP = 'incoherentGap(%numLine%, %date%, %valeur%, %qualite%)';

    const INCOHERENT_DATES = 'incoherentDates(%numLine%, %valeur%)';

    const BAD_UNIT_INPUT_BAREME = 'badUniteEntreeForBareme(%unite%)';

    const BAD_UNIT_OUTPUT_BAREME = 'badUniteSortieForBareme(%unite%)';

    const VARIABLE_NOT_NUMERIC = 'variableNotNumeric(%nomvaleur%, %valeur%)';

    const NOT_ENOUGH_DATA = 'notEnoughData(%minDataLines%)';

    const NOT_STRICTLY_ASCENDING = 'notStrictlyAscending(%numLine%, %valeur%, %valeurPrec%)';

    const INCOHERENT_SHAPE = 'incoherentShape(%format%)';

    const PHP_UPLOAD_ERROR = 'phpUploadError(%errorCode%)';

    const NO_IMPORT_ON_CONVERTED = 'noImportOnConverted';

    const NO_CHECKPOINT_ON_CONVERTED = 'noCheckpointOnConverted(%chronique%, %station%)';

    const UNDEFINED_PARAM_CONVERSION = 'undefinedParamConversion';

    const BAD_PARAM_CONVERSION = 'badParamConversion(%param%)';

    const BAD_PARENT_TIME_SERIES = 'badParentTimeSeries';

    /**
     * Facilitator allowing to add a "line error" to the $errors attribute.
     * Note : inspired by Errors::addError() function.
     *
     * @param mixed $numLine
     * @param mixed $date
     * @param mixed $valeur
     * @param mixed $type
     */
    public function lineError($numLine, $date, $valeur, $type)
    {
        $params = [
            '%numLine%' => $numLine,
            '%date%'    => $date,
            '%valeur%'  => $valeur,
        ];

        for ($i = 4; $i < func_num_args(); $i += 2) {
            $params['%' . func_get_arg($i) . '%'] = func_get_arg($i + 1);
        }
        $this->errors[$type][] = $params;

        ++$this->count;
    }

    /**
     * Error handlers.
     */
    public function tooManyErrors()
    {
        $this->addError(static::TOO_MANY_ERRORS);
    }

    public function noFile()
    {
        $this->addError(static::NO_FILE);
    }

    /**
     * @param $mimeType
     */
    public function badMimeType($mimeType)
    {
        $this->addError(static::BAD_MIME_TYPE, 'mimeType', $mimeType);
    }

    public function badZip()
    {
        $this->addError(static::BAD_ZIP);
    }

    /**
     * @param $errors
     */
    public function badShape($errors)
    {
        $this->addError(static::BAD_SHAPE, 'errors', $errors);
    }

    /**
     * @param $mimeType
     */
    public function zipFirstBadMimeType($mimeType)
    {
        $this->addError(static::ZIP_FIRST_BAD_MIME_TYPE, 'mimeType', $mimeType);
    }

    /**
     * @param $format
     */
    public function badFormat($format)
    {
        $this->addError(static::BAD_FORMAT, 'format', $format);
    }

    /**
     * @param $station
     */
    public function badStation($station)
    {
        $this->addError(static::BAD_STATION, 'station', $station);
    }

    /**
     * @param $chronique
     * @param $station
     */
    public function badChroniqueContinue($chronique, $station)
    {
        $this->addError(static::BAD_CHRONIQUE_CONTINUE, 'chronique', $chronique, 'station', $station);
    }

    /**
     * @param $chronique
     * @param $station
     */
    public function badChroniqueDiscontinue($chronique, $station)
    {
        $this->addError(static::BAD_CHRONIQUE_DISCONTINUE, 'chronique', $chronique, 'station', $station);
    }

    /**
     * @param $chronique
     * @param $unite
     */
    public function badUniteForThisChronique($chronique, $unite)
    {
        $this->addError(static::BAD_UNIT_CHRONIQUE, 'chronique', $chronique, 'unite', $unite);
    }

    /**
     * @param $chronique
     */
    public function chroniqueAlreadyExists($chronique)
    {
        $this->addError(static::CHRONIQUE_ALREADY_EXISTS, 'chronique', $chronique);
    }

    /**
     * @param $station
     */
    public function noChroniqueContinueAvailable($station)
    {
        $this->addError(static::NO_CHRONIQUE_CONTINUE_AVAILABLE, 'station', $station);
    }

    /**
     * @param $station
     */
    public function noChroniqueDiscontinueAvailable($station)
    {
        $this->addError(static::NO_CHRONIQUE_DISCONTINUE_AVAILABLE, 'station', $station);
    }

    /**
     * @param $timezone
     */
    public function invalidTimezone($timezone)
    {
        $this->addError(static::INVALID_TIMEZONE, 'timezone', $timezone);
    }

    /**
     * @param $numLine
     * @param $found
     * @param $expected
     */
    public function badLineSize($numLine, $found, $expected)
    {
        $this->addError(static::BAD_LINE_SIZE, 'numLine', $numLine, 'found', $found, 'expected', $expected);
    }

    /**
     * @param $numLine
     * @param $found
     * @param $expected
     */
    public function badMinLineSize($numLine, $found, $expected)
    {
        $this->addError(static::BAD_MIN_LINE_SIZE, 'numLine', $numLine, 'found', $found, 'expected', $expected);
    }

    /**
     * @param $numLine
     * @param $found
     * @param $expected
     */
    public function badCharNumber($numLine, $found, $expected)
    {
        $this->addError(static::BAD_CHAR_NUMBER, 'numLine', $numLine, 'found', $found, 'expected', $expected);
    }

    /**
     * @param $numLine
     * @param $expected
     */
    public function badLineBegin($numLine, $expected)
    {
        $this->addError(static::BAD_LINE_BEGIN, 'numLine', $numLine, 'expected', $expected);
    }

    public function badHeaderNoChronique()
    {
        $this->addError(static::BAD_HEADER_NO_CHRONIQUE);
    }

    /**
     * @param $present
     * @param $expected
     */
    public function badHeaderUnit($present, $expected)
    {
        $this->addError(static::BAD_HEADER_UNIT, 'present', $present, 'expected', $expected);
    }

    public function badHeaderFirstTwoLines()
    {
        $this->addError(static::BAD_HEADER_FIRST_TWO_LINES);
    }

    public function badHeaderSyntax()
    {
        $this->addError(static::BAD_HEADER_SYNTAX);
    }

    /**
     * @param $chronique
     * @param $date
     * @param $count
     * @param $numLines
     */
    public function dateRedundancy($chronique, $date, $count, $numLines)
    {
        $this->addError(static::DATE_REDUNDANCY, 'chronique', $chronique, 'date', $date, 'count', $count, 'numLines', $numLines);
    }

    /**
     * @param $chronique
     * @param $plages
     * @param $numLines
     */
    public function rangeOverlap($chronique, $plages, $numLines)
    {
        $this->addError(static::RANGE_OVERLAP, 'chronique', $chronique, 'plages', $plages, 'numLines', $numLines);
    }

    /**
     * @param $numLine
     * @param $date
     * @param $valeur
     * @param $qualite
     */
    public function notInSourceJeu($numLine, $date, $valeur, $qualite)
    {
        $this->lineError($numLine, $date, $valeur, static::NOT_IN_SOURCE_JEU, 'qualite', $qualite);
    }

    /**
     * @param $numLine
     * @param $date
     * @param $valeur
     * @param $qualite
     */
    public function forbiddenValueLimit($numLine, $date, $valeur, $qualite)
    {
        $this->lineError($numLine, $date, $valeur, static::FORBIDDEN_VALUE_LIMIT, 'qualite', $qualite);
    }

    /**
     * @param $numLine
     * @param $qualite
     */
    public function valueLimitInBareme($numLine, $qualite)
    {
        $this->addError(static::VALUE_LIMIT_IN_BAREME, 'numLine', $numLine, 'qualite', $qualite);
    }

    /**
     * @param $numLine
     * @param $date
     * @param $valeur
     * @param $qualite
     */
    public function qualityNotForCheckpoint($numLine, $date, $valeur, $qualite)
    {
        $this->lineError($numLine, $date, $valeur, static::QUALITY_NOT_FOR_CHECKPOINT, 'qualite', $qualite);
    }

    /**
     * @param $station
     */
    public function noRightOnStation($station)
    {
        $this->addError(static::NO_RIGHT_ON_STATION, 'station', $station);
    }

    /**
     * @param $numLine
     * @param $date
     * @param $valeur
     * @param $qualite
     */
    public function noTransInObservatoireJeu($numLine, $date, $valeur, $qualite)
    {
        $this->lineError($numLine, $date, $valeur, static::NO_TRANS_IN_OBSERVATOIRE_JEU, 'qualite', $qualite);
    }

    /**
     * @param $numLine
     * @param $qualite
     */
    public function badCodeQualite($numLine, $qualite)
    {
        $this->addError(static::BAD_CODE_QUALITE, 'numLine', $numLine, 'qualite', $qualite);
    }

    /**
     * @param $numLine
     * @param $date
     * @param $valeur
     */
    public function valeurNotNumeric($numLine, $date, $valeur)
    {
        $this->lineError($numLine, $date, $valeur, static::VALEUR_NOT_NUMERIC);
    }

    /**
     * @param $numLine
     * @param $date
     * @param $codeQualite
     */
    public function validButNoValue($numLine, $date, $codeQualite)
    {
        $this->lineError($numLine, $date, null, static::VALID_NO_VALUE, 'qualite', $codeQualite);
    }

    /**
     * @param $numLine
     * @param $date
     * @param $valeur
     * @param $minimum
     */
    public function minimumNotNumeric($numLine, $date, $valeur, $minimum)
    {
        $this->lineError($numLine, $date, $valeur, static::MINIMUM_NOT_NUMERIC, 'minimum', $minimum);
    }

    /**
     * @param $numLine
     * @param $date
     * @param $valeur
     * @param $maximum
     */
    public function maximumNotNumeric($numLine, $date, $valeur, $maximum)
    {
        $this->lineError($numLine, $date, $valeur, static::MAXIMUM_NOT_NUMERIC, 'maximum', $maximum);
    }

    /**
     * @param $numLine
     * @param $date
     * @param $valeur
     */
    public function dateInvalidFormat($numLine, $date, $valeur)
    {
        $this->lineError($numLine, $date, $valeur, static::DATE_INVALID_FORMAT);
    }

    /**
     * @param $numLine
     * @param $date
     * @param $valeur
     */
    public function dateAfterNow($numLine, $date, $valeur)
    {
        $this->lineError($numLine, $date, $valeur, static::DATE_AFTER_NOW);
    }

    /**
     * @param $numLine
     * @param $date
     * @param $valeur
     * @param $qualite
     */
    public function incoherentGap($numLine, $date, $valeur, $qualite)
    {
        $this->lineError($numLine, $date, $valeur, static::INCOHERENT_GAP, 'qualite', $qualite);
    }

    /**
     * @param $numLine
     * @param $valeur
     */
    public function incoherentDates($numLine, $valeur)
    {
        $this->addError(static::INCOHERENT_DATES, 'numLine', $numLine, 'valeur', $valeur);
    }

    /**
     * @param $unite
     */
    public function badUniteEntreeForBareme($unite)
    {
        $this->addError(static::BAD_UNIT_INPUT_BAREME, 'unite', $unite);
    }

    /**
     * @param $unite
     */
    public function badUniteSortieForBareme($unite)
    {
        $this->addError(static::BAD_UNIT_OUTPUT_BAREME, 'unite', $unite);
    }

    /**
     * @param $numLine
     * @param $nomValeur
     * @param $valeur
     */
    public function variableNotNumeric($numLine, $nomValeur, $valeur)
    {
        $this->addError(static::VARIABLE_NOT_NUMERIC, 'numLine', $numLine, 'nomValeur', $nomValeur, 'valeur', $valeur);
    }

    /**
     * @param $minDataLines
     */
    public function notEnoughData($minDataLines)
    {
        $this->addError(static::NOT_ENOUGH_DATA, 'minDataLines', $minDataLines);
    }

    /**
     * @param $numLine
     * @param $valeur
     * @param $valeurPrec
     */
    public function notStrictlyAscending($numLine, $valeur, $valeurPrec)
    {
        $this->addError(static::NOT_STRICTLY_ASCENDING, 'numLine', $numLine, 'valeur', $valeur, 'valeurPrec', $valeurPrec);
    }

    /**
     * @param $format
     */
    public function incoherentShape($format)
    {
        $this->addError(static::INCOHERENT_SHAPE, 'format', $format);
    }

    /**
     * @param $errorCode
     */
    public function phpUploadError($errorCode)
    {
        $this->addError(static::PHP_UPLOAD_ERROR, 'errorCode', $errorCode);
    }

    /**
     * @param $chronique
     */
    public function noImportOnConverted($chronique)
    {
        $this->addError(static::NO_IMPORT_ON_CONVERTED, 'chronique', $chronique);
    }

    /**
     * @param $chronique
     */
    public function noCheckpointOnConverted($chronique)
    {
        $this->addError(static::NO_CHECKPOINT_ON_CONVERTED, 'chronique', $chronique);
    }

    public function undefinedParamConversion()
    {
        $this->addError(static::UNDEFINED_PARAM_CONVERSION);
    }

    /**
     * @param $param
     */
    public function badParamConversion($param)
    {
        $this->addError(static::BAD_PARAM_CONVERSION, 'param', $param);
    }

    public function badParentTimeSeries()
    {
        $this->addError(static::BAD_PARENT_TIME_SERIES);
    }
}
