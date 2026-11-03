<?php
// SPDX-FileCopyrightText: The BDOH authors
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace Irstea\BdohTheiaOzcarBundle\Mapper;

use Irstea\BdohDataBundle\Entity\Observatoire;
use Irstea\BdohTheiaOzcarBundle\Model\FormatPivot;

interface MapperInterface
{
    public function mapFormatPivotInterface(Observatoire $entity): FormatPivot;
}
