<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohDataBundle\Entity;

/**
 * Class Commune.
 */
class Commune
{
    /**
     * @var int
     */
    protected $id;

    /**
     * @var string
     */
    protected $nom;

    /**
     * @var string
     */
    protected $codePostal;

    /**
     * @var string
     */
    protected $codeInsee;

    /**
     * Get id.
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set nom.
     *
     * @param string $nom
     */
    public function setNom($nom)
    {
        $this->nom = $nom;
    }

    /**
     * Get nom.
     *
     * @return string
     */
    public function getNom()
    {
        return $this->nom;
    }

    /**
     * Set codePostal.
     *
     * @param string $codePostal
     */
    public function setCodePostal($codePostal)
    {
        $this->codePostal = $codePostal;
    }

    /**
     * Get codePostal.
     *
     * @return string
     */
    public function getCodePostal()
    {
        return $this->codePostal;
    }

    /**
     * Set codeInsee.
     *
     * @param string $codeInsee
     */
    public function setCodeInsee($codeInsee)
    {
        $this->codeInsee = $codeInsee;
    }

    /**
     * Get codeInsee.
     *
     * @return string
     */
    public function getCodeInsee()
    {
        return $this->codeInsee;
    }

    /**
     * To String.
     *
     * @return string
     */
    public function __toString()
    {
        $ts = $this->getNom() ?? '';
        $cp = $this->getCodePostal();
        if ($cp) {
            $ts .= ' (' . $cp . ')';
        }

        return $ts;
    }

    /**
     * Get the 'departement' number of a 'commune' from its 'codePostal'.
     *
     * @return string
     */
    public function getDepartement()
    {
        return $this->codePostal ? substr($this->codePostal, 0, 2) : '';
    }
}
