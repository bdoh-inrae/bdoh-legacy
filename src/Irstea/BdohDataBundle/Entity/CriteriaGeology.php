<?php

namespace Irstea\BdohDataBundle\Entity;

class CriteriaGeology
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
     * @param $id
     */
    public function setId($id)
    {
        $this->id = $id;
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
