<?php

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
