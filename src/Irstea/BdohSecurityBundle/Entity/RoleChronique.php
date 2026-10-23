<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohSecurityBundle\Entity;

use Irstea\BdohDataBundle\Entity\Chronique;
use Irstea\BdohDataBundle\Entity\ChroniqueRelatedInterface;
use Irstea\BdohDataBundle\Entity\ChroniqueRelatedTrait;

/**
 * Class RoleChronique.
 */
class RoleChronique extends Role implements ChroniqueRelatedInterface
{
    use ChroniqueRelatedTrait;

    /**
     * @var Chronique
     */
    protected $chronique;

    /**
     * Set chronique.
     *
     * @param Chronique $chronique
     */
    public function setChronique(Chronique $chronique)
    {
        $this->chronique = $chronique;
    }

    /**
     * Get chronique.
     *
     * @return Chronique
     */
    public function getChronique()
    {
        return $this->chronique;
    }

    /**
     * {@inheritdoc}
     */
    protected function isTarget($target)
    {
        return $target instanceof Chronique && $target->getId() === $this->chronique->getId();
    }

    /**
     * {@inheritdoc}
     */
    public function getTarget()
    {
        return $this->chronique;
    }

    /**
     * {@inheritdoc}
     */
    public function setTarget($target)
    {
        $this->setChronique($target);
    }
}
