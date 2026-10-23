<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohLoggerBundle\Entity;

use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * HistoriqueAdministrationUnite.
 */
class HistoriqueAdministrationUnite extends HistoriqueAdministration
{
    /**
     * @var \Irstea\BdohDataBundle\Entity\Unite
     */
    protected $unite;

    /**
     * Set unite.
     *
     * @param \Irstea\BdohDataBundle\Entity\Unite $unite
     *
     * @return HistoriqueAdministrationUnite
     */
    public function setUnite(?\Irstea\BdohDataBundle\Entity\Unite $unite = null)
    {
        $this->unite = $unite;

        return $this;
    }

    /**
     * Get unite.
     *
     * @return \Irstea\BdohDataBundle\Entity\Unite
     */
    public function getUnite()
    {
        return $this->unite;
    }

    /**
     * @param TranslatorInterface $translator
     */
    public function constructDescription(TranslatorInterface $translator)
    {
        parent::constructDescription($translator);
        $this->setDescription(
            $this->getDescription() . "\n" .
            $translator->trans('historique.unite') . $translator->trans('deux_points') . ' ' .
            $this->getUnite()
        );
    }
}
