<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohDataBundle\Entity;

use Doctrine\Common\Collections\Collection;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

/**
 *FamilleParametres.
 */
#[UniqueEntity(fields: 'nom', message: 'FamilleParametres.nom.alreadyExists')]
#[UniqueEntity(fields: 'nomEn', message: 'FamilleParametres.nom.alreadyExists')]
// FamilleParametresRepository::findAllHierachy y accroche la profondeur de chaque famille (depth).
#[\AllowDynamicProperties]
class FamilleParametres
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
     * @var array
     */
    protected $nomEn;

    /**
     * @var string
     */
    protected $prefix;

    /**
     * @var FamilleParametres
     */
    protected $familleParente;

    /**
     * @var Collection
     */
    private $typesParametres;

    /**
     * @var Collection
     */
    private $famillesFilles;

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->typesParametres = new \Doctrine\Common\Collections\ArrayCollection();
        $this->famillesFilles = new \Doctrine\Common\Collections\ArrayCollection();
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
     * @return FamilleParametres
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
     * @return FamilleParametres
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
     * Set prefix.
     *
     * @param string $prefix
     *
     * @return FamilleParametres
     */
    public function setPrefix($prefix)
    {
        $this->prefix = $prefix;

        return $this;
    }

    /**
     * Get prefix.
     *
     * @return string
     */
    public function getPrefix()
    {
        return $this->prefix;
    }

    /**
     * Set familleParente.
     *
     * @param FamilleParametres $familleParente
     *
     * @return FamilleParametres
     */
    public function setFamilleParente($familleParente)
    {
        $this->familleParente = $familleParente;

        return $this;
    }

    /**
     * Get familleParente.
     *
     * @return FamilleParametres
     */
    public function getFamilleParente()
    {
        return $this->familleParente;
    }

    /**
     * Add typeParametre.
     *
     * @param TypeParametre $typeParametre
     *
     * @return FamilleParametres
     */
    public function addTypeParametre(TypeParametre $typeParametre)
    {
        $this->typesParametres[] = $typeParametre;

        return $this;
    }

    /**
     * Remove typeParametre.
     *
     * @param TypeParametre $typeParametre
     */
    public function removeTypeParametre(TypeParametre $typeParametre)
    {
        $this->typesParametres->removeElement($typeParametre);
    }

    /**
     * Get typesParametres.
     *
     * @return Collection
     */
    public function getTypesParametres()
    {
        return $this->typesParametres;
    }

    /**
     * Add familleFille.
     *
     * @param FamilleParametres $familleFille
     *
     * @return FamilleParametres
     */
    public function addFamilleFille(FamilleParametres $familleFille)
    {
        $this->famillesFilles[] = $familleFille;

        return $this;
    }

    /**
     * Remove familleFille.
     *
     * @param FamilleParametres $familleFille
     */
    public function removeFamilleFille(FamilleParametres $familleFille)
    {
        $this->famillesFilles->removeElement($familleFille);
    }

    /**
     * Get famillesFilles.
     *
     * @return Collection
     */
    public function getFamillesFilles()
    {
        return $this->famillesFilles;
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
}
