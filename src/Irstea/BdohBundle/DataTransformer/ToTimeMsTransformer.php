<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohBundle\DataTransformer;

/**
 * Validates and transforms time, from any format to TimeMs format (hh:mm:ss.uuu).
 *
 * Each function returns :
 *  => the transformed time (format "H:i:s.u") ;
 *  => or FALSE if any error occured.
 */
class ToTimeMsTransformer
{
    /**
     * The parameter should be :
     *    => a time with just hours       :  format (h)h
     *    => a time without seconds       :  format (h)h:mm
     *    => a time without milliseconds  :  format (h)h:mm:ss
     *    => a time with milliseconds     :  format (h)h:mm:ss.uuu.
     *
     * @param mixed $time
     */
    public static function varTimeMs($time)
    {
        $time = trim($time);
        $len = strlen($time);

        // Cases of once digit for hours
        if (in_array($len, [1, 4, 7, 11])) {
            ++$len;
            $time = '0' . $time;
        }

        switch ($len) {
            case 2:
                return static::checkTimeJustHours($time) ? $time . ':00:00.000' : false;
            case 5:
                return static::checkTimeWithoutSeconds($time) ? $time . ':00.000' : false;
            case 8:
                return static::checkTime($time) ? $time . '.000' : false;
            case 12:
                return static::checkTimeMs($time) ? $time : false;
            default:
                return false;
        }
    }

    /**
     * Checks that a time with just hours is valid.
     * Note : the string length is assumed to be good (equal to 2).
     *
     * @param mixed $time
     */
    public static function checkTimeJustHours($time)
    {
        return (($time[0] === '0' || $time[0] === '1') && ctype_digit($time[1]))
            || ($time[0] === '2' && $time[1] >= '0' && $time[1] <= '4');
    }

    /**
     * Checks that a time without seconds is valid.
     * Note : the string length is assumed to be good (equal to 5).
     *
     * @param mixed $time
     */
    public static function checkTimeWithoutSeconds($time)
    {
        return ('24:00' === $time)
            ||
            ((':' === $time[2])
                && ($time[3] >= '0' && $time[3] < '6')
                && ctype_digit($time[4])
                && static::checkTimeJustHours($time[0] . $time[1]));
    }

    /**
     * Checks that a time without milliseconds is valid.
     * Note : the string length is assumed to be good (equal to 8).
     *
     * @param mixed $time
     */
    public static function checkTime($time)
    {
        return ('24:00:00' === $time)
            ||
            ((':' === $time[5])
                && ($time[6] >= '0' && $time[6] < '6')
                && ctype_digit($time[7])
                && static::checkTimeWithoutSeconds(substr($time, 0, 5)));
    }

    /**
     * Checks that a time with milliseconds is valid.
     * Note : the string length is assumed to be good (equal to 12).
     *
     * @param mixed $time
     */
    public static function checkTimeMs($time)
    {
        return ('24:00:00.000' === $time)
            ||
            (('.' === $time[8])
                && is_numeric($time[9] . $time[10] . $time[11])
                && static::checkTime(substr($time, 0, 8)));
    }
}
