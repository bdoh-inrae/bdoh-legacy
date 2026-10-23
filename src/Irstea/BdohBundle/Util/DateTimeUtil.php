<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohBundle\Util;

/**
 * Utilities class for getting data from dates.
 */
class DateTimeUtil
{
    /**
     * Formats a SQL datetime, in localized manner.
     *
     * @param string     $dateTime   SQL date
     * @param mixed      $datetime
     * @param mixed      $dateFormat
     * @param mixed      $timeFormat
     * @param mixed|null $locale
     *
     * @return string
     */
    public static function localizedDate($datetime, $dateFormat = 'medium', $timeFormat = 'medium', $locale = null)
    {
        $formatValues = [
            'none'   => \IntlDateFormatter::NONE,
            'short'  => \IntlDateFormatter::SHORT,
            'medium' => \IntlDateFormatter::MEDIUM,
            'long'   => \IntlDateFormatter::LONG,
            'full'   => \IntlDateFormatter::FULL,
        ];

        $formatter = \IntlDateFormatter::create(
            $locale !== null ? $locale : \Locale::getDefault(),
            $formatValues[$dateFormat],
            $formatValues[$timeFormat],
            \date_default_timezone_get()
        );

        $datetime = new \DateTime($datetime);

        return $formatter->format($datetime->getTimestamp());
    }

    /**
     * Returns the Unix timestamp + milliseconds, from $dateTime.
     *
     * @param string $dateTime SQL date
     *
     * @return float
     */
    public static function toSeconds($dateTime)
    {
        // Retrieves the milliseconds
        strtok($dateTime, '.');
        $milliseconds = strtok('');

        $date = new \DateTime($dateTime);

        return $milliseconds ? ($date->format('U') + $milliseconds / 1000) : $date->format('U');
    }

    /**
     * Returns the first day of current month of given DateTime.
     *
     * @param DateTime $date
     */
    public static function getFirstDayOfCurrentMonth(\DateTime $datetime)
    {
        $newDatetime = clone $datetime;
        $newDatetime->setDate($datetime->format('Y'), $datetime->format('m'), 1);
        $newDatetime->setTime(0, 0, 0);

        return $newDatetime;
    }

    /**
     * Returns the last day of current month of given DateTime.
     *
     * @param DateTime $date
     */
    public static function getLastDayOfCurrentMonth(\DateTime $datetime)
    {
        $newDatetime = clone $datetime;

        // First, we put it at first day of *next* month
        if ($datetime->format('m') !== 12) {
            $newDatetime->setDate($datetime->format('Y'), $datetime->format('m') + 1, 1);
        } else {
            $newDatetime->setDate($datetime->format('Y') + 1, 1, 1);
        }
        $newDatetime->setTime(0, 0, 0);

        // Then, we substract one second ; should be last second of previous month, as intended
        $newDatetime->modify('-1 second');

        return $newDatetime;
    }

    public static function dateValidationAndString($date, $toString = true)
    {
        // If $date is null or empty, create new DateTime
        if (!$date) {
            $date = new \DateTime();
        }

        // If $date is a string, try creating a DateTime with it
        if (\is_string($date)) {
            try {
                $date = new \DateTime($date);
            } catch (\Exception $e) {
                $date = null;
            }
        }

        // By now, $date should be a DateTime
        $valid = $date && ($date instanceof \DateTime);

        // If so, returned date is $date as DateTime or string. Otherwise, it is null.
        if ($toString && $valid) {
            $date = $date->format('Y-m-d H:i:s');
        }

        return [$valid, $valid ? $date : null];
    }
}
