<?php

namespace Irstea\BdohTheiaOzcarBundle\Model;

final class Fundings
{
    /** @var string */
    private $type = '';

    /** @var string */
    private $iso3166 = '';

    /**
     * @var string
     */
    private $name = '';

    /**
     * @var string
     */
    private $idScanR;

    //------------------------------getter et setter-----------------------------------

    /**
     * @return string
     */
    public function getType(): ?string
    {
        return $this->type;
    }

    /**
     * @param string $type
     */
    public function setType(string $type): void
    {
        $this->type = $type;
    }

    /**
     * @return string
     */
    public function getIso3166(): string
    {
        return $this->iso3166;
    }

    /**
     * @param string $iso3166
     */
    public function setIso3166(string $iso3166): void
    {
        $this->iso3166 = $iso3166;
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
     * @return string|null
     */
    public function getIdScanR(): ?string
    {
        return $this->idScanR;
    }

    /**
     * @param string|null $idScanR
     */
    public function setIdScanR(?string $idScanR): void
    {
        $this->idScanR = $idScanR;
    }
}
