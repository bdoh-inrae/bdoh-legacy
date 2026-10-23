<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohLoggerBundle\Entity;

use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * HistoriqueDonneesImport.
 */
class HistoriqueDonneesImport extends HistoriqueDonnees
{
    /**
     * @var string
     */
    protected $formatImport;

    /**
     * @var string
     */
    protected $fuseau;

    /**
     * Set formatImport.
     *
     * @param string $formatImport
     *
     * @return HistoriqueDonneesImport
     */
    public function setFormatImport($formatImport)
    {
        $this->formatImport = $formatImport;

        return $this;
    }

    /**
     * Get formatImport.
     *
     * @return string
     */
    public function getFormatImport()
    {
        return $this->formatImport;
    }

    /**
     * Set fuseau.
     *
     * @param string $fuseau
     *
     * @return HistoriqueDonneesImport
     */
    public function setFuseau($fuseau)
    {
        $this->fuseau = $fuseau;

        return $this;
    }

    /**
     * Get fuseau.
     *
     * @return string
     */
    public function getFuseau()
    {
        return $this->fuseau;
    }

    /**
     * @param TranslatorInterface $translator
     */
    public function constructDescription(TranslatorInterface $translator)
    {
        parent::constructDescription($translator);
        $this->setDescription(
            $translator->trans('historique.action') . $translator->trans('deux_points') . ' ' .
            $translator->trans('historique.actions.MesurePlageImport') . "\n" .
            $this->getDescription() . "\n" .
            $translator->trans('historique.formatImport') . $translator->trans('deux_points') . ' ' .
            $translator->trans('import.' . $this->getFormatImport()) . "\n" .
            $translator->trans('historique.fuseau') . $translator->trans('deux_points') . ' ' . $this->getFuseau()
        );
    }
}
