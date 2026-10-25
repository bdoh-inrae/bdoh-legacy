<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohAdminBundle\Importer\Measure;

use Irstea\BdohDataBundle\Util\JeuToJeu;

/**
 * https://forge.cemagref.fr/projects/bdoh/wiki/Formats_de_fichier_Import_du_stock#Biche.
 */
class BdohImporter extends Importer implements ImporterInterface
{
    const COMMENT = '#';

    const GAP_QUALITY = 'gap';

    const GAP_VALUE = '-9999';

    /**
     * @var int
     */
    protected $headerSize = 3;

    /**
     * @var int
     */
    protected $dateNumber = 1;

    /**
     * @var
     */
    protected $hasMinMax;

    /**
     * @var array
     */
    protected $columnInfoPerChronique = [];

    /**
     * @var
     */
    protected $expectedColumnNumber;

    /**
     * @throws Exception\StopImportException
     */
    protected function setMetadataFromHeader()
    {
        // Three-line header
        for ($i = 0; $i < 3; ++$i) {
            $headerData[$i] = explode(';', fgets($this->file));
        }

        // First and second line should have the same columns number
        if (count($headerData[0]) !== count($headerData[1])) {
            $this->errors->badHeaderFirstTwoLines();
            throw new Exception\StopImportException();
        }

        // First line should be : Station ; Fuseau ; { Chronique ; Unite } (n fois).
        $chroniqueNumber = 0;
        $numColumn = count($headerData[0]);

        if ($numColumn < 4 || ($numColumn - 2) % 2 !== 0) {
            $this->errors->badHeaderSyntax();
            throw new Exception\StopImportException();
        }

        for ($i = 2; $i < ($numColumn - 1); $i += 2) {
            if (('chronique' === trim(strtolower($headerData[0][$i]))) &&
                ('unite' === trim(strtolower($headerData[0][$i + 1])))) {
                ++$chroniqueNumber;
            } else {
                $this->errors->badHeaderSyntax();
                throw new Exception\StopImportException();
            }
        }

        if ($chroniqueNumber > 0) {
            $this->expectedChroniques = $chroniqueNumber;
        } else {
            $this->errors->badHeaderNoChronique();
            throw new Exception\StopImportException();
        }

        // Second line should be : codeStation ; valeurFuseau ; { codeChronique ; libelleUnite } (n times)
        $this->setStation(trim($headerData[1][0]));
        $this->setTimezone($headerData[1][1]);

        // If bad station, retrieving of 'chroniques' will fail => so stops
        if ($this->errors->isDirty()) {
            throw new Exception\StopImportException();
        }

        for ($i = 0; $i < $this->expectedChroniques; ++$i) {
            $this->setChronique(trim($headerData[1][2 * $i + 2]), $i, trim($headerData[1][2 * $i + 3]));
        }

        $this->process3rdLine($headerData[2]);

        $lineSize = count($headerData[2]);
        $withoutMinMax = $this->dateNumber + 2 * $this->expectedChroniques;
        $withMinMax = $this->dateNumber + 4 * $this->expectedChroniques;

        $allowedCollumnNumber = range($withoutMinMax, $withMinMax, 2);

        if ($lineSize === $withoutMinMax) {
            $this->hasMinMax = false;
        } elseif (in_array($lineSize, $allowedCollumnNumber)) {
            $this->hasMinMax = true;
        } else {
            $this->errors->badLineSize(3, $lineSize, join('/', $allowedCollumnNumber));
        }
    }

    /**
     * @param $headerLineData
     *
     * @throws Exception\StopImportException
     */
    protected function process3rdLine($headerLineData)
    {
        $trimmedData = [];
        foreach ($headerLineData as $hld) {
            $trimmedData[] = trim(strtolower($hld));
        }
        $nHeaderData = \count($trimmedData);
        if ($nHeaderData < 2 || $trimmedData[0] !== 'dateheure') {
            $this->errors->badHeaderSyntax();
            throw new Exception\StopImportException();
        }

        $this->processRestOf3rdLine($trimmedData, $nHeaderData);
    }

