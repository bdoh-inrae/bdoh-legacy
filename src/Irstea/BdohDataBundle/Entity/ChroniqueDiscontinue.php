<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohDataBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

/**
 * Class ChroniqueDiscontinue.
 */
class ChroniqueDiscontinue extends Chronique
{
    /**
     * @var Collection
     */
    protected $plages;

    /**
     * @var Collection|Conversion[]
     */
    private $conversions;

    /**
     * ChroniqueDiscontinue constructor.
     */
    public function __construct()
    {
        $this->plages = new ArrayCollection();
        $this->conversions = new ArrayCollection();
    }

    /**
     * Add plages.
     *
     * @param Plage $plages
     */
    public function addPlage(Plage $plages)
    {
        $this->plages[] = $plages;
    }

    /**
     * Set plages.
     *
     * @param Collection $plages
     */
    public function setPlages(Collection $plages)
    {
        $this->plages = $plages;
    }

    /**
     * Get plages.
     *
     * @return Collection
     */
    public function getPlages()
    {
        return $this->plages;
    }

    /*
     * Whether this time series should be plotted with reverse Y axis
     *
     * @return bool
     */
    public function getReverseAxis()
    {
        return false;
    }

    /** Get transformations.
     * @return Collection|Conversion[]
     */
    public function getTransformations()
    {
        return $this->getConversions();
    }

    /** Get conversions.
     * @return Collection|Conversion[]
     */
    public function getConversions()
    {
        return $this->conversions;
    }

    /** Has children.
     * @return bool
     */
    public function hasChildren()
    {
        return !($this->conversions->isEmpty());
    }

    /**
     * {@inheritdoc}
     */
    public function isDiscontinue()
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function dtype()
    {
        return 'discontinue';
    }
}
