<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohDataBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

/**
 * Class ChroniqueContinue.
 */
class ChroniqueContinue extends Chronique
{
    /**
     * @var Collection|PointControle[]
     */
    protected $pointsControle;

    /**
     * @var Collection|Mesure[]
     */
    protected $mesures;

    /**
     * @var Echantillonnage
     */
    protected $echantillonnage;

    /**
     * @var Collection|TauxRemplissage[]
     */
    protected $tauxRemplissage;

    /**
     * @var string
     */
    protected $directionMesure;

    /**
     * @var Unite
     */
    protected $uniteCumul;

    /**
     * @var Collection|Echantillonnage[]
     */
    protected $echantillonnagesSortieLicites;

    /**
     * @var Collection|Echantillonnage[]
     */
    protected $optionsEchantillonnageSortie;

    /**
     * @var Collection|Transformation[]
     */
    private $transformations;

    /**
     * ChroniqueContinue constructor.
     */
    public function __construct()
    {
        $this->pointsControle = new ArrayCollection();
        $this->mesures = new ArrayCollection();
        $this->tauxRemplissage = new ArrayCollection();
        $this->echantillonnagesSortieLicites = new ArrayCollection();
        $this->optionsEchantillonnageSortie = new ArrayCollection();
        $this->transformations = new ArrayCollection();
    }

    /**
     * Add pointsControle.
     *
     * @param PointControle $pointsControle
     */
    public function addPointControle(PointControle $pointsControle)
    {
        $this->pointsControle[] = $pointsControle;
    }

    /**
     * Set pointsControle.
     *
     * @param ArrayCollection $pointsControle
     */
    public function setPointsControle(ArrayCollection $pointsControle)
    {
        $this->pointsControle = $pointsControle;
    }

    /**
     * Get pointsControle.
     *
     * @return Collection
     */
    public function getPointsControle()
    {
        return $this->pointsControle;
    }

    /**
     * Add mesures.
     *
     * @param Mesure $mesures
     */
    public function addMesure(Mesure $mesures)
    {
        $this->mesures[] = $mesures;
    }

    /**
     * Set mesures.
     *
     * @param ArrayCollection $mesures
     */
    public function setMesures(ArrayCollection $mesures)
    {
        $this->mesures = $mesures;
    }

    /**
     * Get mesures.
     *
     * @return Collection
     */
    public function getMesures()
    {
        return $this->mesures;
    }

    /**
     * Set echantillonnage.
     *
     * @param Echantillonnage $echantillonnage
     */
    public function setEchantillonnage(Echantillonnage $echantillonnage)
    {
        $this->echantillonnage = $echantillonnage;
    }

    /**
     * Get echantillonnage.
     *
     * @return Echantillonnage
     */
    public function getEchantillonnage()
    {
        return $this->echantillonnage;
    }

    /**
     * Add tauxRemplissage.
     *
     * @param TauxRemplissage $tauxRemplissage
     */
    public function addTauxRemplissage(TauxRemplissage $tauxRemplissage)
    {
        $this->tauxRemplissage[] = $tauxRemplissage;
    }

    /**
     * Set tauxRemplissage.
     *
     * @param ArrayCollection $tauxRemplissage
     */
    public function setTauxRemplissage(ArrayCollection $tauxRemplissage)
    {
        $this->tauxRemplissage = $tauxRemplissage;
    }

    /**
     * Get tauxRemplissage.
     *
     * @return Collection
     */
    public function getTauxRemplissage()
    {
        return $this->tauxRemplissage;
    }

    /**
     * Set directionMesure.
     *
     * @param string $directionMesure
     *
     * @return ChroniqueContinue
     */
    public function setDirectionMesure($directionMesure)
    {
        $this->directionMesure = $directionMesure;

        return $this;
    }

    /**
     * Get directionMesure.
     *
     * @return string
     */
    public function getDirectionMesure()
    {
        return $this->directionMesure;
    }

    /**
     * Set uniteCumul.
     *
     * @param Unite|null $uniteCumul
     */
    public function setUniteCumul(?Unite $uniteCumul = null)
    {
        if ($uniteCumul) {
            $this->uniteCumul = $uniteCumul;
        }
    }

    /**
     * Get uniteCumul.
     *
     * @return Unite
     */
    public function getUniteCumul()
    {
        return $this->uniteCumul;
    }

