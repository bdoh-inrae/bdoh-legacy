<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohLoggerBundle\Entity;

use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * HistoriqueAdministrationCommune.
 */
class HistoriqueAdministrationCommune extends HistoriqueAdministration
{
    /**
     * @var \Irstea\BdohDataBundle\Entity\Commune
     */
    protected $commune;

    /**
     * Set commune.
     *
     * @param \Irstea\BdohDataBundle\Entity\Commune $commune
     *
     * @return HistoriqueAdministrationCommune
     */
    public function setCommune(?\Irstea\BdohDataBundle\Entity\Commune $commune = null)
    {
        $this->commune = $commune;

        return $this;
    }

    /**
     * Get commune.
     *
     * @return \Irstea\BdohDataBundle\Entity\Commune
     */
    public function getCommune()
    {
        return $this->commune;
    }

    /**
     * @param TranslatorInterface $translator
     */
    public function constructDescription(TranslatorInterface $translator)
    {
        parent::constructDescription($translator);
        $this->setDescription(
            $this->getDescription() . "\n" .
            $translator->trans('historique.commune') . $translator->trans('deux_points') . ' ' .
            $this->getCommune()
        );
    }
}
