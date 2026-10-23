<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohBundle\DataTransformer;

/**
 * Validates and transforms timezone, from any format to "UTC diff" format
 * (+/-hh:mm ; eg: +02:00, -04:30, +11:00, -00:49...).
 *
 * Each function returns :
 *  => the transformed timezone ("UTC diff" format "+/-hh:mm") ;
 *  => or FALSE if any error occured.
 */
class ToUtcDiffTransformer
{
    /**
     * "UTC diff" timezone : format "+/-hh:mm"
     * Note : Consistency of hours and minutes is tested (only hitch : "+12:37" or "-12:01" pass).
     *
     * @param mixed $utcDiffTimezone
     */
    public static function utcDiff($utcDiffTimezone)
    {
        $tz = trim($utcDiffTimezone);

        if (strlen($tz) === 6
            && in_array($tz[0], ['+', '-'])
            && (
                ($tz[1] === '0' && ctype_digit($tz[2]))
                || ($tz[1] === '1' && in_array($tz[2], ['0', '1', '2']))
            )
            && $tz[3] === ':'
            && in_array($tz[4], ['0', '1', '2', '3', '4', '5'])
            && ctype_digit($tz[5])
        ) {
            return $tz;
        }

        return false;
    }

    /**
     * Bdoh timezone format : "UTC+/-(h)h" (ex : +2, -10, +0...).
     * Consistency of hour is tested  :
     *      => no hour bigger than 12 ;
     *      => both +12 and -12 are accepted ;
     *      => hour entered as "+/- 0X" (ex : -02, +03) is accepted .
     *
     * @param mixed $timezone
     */
    public static function bdohTimezone($timezone)
    {
        // The string without any space
        $tz = str_replace(' ', '', $timezone);

        // $tz should contain 'UTC', treats it
        if ('UTC' === strtoupper(substr($tz, 0, 3))) {
            $tz = substr($tz, 3);
            $len = strlen($tz);

            if (($len === 2 || $len === 3) && in_array($tz[0], ['+', '-'])) {
                if ($len === 2) {
                    if (ctype_digit($tz[1])) {
                        return $tz[0] . '0' . $tz[1] . ':00';
                    }
                } elseif (($tz[1] === '0' && ctype_digit($tz[2])) ||
                    ($tz[1] === '1' && in_array($tz[2], ['0', '1', '2']))) {
                    return $tz . ':00';
                }
            }
        }

        return false;
    }
}
