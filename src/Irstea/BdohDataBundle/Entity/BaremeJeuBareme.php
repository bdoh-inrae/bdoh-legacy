<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohDataBundle\Entity;

/**
 * Class BaremeJeuBareme.
 */
class BaremeJeuBareme
{
    /**
     * @var int
     */
    protected $id;

    /**
     * @var \Datetime
     */
    protected $debutValidite;

    /**
     * @var \Datetime
     */
    protected $finValidite;

    /**
     * @var Bareme
     */
    protected $bareme;

    /**
     * @var JeuBareme
     */
    protected $jeuBareme;

    /**
     * Get id.
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set debutValidite.
     *
     * @param \Datetime
     * @param mixed $debutValidite
     *
     * @return BaremeJeuBareme
     */
    public function setDebutValidite($debutValidite)
    {
        $this->debutValidite = $debutValidite;

        return $this;
    }

    /**
     * Get debutValidite.
     *
     * @return \Datetime
     */
    public function getDebutValidite()
    {
        return $this->debutValidite;
    }

    /**
     * Set finValidite.
     *
     * @param \Datetime
     * @param mixed $finValidite
     *
     * @return BaremeJeuBareme
     */
    public function setFinValidite($finValidite)
    {
        $this->finValidite = $finValidite;

        return $this;
    }

    /**
     * Get finValidite.
     *
     * @return \Datetime
     */
    public function getFinValidite()
    {
        return $this->finValidite;
    }

    /**
     * Set bareme.
     *
     * @param Bareme $bareme
     *
     * @return BaremeJeuBareme
     */
    public function setBareme(?Bareme $bareme = null)
    {
        $this->bareme = $bareme;

        return $this;
    }

    /**
     * Get bareme.
     *
     * @return Bareme
     */
    public function getBareme()
    {
        return $this->bareme;
    }

    /**
     * Set jeuBareme.
     *
     * @param JeuBareme $jeuBareme
     *
     * @return BaremeJeuBareme
     */
    public function setJeuBareme(?JeuBareme $jeuBareme = null)
    {
        $this->jeuBareme = $jeuBareme;

        return $this;
    }

    /**
     * Get jeuBareme.
     *
     * @return JeuBareme
     */
    public function getJeuBareme()
    {
        return $this->jeuBareme;
    }

    /**
     * @param self $bjb
     *
     * @return bool
     */
    public function equals(self $bjb)
    {
        if (!$bjb) {
            return false;
        }

        if (!$this->getBareme()) {
            $equalBaremes = (!$bjb->getBareme());
        } else {
            $equalBaremes = ($this->getBareme()->getId() === $bjb->getBareme()->getId());
        }

        if (!$this->getDebutValidite()) {
            $equalBeginnings = (!$bjb->getDebutValidite());
        } else {
            $equalBeginnings = ($this->getDebutValidite()->format('Y-m-d H:i:s') === $bjb->getDebutValidite()->format('Y-m-d H:i:s'));
        }

        if (!$this->getFinValidite()) {
            $equalEnds = (!$bjb->getFinValidite());
        } else {
            if (!$bjb->getFinValidite()) {
                $equalEnds = false;
            } else {
                $equalEnds = ($this->getFinValidite()->format('Y-m-d H:i:s') === $bjb->getFinValidite()->format('Y-m-d H:i:s'));
            }
        }

        return $equalBaremes && $equalBeginnings && $equalEnds;
    }
}
