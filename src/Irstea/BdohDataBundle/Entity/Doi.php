<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohDataBundle\Entity;

/**
 * Description of Doi.
 */
class Doi implements ObservatoireRelatedInterface
{
    /**
     * @var int
     */
    protected $id;

    /**
     * @var string
     */
    private $identifiant = '10.17180/';

    /**
     * @var string
     */
    private $description;

    /**
     * @var string
     */
    private $descriptionEn;

    /**
     * @var Observatoire
     */
    private $observatoire;

    /**
     * @return string
     */
    public function __toString()
    {
        return 'DOI ' . $this->identifiant;
    }

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param $identifiant
     */
    public function setIdentifiant($identifiant)
    {
        $this->identifiant = $identifiant;
    }

    /**
     * @return string
     */
    public function getIdentifiant()
    {
        return $this->identifiant;
    }

    /**
     * @param $description
     */
    public function setDescription($description)
    {
        $this->description = $description;
    }

    /**
     * @return string
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * @return string
     */
    public function getDescriptionEn()
    {
        return $this->descriptionEn;
    }

    /**
     * @param string $descriptionEn
     */
    public function setDescriptionEn($descriptionEn)
    {
        $this->descriptionEn = $descriptionEn;
    }

    /**
     * @param Observatoire|null $observatoire
     */
    public function setObservatoire(?Observatoire $observatoire = null)
    {
        $this->observatoire = $observatoire;
    }

    /**
     * @return Observatoire
     */
    public function getObservatoire()
    {
        return $this->observatoire;
    }
}
