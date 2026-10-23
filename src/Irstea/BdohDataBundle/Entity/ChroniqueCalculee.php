<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohDataBundle\Entity;

/**
 * Class ChroniqueCalculee.
 */
class ChroniqueCalculee extends ChroniqueContinue
{
    /**
     * @var float
     */
    protected $facteurMultiplicatif = 1.0;

    /**
     * @var \DateTime
     */
    protected $miseAJour;

    /**
     * @var Transformation|null
     */
    protected $premiereEntree;

    /**
     * @var Transformation|null
     */
    protected $secondeEntree;

    /**
     * Set facteurMultiplicatif.
     *
     * @param float $facteurMultiplicatif
     */
    public function setFacteurMultiplicatif($facteurMultiplicatif)
    {
        $this->facteurMultiplicatif = $facteurMultiplicatif;
    }

    /**
     * Get facteurMultiplicatif.
     *
     * @return float
     */
    public function getFacteurMultiplicatif()
    {
        return $this->facteurMultiplicatif;
    }

    /**
     * @return \DateTime
     */
    public function getMiseAJour()
    {
        return $this->miseAJour;
    }

    /**
     * @param \DateTime $miseAJour
     *
     * @return ChroniqueCalculee
     */
    public function setMiseAJour($miseAJour)
    {
        $this->miseAJour = $miseAJour;

        return $this;
    }

    /**
     * Get premiereEntree.
     *
     * @return Transformation|null
     */
    public function getPremiereEntree()
    {
        return $this->premiereEntree;
    }

    /**
     * Set premiereEntree.
     *
     * @param Transformation|null $transformation
     */
    public function setPremiereEntree(?Transformation $transformation = null)
    {
        $this->premiereEntree = $transformation;
        if ($transformation) {
            $transformation->setSortie($this);
        }
    }

    /**
     * Get secondeEntree.
     *
     * @return Transformation|null
     */
    public function getSecondeEntree()
    {
        return $this->secondeEntree;
    }

    /**
     * Set secondeEntree.
     *
     * @param Transformation|null $transformation
     */
    public function setSecondeEntree(?Transformation $transformation = null)
    {
        $this->secondeEntree = $transformation;
        if ($transformation) {
            $transformation->setSortie($this);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function isCalculee()
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function dtype()
    {
        return 'calculee';
    }
}
