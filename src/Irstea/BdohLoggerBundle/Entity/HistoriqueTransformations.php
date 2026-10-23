<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohLoggerBundle\Entity;

use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Class HistoriqueTransformations.
 */
class HistoriqueTransformations extends Historique
{
    /**
     * @var string
     */
    private $action;

    /**
     * Set action.
     *
     * @param string $action
     *
     * @return HistoriqueTransformations
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
     * @param TranslatorInterface $translator
     */
    public function constructDescription(TranslatorInterface $translator)
    {
        $this->setDescription(
            $translator->trans('historique.action') . $translator->trans('deux_points') . ' ' .
            $this->getAction()
        );
        //To be overwritten by subclasses.
    }

    /**
     * @param TranslatorInterface $translator
     *
     * @return string
     */
    public function actionLine(TranslatorInterface $translator)
    {
        return $translator->trans('historique.action') . $translator->trans('deux_points') . ' ' .
            $translator->trans($this->getAction()) . "\n";
    }
}
