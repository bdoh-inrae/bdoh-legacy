<?php

namespace Irstea\BdohDataBundle\Doctrine\DBAL\Types;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

/**
 * Description of PointType.
 */
class GeometryType extends Type
{
    const GEOMETRY = 'geometry';

    /**
     * {@inheritdoc}
     */
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return 'geometry';
    }
}
