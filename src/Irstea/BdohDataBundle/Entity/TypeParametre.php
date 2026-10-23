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
 *TypeParametre.
 */
#[UniqueEntity(fields: 'nom')]
#[UniqueEntity(fields: 'nomEn')]
class TypeParametre
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
    protected $nomEn;

    /**
     * @var string
     */
    protected $code;

    /**
     * @var FamilleParametres
     */
    protected $familleParametres;

    /**
     * @var Collection
     */
    protected $unites;

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->unites = new ArrayCollection();
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
     * Set nom.
     *
     * @param string $nom
     *
     * @return TypeParametre
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
     * Set nomEn.
     *
     * @param string $nomEn
     *
     * @return TypeParametre
     */
    public function setNomEn($nomEn)
    {
        $this->nomEn = $nomEn;

        return $this;
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
     * Set code.
     *
     * @param string $code
     *
     * @return TypeParametre
     */
    public function setCode($code)
    {
        $this->code = $code;

        return $this;
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
     * Set familleParametres.
     *
     * @param FamilleParametres $familleParametres
     *
     * @return TypeParametre
     */
    public function setFamilleParametres($familleParametres)
    {
        $this->familleParametres = $familleParametres;

        return $this;
    }

    /**
     * Get familleParametres.
     *
     * @return FamilleParametres
     */
    public function getFamilleParametres()
    {
        return $this->familleParametres;
    }

    /**
     * Add unites.
     *
     * @param Unite $unites
     *
     * @return TypeParametre
     */
    public function addUnite(Unite $unites)
    {
        $this->unites[] = $unites;

        return $this;
    }

    /**
     * Remove unites.
     *
     * @param Unite $unites
     */
    public function removeUnite(Unite $unites)
    {
        $this->unites->removeElement($unites);
    }

    /**
     * Set unites.
     *
     * @param Collection $unites
     *
     * @return TypeParametre
     */
    public function setUnites(Collection $unites)
    {
        $this->unites = $unites;

        return $this;
    }

    /**
     * Get unites.
     *
     * @return Collection
     */
    public function getUnites()
    {
        return $this->unites;
    }

    /**
     * To String.
     *
     * @return string
     */
    public function __toString()
    {
        return $this->getNom() ?: '';
    }

    /**
     * @var Collection
     */
    protected $chroniques;

    /**
     * Add chroniques.
     *
     * @param Chronique $chroniques
     *
     * @return TypeParametre
     */
    public function addChronique(Chronique $chroniques)
    {
        $this->chroniques[] = $chroniques;

        return $this;
    }

    /**
     * Remove chroniques.
     *
     * @param Chronique $chroniques
     */
    public function removeChronique(Chronique $chroniques)
    {
        $this->chroniques->removeElement($chroniques);
    }

    /**
     * Get chroniques.
     *
     * @return Collection
     */
    public function getChroniques()
    {
        return $this->chroniques;
    }
}
