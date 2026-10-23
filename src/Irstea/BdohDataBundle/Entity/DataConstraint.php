<?php

namespace Irstea\BdohDataBundle\Entity;

class DataConstraint
{
    /**
     * @var int
     */
    protected $id;

    /**
     * @var string
     */
    protected $nom;
    //--------------------getter et setter

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getNom()
    {
        return $this->nom;
    }

    /**
     * @param $nom
     */
    public function setNom($nom)
    {
        $this->nom = $nom;
    }

    public function __toString()
    {
        return $this->getNom() ?: '';
    }
}
