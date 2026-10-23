<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohDataBundle\Entity;

/**
 * Class Plage.
 */
class Plage
{
    /**
     * @var int
     */
    protected $id;

    /**
     * @var string
     */
    protected $debut;

    /**
     * @var string
     */
    protected $fin;

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
     * @var Qualite
     */
    protected $qualite;

    /**
     * @var ChroniqueDiscontinue
     */
    protected $chronique;

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
     * Set debut.
     *
     * @param string $debut
     */
    public function setDebut($debut)
    {
        $this->debut = $debut;
    }

    /**
     * Get debut.
     *
     * @return string
     */
    public function getDebut()
    {
        return $this->debut;
    }

    /**
     * Set fin.
     *
     * @param string $fin
     */
    public function setFin($fin)
    {
        $this->fin = $fin;
    }

    /**
     * Get fin.
     *
     * @return string
     */
    public function getFin()
    {
        return $this->fin;
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
     * Set qualite.
     *
     * @param Qualite $qualite
     */
    public function setQualite(Qualite $qualite)
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

    /**
     * Set chronique.
     *
     * @param ChroniqueDiscontinue $chronique
     */
    public function setChronique(ChroniqueDiscontinue $chronique)
    {
        $this->chronique = $chronique;
    }

    /**
     * Get chronique.
     *
     * @return ChroniqueDiscontinue
     */
    public function getChronique()
    {
        return $this->chronique;
    }
}
