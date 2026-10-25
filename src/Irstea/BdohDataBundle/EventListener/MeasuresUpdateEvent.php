<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohDataBundle\EventListener;

use Irstea\BdohDataBundle\Entity\Chronique;
use Irstea\BdohSecurityBundle\Entity\Utilisateur;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * {@inheritdoc}
 */
class MeasuresUpdateEvent extends Event
{
    /**
     * @var Chronique
     */
    private $chronique;

    /**
     * @var Utilisateur
     */
    private $user;

    /**
     * @var \DateTime|null
     */
    private $debut;

    /**
     * @var \DateTime|null
     */
    private $fin;

    /**
     * @var bool
     */
    private $propagate;

    /**
     * MeasuresUpdateEvent constructor.
     *
     * @param Chronique   $chronique
     * @param Utilisateur $user
     * @param \DateTime   $debut
     * @param \DateTime   $fin
     * @param bool        $propagate
     */
    public function __construct(Chronique $chronique, Utilisateur $user, ?\DateTime $debut = null, ?\DateTime $fin = null, $propagate = false)
    {
        $this->chronique = $chronique;
        $this->user = $user;
        $this->debut = $debut;
        $this->fin = $fin;
        $this->propagate = $propagate;
    }

    /** Get chronique.
     * @return Chronique
     */
    public function getChronique()
    {
        return $this->chronique;
    }

    /** Get user.
     * @return Utilisateur
     */
    public function getUser()
    {
        return $this->user;
    }

    /** Get debut.
     * @return \DateTime
     */
    public function getDebut()
    {
        return $this->debut;
    }

    /** Get fin.
     * @return \DateTime
     */
    public function getFin()
    {
        return $this->fin;
    }

    /** Get propagate.
     * @return bool
     */
    public function doesPropagate()
    {
        return $this->propagate;
    }
}
