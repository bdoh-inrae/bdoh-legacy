<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohDataBundle\Entity;

/**
 * Class TauxRemplissage.
 */
class TauxRemplissage implements ChroniqueRelatedInterface
{
    use ChroniqueRelatedTrait;

    /**
     * @var int
     */
    protected $id;

    /**
     * @var datetimems
     */
    protected $debut;

    /**
     * @var datetimems
     */
    protected $fin;

    /**
     * @var float
     */
    protected $taux;

    /**
     * @var float
     */
    protected $poids;

    /**
     * @var ChroniqueContinue
     */
    protected $chronique;

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
     * Set debut.
     *
     * @param datetimems $debut
     *
     * @return TauxRemplissage
     */
    public function setDebut($debut)
    {
        $this->debut = $debut;

        return $this;
    }

    /**
     * Get debut.
     *
     * @return datetimems
     */
    public function getDebut()
    {
        return $this->debut;
    }

    /**
     * Set fin.
     *
     * @param datetimems $fin
     *
     * @return TauxRemplissage
     */
    public function setFin($fin)
    {
        $this->fin = $fin;

        return $this;
    }

    /**
     * Get fin.
     *
     * @return datetimems
     */
    public function getFin()
    {
        return $this->fin;
    }

    /**
     * Set taux.
     *
     * @param float $taux
     *
     * @return TauxRemplissage
     */
    public function setTaux($taux)
    {
        $this->taux = $taux;

        return $this;
    }

    /**
     * Get taux.
     *
     * @return float
     */
    public function getTaux()
    {
        return $this->taux;
    }

    /**
     * Set poids.
     *
     * @param float $poids
     *
     * @return TauxRemplissage
     */
    public function setPoids($poids)
    {
        $this->poids = $poids;

        return $this;
    }

    /**
     * Get poids.
     *
     * @return float
     */
    public function getPoids()
    {
        return $this->poids;
    }

    /**
     * Set chronique.
     *
     * @param ChroniqueContinue $chronique
     *
     * @return TauxRemplissage
     */
    public function setChronique(?ChroniqueContinue $chronique = null)
    {
        $this->chronique = $chronique;

        return $this;
    }

    /**
     * Get chronique.
     *
     * @return ChroniqueContinue
     */
    public function getChronique()
    {
        return $this->chronique;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return $this->taux . '%';
    }
}
