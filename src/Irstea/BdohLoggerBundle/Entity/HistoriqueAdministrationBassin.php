<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohLoggerBundle\Entity;

use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * HistoriqueAdministrationBassin.
 */
class HistoriqueAdministrationBassin extends HistoriqueAdministration
{
    /**
     * @var \Irstea\BdohDataBundle\Entity\Bassin
     */
    protected $bassin;

    /**
     * Set bassin.
     *
     * @param \Irstea\BdohDataBundle\Entity\Bassin $bassin
     *
     * @return HistoriqueAdministrationBassin
     */
    public function setBassin(?\Irstea\BdohDataBundle\Entity\Bassin $bassin = null)
    {
        $this->bassin = $bassin;

        return $this;
    }

    /**
     * Get bassin.
     *
     * @return \Irstea\BdohDataBundle\Entity\Bassin
     */
    public function getBassin()
    {
        return $this->bassin;
    }

    /**
     * @param TranslatorInterface $translator
     */
    public function constructDescription(TranslatorInterface $translator)
    {
        parent::constructDescription($translator);
        $this->setDescription(
            $this->getDescription() . "\n" .
            $translator->trans('historique.bassin') . $translator->trans('deux_points') . ' ' .
            $this->getBassin()
        );
    }
}
