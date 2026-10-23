<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohLoggerBundle\Entity;

use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * HistoriqueAdministrationPartenaire.
 */
class HistoriqueAdministrationPartenaire extends HistoriqueAdministration
{
    /**
     * @var \Irstea\BdohDataBundle\Entity\Partenaire
     */
    protected $partenaire;

    /**
     * Set partenaire.
     *
     * @param \Irstea\BdohDataBundle\Entity\Partenaire $partenaire
     *
     * @return HistoriqueAdministrationPartenaire
     */
    public function setPartenaire(?\Irstea\BdohDataBundle\Entity\Partenaire $partenaire = null)
    {
        $this->partenaire = $partenaire;

        return $this;
    }

    /**
     * Get partenaire.
     *
     * @return \Irstea\BdohDataBundle\Entity\Partenaire
     */
    public function getPartenaire()
    {
        return $this->partenaire;
    }

    /**
     * @param TranslatorInterface $translator
     */
    public function constructDescription(TranslatorInterface $translator)
    {
        parent::constructDescription($translator);
        $this->setDescription(
            $this->getDescription() . "\n" .
            $translator->trans('historique.partenaire') . $translator->trans('deux_points') . ' ' .
            $this->getPartenaire()
        );
    }
}
