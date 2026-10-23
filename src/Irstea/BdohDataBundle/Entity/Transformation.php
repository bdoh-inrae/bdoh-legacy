<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohDataBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

/**
 * Class Transformation.
 */
class Transformation implements ChroniqueRelatedInterface
{
    use ChroniqueRelatedTrait;

    /**
     * @var int
     */
    protected $id;

    /**
     * @var JeuBareme|null
     */
    protected $jeuBaremeActuel;

    /**
     * @var Collection|JeuBareme[]
     */
    protected $jeuBaremesHistoriques;

    /**
     * @var ChroniqueContinue|null
     */
    private $entree;

    /**
     * @var ChroniqueCalculee|null
     */
    private $sortie;

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->jeuBaremesHistoriques = new ArrayCollection();
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
     * Set jeuBaremeActuel.
     *
     * @param JeuBareme|null $jeuBaremeActuel
     *
     * @return Transformation
     */
    public function setJeuBaremeActuel(?JeuBareme $jeuBaremeActuel = null)
    {
        $this->jeuBaremeActuel = $jeuBaremeActuel;

        return $this;
    }

    /**
     * Get jeuBaremeActuel.
     *
     * @return JeuBareme|null
     */
    public function getJeuBaremeActuel()
    {
        return $this->jeuBaremeActuel;
    }

    /**
     * Add jeuBaremesHistoriques.
     *
     * @param JeuBareme $jeuBaremesHistoriques
     *
     * @return Transformation
     */
    public function addJeuBaremesHistorique(JeuBareme $jeuBaremesHistoriques)
    {
        $this->jeuBaremesHistoriques[] = $jeuBaremesHistoriques;

        return $this;
    }

    /**
     * Get jeuBaremesHistoriques.
     *
     * @return Collection|JeuBareme[]
     */
    public function getJeuBaremesHistoriques()
    {
        return $this->jeuBaremesHistoriques;
    }

    /**
     * Set entree.
     *
     * @param ChroniqueContinue|null $entree
     *
     * @return Transformation
     */
    public function setEntree(?ChroniqueContinue $entree = null)
    {
        $this->entree = $entree;

        return $this;
    }

    /**
     * Get entree.
     *
     * @return ChroniqueContinue|null
     */
    public function getEntree()
    {
        return $this->entree;
    }

    /**
     * Set sortie.
     *
     * @param ChroniqueCalculee|null $sortie
     *
     * @return Transformation
     */
    public function setSortie($sortie = null)
    {
        $this->sortie = $sortie;

        return $this;
    }

    /**
     * Get sortie.
     *
     * @return ChroniqueCalculee|null
     */
    public function getSortie()
    {
        return $this->sortie;
    }

    /**
     * @return Chronique
     */
    public function getChronique()
    {
        return $this->getSortie();
    }
}
