<?php

namespace Irstea\BdohTheiaOzcarBundle\Model;

final class ObservedProperty
{
    /**
     * @var string
     */
    private $name = '';

    /**
     * @var string
     */
    private $unit = '';

    /**
     * @var string[]
     */
    public $theiaCategories = [];

    //--------------------------getter et setter----------------------------

    /**
     * @return string|null
     */
    public function getName(): ?string
    {
        return $this->name;
    }

    /**
     * @param string|null $name
     */
    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    /**
     * @return string
     */
    public function getUnit(): string
    {
        return $this->unit;
    }

    /**
     * @param string $unit
     */
    public function setUnit(string $unit): void
    {
        $this->unit = $unit;
    }

    /**
     * @return string[]
     */
    public function getTheiaCategories()
    {
        return $this->theiaCategories;
    }

    /**
     * @param string[] $theiaCategories
     */
    public function setTheiaCategories($theiaCategories)
    {
        $this->theiaCategories = $theiaCategories;
    }
}
