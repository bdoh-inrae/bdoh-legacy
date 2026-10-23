<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohLoggerBundle\Entity;

use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * HistoriqueDonneesSuppression.
 */
class HistoriqueDonneesSuppression extends HistoriqueDonnees
{
    /**
     * @var bool
     */
    protected $changedIntoGap;

    /**
     * Set changedIntoGap.
     *
     * @param bool $changedIntoGap
     *
     * @return HistoriqueDonneesSuppression
     */
    public function setChangedIntoGap($changedIntoGap)
    {
        $this->changedIntoGap = $changedIntoGap;

        return $this;
    }

    /**
     * Get changedIntoGap.
     *
     * @return bool
     */
    public function getChangedIntoGap()
    {
        return $this->changedIntoGap;
    }

    /**
     * {@inheritdoc}
     */
    public function constructDescription(TranslatorInterface $translator)
    {
        parent::constructDescription($translator);
        $this->setDescription(
            $translator->trans('historique.action') . $translator->trans('deux_points') . ' ' .
            $translator->trans('historique.actions.MesurePlageSuppression') . "\n" .
            ($this->changedIntoGap ? $translator->trans('historique.changedIntoGap') . "\n" : '') .
            $this->getDescription()
        );
    }
}
