<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohDataBundle\Doctrine\DBAL\Types;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

class BaremeType extends Type
{
    const BAREME = 'bareme';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return '_float8';
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if (!is_array($value) || sizeof($value) < 2) {
            return null;
        }

        $valueToReturn = [];
        foreach ($value as $line) {
            foreach ($line as $index => $x) {
                if (!is_numeric($x)) {
                    $line[$index] = 'null';
                }
            }
            while (sizeof($line) < 5) {
                $line[] = 'null';
            }

            $valueToReturn[] = '{' . implode(',', $line) . '}';
        }

        return '{' . implode(',', $valueToReturn) . '}';
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?array
    {
        if ($value === null) {
            return null;
        }

        $value = str_replace('},{', ';', $value);
        $value = str_replace('{', '', $value);
        $value = str_replace('}', '', $value);

        $values = explode(';', $value);

        $toReturn = [];

        foreach ($values as $line) {
            $temp = explode(',', $line);
            $toReturn[] = $temp;
        }

        return $toReturn;
    }
}
