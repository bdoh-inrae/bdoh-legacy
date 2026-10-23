<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohLoggerBundle\Entity;

use Irstea\BdohDataBundle\Entity\ObservatoireRelatedInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Historique.
 */
class Historique implements ObservatoireRelatedInterface
{
    /**
     * @var int
     */
    protected $id;

    /**
     * @var \DateTime
     */
    protected $date;

    /**
     * @var string
     */
    protected $description;

    /**
     * @var string
     */
    protected $lien;

    /**
     * @var \Irstea\BdohSecurityBundle\Entity\Utilisateur
     */
    protected $auteur;

    /**
     * @var \Irstea\BdohDataBundle\Entity\Observatoire
     */
    protected $observatoire;

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
     * Set date.
     *
     * @param \DateTime $date
     *
     * @return Historique
     */
    public function setDate($date)
    {
        $this->date = $date;

        return $this;
    }

    /**
     * Get date.
     *
     * @return \DateTime
     */
    public function getDate()
    {
        return $this->date;
    }

    /**
     * Set description.
     *
     * @param string $description
     *
     * @return Historique
     */
    public function setDescription($description)
    {
        $this->description = $description;

        return $this;
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
     * Set lien.
     *
     * @param string $lien
     *
     * @return Historique
     */
    public function setLien($lien)
    {
        $this->lien = $lien;

        return $this;
    }

    /**
     * Get lien.
     *
     * @return string
     */
    public function getLien()
    {
        return $this->lien;
    }

    /**
     * Set auteur.
     *
     * @param \Irstea\BdohSecurityBundle\Entity\Utilisateur $auteur
     *
     * @return Historique
     */
    public function setAuteur(?\Irstea\BdohSecurityBundle\Entity\Utilisateur $auteur = null)
    {
        $this->auteur = $auteur;

        return $this;
    }

    /**
     * Get auteur.
     *
     * @return \Irstea\BdohSecurityBundle\Entity\Utilisateur
     */
    public function getAuteur()
    {
        return $this->auteur;
    }

    /**
     * Set observatoire.
     *
     * @param \Irstea\BdohDataBundle\Entity\Observatoire $observatoire
     *
     * @return Historique
     */
    public function setObservatoire(?\Irstea\BdohDataBundle\Entity\Observatoire $observatoire = null)
    {
        $this->observatoire = $observatoire;

        return $this;
    }

    /**
     * Get observatoire.
     *
     * @return \Irstea\BdohDataBundle\Entity\Observatoire
     */
    public function getObservatoire()
    {
        return $this->observatoire;
    }

    /**
     * @param TranslatorInterface $translator
     */
    public function constructDescription(TranslatorInterface $translator)
    {
        //Do nothing ; is supposed to be overwritten by subclasses.
    }

    public function constructLien()
    {
        //Do nothing ; is supposed to be overwritten by subclasses.
    }
}
