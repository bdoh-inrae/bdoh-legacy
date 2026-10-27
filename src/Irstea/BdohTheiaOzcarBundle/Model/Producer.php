<?php

namespace Irstea\BdohTheiaOzcarBundle\Model;

class Producer
{
    /**
     * @var string
     */
    private $producerId = '';

    /**
     * @var string
     */
    private $name = '';

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
    private $email = '';

    /**
     * @var Fundings[]
     */
    private $fundings = [];

    /**
     * @var Contact[]
     */
    public $contacts = [];

    /**
     * @var OnlineResource
     */
    private $onlineResource;

    //------------------------------------getter et setter

    /**
     * @return string|null
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
     * @return string
     */
    public function getProducerId(): string
    {
        return $this->producerId;
    }

    /**
     * @param string $producerId
     */
    public function setProducerId(string $producerId): void
    {
        $this->producerId = $producerId;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @param string $name
     */
    public function setName(string $name): void
    {
        $this->name = $name;
    }

    /**
     * @return string
     */
    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * @param string
     */
    public function setDescription(string $description): void
    {
        $this->description = $description;
    }

    /**
     * @return string|null
     */
    public function getEmail(): ?string
    {
        return $this->email;
    }

    /**
     * @param string|null $email
     */
    public function setEmail(?string $email): void
    {
        $this->email = $email;
    }

    /**
     * @return Fundings[]
     */
    public function getFundings(): array
    {
        return $this->fundings;
    }

    /**
     * @param Fundings $fundings
     */
    public function setFundings(Fundings $fundings): void
    {
        $this->fundings[] = $fundings;
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
    public function setContact(Contact $contact): void
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
