<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohLoggerBundle\Entity;

use Irstea\BdohDataBundle\Entity\Observatoire;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * HistoriqueAdministrationObservatoire.
 */
class HistoriqueAdministrationObservatoire extends HistoriqueAdministration
{
    /**
     * @var \Irstea\BdohDataBundle\Entity\Observatoire
     */
    protected $observatoireCible;

    /**
     * Set observatoireCible.
     *
     * @param \Irstea\BdohDataBundle\Entity\Observatoire $observatoireCible
     *
     * @return HistoriqueAdministrationObservatoire
     */
    public function setObservatoireCible(?Observatoire $observatoireCible = null)
    {
        $this->observatoireCible = $observatoireCible;

        return $this;
    }

    /**
     * Get observatoireCible.
     *
     * @return \Irstea\BdohDataBundle\Entity\Observatoire
     */
    public function getObservatoireCible()
    {
        return $this->observatoireCible;
    }

    /**
     * @param TranslatorInterface $translator
     */
    public function constructDescription(TranslatorInterface $translator)
    {
        parent::constructDescription($translator);
        $this->setDescription(
            $this->getDescription() . "\n" .
            $translator->trans('historique.observatoire') . $translator->trans('deux_points') . ' ' .
            $this->getObservatoireCible()
        );
    }
}