    /**
     * @param $trimmedData
     * @param $nHeaderData
     *
     * @throws Exception\StopImportException
     */
    protected function processRestOf3rdLine($trimmedData, $nHeaderData)
    {
        $iCell = $this->dateNumber;
        for ($i = 0; $i < $this->expectedChroniques; ++$i) {
            $iStart = $iCell;
            if ($iCell > $nHeaderData - 2
                || $trimmedData[$iCell++] !== 'valeur'
                || $trimmedData[$iCell++] !== 'qualite') {
                $this->errors->badHeaderSyntax();
                throw new Exception\StopImportException();
            }
            if (($hasMin = ($iCell < $nHeaderData && $trimmedData[$iCell] === 'min'))) {
                ++$iCell;
            }
            if (($hasMax = ($iCell < $nHeaderData && $trimmedData[$iCell] === 'max'))) {
                ++$iCell;
            }

            $this->columnInfoPerChronique[] = ['hasMin' => $hasMin, 'hasMax' => $hasMax, 'iStart' => $iStart, 'iEnd' => $iCell - 1];
        }
        $this->expectedColumnNumber = $iCell;
    }

    /**
     * @throws Exception\StopImportException
     */
    protected function read()
    {
        // Retrieves the real "gap quality" of the source 'JeuQualite'
        $jeuToJeu = new JeuToJeu($this->em, $this->sourceJeuQualite, $this->sourceJeuQualite);
        $srcGapQuality = $jeuToJeu->codeToCode(static::GAP_QUALITY);

        // Computes correct expected line size
        //        $expectedSize = $this->dateNumber + ($this->hasMinMax ? 4 : 2) * $this->expectedChroniques;;

        $numLine = $this->headerSize + 1;

        while (($line = fgets($this->file)) !== false) {
            $this->processMeasureLine($line, $numLine, $srcGapQuality, $jeuToJeu);
            ++$numLine;
        }
    }

    /**
     * @param $line
     * @param $numLine
     * @param $srcGapQuality
     * @param $jeuToJeu
     *
     * @throws Exception\StopImportException
     */
    protected function processMeasureLine($line, $numLine, $srcGapQuality, $jeuToJeu)
    {
        $commentPos = \strpos($line, self::COMMENT);
        $trimmedLine = trim($commentPos === false ? $line : substr($line, 0, $commentPos));
        if (strlen($trimmedLine) === 0) {
            return;
        }

        $lineData = explode(';', $trimmedLine);
        if (($found = count($lineData)) !== $this->expectedColumnNumber) {
            $this->errors->badLineSize($numLine, $found, $this->expectedColumnNumber);
            throw new Exception\StopImportException();
        }

        for ($i = 0; $i < $this->expectedChroniques; ++$i) {
            $this->processChroniqueInLine($lineData, $i, $srcGapQuality, $numLine, $jeuToJeu);
        }
    }

    /**
     * @param $lineData
     * @param $iChronique
     * @param $srcGapQuality
     * @param $numLine
     * @param $jeuToJeu
     */
    protected function processChroniqueInLine($lineData, $iChronique, $srcGapQuality, $numLine, $jeuToJeu)
    {
        $date = trim($lineData[0]);
        $colInfo = $this->columnInfoPerChronique[$iChronique];
        $colIndex = $colInfo['iStart'];
        $value = trim($lineData[$colIndex++]);
        $quality = trim($lineData[$colIndex++]);
        $min = $colInfo['hasMin'] ? trim($lineData[$colIndex++]) : null;
        $max = $colInfo['hasMax'] ? trim($lineData[$colIndex]) : null;

        if (($quality === $srcGapQuality) && ($value === static::GAP_VALUE)) {
            $value = null;
        } elseif ($quality === $srcGapQuality) {
            $this->errors->incoherentGap($numLine, $date, $value, $quality);
        }
        $isGapType = $jeuToJeu->isGapType($quality);
        if ($isGapType) {//larger scope than just $srcGapQuality ; cans include other qualities as well.
            $value = null;
        }

        $this->processMesure($iChronique, $date, $value, $quality, $min, $max, $numLine);
    }

    /**
     * Treatments to do when serializing.
     */
    public function __sleep()
    {
        $attributesToSerialize = parent::__sleep();
        foreach ([
            'hasMinMax',
            'expectedColumnNumber',
            'columnInfoPerChronique',
        ] as $attr) {
            $attributesToSerialize[] = $attr;
        }

        return $attributesToSerialize;
    }
}