    /*
     * Whether this time series should be plotted with reverse Y axis
     *
     * @return bool
     */
    public function getReverseAxis()
    {
        return ($param = $this->getParametre()) && ($param->getCode() === 'PRCP');
    }

    /**
     * Add pointsControle.
     *
     * @param PointControle $pointsControle
     *
     * @return ChroniqueContinue
     */
    public function addPointsControle(PointControle $pointsControle)
    {
        $this->pointsControle[] = $pointsControle;

        return $this;
    }

    /**
     * Remove pointsControle.
     *
     * @param PointControle $pointsControle
     */
    public function removePointsControle(PointControle $pointsControle)
    {
        $this->pointsControle->removeElement($pointsControle);
    }

    /**
     * Remove mesures.
     *
     * @param Mesure $mesures
     */
    public function removeMesure(Mesure $mesures)
    {
        $this->mesures->removeElement($mesures);
    }

    /**
     * Remove tauxRemplissage.
     *
     * @param TauxRemplissage $tauxRemplissage
     */
    public function removeTauxRemplissage(TauxRemplissage $tauxRemplissage)
    {
        $this->tauxRemplissage->removeElement($tauxRemplissage);
    }

    /**
     * Add echantillonnagesSortieLicites.
     *
     * @param Echantillonnage $echantillonnagesSortieLicites
     *
     * @return ChroniqueContinue
     */
    public function addEchantillonnagesSortieLicite(Echantillonnage $echantillonnagesSortieLicites)
    {
        $this->echantillonnagesSortieLicites[] = $echantillonnagesSortieLicites;

        return $this;
    }

    /**
     * Set $echantillonnagesSortieLicites.
     *
     * @param Collection $echantillonnagesSortieLicites
     */
    public function setEchantillonnagesSortieLicite(Collection $echantillonnagesSortieLicites)
    {
        $this->echantillonnagesSortieLicites[] = $echantillonnagesSortieLicites;
    }

    /**
     * Remove echantillonnagesSortieLicites.
     *
     * @param Echantillonnage $echantillonnagesSortieLicites
     */
    public function removeEchantillonnagesSortieLicite(Echantillonnage $echantillonnagesSortieLicites)
    {
        $this->echantillonnagesSortieLicites->removeElement($echantillonnagesSortieLicites);
    }

    /**
     * Get echantillonnagesSortieLicites.
     *
     * @return Collection
     */
    public function getEchantillonnagesSortieLicites()
    {
        return $this->echantillonnagesSortieLicites;
    }

    /**
     * Add optionsEchantillonnageSortie.
     *
     * @param OptionEchantillonnageSortie $optionsEchantillonnageSortie
     *
     * @return ChroniqueContinue
     */
    public function addOptionsEchantillonnageSortie(OptionEchantillonnageSortie $optionsEchantillonnageSortie)
    {
        $this->optionsEchantillonnageSortie[] = $optionsEchantillonnageSortie;

        return $this;
    }

    /**
     * Set $optionsEchantillonnageSortie.
     *
     * @param ArrayCollection $optionsEchantillonnageSortie
     */
    public function setOptionsEchantillonnageSortie(Collection $optionsEchantillonnageSortie)
    {
        $this->optionsEchantillonnageSortie[] = $optionsEchantillonnageSortie;
    }

    /**
     * Remove optionsEchantillonnageSortie.
     *
     * @param OptionEchantillonnageSortie $optionsEchantillonnageSortie
     */
    public function removeOptionsEchantillonnageSortie(OptionEchantillonnageSortie $optionsEchantillonnageSortie)
    {
        $this->optionsEchantillonnageSortie->removeElement($optionsEchantillonnageSortie);
    }

    /**
     * Get optionsEchantillonnageSortie.
     *
     * @return Collection
     */
    public function getOptionsEchantillonnageSortie()
    {
        return $this->optionsEchantillonnageSortie;
    }

    /** Get transformations.
     * @return Collection|Transformation[]
     */
    public function getTransformations()
    {
        return $this->transformations;
    }

    /** Has children.
     * @return bool
     */
    public function hasChildren()
    {
        return !($this->transformations->isEmpty());
    }

    /**
     * @return bool
     */
    public function getMustEditExportOptions()
    {
        return !$this->getEchantillonageSet()
            && \count($this->getEchantillonnagesSortieLicites()) === 0
            && \count($this->getOptionsEchantillonnageSortie()) === 0;
    }

    /**
     * {@inheritdoc}
     */
    public function isContinue()
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function dtype()
    {
        return 'continue';
    }
}
