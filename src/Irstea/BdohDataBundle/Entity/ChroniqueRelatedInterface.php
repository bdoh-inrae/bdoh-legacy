<?php

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
