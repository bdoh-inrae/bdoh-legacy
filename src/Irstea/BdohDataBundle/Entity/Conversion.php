<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohDataBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

/**
 * Class Conversion.
 */
class Conversion
{
    /**
     * @var int
     */
    protected $id;

    /**
     * @var JeuConversion
     */
    protected $jeuConversionActuel;

    /**
     * @var Collection|JeuConversion[]
     */
    protected $jeuConversionsHistoriques;

    /**
     * @var ChroniqueDiscontinue
     */
    private $entree;

    /**
     * @var ChroniqueConvertie
     */
    private $sortie;

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->jeuConversionsHistoriques = new ArrayCollection();
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
     * Set jeuConversionActuel.
     *
     * @param JeuConversion $jeuConversion
     *
     * @return Conversion
     */
    public function setJeuConversionActuel(JeuConversion $jeuConversion)
    {
        $this->jeuConversionActuel = $jeuConversion;

        return $this;
    }

    /**
     * Get jeuConversionActuel.
     *
     * @return JeuConversion
     */
    public function getJeuConversionActuel()
    {
        return $this->jeuConversionActuel;
    }

    /**
     * Add jeuConversionsHistoriques.
     *
     * @param JeuConversion $jeuConversion
     *
     * @return Conversion
     */
    public function addJeuConversionsHistoriques(JeuConversion $jeuConversion)
    {
        $this->jeuConversionsHistoriques[] = $jeuConversion;

        return $this;
    }

    /**
     * Get jeuConversionsHistoriques.
     *
     * @return Collection|JeuConversion[]
     */
    public function getJeuConversionsHistoriques()
    {
        return $this->jeuConversionsHistoriques;
    }

    /**
     * Set entree.
     *
     * @param ChroniqueDiscontinue $entree
     *
     * @return Conversion
     */
    public function setEntree(ChroniqueDiscontinue $entree)
    {
        $this->entree = $entree;

        return $this;
    }

    /**
     * Get entree.
     *
     * @return ChroniqueDiscontinue
     */
    public function getEntree()
    {
        return $this->entree;
    }

    /**
     * Set sortie.
     *
     * @param ChroniqueConvertie $sortie
     *
     * @return Conversion
     */
    public function setSortie(ChroniqueConvertie $sortie)
    {
        $this->sortie = $sortie;

        return $this;
    }

    /**
     * Get sortie.
     *
     * @return ChroniqueConvertie
     */
    public function getSortie()
    {
        return $this->sortie;
    }
}
