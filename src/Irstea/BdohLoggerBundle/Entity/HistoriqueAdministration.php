<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohLoggerBundle\Entity;

use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * HistoriqueAdministration.
 */
class HistoriqueAdministration extends Historique
{
    /**
     * @var string
     */
    protected $action;

    /**
     * Set action.
     *
     * @param string $action
     *
     * @return HistoriqueAdministration
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
            $translator->trans($this->getAction())
        );
        //To be overwritten by subclasses.
    }
}
