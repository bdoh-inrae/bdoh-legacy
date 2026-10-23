<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohDataBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Irstea\BdohBundle\Model\LabelledEntityInterface;
use Irstea\BdohBundle\Model\LabelledEntityTrait;
use Irstea\BdohDataBundle\IrsteaBdohDataBundle;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

/**
 * Chronique.
 *
 * @SuppressWarnings(PHPMD.TooManyFields)
 */
#[UniqueEntity(fields: ['code', 'station'], message: 'Chronique.code.alreadyExists', repositoryMethod: 'findAnyByStationAndCode')]
// Les pages de recherche y accrochent des données calculées (bassins, coursEaux, familles).
#[\AllowDynamicProperties]
abstract class Chronique implements ChroniqueRelatedInterface, LabelledEntityInterface
{
    use ChroniqueRelatedTrait;
    use LabelledEntityTrait;

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
    protected $genealogie;

    /**
     * @var string
     */
    protected $genealogieEn;

    /**
     * @var string
     */
    protected $description;

    /**
     * @var bool
     */
    protected $estVisible = true;

    /**
     * @var float
     */
    protected $minimumValide;

    /**
     * @var float
     */
    protected $maximumValide;

    /**
     * @var string
     */
    protected $dateDebutMesures;

    /**
     * @var string
     */
    protected $dateFinMesures;

    /**
     * @var int
     */
    protected $nbMesures;

    /**
     * @var Unite
     */
    protected $unite;

    /**
     * @var TypeParametre
     */
    protected $parametre;

    /**
     * @var Station
     */
    protected $station;

    /**
     * @var Partenaire
     */
    protected $producteur;

    /**
     * @var bool
     */
    protected $echantillonageSet = false;

    /**
     * @var bool
     */
    protected $allowValueLimits = false;

    /**
     * @var DataSet|null
     */
    protected $dataset;

    /**
     * @var Milieu
     */
    protected $milieu;

    //--------------------------------------ctor------------------------------------------------------------

    //-----------------------------getter et setter------------------------------------------------------

