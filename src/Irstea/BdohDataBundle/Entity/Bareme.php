<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohDataBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

/**
 * Bareme.
 */
class Bareme implements ObservatoireRelatedInterface
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
    protected $commentaire;

    /**
     * @var bareme
     */
    protected $valeurs;

    /**
     * @var Unite
     */
    protected $uniteEntree;

    /**
     * @var Unite
     */
    protected $uniteSortie;

    /**
     * @var \DateTime
     */
    protected $dateCreation;

    /**
     * @var Observatoire
     */
    protected $observatoire;

    /**
     * @var Collection
     */
    protected $baremeJeuBaremes;

    public function __construct()
    {
        $this->baremeJeuBaremes = new ArrayCollection();
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
     * @return Bareme
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
     * Set commentaire.
     *
     * @param string $commentaire
     *
     * @return Bareme
     */
    public function setCommentaire($commentaire)
    {
        $this->commentaire = $commentaire;

        return $this;
    }

    /**
     * Get commentaire.
     *
     * @return string
     */
    public function getCommentaire()
    {
        return $this->commentaire;
    }

    /**
     * Set valeurs.
     *
     * @param bareme $valeurs
     *
     * @return Bareme
     */
    public function setValeurs($valeurs)
    {
        $this->valeurs = $valeurs;

        return $this;
    }

    /**
     * Get valeurs.
     *
     * @return bareme
     */
    public function getValeurs()
    {
        return $this->valeurs;
    }

    /**
     * Set uniteEntree.
     *
     * @param Unite $uniteEntree
     *
     * @return Bareme
     */
    public function setUniteEntree(?Unite $uniteEntree = null)
    {
        $this->uniteEntree = $uniteEntree;

        return $this;
    }

    /**
     * Get uniteEntree.
     *
     * @return Unite
     */
    public function getUniteEntree()
    {
        return $this->uniteEntree;
    }

    /**
     * Set uniteSortie.
     *
     * @param Unite $uniteSortie
     *
     * @return Bareme
     */
    public function setUniteSortie(?Unite $uniteSortie = null)
    {
        $this->uniteSortie = $uniteSortie;

        return $this;
    }

    /**
     * Get uniteSortie.
     *
     * @return Unite
     */
    public function getUniteSortie()
    {
        return $this->uniteSortie;
    }

    /**
     * @return \DateTime
     */
    public function getDateCreation()
    {
        return $this->dateCreation;
    }

    /**
     * @param \DateTime
     * @param mixed $dateCreation
     *
     * @return Bareme
     */
    public function setDateCreation($dateCreation)
    {
        $this->dateCreation = $dateCreation;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getObservatoire()
    {
        return $this->observatoire;
    }

    /**
     * @param type Observatoire
     * @param mixed $observatoire
     *
     * @return Bareme
     */
    public function setObservatoire($observatoire)
    {
        $this->observatoire = $observatoire;

        return $this;
    }

    /**
     * @return ArrayCollection|Collection
     */
    public function getBaremeJeuBaremes()
    {
        return $this->baremeJeuBaremes;
    }

    /**
     * @return bool
     */
    public function isOrphan()
    {
        return $this->baremeJeuBaremes->count() === 0;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return $this->nom;
    }
}
