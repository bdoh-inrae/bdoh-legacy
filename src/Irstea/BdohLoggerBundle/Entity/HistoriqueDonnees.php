<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohLoggerBundle\Entity;

use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * HistoriqueDonnees.
 */
class HistoriqueDonnees extends Historique
{
    /**
     * @var string
     */
    protected $debut;

    /**
     * @var string
     */
    protected $fin;

    /**
     * @var int
     */
    protected $nombreMesures;

    /**
     * @var \Irstea\BdohDataBundle\Entity\Chronique
     */
    protected $chronique;

    /**
     * Set debut.
     *
     * @param string $debut
     *
     * @return HistoriqueDonnees
     */
    public function setDebut($debut)
    {
        $this->debut = $debut;

        return $this;
    }

    /**
     * Get debut.
     *
     * @return string
     */
    public function getDebut()
    {
        return $this->debut;
    }

    /**
     * @return \Datetime
     */
    public function getDebutDescription()
    {
        return new \DateTime($this->debut);
    }

    /**
     * Set fin.
     *
     * @param string $fin
     *
     * @return HistoriqueDonnees
     */
    public function setFin($fin)
    {
        $this->fin = $fin;

        return $this;
    }

    /**
     * Get fin.
     *
     * @return string
     */
    public function getFin()
    {
        return $this->fin;
    }

    /**
     * @return \Datetime
     */
    public function getFinDescription()
    {
        return new \DateTime($this->fin);
    }

    /**
     * Set nombreMesures.
     *
     * @param int $nombreMesures
     *
     * @return HistoriqueDonnees
     */
    public function setNombreMesures($nombreMesures)
    {
        $this->nombreMesures = $nombreMesures;

        return $this;
    }

    /**
     * Get nombreMesures.
     *
     * @return int
     */
    public function getNombreMesures()
    {
        return $this->nombreMesures;
    }

    /**
     * Set chronique.
     *
     * @param \Irstea\BdohDataBundle\Entity\Chronique $chronique
     *
     * @return HistoriqueDonnees
     */
    public function setChronique(?\Irstea\BdohDataBundle\Entity\Chronique $chronique = null)
    {
        $this->chronique = $chronique;

        return $this;
    }

    /**
     * Get chronique.
     *
     * @return \Irstea\BdohDataBundle\Entity\Chronique
     */
    public function getChronique()
    {
        return $this->chronique;
    }

    /**
     * @param TranslatorInterface $translator
     */
    public function constructDescription(TranslatorInterface $translator)
    {
        $this->setDescription(
            $translator->trans('historique.chronique') . $translator->trans('deux_points') . ' ' .
            $this->getChronique() . "\n" .
            $translator->trans('historique.debut') . $translator->trans('deux_points') . ' ' .
            $this->getDebutDescription()->format($translator->trans('transformation.bareme.dateBareme')) .
            ' ' . $translator->trans('UTC') . "\n" .
            $translator->trans('historique.fin') . $translator->trans('deux_points') . ' ' .
            $this->getFinDescription()->format($translator->trans('transformation.bareme.dateBareme')) .
            ' ' . $translator->trans('UTC') . "\n" .
            $translator->trans('historique.nbMesures') . $translator->trans('deux_points') . ' ' .
            $this->getNombreMesures()
        );
    }
}
