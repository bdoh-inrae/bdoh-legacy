<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohLoggerBundle\Entity;

use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * HistoriqueDonneesExport.
 */
class HistoriqueDonneesExport extends HistoriqueDonnees
{
    /**
     * @var string
     */
    protected $formatExport;

    /**
     * @var string
     */
    protected $typeExport;

    /**
     * @var string
     */
    protected $timestep;

    /**
     * @var string
     */
    protected $fuseau;

    /**
     * Set formatExport.
     *
     * @param string $formatExport
     *
     * @return HistoriqueDonneesExport
     */
    public function setFormatExport($formatExport)
    {
        $this->formatExport = $formatExport;

        return $this;
    }

    /**
     * Get formatExport.
     *
     * @return string
     */
    public function getFormatExport()
    {
        return $this->formatExport;
    }

    /**
     * Set typeExport.
     *
     * @param string $typeExport
     *
     * @return HistoriqueDonneesExport
     */
    public function setTypeExport($typeExport)
    {
        $this->typeExport = $typeExport;

        return $this;
    }

    /**
     * Get typeExport.
     *
     * @return string
     */
    public function getTypeExport()
    {
        return $this->typeExport;
    }

    /**
     * Set timestep.
     *
     * @param string $timestep
     *
     * @return HistoriqueDonneesExport
     */
    public function setTimestep($timestep)
    {
        $this->timestep = $timestep;

        return $this;
    }

    /**
     * Get timestep.
     *
     * @return string
     */
    public function getTimestep()
    {
        return $this->timestep;
    }

    /**
     * Set fuseau.
     *
     * @param string $fuseau
     *
     * @return HistoriqueDonneesExport
     */
    public function setFuseau($fuseau)
    {
        $this->fuseau = $fuseau;

        return $this;
    }

    /**
     * Get fuseau.
     *
     * @return string
     */
    public function getFuseau()
    {
        return $this->fuseau;
    }

    /**
     * @param TranslatorInterface $translator
     */
    public function constructDescription(TranslatorInterface $translator)
    {
        parent::constructDescription($translator);

        $timestep = json_decode($this->getTimestep(), true);
        if (\strpos($translator->getLocale(), 'en') === 0) {
            $timestep = $timestep['libelleEn'];
        } else {
            $timestep = $timestep['libelle'];
        }

        $this->setDescription(
            $translator->trans('historique.action') . $translator->trans('deux_points') . ' ' .
            $translator->trans('historique.actions.MesurePlageExport') . "\n" .
            $this->getDescription() . "\n" .
            $translator->trans('historique.formatExport') . $translator->trans('deux_points') . ' ' .
            $translator->trans('export.' . ucfirst($this->getFormatExport())) . "\n" .
            $translator->trans('historique.typeExport') . $translator->trans('deux_points') . ' ' .
            $translator->trans('historique.exportTypes.' . $this->getTypeExport()) . "\n" .
            ($timestep === '' ? '' :
                $translator->trans('historique.timestep') . $translator->trans('deux_points') . ' ' .
                $timestep . "\n") .
            $translator->trans('historique.fuseau') . $translator->trans('deux_points') . ' ' . $this->getFuseau()
        );
    }
}
