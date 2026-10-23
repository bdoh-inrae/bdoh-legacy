<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohDataBundle\Entity;

use Doctrine\Common\Collections\Collection;

/**
 * Trait SiteRelatedTrait.
 */
trait SiteRelatedTrait
{
    /**
     * @return SiteExperimental[]|Collection
     */
    abstract public function getSites();

    /**
     * @return Observatoire|null
     */
    public function getObservatoire()
    {
        $sites = $this->getSites();
        if ($sites instanceof Collection) {
            return $sites->isEmpty() ? null : $sites->first()->getObservatoire();
        }

        return empty($sites) ? null : $sites[0]->getObservatoire();
    }
}
