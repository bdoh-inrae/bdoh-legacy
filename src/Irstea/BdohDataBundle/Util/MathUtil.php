<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohDataBundle\Util;

class MathUtil
{
    /**
     * Performs a linear interpolation of a value as a function of time.
     *
     * @param array  $dateValue1 Array containing the 1st date as a 1st element, and the associated value as a 2nd element
     * @param array  $dateValue2 Same as $dateValue1 but for the 2nd date
     * @param string $dateInterp Date at which the value must be interpolated
     *
     * @return float Interpolated value
     */
    public static function interpolate($dateValue1, $dateValue2, $dateInterp)
    {
        //abs(strtotime($date2) - strtotime($date1));
        $x = ((float) (strtotime($dateInterp) - strtotime($dateValue1[0])))
            / (strtotime($dateValue2[0]) - strtotime($dateValue1[0]));

        return $x * ((float) $dateValue2[1]) + (1.0 - $x) * ((float) $dateValue1[1]);
    }
}
