<?php

namespace Irstea\BdohTheiaOzcarBundle\Mapper;

use Irstea\BdohDataBundle\Entity\Observatoire;
use Irstea\BdohTheiaOzcarBundle\Model\FormatPivot;

interface MapperInterface
{
    public function mapFormatPivotInterface(Observatoire $entity): FormatPivot;
}
