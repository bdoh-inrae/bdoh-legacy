<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohBundle\DataTransformer;

/**
 * Validates and transforms date, from any format to "YYYY-MM-DD" format.
 *
 * Each function returns :
 *  => the transformed date (format "YYYY-MM-DD") ;
 *  => or FALSE if any error occured.
 */
class ToDateTransformer
{
    /**
     * Format : "YYYY-MM-DD"
     * Note   : $date is supposed have a good length (so, do a "trim" before).
     *
     * @param mixed $date
     */
    public static function date($date)
    {
        // Bad length
        if (10 !== strlen($date)) {
            return false;
        }

        // Splits date
        $year = substr($date, 0, 4);
        $month = substr($date, 5, 2);
        $day = substr($date, 8, 2);

        return static::checkSplitDate($year, $month, $day, $date[4], $date[7]) ?
            $date : false;
    }

    /**
     * French date : format "D/MM/YYYY" or  "DD/MM/YYYY"
     * Note        : $date is supposed have a good length (so, do a "trim" before).
     *
     * @param mixed $date
     */
    public static function frenchDate($date)
    {
        $len = strlen($date);

        // Bad length
        if (9 !== $len && 10 !== $len) {
            return false;
        }

        // Splits date
        $day = substr($date, 0, $len - 8);
        $month = substr($date, $len - 7, 2);
        $year = substr($date, $len - 4, 4);

        return static::checkSplitDate($year, $month, $day, $date[$len - 5], $date[$len - 8], '/') ?
            $year . '-' . $month . '-' . (9 === $len ? '0' : '') . $day :
            false;
    }

    /**
     * Short french date : format "D/MM/YY" or  "DD/MM/YY"
     * Note              : $date is supposed have a good length (so, do a "trim" before).
     *
     * @param mixed $date
     */
    public static function shortFrenchDate($date)
    {
        $len = strlen($date);

        // Bad length
        if (7 !== $len && 8 !== $len) {
            return false;
        }

        // Splits date
        $day = substr($date, 0, $len - 6);
        $month = substr($date, $len - 5, 2);
        $year = substr($date, $len - 2, 2);
        $year = (((int) $year > 45) ? '19' : '20') . $year;

        return static::checkSplitDate($year, $month, $day, $date[$len - 3], $date[$len - 6], '/') ?
            $year . '-' . $month . '-' . (7 === $len ? '0' : '') . $day :
            false;
    }

    /**
     * Returns true if each part of a date is valid.
     * Else, returns false.
     *
     * @param mixed $year
     * @param mixed $month
     * @param mixed $day
     * @param mixed $yearDelimiter
     * @param mixed $monthDelimiter
     * @param mixed $goodDelimiter
     */
    public static function checkSplitDate($year, $month, $day, $yearDelimiter, $monthDelimiter, $goodDelimiter = '-')
    {
        return ($yearDelimiter === $goodDelimiter) && ($monthDelimiter === $goodDelimiter)
            && is_numeric($year) && is_numeric($month) && is_numeric($day)
            && checkdate((int) $month, (int) $day, (int) $year);
    }
}
