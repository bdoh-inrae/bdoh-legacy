<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohDataBundle\Entity;

/**
 * Class ChroniqueRelatedInterface.
 */
interface ChroniqueRelatedInterface extends SiteRelatedInterface
{
    /**
     * @return Chronique
     */
    public function getChronique();
}
