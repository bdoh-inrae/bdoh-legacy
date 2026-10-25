<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohAdminBundle\Importer\Measure;

/**
 * QTVAR (banque HYDRO2) : UNTESTED, DO NOT USE.
 */
class QtvarImporter extends QjoImporter
{
    /**
     * @var int
     */
    protected $headerSize = 0;

    /**
     * @var int
     */
    protected $lineSize = 9;

    /**
     * @var string
     */
    protected $lineBegin = '920';

    /**
     * @var int
     */
    protected $secondsToAdd = 5;

    /**
     * @param $data
     * @param $numLine
     */
    protected function treatLine($data, $numLine)
    {
        $value = trim($data[5]);
        $quality = trim($data[6]);

        $currentDateTime = new \DateTime(
            static::preValidateDateTimeMs($data[3]) . ' ' . trim($data[4]),
            new \DateTimeZone('UTC')
        );

        $dateToStore = clone $currentDateTime;

        //Redundancy processing
        //NB : $this->secondsToAdd is incremented 5 by 5 in order to work with gap processing (which use 1 second delay).
        if (isset($this->previousDate)) {
            if ($currentDateTime == $this->previousDate) {
                //Add an arbitrary number of seconds in order to avoid redundancy
                $currentDateTime->add(\DateInterval::createFromDateString($this->secondsToAdd . ' seconds'));
                $this->secondsToAdd += 5;
            } else {
                //Reset the number of seconds in order to restart from a low amount
                $this->secondsToAdd = 5;
            }
        }

        //Gap processing
        if (trim($data[7]) !== '2') {
            //We add two gap values, at previousDate + 1 sec and currentDate - 1 sec
            $tempCurrentDate = clone $currentDateTime;
            $gapDateStrings[0] = $tempCurrentDate->sub(\DateInterval::createFromDateString('1 second'))->format('Y-m-d H:i:s');
            if (isset($this->previousDate)) {
                $tempPreviousDate = clone $this->previousDate;
                $gapDateStrings[1] = $tempPreviousDate->add(\DateInterval::createFromDateString('1 second'))->format('Y-m-d H:i:s');
            }

            foreach ($gapDateStrings as $gapDate) {
                $this->processMesure(0, $gapDate, null, QjoImporter::GAP_QUALITY, null, null, $numLine);
            }
        }

        // Gap line ; maybe inexistant in QTVAR
        if (empty($value) || empty($quality)) {
            $value = null;
            $quality = QjoImporter::GAP_QUALITY;
        }

        $this->processMesure(0, $currentDateTime->format('Y-m-d H:i:s'), $value, $quality, null, null, $numLine);
        $this->previousDate = $dateToStore;
    }
}
