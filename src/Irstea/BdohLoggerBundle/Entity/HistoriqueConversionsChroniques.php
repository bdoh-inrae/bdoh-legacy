<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohLoggerBundle\Entity;

use Irstea\BdohBundle\Util\DurationFormater;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * HistoriqueConversionsChroniques.
 */
class HistoriqueConversionsChroniques extends HistoriqueTransformations
{
    /**
     * @var \Irstea\BdohDataBundle\Entity\ChroniqueConvertie
     */
    protected $chroniqueConvertie;

    /**
     * @var \Irstea\BdohDataBundle\Entity\ChroniqueDiscontinue
     */
    protected $chroniqueMere;

    /**
     * @var int
     */
    protected $paramConversion;

    /**
     * Set chroniqueConvertie.
     *
     * @param \Irstea\BdohDataBundle\Entity\ChroniqueConvertie $chroniqueConvertie
     *
     * @return HistoriqueConversionsChroniques
     */
    public function setChroniqueConvertie(?\Irstea\BdohDataBundle\Entity\ChroniqueConvertie $chroniqueConvertie = null)
    {
        $this->chroniqueConvertie = $chroniqueConvertie;

        return $this;
    }

    /**
     * Get chroniqueConvertie.
     *
     * @return \Irstea\BdohDataBundle\Entity\ChroniqueConvertie
     */
    public function getChroniqueConvertie()
    {
        return $this->chroniqueConvertie;
    }

    /**
     * Set chroniqueMere.
     *
     * @param \Irstea\BdohDataBundle\Entity\ChroniqueDiscontinue $chroniqueMere
     *
     * @return HistoriqueConversionsChroniques
     */
    public function setChroniqueMere(?\Irstea\BdohDataBundle\Entity\ChroniqueDiscontinue $chroniqueMere = null)
    {
        $this->chroniqueMere = $chroniqueMere;

        return $this;
    }

    /**
     * Get chroniqueMere.
     *
     * @return \Irstea\BdohDataBundle\Entity\ChroniqueDiscontinue
     */
    public function getChroniqueMere()
    {
        return $this->chroniqueMere;
    }

    /**
     * Set paramConversion.
     *
     * @param int $paramConversion
     *
     * @return HistoriqueConversionsChroniques
     */
    public function setParamConversion($paramConversion)
    {
        $this->paramConversion = $paramConversion;

        return $this;
    }

    /**
     * Get paramConversion.
     *
     * @return int
     */
    public function getParamConversion()
    {
        return $this->paramConversion;
    }

    /**
     * @param TranslatorInterface $translator
     */
    public function constructDescription(TranslatorInterface $translator)
    {
        $durationFormater = new DurationFormater($translator);
        $paramConversionMinutes = intdiv($this->getParamConversion(), 60);
        $this->setDescription(
            $translator->trans('historique.action') . $translator->trans('deux_points') . ' ' .
            $translator->trans('historique.actions.ConversionChronique') . "\n" .
            $translator->trans('historique.chronique') . $translator->trans('deux_points') . ' ' .
            $this->getChroniqueConvertie() . "\n" .
            $translator->trans('historique.chroniqueMere') . $translator->trans('deux_points') . ' ' .
            $this->getChroniqueMere() . "\n" .
            $translator->trans('historique.paramConversion') . $translator->trans('deux_points') . ' ' .
            $paramConversionMinutes . ' ' . $translator->trans('minute', ['%count%' => $paramConversionMinutes]) .
            (($paramConversionMinutes >= 60) ? ' (' . $durationFormater->format($this->getParamConversion()) . ')' : '')
        );
    }
}
