<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohAdminBundle\Importer\Measure;

/**
 * Standard BDOH Importer for ChroniqueDiscontinue.
 */
class BdohPlageImporter extends BdohImporter
{
    /**
     * @var string
     */
    protected $typeOfMeasure = 'Plage';

    /**
     * @var int
     */
    protected $dateNumber = 2;

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
        if ($nHeaderData < 3 || $trimmedData[0] !== 'debut' || $trimmedData[1] !== 'fin') {
            $this->errors->badHeaderSyntax();
            throw new Exception\StopImportException();
        }

        $this->processRestOf3rdLine($trimmedData, $nHeaderData);
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
        $debut = trim($lineData[0]);
        $fin = trim($lineData[1]);
        $colInfo = $this->columnInfoPerChronique[$iChronique];
        $colIndex = $colInfo['iStart'];
        $value = trim($lineData[$colIndex++]);
        $quality = trim($lineData[$colIndex++]);
        $min = $colInfo['hasMin'] ? trim($lineData[$colIndex++]) : null;
        $max = $colInfo['hasMax'] ? trim($lineData[$colIndex]) : null;

        if (($quality === $srcGapQuality) && ($value === static::GAP_VALUE)) {
            $value = null;
        } elseif ($quality === $srcGapQuality) {
            $this->errors->incoherentGap($numLine, $debut, $value, $quality);
        }
        $isGapType = $jeuToJeu->isGapType($quality);
        if ($isGapType) {//larger scope than just $srcGapQuality ; cans include other qualities as well.
            $value = null;
        }

        $this->processPlage($iChronique, $debut, $fin, $value, $quality, $min, $max, $numLine);
    }
}
