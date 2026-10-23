<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohLoggerBundle\Entity;

use Irstea\BdohDataBundle\Entity\Doi;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Description of HistoriqueAdministrationDoi.
 */
class HistoriqueAdministrationDoi extends HistoriqueAdministration
{
    /**
     * @var Doi
     */
    protected $doi;

    /**
     * set DOI.
     *
     * @param Doi $doi
     *
     * @return self
     */
    public function setDoi(?Doi $doi = null)
    {
        $this->doi = $doi;

        return $this;
    }

    /**
     * Get DOI.
     *
     * @return Doi
     */
    public function getDoi()
    {
        return $this->doi;
    }

    /**
     * @param TranslatorInterface $translator
     */
    public function constructDescription(TranslatorInterface $translator)
    {
        parent::constructDescription($translator);
        $this->setDescription(
            $this->getDescription() . "\n" .
            $translator->trans('historique.doi') . $translator->trans('deux_points') . ' ' .
            $this->getDoi()->getIdentifiant()
        );
    }
}
