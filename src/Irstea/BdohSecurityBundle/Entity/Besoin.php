<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohSecurityBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Irstea\BdohDataBundle\Entity\Observatoire;
use Irstea\BdohDataBundle\Entity\ObservatoireRelatedInterface;
use Irstea\BdohDataBundle\Entity\Station;
use Irstea\BdohDataBundle\Entity\TypeParametre;

/**
 * Class Besoin.
 */
class Besoin implements ObservatoireRelatedInterface
{
    /**
     * @var int
     */
    protected $id;

    /**
     * @var string
     */
    protected $duree;

    /**
     * @var string
     */
    protected $details;

    /**
     * @var Utilisateur
     */
    protected $utilisateur;

    /**
     * @var ObjectifRecherche
     */
    protected $objectif;

    /**
     * @var TypeTravaux
     */
    protected $type;

    /**
     * @var Observatoire
     */
    protected $observatoire;

    /**
     * @var TypeParametre
     */
    protected $parametres;

    /**
     * @var Station
     */
    protected $stations;

    /**
     * @var bool
     */
    protected $traite = false;

    /**
     * Besoin constructor.
     */
    public function __construct()
    {
        $this->parametres = new ArrayCollection();
        $this->stations = new ArrayCollection();
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
     * Set duree.
     *
     * @param string $duree
     */
    public function setDuree($duree)
    {
        $this->duree = $duree;
    }

    /**
     * Get duree.
     *
     * @return string
     */
    public function getDuree()
    {
        return $this->duree;
    }

    /**
     * Set details.
     *
     * @param string $details
     */
    public function setDetails($details)
    {
        $this->details = $details;
    }

    /**
     * Get details.
     *
     * @return string
     */
    public function getDetails()
    {
        return $this->details;
    }

    /**
     * Set utilisateur.
     *
     * @param Utilisateur $utilisateur
     */
    public function setUtilisateur(Utilisateur $utilisateur)
    {
        $this->utilisateur = $utilisateur;
    }

    /**
     * Get utilisateur.
     *
     * @return Utilisateur
     */
    public function getUtilisateur()
    {
        return $this->utilisateur;
    }

    /**
     * Set objectif.
     *
     * @param ObjectifRecherche $objectif
     */
    public function setObjectif(ObjectifRecherche $objectif)
    {
        $this->objectif = $objectif;
    }

    /**
     * Get objectif.
     *
     * @return ObjectifRecherche
     */
    public function getObjectif()
    {
        return $this->objectif;
    }

    /**
     * Set type.
     *
     * @param TypeTravaux $type
     */
    public function setType(TypeTravaux $type)
    {
        $this->type = $type;
    }

    /**
     * Get type.
     *
     * @return TypeTravaux
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * Set observatoire.
     *
     * @param Observatoire $observatoire
     */
    public function setObservatoire(Observatoire $observatoire)
    {
        $this->observatoire = $observatoire;
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
     * Add parametres.
     *
     * @param TypeParametre $parametres
     */
    public function addTypeParametre(TypeParametre $parametres)
    {
        $this->parametres[] = $parametres;
    }

    /**
     * Get parametres.
     *
     * @return Collection|TypeParametre[]
     */
    public function getParametres()
    {
        return $this->parametres;
    }

    /**
     * Add stations.
     *
     * @param Station $stations
     */
    public function addStation(Station $stations)
    {
        $this->stations[] = $stations;
    }

    /**
     * Get stations.
     *
     * @return Collection|Station[]
     */
    public function getStations()
    {
        return $this->stations;
    }

    /**
     * Set traite.
     *
     * @param bool $traite
     */
    public function setTraite($traite)
    {
        $this->traite = $traite;
    }

    /**
     * Get traite.
     *
     * @return bool
     */
    public function getTraite()
    {
        return $this->traite;
    }
}
