<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohSecurityBundle\Entity;

use Irstea\BdohDataBundle\Entity\Observatoire;
use Irstea\BdohDataBundle\Entity\ObservatoireRelatedInterface;

/**
 * Class RoleObservatoire.
 */
class RoleObservatoire extends Role implements ObservatoireRelatedInterface
{
    /**
     * @var Observatoire
     */
    private $observatoire;

    /**
     * Set observatoire.
     *
     * @param Observatoire $observatoire
     */
    public function setObservatoire(Observatoire $observatoire)
    {
        $this->observatoire = $observatoire;
    }

    /**
     * Get observatoire.
     *
     * @return Observatoire
     */
    public function getObservatoire()
    {
        return $this->observatoire;
    }

    /**
     * {@inheritdoc}
     */
    protected function isTarget($target)
    {
        return $target instanceof Observatoire && $target->getId() === $this->observatoire->getId();
    }

    /**
     * {@inheritdoc}
     */
    public function getTarget()
    {
        return $this->observatoire;
    }

    /**
     * {@inheritdoc}
     */
    public function setTarget($target)
    {
        $this->setObservatoire($target);
    }
}
