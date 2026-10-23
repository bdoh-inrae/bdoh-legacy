<?php

namespace Irstea\BdohDataBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Irstea\BdohDataBundle\IrsteaBdohDataBundle;
use Symfony\Component\Validator\Constraints as Assert;

/**
 *  Class Jeu de donnée.
 */
class DataSet
{
    /**
     * @var int
     */
    protected $id;

    /**
     * @var string|null
     */
    protected $uuid;

    /**
     * @var string|null
     */
    protected $titre;

    /**
     * @var string|null
     */
    protected $titreEn;

    /**
     * @var string|null
     */
    protected $description;

    /**
     * @var string|null
     */
    protected $descriptionEn;

    /**
     * @var string|null
     */
    protected $genealogie;

    /**
     * @var string|null
     */
    protected $genealogieEn;

    /**
     * @var Collection<TopicCategory>
     */
    #[Assert\NotNull]
    #[Assert\Count(min: 1)]
    protected $topicCategories;

    /**
     * @var Collection<CriteriaClimat>
     */
    protected $portailSearchCriteriaClimat;

    /**
     * @var Collection<CriteriaGeology>
     */
    protected $portailSearchCriteriaGeology;

    /**
     * @var DataConstraint|null
     */
    #[Assert\NotNull]
    protected $dataConstraint;

    /**
     * @var InspireTheme|null
     */
    protected $inspireTheme;

    /**
     * @var Collection<PersonneTheia>
     */
    protected $dataManagers;

    /**
     * @var Collection<PersonneTheia>
     */
    #[Assert\NotNull]
    #[Assert\Count(min: 1)]
    protected $principalInvestigators;

    /**
     * @var Collection<PersonneTheia>
     */
    protected $dataCollectors;

    /**
     * @var Collection<PersonneTheia>
     */
    protected $projectMembers;

    /**
     * @var Collection<Chronique>
     */
    protected $chroniques;

    /**
     *@var Observatoire|null
     */
    protected $observatoire;

    /**
     * @var Doi|null
     */
    protected $doi;

    //------------------------ctr----------------------------------------

    /**
     * DataSet constructor.
     */
    public function __construct()
    {
        $this->topicCategories = new ArrayCollection();
        $this->portailSearchCriteriaClimat = new ArrayCollection();
        $this->portailSearchCriteriaGeology = new ArrayCollection();
        $this->projectMembers = new ArrayCollection();
        $this->dataCollectors = new ArrayCollection();
        $this->principalInvestigators = new ArrayCollection();
        $this->dataManagers = new ArrayCollection();
        $this->chroniques = new ArrayCollection();
    }

    //--------------------------------- getter et setter -------------------------------------------------

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUuid(): string
    {
        return $this->uuid = IrsteaBdohDataBundle::slugify($this->getTitre());
    }

    public function setUuid(?string $uuid = null): void
    {
        $this->uuid = IrsteaBdohDataBundle::slugify($this->getTitre());
    }

    public function getTitre(): ?string
    {
        return $this->titre;
    }

    public function setTitre(?string $titre): void
    {
        $this->titre = $titre;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }

    public function getGenealogie(): ?string
    {
        return $this->genealogie;
    }

    public function setGenealogie(?string $genealogie): void
    {
        $this->genealogie = $genealogie;
    }

    public function getTitreEn(): ?string
    {
        return $this->titreEn;
    }

    public function setTitreEn(?string $titreEn): void
    {
        $this->titreEn = $titreEn;
    }

    public function getDescriptionEn(): ?string
    {
        return $this->descriptionEn;
    }

    public function setDescriptionEn(?string $descriptionEn): void
    {
        $this->descriptionEn = $descriptionEn;
    }

    public function getGenealogieEn(): ?string
    {
        return $this->genealogieEn;
    }

    public function setGenealogieEn(?string $genealogieEn): void
    {
        $this->genealogieEn = $genealogieEn;
    }

    public function getInspireTheme(): ?InspireTheme
    {
        return $this->inspireTheme;
    }

    public function setInspireTheme(?InspireTheme $inspireTheme)
    {
        $this->inspireTheme = $inspireTheme;
    }

