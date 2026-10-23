<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohDataBundle\Entity;

use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

/**
 * Class Bassin.
 */
#[UniqueEntity(fields: ['nom', 'observatoire'], message: 'Bassin.nom.alreadyExists')]
class Bassin implements ObservatoireRelatedInterface
{
    /**
     * @var int
     */
    protected $id;

    /**
     * @var string
     */
    protected $nom;

    /**
     * @var float
     */
    protected $aire;

    /**
     * @var geometry
     */
    protected $perimetre;

    /**
     * @var Observatoire
     */
    protected $observatoire;

    /**
     * @var Station
     */
    protected $stationExutoire;

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
     * Set nom.
     *
     * @param string $nom
     *
     * @return Bassin
     */
    public function setNom($nom)
    {
        $this->nom = $nom;

        return $this;
    }

    /**
     * Get nom.
     *
     * @return string
     */
    public function getNom()
    {
        return $this->nom;
    }

    /**
     * Set aire.
     *
     * @param float $aire
     *
     * @return Bassin
     */
    public function setAire($aire)
    {
        $this->aire = $aire;

        return $this;
    }

    /**
     * Get aire.
     *
     * @return float
     */
    public function getAire()
    {
        return $this->aire;
    }

    /**
     * Set perimetre.
     *
     * @param geometry $perimetre
     *
     * @return Bassin
     */
    public function setPerimetre($perimetre)
    {
        $this->perimetre = $perimetre;

        return $this;
    }

    /**
     * Get perimetre.
     *
     * @return geometry
     */
    public function getPerimetre()
    {
        return $this->perimetre;
    }

    /**
     * Set observatoire.
     *
     * @param Observatoire $observatoire
     *
     * @return Bassin
     */
    public function setObservatoire(?Observatoire $observatoire = null)
    {
        $this->observatoire = $observatoire;

        return $this;
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
     * Set stationExutoire.
     *
     * @param Station $stationExutoire
     *
     * @return Bassin
     */
    public function setStationExutoire(?Station $stationExutoire = null)
    {
        $this->stationExutoire = $stationExutoire;

        return $this;
    }

    /**
     * Get stationExutoire.
     *
     * @return Station
     */
    public function getStationExutoire()
    {
        return $this->stationExutoire;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return $this->nom;
    }
}
