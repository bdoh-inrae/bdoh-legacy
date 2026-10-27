<?php

namespace Irstea\BdohTheiaOzcarBundle\Model;

final class Metadata
{
    /**
     * @var string
     */
    private $title = '';

    /**
     * @var string
     */
    private $description = '';

    /**
     * @var string
     */
    private $datasetLineage = '';

    /**
     * @var string[]
     */
    private $topicCategories = [];

    /**
     * @var string
     */
    private $inspireTheme = '';

    /**
     * @var PortalSearchCriteria
     */
    public $portalSearchCriteria;

    /**
     * @var Contact[]
     */
    public $contacts = [];

    /**
     * @var DataConstraint
     */
    private $dataConstraint = '';

    /**
     * @var OnlineResource
     */
    private $onlineResource;

    /**
     * @var SpatialExtent
     */
    private $spatialExtent;

    //-------------------ctor----------------

    public function __construct()
    {
        $this->portalSearchCriteria = new PortalSearchCriteria();
        $this->spatialExtent = new SpatialExtent();
    }

    //----------------getter-----------------------

    /**
     * @return SpatialExtent
     */
    public function getSpatialExtent()
    {
        return $this->spatialExtent;
    }

    /**
     * @param SpatialExtent $spatialextends
     */
    public function setSpatialExtent(SpatialExtent $spatialextends)
    {
        $this->spatialExtent = $spatialextends;
    }

    /**
     * @return string
     */
    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * @param string $title
     */
    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    /**
     * @return DataConstraint
     */
    public function getDataConstraint(): DataConstraint
    {
        return $this->dataConstraint;
    }

    /**
     * @param DataConstraint $dataConstraint
     */
    public function setDataConstraint(DataConstraint $dataConstraint): void
    {
        $this->dataConstraint = $dataConstraint;
    }

    /**
     * @return string[]
     */
    public function getTopicCategories(): array
    {
        return $this->topicCategories;
    }

    /**
     * @param string $topicCategories
     */
    public function addTopicCategories(string $topicCategories): void
    {
        $this->topicCategories[] = $topicCategories;
    }

    /**
     * @return string
     */
    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * @param string $description
     */
    public function setDescription(string $description): void
    {
        $this->description = $description;
    }

    /**
     * @return string
     */
    public function getDatasetLineage(): string
    {
        return $this->datasetLineage;
    }

    /**
     * @param string $datasetLineage
     */
    public function setDatasetLineage(string $datasetLineage): void
    {
        $this->datasetLineage = $datasetLineage;
    }

    /**
     * @return string
     */
    public function getInspireTheme(): string
    {
        return $this->inspireTheme;
    }

    /**
     * @param string $inspireTheme
     */
    public function setInspireTheme(string $inspireTheme): void
    {
        $this->inspireTheme = $inspireTheme;
    }

    /**
     * @return Contact[]
     */
    public function getContacts(): array
    {
        return $this->contacts;
    }

    /**
     * @param Contact $contact
     */
    public function addContact(Contact $contact): void
    {
        $this->contacts[] = $contact;
    }

    /**
     * @return OnlineResource|null
     */
    public function getOnlineResource(): ?OnlineResource
    {
        return $this->onlineResource;
    }

    /**
     * @param OnlineResource $onlineRessource
     */
    public function setOnlineResource(OnlineResource $onlineRessource): void
    {
        $this->onlineResource = $onlineRessource;
    }
}