    public function setDataConstraint(?DataConstraint $dataConstraint): void
    {
        $this->dataConstraint = $dataConstraint;
    }

    public function getDataConstraint(): ?DataConstraint
    {
        return $this->dataConstraint;
    }

    public function addTopicCategory(TopicCategory $topicCat): void
    {
        $this->topicCategories[] = $topicCat;
    }

    public function removeTopicCategory(TopicCategory $topicCat): void
    {
        $this->topicCategories->removeElement($topicCat);
    }

    /**
     * @return Collection<TopicCategory>
     */
    public function getTopicCategories(): Collection
    {
        return $this->topicCategories;
    }

    public function addPortailSearchCriteriaClimat(CriteriaClimat $portailSearchCriteriaClimat): void
    {
        $this->portailSearchCriteriaClimat[] = $portailSearchCriteriaClimat;
    }

    public function removePortailSearchCriteriaClimat(CriteriaClimat $portailSearchCriteriaClimat)
    {
        $this->portailSearchCriteriaClimat->removeElement($portailSearchCriteriaClimat);
    }

    /**
     * @return Collection<CriteriaClimat>
     */
    public function getPortailSearchCriteriaClimat(): Collection
    {
        return $this->portailSearchCriteriaClimat;
    }

    public function addPortailSearchCriteriaGeology(CriteriaGeology $portailSearchCriteriaGeology): void
    {
        $this->portailSearchCriteriaGeology[] = $portailSearchCriteriaGeology;
    }

    public function removePortailSearchCriteriaGeology(CriteriaGeology $portailSearchCriteriaGeology): void
    {
        $this->portailSearchCriteriaGeology->removeElement($portailSearchCriteriaGeology);
    }

    /**
     * @return Collection<CriteriaGeology>
     */
    public function getPortailSearchCriteriaGeology(): Collection
    {
        return $this->portailSearchCriteriaGeology;
    }

    public function addPrincipalInvestigator(PersonneTheia $principalInvestigator): void
    {
        $this->principalInvestigators[] = $principalInvestigator;
    }

    public function removePrincipalInvestigator(PersonneTheia $principalInvestigator): void
    {
        $this->principalInvestigators->removeElement($principalInvestigator);
    }

    /**
     * @return Collection<PersonneTheia>
     */
    public function getPrincipalInvestigators(): Collection
    {
        return $this->principalInvestigators;
    }

    public function addDataCollector(PersonneTheia $dataCollector): void
    {
        $this->principalInvestigators[] = $dataCollector;
    }

    public function removeDataCollector(PersonneTheia $dataCollector): void
    {
        $this->dataCollectors->removeElement($dataCollector);
    }

    /**
     * @return Collection<PersonneTheia>
     */
    public function getDataCollectors(): Collection
    {
        return $this->dataCollectors;
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

    public function addProjectMember(PersonneTheia $projectMember): void
    {
        $this->projectMembers[] = $projectMember;
    }

    public function removeProjectMember(PersonneTheia $projectMember): void
    {
        $this->projectMembers->removeElement($projectMember);
    }

    /**
     * @return Collection<PersonneTheia>
     */
    public function getProjectMembers(): Collection
    {
        return $this->projectMembers;
    }

    public function addChronique(Chronique $chronique): void
    {
        $chronique->setDataSet($this);
        $this->chroniques[] = $chronique;
    }

    public function removeChronique(Chronique $chronique): void
    {
        $chronique->setDataSet(null);
        $this->chroniques->removeElement($chronique);
    }

    /**
     * @return Collection<Chronique>
     */
    public function getChroniques(): Collection
    {
        return $this->chroniques;
    }

    public function getDoi(): ?Doi
    {
        return $this->doi;
    }

    public function setDoi(?Doi $doi): void
    {
        $this->doi = $doi;
    }

    public function getObservatoire(): ?Observatoire
    {
        return $this->observatoire;
    }

    public function setObservatoire(Observatoire $observatoire)
    {
        $this->observatoire = $observatoire;
    }

    /**
     * To String.
     *
     * @return string
     */
    public function __toString()
    {
        return $this->getTitre() ?: '';
    }
}
