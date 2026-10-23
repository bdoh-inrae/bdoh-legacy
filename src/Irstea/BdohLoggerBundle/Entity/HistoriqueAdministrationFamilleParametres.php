<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohLoggerBundle\Entity;

use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * HistoriqueAdministrationFamilleParametres.
 */
class HistoriqueAdministrationFamilleParametres extends HistoriqueAdministration
{
    /**
     * @var \Irstea\BdohDataBundle\Entity\FamilleParametres
     */
    protected $familleParametres;

    /**
     * @var \Irstea\BdohDataBundle\Entity\FamilleParametres
     */
    protected $familleParente;

    /**
     * Set familleParametres.
     *
     * @param \Irstea\BdohDataBundle\Entity\FamilleParametres $familleParametres
     *
     * @return HistoriqueAdministrationFamilleParametres
     */
    public function setFamilleParametres(?\Irstea\BdohDataBundle\Entity\FamilleParametres $familleParametres = null)
    {
        $this->familleParametres = $familleParametres;

        return $this;
    }

    /**
     * Get familleParametres.
     *
     * @return \Irstea\BdohDataBundle\Entity\FamilleParametres
     */
    public function getFamilleParametres()
    {
        return $this->familleParametres;
    }

    /**
     * Set familleParente.
     *
     * @param \Irstea\BdohDataBundle\Entity\FamilleParametres $familleParente
     *
     * @return HistoriqueAdministrationFamilleParametres
     */
    public function setFamilleParente(?\Irstea\BdohDataBundle\Entity\FamilleParametres $familleParente = null)
    {
        $this->familleParente = $familleParente;

        return $this;
    }

    /**
     * Get familleParente.
     *
     * @return \Irstea\BdohDataBundle\Entity\FamilleParametres
     */
    public function getFamilleParente()
    {
        return $this->familleParente;
    }

    /**
     * @param TranslatorInterface $translator
     */
    public function constructDescription(TranslatorInterface $translator)
    {
        parent::constructDescription($translator);
        $inEnglish = \strpos($translator->getLocale(), 'en') === 0;
        $famille = $inEnglish ? $this->getFamilleParametres()->getNomEn() : $this->getFamilleParametres()->getNom();
        $parente = $this->getFamilleParente() ?
            ($inEnglish ? $this->getFamilleParente()->getNomEn() : $this->getFamilleParente()->getNom()) :
            $translator->trans('historique.noFamilleParente');
        $this->setDescription(
            $this->getDescription() . "\n" .
            $translator->trans('historique.familleParametres') . $translator->trans('deux_points') . ' ' .
            $famille . "\n" .
            $translator->trans('historique.familleParente') . $translator->trans('deux_points') . ' ' .
            $parente
        );
    }
}
