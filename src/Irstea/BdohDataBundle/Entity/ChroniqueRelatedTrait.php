<?php

namespace Irstea\BdohDataBundle\Entity;

/**
 * Interface ChroniqueRelatedTrait.
 */
trait ChroniqueRelatedTrait
{
    use SiteRelatedTrait;

    /**
     * @return SiteExperimental[]
     */
    public function getSites()
    {
        return $this->getChronique()->getStation()->getSites();
    }

    /**
     * @return Chronique
     */
    abstract public function getChronique();
}
