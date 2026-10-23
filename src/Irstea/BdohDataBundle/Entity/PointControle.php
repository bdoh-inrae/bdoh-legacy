<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohDataBundle\Entity;

/**
 * PointControle
 * WARNING : the linked 'chroniqueContinue' will be loaded eagerly
 * {@link http://docs.doctrine-project.org/projects/doctrine-orm/en/2.0.x/reference/inheritance-mapping.html#performance-impact
 *        See Doctrine2 documentation for more information.}.
 */
class PointControle implements ChroniqueRelatedInterface
{
    use ChroniqueRelatedTrait;

    /**
     * @var int
     */
    protected $id;

    /**
     * @var string
     */
    protected $date;

    /**
     * @var float
     */
    protected $valeur;

    /**
     * @var float
     */
    protected $minimum;

    /**
     * @var float
     */
    protected $maximum;

    /**
     * @var ChroniqueContinue
     */
    protected $chronique;

    /**
     * @var Qualite
     */
    protected $qualite;

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
     * Set date.
     *
     * @param string $date
     */
    public function setDate($date)
    {
        $this->date = $date;
    }

    /**
     * Get date.
     *
     * @return string
     */
    public function getDate()
    {
        return $this->date;
    }

    /**
     * Set valeur.
     *
     * @param float $valeur
     */
    public function setValeur($valeur)
    {
        $this->valeur = $valeur;
    }

    /**
     * Get valeur.
     *
     * @return float
     */
    public function getValeur()
    {
        return $this->valeur;
    }

    /**
     * Set minimum.
     *
     * @param float $minimum
     */
    public function setMinimum($minimum)
    {
        $this->minimum = $minimum;
    }

    /**
     * Get minimum.
     *
     * @return float
     */
    public function getMinimum()
    {
        return $this->minimum;
    }

    /**
     * Set maximum.
     *
     * @param float $maximum
     */
    public function setMaximum($maximum)
    {
        $this->maximum = $maximum;
    }

    /**
     * Get maximum.
     *
     * @return float
     */
    public function getMaximum()
    {
        return $this->maximum;
    }

    /**
     * Set chronique.
     *
     * @param ChroniqueContinue $chronique
     */
    public function setChronique(ChroniqueContinue $chronique)
    {
        $this->chronique = $chronique;
    }

    /**
     * Get chronique.
     *
     * @return ChroniqueContinue
     */
    public function getChronique()
    {
        return $this->chronique;
    }

    /**
     * Set qualite.
     *
     * @param Qualite $qualite
     */
    public function setQualite(?Qualite $qualite = null)
    {
        $this->qualite = $qualite;
    }

    /**
     * Get qualite.
     *
     * @return Qualite
     */
    public function getQualite()
    {
        return $this->qualite;
    }
}