    /**
     * Get id.
     *
     * @return string
     */
    public function getNomLong()
    {
        $code = $this->getCode();
        $unite = $this->getUnite();
        $station = $this->getStation();
        $parametre = $this->getParametre();
        $separator = ',';
        $tableauSite = $this->getStation()->getSites()->toArray();
        $bool = is_array($tableauSite);
        if ($bool === true) {
            $tableauSite = implode($separator, $tableauSite);
        }
        $dateBeg = $this->getDateDebutMesures();
        if ($dateBeg === null) {
            $dateBeg = 'NA';
        }
        $dateFin = $this->getDateFinMesures();
        if ($dateFin === null) {
            $dateFin = 'NA';
        }
        $nomLong = 'Site(s) :  ' . $tableauSite . ' , Station : '
            . $station . '  ,  Paramètre : ' . $parametre . '   , Unité : ' . $unite . ' , Code : ' . $code
            . ' ( Date Début : ' . $dateBeg . ' , Date Fin : ' . $dateFin . ')';

        return $nomLong;
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
     * Set code.
     *
     * @param string $code
     */
    public function setCode($code)
    {
        $this->code = IrsteaBdohDataBundle::slugify($code);
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
     * @return string
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * @param string $description
     */
    public function setDescription(string $description)
    {
        $this->description = $description;
    }

    /**
     * Set genealogie.
     *
     * @param string $genealogie
     */
    public function setGenealogie($genealogie)
    {
        $this->genealogie = $genealogie;
    }

    /**
     * Get genealogie.
     *
     * @return string
     */
    public function getGenealogie()
    {
        return $this->genealogie;
    }

    /**
     * @return string
     */
    public function getGenealogieEn()
    {
        return $this->genealogieEn;
    }

    /**
     * @param string $genealogieEn
     */
    public function setGenealogieEn(string $genealogieEn)
    {
        $this->genealogieEn = $genealogieEn;
    }

    /**
     * Set estVisible.
     *
     * @param bool $estVisible
     */
    public function setEstVisible($estVisible)
    {
        $this->estVisible = $estVisible;
    }

    /**
     * Get estVisible.
     *
     * @return bool
     */
    public function getEstVisible()
    {
        return $this->estVisible;
    }

    /**
     * Set minimumValide.
     *
     * @param float $minimumValide
     */
    public function setMinimumValide($minimumValide)
    {
        $this->minimumValide = $minimumValide;
    }

    /**
     * Get minimumValide.
     *
     * @return float
     */
    public function getMinimumValide()
    {
        return $this->minimumValide;
    }

    /**
     * Set maximumValide.
     *
     * @param float $maximumValide
     */
    public function setMaximumValide($maximumValide)
    {
        $this->maximumValide = $maximumValide;
    }

    /**
     * Get maximumValide.
     *
     * @return float
     */
    public function getMaximumValide()
    {
        return $this->maximumValide;
    }

    /**
     * Set dateDebutMesures.
     *
     * @param string $dateDebutMesures
     */
    public function setDateDebutMesures($dateDebutMesures)
    {
        $this->dateDebutMesures = $dateDebutMesures;
    }

    /**
     * Get dateDebutMesures.
     *
     * @return string
     */
    public function getDateDebutMesures()
    {
        return $this->dateDebutMesures;
    }

    /**
     * Set dateFinMesures.
     *
     * @param string $dateFinMesures
     */
    public function setDateFinMesures($dateFinMesures)
    {
        $this->dateFinMesures = $dateFinMesures;
    }

    /**
     * Get dateFinMesures.
     *
     * @return string
     */
    public function getDateFinMesures()
    {
        return $this->dateFinMesures;
    }

    /**
     * Set nbMesures.
     *
     * @param int $nbMesures
     */
    public function setNbMesures($nbMesures)
    {
        $this->nbMesures = $nbMesures;
    }

    /**
     * Get nbMesures.
     *
     * @return int
     */
    public function getNbMesures()
    {
        return $this->nbMesures;
    }

    /**
     * Set unite.
     *
     * @param Unite|null $unite
     */
    public function setUnite(?Unite $unite = null)
    {
        if ($unite) {
            $this->unite = $unite;
        }
    }

    /**
     * Get unite.
     *
     * @return Unite
     */
    public function getUnite()
    {
        return $this->unite;
    }

    /**
     * Set producteur.
     *
     * @param Partenaire|null $producteur
     */
    public function setProducteur(?Partenaire $producteur = null)
    {
        $this->producteur = $producteur;
    }

    /**
     * Get producteur.
     *
     * @return Partenaire|null
     */
    public function getProducteur()
    {
        return $this->producteur;
    }

    /**
     * Set parametre.
     *
     * @param TypeParametre $parametre
     */
    public function setParametre(TypeParametre $parametre)
    {
        $this->parametre = $parametre;
    }

    /**
     * Get parametre.
     *
     * @return TypeParametre
     */
    public function getParametre()
    {
        return $this->parametre;
    }

    /**
     * Set station.
     *
     * @param Station $station
     */
    public function setStation(Station $station)
    {
        $this->station = $station;
    }

    /**
     * Get station.
     *
     * @return Station
     */
    public function getStation()
    {
        return $this->station;
    }

    /*
     * Whether this time series should be plotted with reverse Y axis
     */

    /**
     * @return bool
     */
    public function getReverseAxis()
    {
        return false;
    }

    /**
     * Set echantillonageSet.
     *
     * @param bool $echantillonageSet
     */
    public function setEchantillonageSet($echantillonageSet)
    {
        $this->echantillonageSet = $echantillonageSet;
    }

    /**
     * Get echantillonageSet.
     *
     * @return bool
     */
    public function getEchantillonageSet()
    {
        return $this->echantillonageSet;
    }

    /**
     * Set whether quantification/detection limits can be assigned to this chronique's measures.
     *
     * @param bool $allowValueLimits
     */
    public function setAllowValueLimits($allowValueLimits)
    {
        $this->allowValueLimits = $allowValueLimits;
    }

    /**
     * Get whether quantification/detection limits can be assigned to this chronique's measures.
     *
     * @return bool
     */
    public function getAllowValueLimits()
    {
        return $this->allowValueLimits;
    }

    /**
     * To String.
     *
     * @return string
     */
    public function __toString()
    {
        return $this->getCode() ? sprintf('%s / %s', $this->getStation()->getCode(), $this->getCode()) : '';
    }

    /**
     * {@inheritdoc}
     */
    public function isContinue()
    {
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function isDiscontinue()
    {
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function isCalculee()
    {
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function isConvertie()
    {
        return false;
    }

    /**
     * @return string
     */
    abstract public function dtype();

    /**
     * {@inheritdoc}
     */
    public function getChronique()
    {
        return $this;
    }

    /** Get transformations.
     * @return Collection|Transformation[]|Conversion[]
     */
    public function getTransformations()
    {
        return new ArrayCollection();
    }

    /** Has children.
     * @return bool
     */
    public function hasChildren()
    {
        return false;
    }

    /**
     * Renvoie la qualité "lacune" par défaut.
     *
     * @return Qualite|null
     */
    public function getDefaultGapQualite()
    {
        return ($obs = $this->getObservatoire()) ? $obs->getJeu()->getDefaultGapQualite() : null;
    }

    /**
     * @return DataSet|null
     */
    public function getDataSet(): ?DataSet
    {
        return $this->dataset;
    }

    /**
     * @param DataSet|null $dataset
     */
    public function setDataSet(?DataSet $dataset)
    {
        $this->dataset = $dataset;
    }

    /**
     * @return Milieu|null
     */
    public function getMilieu(): ?Milieu
    {
        return $this->milieu;
    }

    /**
     * @param Milieu $milieu
     */
    public function setMilieu(Milieu $milieu): void
    {
        $this->milieu = $milieu;
    }

    public function __clone()
    {
        $this->id = null;
        $this->dataset = null;
    }
}
