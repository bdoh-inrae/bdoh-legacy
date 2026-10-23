<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohDataBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Irstea\BdohDataBundle\IrsteaBdohDataBundle;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

/**
 * Irstea\BdohDataBundle\Entity\SiteExperimental.
 */
#[UniqueEntity(fields: 'slug', message: 'SiteExperimental.slug.alreadyExists')]
class SiteExperimental implements SiteRelatedInterface
{
    use SiteRelatedTrait;

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
    protected $slug;

    /**
     * @var string
     */
    protected $description;

    /**
     * @var string
     */
    protected $descriptionEn;

    /**
     * @var Observatoire
     */
    protected $observatoire;

    /**
     * @var Collection
     */
    protected $stations;

    /**
     * SiteExperimental constructor.
     */
    public function __construct()
    {
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
     * Set nom.
     *
     * @param string $nom
     */
    public function setNom($nom)
    {
        $this->setSlug($nom);
        $this->nom = $nom;
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
     * Set slug.
     *
     * @param string $slug
     */
    public function setSlug($slug)
    {
        $this->slug = IrsteaBdohDataBundle::slugify($slug);
    }

    /**
     * Get slug.
     *
     * @return string
     */
    public function getSlug()
    {
        return $this->slug;
    }

    /**
     * Set description.
     *
     * @param string $description
     */
    public function setDescription($description)
    {
        $this->description = $description;
    }

    /**
     * Get description.
     *
     * @return string
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * Set descriptionEn.
     *
     * @param string $descriptionEn
     */
    public function setDescriptionEn($descriptionEn)
    {
        $this->descriptionEn = $descriptionEn;
    }

    /**
     * Get descriptionEn.
     *
     * @return string
     */
    public function getDescriptionEn()
    {
        return $this->descriptionEn;
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
     * Add stations.
     *
     * @param Station $stations
     */
    public function addStation(Station $stations)
    {
        $this->stations[] = $stations;
    }

    /**
     * Remove stations.
     *
     * @param Station $stations
     */
    public function removeStation(Station $stations)
    {
        $this->stations->removeElement($stations);
    }

    /**
     * Set stations.
     *
     * @param Collection $stations
     */
    public function setStations(Collection $stations)
    {
        $this->stations = $stations;
    }

    /**
     * Get stations.
     *
     * @return Collection
     */
    public function getStations()
    {
        return $this->stations;
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
     * {@inheritdoc}
     */
    public function getSites()
    {
        return [$this];
    }
}
