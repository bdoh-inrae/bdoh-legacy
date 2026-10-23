<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohDataBundle\Doctrine\DBAL\Types;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Types\Type;

/**
 * Type that maps an PostgreSQL TIMESTAMP(3) to a string 'Y-m-d H:i:s.u'.
 */
class DateTimeMsType extends Type
{
    const DATETIMEMS = 'datetimems';

    /**
     * {@inheritdoc}
     */
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        if ($platform instanceof PostgreSQLPlatform) {
            return 'TIMESTAMP(3) WITHOUT TIME ZONE';
        }

        return $platform->getDateTimeTypeDeclarationSQL($column);
    }
}
