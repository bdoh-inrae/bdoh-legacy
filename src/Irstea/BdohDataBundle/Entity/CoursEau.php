<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohDataBundle\Entity;

use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

/**
 * Class CoursEau.
 */
#[UniqueEntity(fields: ['nom', 'observatoire'], message: 'CoursEau.nom.alreadyExists')]
class CoursEau implements ObservatoireRelatedInterface
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
     * @var string
     */
    protected $codeHydro;

    /**
     * @var int
     */
    protected $classification;

    /**
     * @var geometry
     */
    protected $trace;

    /**
     * @var Observatoire
     */
    protected $observatoire;

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
     * @return CoursEau
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
     * Set codeHydro.
     *
     * @param string $codeHydro
     *
     * @return CoursEau
     */
    public function setCodeHydro($codeHydro)
    {
        $this->codeHydro = $codeHydro;

        return $this;
    }

    /**
     * Get codeHydro.
     *
     * @return string
     */
    public function getCodeHydro()
    {
        return $this->codeHydro;
    }

    /**
     * Set classification.
     *
     * @param int $classification
     *
     * @return CoursEau
     */
    public function setClassification($classification)
    {
        $this->classification = $classification;

        return $this;
    }

    /**
     * Get classification.
     *
     * @return int
     */
    public function getClassification()
    {
        return $this->classification;
    }

    /**
     * Set trace.
     *
     * @param geometry $trace
     *
     * @return CoursEau
     */
    public function setTrace($trace)
    {
        $this->trace = $trace;

        return $this;
    }

    /**
     * Get trace.
     *
     * @return geometry
     */
    public function getTrace()
    {
        return $this->trace;
    }

    /**
     * Set observatoire.
     *
     * @param Observatoire $observatoire
     *
     * @return CoursEau
     */
    public function setObservatoire($observatoire)
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
     * @return string
     */
    public function __toString()
    {
        return $this->nom;
    }
}
