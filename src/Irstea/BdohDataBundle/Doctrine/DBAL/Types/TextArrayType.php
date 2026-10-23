<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohDataBundle\Doctrine\DBAL\Types;

use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;

class TextArrayType extends SQLArrayType
{
    /**
     * @return Type
     */
    protected function getInnerType()
    {
        return Type::getType(Types::TEXT);
    }
}
