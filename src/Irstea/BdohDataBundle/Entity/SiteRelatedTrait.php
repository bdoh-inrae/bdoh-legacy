<?php
// SPDX-FileCopyrightText: The BDOH authors
// SPDX-License-Identifier: AGPL-3.0-or-later

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
