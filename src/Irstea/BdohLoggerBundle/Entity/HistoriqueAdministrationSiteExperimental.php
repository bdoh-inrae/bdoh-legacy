<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohLoggerBundle\Entity;

use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * HistoriqueAdministrationSiteExperimental.
 */
class HistoriqueAdministrationSiteExperimental extends HistoriqueAdministration
{
    /**
     * @var \Irstea\BdohDataBundle\Entity\SiteExperimental
     */
    protected $siteExperimental;

    /**
     * Set siteExperimental.
     *
     * @param \Irstea\BdohDataBundle\Entity\SiteExperimental $siteExperimental
     *
     * @return HistoriqueAdministrationSiteExperimental
     */
    public function setSiteExperimental(?\Irstea\BdohDataBundle\Entity\SiteExperimental $siteExperimental = null)
    {
        $this->siteExperimental = $siteExperimental;

        return $this;
    }

    /**
     * Get siteExperimental.
     *
     * @return \Irstea\BdohDataBundle\Entity\SiteExperimental
     */
    public function getSiteExperimental()
    {
        return $this->siteExperimental;
    }

    /**
     * @param TranslatorInterface $translator
     */
    public function constructDescription(TranslatorInterface $translator)
    {
        parent::constructDescription($translator);
        $this->setDescription(
            $this->getDescription() . "\n" .
            $translator->trans('historique.siteExperimental') . $translator->trans('deux_points') . ' ' .
            $this->getSiteExperimental()
        );
    }
}
