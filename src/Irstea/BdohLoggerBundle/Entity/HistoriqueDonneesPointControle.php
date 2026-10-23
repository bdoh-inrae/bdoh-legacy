<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohLoggerBundle\Entity;

use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * HistoriqueDonneesPointControle.
 */
class HistoriqueDonneesPointControle extends HistoriqueDonnees
{
    /**
     * @var string
     */
    protected $action;

    /**
     * @var string
     */
    protected $fuseau;

    /**
     * Set action.
     *
     * @param string $action
     *
     * @return HistoriqueDonneesPointControle
     */
    public function setAction($action)
    {
        $this->action = $action;

        return $this;
    }

    /**
     * Get action.
     *
     * @return string
     */
    public function getAction()
    {
        return $this->action;
    }

    /**
     * Set fuseau.
     *
     * @param string $fuseau
     *
     * @return HistoriqueDonneesPointControle
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
            $translator->trans($this->getAction()) . "\n" .
            $this->getDescription() . "\n" .
            $translator->trans('historique.fuseau') . $translator->trans('deux_points') . ' ' . $this->getFuseau()
        );
    }
}
