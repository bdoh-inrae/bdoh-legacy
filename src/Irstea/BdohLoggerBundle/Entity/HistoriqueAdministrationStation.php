<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohLoggerBundle\Entity;

use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * HistoriqueAdministrationStation.
 */
class HistoriqueAdministrationStation extends HistoriqueAdministration
{
    /**
     * @var \Irstea\BdohDataBundle\Entity\Station
     */
    protected $station;

    /**
     * Set station.
     *
     * @param \Irstea\BdohDataBundle\Entity\Station $station
     *
     * @return HistoriqueAdministrationStation
     */
    public function setStation(?\Irstea\BdohDataBundle\Entity\Station $station = null)
    {
        $this->station = $station;

        return $this;
    }

    /**
     * Get station.
     *
     * @return \Irstea\BdohDataBundle\Entity\Station
     */
    public function getStation()
    {
        return $this->station;
    }

    /**
     * @param TranslatorInterface $translator
     */
    public function constructDescription(TranslatorInterface $translator)
    {
        parent::constructDescription($translator);
        $this->setDescription(
            $this->getDescription() . "\n" .
            $translator->trans('historique.station') . $translator->trans('deux_points') . ' ' .
            $this->getStation()->getNom() . ' (' . $this->getStation()->getCode() . ')'
        );
    }
}
