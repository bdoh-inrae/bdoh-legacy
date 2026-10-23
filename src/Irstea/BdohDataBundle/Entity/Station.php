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
use Symfony\Component\Validator\Constraints as Assert;

#[UniqueEntity(fields: 'nom', message: 'Station.nom.alreadyExists')]
#[UniqueEntity(fields: 'code', message: 'Station.code.alreadyExists')]
class Station implements SiteRelatedInterface
{
    use SiteRelatedTrait;

    /**
     * Some constants to retrieve a specific alternative code.
     */
    const ALT_CODE_SEPARATOR = ';';

    const ALT_CODE_KEYVALUE_SEPARATOR = '=';

    public const SRID = '2154'; // RGF93 / Lambert-93 -- France

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
    protected $code;

    /**
     * @var string
     */
    protected $codeAlternatif;

    /**
     * @var float
     */
    protected $altitude;

    /**
     * @var bool
     */
    protected $estActive = true;

    /**
     * @var string
     */
    protected $commentaire;

    /**
     * @var string
     */
    protected $commentaireEn;

    /**
     * @var Commune
     */
    protected $commune;

    /**
     * @var Collection
     */
    #[Assert\Count(min: 1)]
    protected $sites;

    /**
     * @var Collection
     */
    protected $chroniques;

    /*
     * @var string
     */
    /**
     * @var
     */
    protected $point;

    /*
     * @var float
     */
    /**
     * @var
     */
    protected $latitude;

    /*
     * @var float
     */
    /**
     * @var
     */
    protected $longitude;

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->sites = new ArrayCollection();
        $this->chroniques = new ArrayCollection();
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
     * @return Station
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
     * Set code.
     *
     * @param string $code
     *
     * @return Station
     */
    public function setCode($code)
    {
        $this->code = IrsteaBdohDataBundle::slugify($code);

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
     * Set altitude.
     *
     * @param float $altitude
     *
     * @return Station
     */
    public function setAltitude($altitude)
    {
        $this->altitude = $altitude;

        return $this;
    }

    /**
     * Get altitude.
     *
     * @return float
     */
    public function getAltitude()
    {
        return $this->altitude;
    }

    /**
     * Set estActive.
     *
     * @param bool $estActive
     *
     * @return Station
     */
    public function setEstActive($estActive)
    {
        $this->estActive = $estActive;

        return $this;
    }

    /**
     * Get estActive.
     *
     * @return bool
     */
    public function getEstActive()
    {
        return $this->estActive;
    }

    /**
     * Set commentaire.
     *
     * @param string $commentaire
     *
     * @return Station
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
     * @return string
     */
    public function getCommentaireEn()
    {
        return $this->commentaireEn;
    }

    /**
     * @param string $commentaireEn
     *
     * @return Station
     */
    public function setCommentaireEn($commentaireEn)
    {
        $this->commentaireEn = $commentaireEn;

        return $this;
    }

    /**
     * Set commune.
     *
     * @param Commune|null $commune
     *
     * @return Station
     */
    public function setCommune(?Commune $commune = null)
    {
        $this->commune = $commune;

        return $this;
    }

    /**
     * Get commune.
     *
     * @return Commune
     */
    public function getCommune()
    {
        return $this->commune;
    }

    /**
     * Add site.
     *
     * @param SiteExperimental $site
     *
     * @return Station
     */
    public function addSite(SiteExperimental $site)
    {
        $this->sites[] = $site;
        $this->validateSites();

        return $this;
    }

    /**
     * Remove sites.
     *
     * @param SiteExperimental $sites
     */
    public function removeSite(SiteExperimental $sites)
    {
        $this->sites->removeElement($sites);
    }

    /**
     * Set sites.
     *
     * @param Collection $sites
     *
     * @return Station
     */
    public function setSites(Collection $sites)
    {
        $this->sites = $sites;
        $this->validateSites();

        return $this;
    }

    /**
     * Valide que les sites sont dans le même observatoire.
     */
    private function validateSites()
    {
        if ($this->sites->isEmpty()) {
            return;
        }
        /** @var Observatoire $obs */
        $obs = $this->getObservatoire();
        /** @var SiteExperimental $site */
        foreach ($this->sites as $site) {
            if ($obs->getId() !== $site->getObservatoire()->getId()) {
                throw new \InvalidArgumentException('Cannot assign a station to sites from different observatories');
            }
        }
    }

    /**
     * Get sites.
     *
     * @return Collection|SiteExperimental[]
     */
    public function getSites()
    {
        return $this->sites;
    }

    /**
     * Set codeAlternatif.
     *
     * @param string $codeAlternatif
     *
     * @return Station
     */
    public function setCodeAlternatif($codeAlternatif)
    {
        $this->codeAlternatif = $codeAlternatif;

        return $this;
    }

    /**
     * Get codeAlternatif.
     *
     * @return string
     */
    public function getCodeAlternatif()
    {
        return $this->codeAlternatif;
    }

    /**
     * @return float
     */
    public function getLatitude()
    {
        return $this->latitude;
    }

    /**
     * @param float $latitude
     *
     * @return self
     */
    public function setLatitude($latitude)
    {
        $this->latitude = $latitude;

        return $this;
    }

    /**
     * @return float
     */
    public function getLongitude()
    {
        return $this->longitude;
    }

    /**
     * @param $longitude
     *
     * @return self
     */
    public function setLongitude($longitude)
    {
        $this->longitude = $longitude;

        return $this;
    }

    /**
     * Gets one specific 'CodeAlternative'.
     * If several codes have the same key, returns the last.
     * If no code found for this key, returns null.
     *
     * @param string $key Key of the code to retrieve
     *
     * @return string|null
     */
    public function getOneCodeAlternatif($key)
    {
        if ($this->getCodeAlternatif()) {
            foreach (\explode(self::ALT_CODE_SEPARATOR, $this->getCodeAlternatif()) as $subCode) {
                $keyAndValue = \explode(self::ALT_CODE_KEYVALUE_SEPARATOR, $subCode);
                if (\sizeof($keyAndValue) > 1 && \strtolower(\trim($keyAndValue[0])) === $key) {
                    return \trim($keyAndValue[1]);
                }
            }
        }

        return null;
    }

    /**
     * To string.
     *
     * @return string
     */
    public function __toString()
    {
        return $this->getNom() ?: '';
    }

    /**
     * Add sites.
     *
     * @param SiteExperimental $sites
     *
     * @return self
     */
    public function addSiteExperimental(SiteExperimental $sites)
    {
        $this->sites[] = $sites;

        return $this;
    }

    /**
     * Add chronique.
     *
     * @param Chronique $chronique
     *
     * @return self
     */
    public function addChronique(Chronique $chronique)
    {
        $this->chroniques[] = $chronique;

        return $this;
    }

    /**
     * Remove chroniques.
     *
     * @param Chronique $chroniques
     *
     * @return self
     */
    public function removeChronique(Chronique $chroniques)
    {
        $this->chroniques->removeElement($chroniques);

        return $this;
    }

    /**
     * Set chroniques.
     *
     * @param Collection $chroniques
     *
     * @return self
     */
    public function setChroniques(Collection $chroniques)
    {
        $this->chroniques = $chroniques;

        return $this;
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

    /**
     * @return string
     */
    public function getPoint()
    {
        return $this->point;
    }

    /**
     * @param string $point
     *
     * @return self
     */
    public function setPoint($point)
    {
        $this->point = $point;

        return $this;
    }
}
