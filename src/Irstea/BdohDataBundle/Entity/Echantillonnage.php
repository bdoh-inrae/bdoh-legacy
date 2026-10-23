<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohDataBundle\Entity;

use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

/**
 * Irstea\BdohDataBundle\Entity\Echantillonnage.
 */
#[UniqueEntity(fields: 'nom')]
class Echantillonnage
{
    /**
     * @var int
     */
    protected $id;

    /**
     * @var string
     */
    protected $code;

    /**
     * @var string
     */
    protected $nom;

    /**
     * @var string
     */
    protected $nomEn;

    /**
     * @var int
     */
    protected $ordreSortie;

    /**
     * @var bool
     */
    protected $hasDirection;

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
     * Get nom.
     *
     * @return string
     */
    public function getNom()
    {
        return $this->nom;
    }

    /**
     * Get ordreSortie.
     *
     * @return int
     */
    public function getOrdreSortie()
    {
        return $this->ordreSortie;
    }

    /**
     * Get hasDirection.
     *
     * @return bool
     */
    public function getHasDirection()
    {
        return $this->hasDirection;
    }

    /**
     * To String.
     *
     * @return string
     */
    public function __toString()
    {
        return $this->getNom();
    }

    /**
     * Get nomEn.
     *
     * @return string
     */
    public function getNomEn()
    {
        return $this->nomEn;
    }

    /**
     * Get code.
     *
     * @return string
     */
    public function getCode()
    {
        return $this->code;
    }

    /**
     * Set code.
     *
     * @param string $code
     *
     * @return Echantillonnage
     */
    public function setCode($code)
    {
        $this->code = $code;

        return $this;
    }

    /**
     * Set nom.
     *
     * @param string $nom
     *
     * @return Echantillonnage
     */
    public function setNom($nom)
    {
        $this->nom = $nom;

        return $this;
    }

    /**
     * Set nomEn.
     *
     * @param string $nomEn
     *
     * @return Echantillonnage
     */
    public function setNomEn($nomEn)
    {
        $this->nomEn = $nomEn;

        return $this;
    }

    /**
     * Set ordreSortie.
     *
     * @param int $ordreSortie
     *
     * @return Echantillonnage
     */
    public function setOrdreSortie($ordreSortie)
    {
        $this->ordreSortie = $ordreSortie;

        return $this;
    }

    /**
     * Set hasDirection.
     *
     * @param bool $hasDirection
     *
     * @return Echantillonnage
     */
    public function setHasDirection($hasDirection)
    {
        $this->hasDirection = $hasDirection;

        return $this;
    }
}
