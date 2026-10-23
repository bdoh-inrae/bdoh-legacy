<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohDataBundle\Entity;

/**
 * Interface SiteRelatedInterface.
 */
interface SiteRelatedInterface extends ObservatoireRelatedInterface
{
    /**
     * @return SiteExperimental[]
     */
    public function getSites();
}
