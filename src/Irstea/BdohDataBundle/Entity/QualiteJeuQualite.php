<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohDataBundle\Entity;

/**
 * Class QualiteJeuQualite.
 */
class QualiteJeuQualite
{
    /**
     * @var int
     */
    protected $id;

    /**
     * @var Qualite
     */
    protected $traduction;

    /**
     * @var JeuQualite
     */
    protected $jeu;

    /**
     * @var Qualite
     */
    protected $qualite;

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
     * Set traduction.
     *
     * @param Qualite $traduction
     */
    public function setTraduction(Qualite $traduction)
    {
        $this->traduction = $traduction;
    }

    /**
     * Get traduction.
     *
     * @return Qualite
     */
    public function getTraduction()
    {
        return $this->traduction;
    }

    /**
     * Set jeu.
     *
     * @param JeuQualite $jeu
     */
    public function setJeu(JeuQualite $jeu)
    {
        $this->jeu = $jeu;
    }

    /**
     * Get jeu.
     *
     * @return JeuQualite
     */
    public function getJeu()
    {
        return $this->jeu;
    }

    /**
     * Set qualite.
     *
     * @param Qualite $qualite
     */
    public function setQualite(Qualite $qualite)
    {
        $this->qualite = $qualite;
    }

    /**
     * Get qualite.
     *
     * @return Qualite
     */
    public function getQualite()
    {
        return $this->qualite;
    }
}
