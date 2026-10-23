<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohBundle\DataTransformer;

/**
 * Validates and transforms datetime, from any format to DateTimeMs format
 * (see : Irstea\BdohDataBundle\Doctrine\DBAL\Types\DateTimeMs).
 *
 * Each function returns :
 *  => the transformed datetime (format "YYYY-MM-DD hh:mm:ss.uuu") ;
 *  => or FALSE if any error occured.
 */
class ToDateTimeMsTransformer
{
    /**
     * Format : "YYYY-MM-DD" + a possible variable time.
     * See    : ToTimeMsTransformer::varTimeMs().
     *
     * @param mixed $datetime
     */
    public static function varDateTimeMs($datetime)
    {
        // Splits $datetime into its "date" and "time" parts
        $datetime = trim($datetime);
        $date = strtok($datetime, ' ');
        $time = strtok(' ');

        if (false === $date = ToDateTransformer::date($date)) {
            return false;
        }

        if (false !== $time) {
            return ($time = ToTimeMsTransformer::varTimeMs($time)) ?
                ($date . ' ' . $time) : false;
        }

        return $date . ' 00:00:00.000';
    }

    /**
     * Format : "D/MM/YYYY" or "DD/MM/YYYY" + a possible variable time
     * See    : ToTimeMsTransformer::varTimeMs().
     *
     * @param mixed $datetime
     */
    public static function varFrenchDateTimeMs($datetime)
    {
        // Splits $datetime into its "date" and "time" parts
        $datetime = trim($datetime);
        $date = strtok($datetime, ' ');
        $time = strtok(' ');

        if (false === $date = ToDateTransformer::frenchDate($date)) {
            return false;
        }

        if (false !== $time) {
            return ($time = ToTimeMsTransformer::varTimeMs($time)) ?
                ($date . ' ' . $time) : false;
        }

        return $date . ' 00:00:00.000';
    }

    /**
     * Format : "D/MM/YY" or "DD/MM/YY" + a possible variable time
     * See    : ToTimeMsTransformer::varTimeMs().
     *
     * @param mixed $datetime
     */
    public static function varShortFrenchDateTimeMs($datetime)
    {
        // Splits $datetime into its "date" and "time" parts
        $datetime = trim($datetime);
        $date = strtok($datetime, ' ');
        $time = strtok(' ');

        if (false === $date = ToDateTransformer::shortFrenchDate($date)) {
            return false;
        }

        if (false !== $time) {
            return ($time = ToTimeMsTransformer::varTimeMs($time)) ?
                ($date . ' ' . $time) : false;
        }

        return $date . ' 00:00:00.000';
    }
}
