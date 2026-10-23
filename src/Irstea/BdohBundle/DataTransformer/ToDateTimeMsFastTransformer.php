<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohBundle\DataTransformer;

/**
 * Transforms datetime (without validating), from any format to DateTimeMs format
 * (see : Irstea\BdohDataBundle\Doctrine\DBAL\Types\DateTimeMs).
 *
 * Each function returns :
 *  => the transformed datetime (format "YYYY-MM-DD hh:mm:ss.uuu") ;
 *  => or FALSE if any error occured.
 */
class ToDateTimeMsFastTransformer
{
    /**
     * Format : strictly "YYYY-MM-DD hh:mm:ss".
     *
     * @param mixed $datetime
     */
    public static function dateTime($datetime)
    {
        return trim($datetime);
    }

    /**
     * Format : strictly "DD/MM/YYYY hh:mm:ss".
     *
     * @param mixed $datetime
     */
    public static function frenchDateTime($datetime)
    {
        $datetime = trim($datetime);
        $day = strtok($datetime, '/');
        $month = strtok('/');
        $year = strtok(' ');
        $time = strtok('');

        return "$year-$month-$day $time";
    }

    /**
     * Format : strictly "DD/MM/YY hh:mm:ss".
     *
     * @param mixed $datetime
     */
    public static function shortFrenchDateTimeMs($datetime)
    {
        $datetime = trim($datetime);
        $day = strtok($datetime, '/');
        $month = strtok('/');
        $year = strtok(' ');
        $time = strtok('');
        $year = (((int) $year > 45) ? '19' : '20') . $year;

        return "$year-$month-$day $time";
    }
}
