<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohLoggerBundle\Entity;

use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * HistoriqueAdministrationTypeParametre.
 */
class HistoriqueAdministrationTypeParametre extends HistoriqueAdministration
{
    /**
     * @var \Irstea\BdohDataBundle\Entity\TypeParametre
     */
    protected $typeParametre;

    /**
     * @var \Irstea\BdohDataBundle\Entity\FamilleParametres
     */
    protected $familleParametres;

    /**
     * Set typeParametre.
     *
     * @param \Irstea\BdohDataBundle\Entity\TypeParametre $typeParametre
     *
     * @return HistoriqueAdministrationTypeParametre
     */
    public function setTypeParametre(?\Irstea\BdohDataBundle\Entity\TypeParametre $typeParametre = null)
    {
        $this->typeParametre = $typeParametre;

        return $this;
    }

    /**
     * Get typeParametre.
     *
     * @return \Irstea\BdohDataBundle\Entity\TypeParametre
     */
    public function getTypeParametre()
    {
        return $this->typeParametre;
    }

    /**
     * Set familleParametres.
     *
     * @param \Irstea\BdohDataBundle\Entity\FamilleParametres $familleParametres
     *
     * @return HistoriqueAdministrationTypeParametre
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
     * @param TranslatorInterface $translator
     */
    public function constructDescription(TranslatorInterface $translator)
    {
        parent::constructDescription($translator);
        $inEnglish = \strpos($translator->getLocale(), 'en') === 0;
        $type = $inEnglish ? $this->getTypeParametre()->getNomEn() : $this->getTypeParametre()->getNom();
        $famille = $this->getFamilleParametres() ?
            ($inEnglish ? $this->getFamilleParametres()->getNomEn() : $this->getFamilleParametres()->getNom()) :
            $translator->trans('historique.noFamilleParametres');
        $this->setDescription(
            $this->getDescription() . "\n" .
            $translator->trans('historique.typeParametre') . $translator->trans('deux_points') . ' ' .
            $type . "\n" .
            $translator->trans('historique.familleParametres') . $translator->trans('deux_points') . ' ' .
            $famille
        );
    }
}
