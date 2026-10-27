<?php

namespace Irstea\BdohTheiaOzcarBundle\Model;

final class Organisation
{
    /** @var string */
    private $iso3166 = '';

    /**
     * @var string
     */
    private $name = '';

    /**
     * @var string|null
     */
    private $idScanR = '';

    /**
     * @var string
     */
    private $role = 'Research group';

    //-----------------------------------getter et setter-----------------------------

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
    public function setName(string $name)
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

    /**
     * @return string
     */
    public function getRole(): string
    {
        return $this->role;
    }

    /**
     * @param string $role
     */
    public function setRole(string $role): void
    {
        $this->role = $role;
    }
}
