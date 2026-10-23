<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohDataBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

/**
 * Unite.
 */
#[UniqueEntity(fields: 'libelle')]
class Unite
{
    /**
     * @var int
     */
    protected $id;

    /**
     * @var string
     */
    protected $libelle;

    /**
     * @var string
     */
    protected $libelleEn;

    /**
     * @var Collection
     */
    protected $parametres;

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->parametres = new ArrayCollection();
    }

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
     * Set libelle.
     *
     * @param string $libelle
     *
     * @return Unite
     */
    public function setLibelle($libelle)
    {
        $this->libelle = $libelle;

        return $this;
    }

    /**
     * Get libelle.
     *
     * @return string
     */
    public function getLibelle()
    {
        return $this->libelle;
    }

    /**
     * @return string
     */
    public function getLibelleEn()
    {
        return $this->libelleEn;
    }

    /**
     * @param string $libelleEn
     *
     * @return Unite
     */
    public function setLibelleEn($libelleEn)
    {
        $this->libelleEn = $libelleEn;

        return $this;
    }

    /**
     * Add parametres.
     *
     * @param TypeParametre $parametres
     *
     * @return Unite
     */
    public function addParametre(TypeParametre $parametres)
    {
        $this->parametres[] = $parametres;

        return $this;
    }

    /**
     * Remove parametres.
     *
     * @param TypeParametre $parametres
     */
    public function removeParametre(TypeParametre $parametres)
    {
        $this->parametres->removeElement($parametres);
    }

    /**
     * Set parametres.
     *
     * @param Collection $parametres
     *
     * @return Unite
     */
    public function setParametres(Collection $parametres)
    {
        $this->parametres = $parametres;

        return $this;
    }

    /**
     * Get parametres.
     *
     * @return Collection
     */
    public function getParametres()
    {
        return $this->parametres;
    }

    /**
     * To String.
     *
     * @return string
     */
    public function __toString()
    {
        return $this->getLibelle() ?: '';
    }
}
