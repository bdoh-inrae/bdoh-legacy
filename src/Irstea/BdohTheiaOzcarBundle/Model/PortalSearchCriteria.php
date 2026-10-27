<?php

namespace Irstea\BdohTheiaOzcarBundle\Model;

class PortalSearchCriteria
{
    /**
     * @var string[]
     */
    private $climates = [];

    /**
     * @var string[]
     */
    private $geologies = [];

    //--------------------getter et setter--------

    /**
     * @return string[]
     */
    public function getClimates(): array
    {
        return $this->climates;
    }

    /**
     * @param string $climate
     */
    public function addClimate(string $climate): void
    {
        $this->climates[] = $climate;
    }

    /**
     * @return string[]
     */
    public function getGeologies(): array
    {
        return $this->geologies;
    }

    /**
     * @param string $geology
     */
    public function addGeology(string $geology): void
    {
        $this->geologies[] = $geology;
    }
}
