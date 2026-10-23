<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohBundle\DataTransformer;

/**
 * Transforms datetime (without validating), from DateTimeMs format ("YYYY-MM-DD hh:mm:ss.uuu") to any format
 * (see : Irstea\BdohDataBundle\Doctrine\DBAL\Types\DateTimeMs).
 *
 * Each function returns :
 *  => the transformed datetime ;
 *  => or FALSE if any error occured.
 */
class FromDateTimeMsTransformer
{
    /**
     * Return format : "DD/MM/YYYY hh:mm:ss" or "DD/MM/YYYY hh:mm:ss.uuu".
     *
     * @param mixed $dt
     */
    public static function toFrenchDateTimeMs($dt)
    {
        return substr($dt, 8, 2) . '/' .
            substr($dt, 5, 2) . '/' .
            substr($dt, 0, 4) . ' ' .
            substr($dt, 11);
    }
}
