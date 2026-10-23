<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohDataBundle\Entity;

use Irstea\BdohDataBundle\IrsteaBdohDataBundle;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Class Partenaire.
 */
#[UniqueEntity(fields: ['nom', 'observatoire'], errorPath: 'nom', message: 'Ce nom existe déjà dans cet Observatoire')]
class Partenaire implements DocumentedEntityInterface
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
    protected $lien;

    /**
     * @var string
     */
    protected $pathLogo;

    /**
     * @var bool
     */
    protected $estFinanceur = true;

    /**
     * @var TypeFunding
     */
    protected $typeFundings;

    /**
     *@var Observatoire
     */
    protected $observatoire;

    /**
     * @var string
     */
    protected $iso;

    /**
     * @var string
     */
    protected $scanR;

    /**
     * Temporary variable used to upload file ; not mapped to Doctrine !
     *
     * @var UploadedFile
     */
    #[Assert\Image(maxSize: '50k')]
    protected $fileLogo = null;

    //------------------------ctr----------------------------------------

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
     * @return Observatoire
     */
    public function getObservatoire()
    {
        return $this->observatoire;
    }

    /**
     * @param Observatoire $observatoire
     */
    public function setObservatoire($observatoire)
    {
        $this->observatoire = $observatoire;
    }

    /**
     * Set lien.
     *
     * @param string $lien
     */
    public function setLien($lien)
    {
        $this->lien = $lien;
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
     * Set pathLogo.
     *
     * @param string $pathLogo
     */
    public function setPathLogo($pathLogo)
    {
        $this->pathLogo = $pathLogo;
    }

    /**
     * @return UploadedFile|string|null
     */
    public function getPathLogo()
    {
        return $this->pathLogo;
    }

    /**
     * Set TypeFundings.
     *
     * @param TypeFunding $typeFundings
     *
     * @return Partenaire
     */
    public function setTypeFundings(?TypeFunding $typeFundings = null)
    {
        $this->typeFundings = $typeFundings;

        return $this;
    }

    /**
     * Get TypeFundings.
     *
     * @return TypeFunding
     */
    public function getTypeFundings()
    {
        return $this->typeFundings;
    }

    /**
     * Set estFinanceur.
     *
     * @param bool $estFinanceur
     */
    public function setEstFinanceur($estFinanceur)
    {
        $this->estFinanceur = $estFinanceur;
    }

    /**
     * Get estFinanceur.
     *
     * @return bool
     */
    public function getEstFinanceur()
    {
        return $this->estFinanceur;
    }

    /**
     * Set iso.
     *
     * @param string $iso
     */
    public function setIso($iso)
    {
        $this->iso = $iso;
    }

    /**
     * Get iso.
     *
     * @return string
     */
    public function getIso()
    {
        return $this->iso;
    }

    /**
     * Set scanR.
     *
     * @param string $scanR
     */
    public function setScanR($scanR)
    {
        $this->scanR = $scanR;
    }

    /**
     * Get scanR.
     *
     * @return string
     */
    public function getScanR()
    {
        return $this->scanR;
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
    public function getDocumentFilePrefix()
    {
        return IrsteaBdohDataBundle::slugify($this->nom);
    }

    /**
     * {@inheritdoc}
     */
    public function getDocumentFields()
    {
        return ['pathLogo'];
    }
}
