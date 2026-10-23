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
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Observatoire
 * This class allows to manage :
 *  -> any kind of 'observatoires' ;
 *  -> and the special "current observatoire" and "all observatoires".
 */
#[UniqueEntity(fields: 'slug', message: 'Observatoire.slug.alreadyExists')]
#[UniqueEntity(fields: 'theiaCode', message: 'Observatoire.slug.alreadyExists')]
class Observatoire implements ObservatoireRelatedInterface, DocumentedEntityInterface
{
    /***************************************************************************
     * CONSTANTS
     **************************************************************************/

    const DEFAULT_COULEUR_PRIMAIRE = '#003A80';

    const DEFAULT_COULEUR_SECONDAIRE = '#009EE0';

    /***************************************************************************
     * Attribute and methods to store & manage the current 'observatoire' !
     **************************************************************************/

    protected static $current = null;

    /**
     * @return Observatoire
     *
     * @deprecated Utiliser l'ObservatoireManager
     */
    public static function getCurrent()
    {
        return self::$current;
    }

    /**
     * @deprecated Utiliser l'ObservatoireManager
     */
    public function defineAsCurrent()
    {
        self::$current = $this;
    }

    /**
     * @deprecated Utiliser l'ObservatoireManager
     */
    public static function resetCurrent()
    {
        self::$current = null;
    }

    /**
     * @return bool
     *
     * @deprecated Utiliser l'ObservatoireManager
     */
    public function isCurrent()
    {
        return self::getCurrent() === $this;
    }

    /***************************************************************************
     * Rest of attributes and methods.
     **************************************************************************/

    /**
     * @var int
     */
    protected $id;

    /**
     * @var string|null
     */
    protected $theiaCode;

    /**
     * @var string|null
     */
    protected $theiaPassword;

    /**
     * @var string
     */
    protected $nom;

    /**
     * @var string
     */
    protected $titre;

    /**
     * @var string
     */
    protected $titreEn;

    /**
     * @var string
     */
    protected $email;

    /**
     * @var string
     */
    protected $slug;

    /**
     * @var string|UploadedFile|null
     */
    #[Assert\File(mimeTypes: ['application/pdf', 'application/x-pdf'])]
    protected $conditionsUtilisation;

    /**
     * @var string
     */
    protected $description;

    /**
     * @var string
     */
    protected $descriptionEn;

    /**
     * @var string
     */
    protected $lien;

    /**
     * @var string (color in hexafloat form)
     */
    protected $couleurPrimaire = self::DEFAULT_COULEUR_PRIMAIRE;

    /**
     * @var string (color in hexafloat form)
     */
    protected $couleurSecondaire = self::DEFAULT_COULEUR_SECONDAIRE;

    /**
     * @var string|UploadedFile|null (path relative to web directory)
     */
    #[Assert\Image(maxSize: '500k')]
    protected $pathPhotoPrincipale;

    /**
     * @var string|UploadedFile|null (path relative to web directory)
     */
    #[Assert\Image(maxSize: '500k')]
    protected $pathPhotoSecondaire;

    /**
     * @var string|UploadedFile|null (path relative to web directory)
     */
    #[Assert\Image(maxSize: '50k')]
    protected $pathLogo;

    /**
     * @var Collection<SiteExperimental>
     */
    protected $sites;

    /**
     * @var Collection<CoursEau>
     */
    protected $coursEaux;

    /**
     * @var JeuQualite
     */
    protected $jeu;

    /**
     * @var PersonneTheia|null
     */
    #[Assert\NotNull(message: 'Obligatoire')]
    protected $projectLeader;

    /**
     * @var Collection<PersonneTheia>
     */
    #[Assert\NotNull]
    protected $dataManagers;

    /**
     * @var Collection<Partenaire>
     */
    protected $partenaires;

    /**
     * @var Collection<Doi>
     */
    protected $dois;

    /**
     * @var Doi|null
     */
    protected $doiPrincipal;

    /**
     * @var Collection<DataSet>
     */
    protected $dataSets;

    /**
     * @var bool
     */
    protected $miseAJourAutomatique = false;

