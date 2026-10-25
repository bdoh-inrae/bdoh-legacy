<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohAdminBundle\Importer\Measure;

/**
 * https://forge.cemagref.fr/projects/bdoh/wiki/Formats_de_fichier_Import_du_stock#Débit journalier : QJ0 (banque HYDRO2).
 */
class QjoImporter extends Importer implements ImporterInterface
{
    const GAP_QUALITY = 'gap';

    /**
     * List of Hydro2 line headers.
     * The lines beginning with those should be ignored but not trigger any Exception.
     */
    protected $correctBegins = [
        'FTX',
        '919',
        '922',
        'QME',
        'QMM',
        'CTH',
        'COR',
        'CQT',
        'EVT',
        'HMM',
        'JGG',
        'LOH',
        'MJG',
        'PAT',
        'PIV',
        'PST',
        'SHY',
        'TAR',
        'ZCH',
        'DEC',
        'EMT',
        'DES',
        'DEB',
        '950',
    ];

    /**
     * We keep date of previous line for gap detection purpose.
     */
    protected $previousDate = null;

    /**
     * @var int
     */
    protected $expectedChroniques = 1;

    /**
     * @var string
     */
    protected $sourceJeuQualite = 'Hydro2';

    /**
     * @var int
     */
    protected $headerSize = 0;

    /**
     * @var int
     */
    protected $lineSize = 7;

    /**
     * @var string
     */
    protected $lineBegin = 'QJO';

    // Date format : YYYYMMDD

    /**
     * @param $dateTimeMs
     *
     * @return string
     */
    protected function preValidateDateTimeMs($dateTimeMs)
    {
        $date = trim($dateTimeMs);

        return substr($date, 0, 4) . '-' . substr($date, 4, 2) . '-' . substr($date, 6, 2);
    }

    // When this function is called => $dateTimeMs already formated

    /**
     * @param $dateTimeMs
     *
     * @return bool|string
     */
    protected function validateDateTimeMs($dateTimeMs)
    {
        return $dateTimeMs;
    }

    /**
     * @throws Exception\StopImportException
     */
    protected function read()
    {
        $numLine = $this->headerSize + 1;

        while (($data = fgetcsv($this->file, 0, ';', '"', '\\')) !== false) {
            $beginWord = substr(trim(strtoupper($data[0])), 0, 3);
            if ($beginWord === 'FIN') {
                break;
            }

            if ($beginWord !== $this->lineBegin) {
                /*
                 * Two different cases :
                 *  -> the line is a correct Hydro2 line, but not the expected : we just skip it
                 *  -> the line is something else : Exception
                 */
                if (in_array($beginWord, $this->correctBegins)) {
                    ++$numLine;
                    continue;
                }
                $this->errors->badLineBegin($numLine, $this->lineBegin);
                throw new Exception\StopImportException();
            }

            if ($this->lineSize !== ($found = count($data))) {
                $this->errors->badLineSize($numLine, $found, $this->lineSize);
                throw new Exception\StopImportException();
            }

            $this->treatLine($data, $numLine++);
        }
    }

    /**
     * @param $data
     * @param $numLine
     */
    protected function treatLine($data, $numLine)
    {
        $value = trim($data[3]);
        $quality = trim($data[5]);
        $date = static::preValidateDateTimeMs($data[2]);
        $currentDateTime = new \DateTime(
            $date,
            new \DateTimeZone('UTC')
        );

        // Gap can be a missing line for a given date.
        if (isset($this->previousDate)) {
            $days = date_diff($this->previousDate, $currentDateTime)->d;

            // Gap(s) if this difference is not 1
            if ($days > 1) {
                $tempDate = clone $this->previousDate;

                // Adds $days-1 gap lines
                for ($i = 1; $i < $days; ++$i) {
                    $tempDateString = $tempDate->add(\DateInterval::createFromDateString('1 day'))->format('Y-m-d');
                    $this->processMesure(0, $tempDateString, null, self::GAP_QUALITY, null, null, $numLine);
                }
            }
        }

        // Gap line
        if (empty($value) || empty($quality)) {
            $value = null;
            $quality = static::GAP_QUALITY;
        }

        $this->processMesure(0, $date, $value, $quality, null, null, $numLine);
        $this->previousDate = $currentDateTime;
    }
}
