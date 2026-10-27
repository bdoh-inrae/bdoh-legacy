<?php

namespace Irstea\BdohTheiaOzcarBundle\Model;

use Doctrine\Common\Collections\ArrayCollection;

final class Geometry
{
    /**
     * @var string
     */
    private $type = '';

    /**
     * @var float[]|float
     */
    private $coordinates;

    public function __construct()
    {
        $this->coordinates = new ArrayCollection();
    }

    //-----------------------------------getter et setter-----------------------------------------------

    /**
     * @return float|float[]
     */
    public function getCoordinates()
    {
        return $this->coordinates;
    }

    /**
     * @param float|float[] $coordinates
     */
    public function setCoordinates($coordinates)
    {
        $this->coordinates = $coordinates;
    }

    /**
     * @return string
     */
    public function getType(): string
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
}