    /**
     * Observatoire constructor.
     */
    public function __construct()
    {
        $this->sites = new ArrayCollection();
        $this->partenaires = new ArrayCollection();
        $this->coursEaux = new ArrayCollection();
        $this->dois = new ArrayCollection();
        $this->dataManagers = new ArrayCollection();
        $this->dataSets = new ArrayCollection();
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
    public function setNom($nom): void
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

    public function getTheiaCode(): ?string
    {
        return $this->theiaCode;
    }

    public function setTheiaCode(?string $theiaCode): void
    {
        $this->theiaCode = $theiaCode;
    }

    public function getTheiaPassword(): ?string
    {
        return $this->theiaPassword;
    }

    public function setTheiaPassword(?string $theiaPassword): void
    {
        $this->theiaPassword = $theiaPassword;
    }

    public function isMiseAJourAutomatique(): bool
    {
        return $this->miseAJourAutomatique;
    }

    /**
     * @param bool $miseAJourAutomatique
     */
    public function setMiseAJourAutomatique(bool $miseAJourAutomatique): void
    {
        $this->miseAJourAutomatique = $miseAJourAutomatique;
    }

    /**
     * @return string
     */
    public function getTitre()
    {
        return $this->titre;
    }

    /**
     * @param string $titre
     */
    public function setTitre($titre): void
    {
        $this->titre = $titre;
    }

    /**
     * @return string
     */
    public function getTitreEn()
    {
        return $this->titreEn;
    }

    /**
     * @param string $titreEn
     */
    public function setTitreEn(string $titreEn): void
    {
        $this->titreEn = $titreEn;
    }

    /**
     * @return string
     */
    public function getEmail()
    {
        return $this->email;
    }

    /**
     * @param string $email
     */
    public function setEmail($email): void
    {
        $this->email = $email;
    }

    public function getProjectLeader(): ?PersonneTheia
    {
        return $this->projectLeader;
    }

    public function setProjectLeader(PersonneTheia $projectLeader): void
    {
        $this->projectLeader = $projectLeader;
    }

    public function addDataManager(PersonneTheia $dataManager): void
    {
        $this->dataManagers[] = $dataManager;
    }

    public function removeDataManager(PersonneTheia $dataManager): void
    {
        $this->dataManagers->removeElement($dataManager);
    }

    /**
     * @return Collection<PersonneTheia>
     */
    public function getDataManagers(): Collection
    {
        return $this->dataManagers;
    }

    public function addDataSet(DataSet $dataSet): void
    {
        $dataSet->setObservatoire($this);
        $this->dataSets[] = $dataSet;
    }

    public function removeDataSet(DataSet $dataSet): void
    {
        $dataSet->setObservatoire(null);
        $this->dataSets->removeElement($dataSet);
    }

    /**
     * @return Collection<DataSet>
     */
    public function getDataSets(): Collection
    {
        return $this->dataSets;
    }

    /**
     * Set lien.
     *
     * @param string $lien
     */
    public function setLien($lien): void
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
     * Set slug.
     *
     * @param string $slug
     */
    public function setSlug($slug): void
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
     * Set conditionsUtilisation.
     *
     * @param string|UploadedFile|null $conditionsUtilisation
     */
    public function setConditionsUtilisation($conditionsUtilisation)
    {
        $this->conditionsUtilisation = $conditionsUtilisation;
    }

    /**
     * @return string|UploadedFile|null
     */
    public function getConditionsUtilisation()
    {
        return $this->conditionsUtilisation;
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
     * @param string|null $description
     */
    public function setDescriptionEn(?string $description): void
    {
        $this->descriptionEn = $description;
    }

    /**
     * Get descriptionEn.
     *
     * @return string|null
     */
    public function getDescriptionEn(): ?string
    {
        return $this->descriptionEn;
    }

    /**
     * Set couleurPrimaire.
     *
     * @param string $couleurPrimaire
     */
    public function setCouleurPrimaire($couleurPrimaire)
    {
        $this->couleurPrimaire = $couleurPrimaire;
    }

    /**
     * Get couleurPrimaire.
     *
     * @return string
     */
    public function getCouleurPrimaire()
    {
        return $this->couleurPrimaire;
    }

    /**
     * Set couleurSecondaire.
     *
     * @param string $couleurSecondaire
     */
    public function setCouleurSecondaire($couleurSecondaire)
    {
        $this->couleurSecondaire = $couleurSecondaire;
    }

    /**
     * Get couleurSecondaire.
     *
     * @return string
     */
    public function getCouleurSecondaire(): string
    {
        return $this->couleurSecondaire;
    }

    /**
     * Set pathPhotoPrincipale.
     *
     * @param string|UploadedFile|null $pathPhotoPrincipale
     */
    public function setPathPhotoPrincipale($pathPhotoPrincipale)
    {
        $this->pathPhotoPrincipale = $pathPhotoPrincipale;
    }

    /**
     * @return string|UploadedFile|null
     */
    public function getPathPhotoPrincipale()
    {
        return $this->pathPhotoPrincipale;
    }

    /**
     * Set pathPhotoSecondaire.
     *
     * @param string|UploadedFile|null $pathPhotoSecondaire
     */
    public function setPathPhotoSecondaire($pathPhotoSecondaire)
    {
        $this->pathPhotoSecondaire = $pathPhotoSecondaire;
    }

    /**
     * @return string|UploadedFile|null
     */
    public function getPathPhotoSecondaire()
    {
        return $this->pathPhotoSecondaire;
    }

    /**
     * Set pathLogo.
     *
     * @param string|UploadedFile|null $pathLogo
     */
    public function setPathLogo($pathLogo): void
    {
        $this->pathLogo = $pathLogo;
    }

    /**
     * @return string|UploadedFile|null
     */
    public function getPathLogo()
    {
        return $this->pathLogo;
    }

    public function addSite(SiteExperimental $site): void
    {
        $this->sites[] = $site;
    }

    public function removeSite(SiteExperimental $sites): void
    {
        $this->sites->removeElement($sites);
    }

    /**
     * @param Collection<SiteExperimental> $sites
     */
    public function setSites(Collection $sites): void
    {
        $this->sites = $sites;
    }

    /**
     * @return Collection<SiteExperimental>
     */
    public function getSites(): Collection
    {
        return $this->sites;
    }

    public function setJeu(?JeuQualite $jeu): void
    {
        $this->jeu = $jeu;
    }

    public function getJeu(): ?JeuQualite
    {
        return $this->jeu;
    }

    public function addPartenaire(Partenaire $partenaires): void
    {
        $partenaires->setObservatoire($this);
        $this->partenaires[] = $partenaires;
    }

    public function removePartenaire(Partenaire $partenaires): void
    {
        $partenaires->setObservatoire(null);
        $this->partenaires->removeElement($partenaires);
    }

    /**
     * @return Collection<Partenaire>
     */
    public function getPartenaires(): Collection
    {
        return $this->partenaires;
    }

    public function __toString(): string
    {
        return $this->getNom() ?: '';
    }

    public function addSiteExperimental(SiteExperimental $sites): void
    {
        $this->sites[] = $sites;
    }

    public function addDoi(Doi $doi): void
    {
        $doi->setObservatoire($this);
        $this->dois[] = $doi;
    }

    public function removeDoi(Doi $doi): void
    {
        $doi->setObservatoire(null);
        $this->dois->removeElement($doi);
    }

    /**
     * @return Collection<Doi>
     */
    public function getDois(): Collection
    {
        return $this->dois;
    }

    public function getDoiPrincipal(): ?Doi
    {
        return $this->doiPrincipal;
    }

    public function setDoiPrincipal(?Doi $doiPrincipal): void
    {
        $this->doiPrincipal = $doiPrincipal;
    }

    /**
     * @return bool
     */
    public function hasChronique(): bool
    {
        /** @var SiteExperimental $site */
        foreach ($this->sites as $site) {
            /** @var Station $station */
            foreach ($site->getStations() as $station) {
                if (!$station->getChroniques()->isEmpty()) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function getObservatoire(): Observatoire
    {
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getDocumentFilePrefix(): string
    {
        return $this->slug;
    }

    /**
     * {@inheritdoc}
     */
    public function getDocumentFields(): array
    {
        return ['pathLogo', 'pathPhotoPrincipale', 'pathPhotoSecondaire', 'conditionsUtilisation'];
    }
}
