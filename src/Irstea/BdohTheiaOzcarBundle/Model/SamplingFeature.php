<?php

namespace Irstea\BdohTheiaOzcarBundle\Model;

class SamplingFeature
{
    /**
     * @var string
     */
    private $name;

    /**
     *@var Geometry
     */
    public $geometry;

    /**
     * @var \stdClass
     */
    public $properties;

    /**
     * @var string
     */
    public $type;

    //------------------------------ctor------------------
    public function __construct()
    {
        $this->geometry = new Geometry();
    }

    //--------------------------------------getter/setter ---------------------------------------------------------

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
     * @return \stdClass
     */
    public function getProperties(): \stdClass
    {
        return $this->properties;
    }

    /**
     * @param \stdClass $properties
     */
    public function setProperties(\stdClass $properties): void
    {
        $this->properties = $properties;
    }

    /**
     * @return string
     */
    public function getType(): string
    {
        return 'Feature';
    }

    /**
     * @param string $type
     */
    public function setType(string $type): void
    {
        $this->type = $type;
    }
}
