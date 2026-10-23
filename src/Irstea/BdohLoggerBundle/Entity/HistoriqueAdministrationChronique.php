<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohLoggerBundle\Entity;

use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * HistoriqueAdministrationChronique.
 */
class HistoriqueAdministrationChronique extends HistoriqueAdministration
{
    /**
     * @var \Irstea\BdohDataBundle\Entity\Chronique
     */
    protected $chronique;

    /**
     * Set chronique.
     *
     * @param \Irstea\BdohDataBundle\Entity\Chronique $chronique
     *
     * @return HistoriqueAdministrationChronique
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
        parent::constructDescription($translator);
        $this->setDescription(
            $this->getDescription() . "\n" .
            $translator->trans('historique.chronique') . $translator->trans('deux_points') . ' ' .
            $this->getChronique()
        );
    }
}
