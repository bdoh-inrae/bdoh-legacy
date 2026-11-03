<?php
// SPDX-FileCopyrightText: The BDOH authors
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace Irstea\BdohSecurityBundle\Entity;

use Irstea\BdohDataBundle\Entity\SiteExperimental;
use Irstea\BdohDataBundle\Entity\SiteRelatedInterface;
use Irstea\BdohDataBundle\Entity\SiteRelatedTrait;

/**
 * Class RoleSite.
 */
class RoleSite extends Role implements SiteRelatedInterface
{
    use SiteRelatedTrait;

    /**
     * @var SiteExperimental
     */
    protected $site;

    /**
     * Set site.
     *
     * @param SiteExperimental $site
     */
    public function setSite(SiteExperimental $site)
    {
        $this->site = $site;
    }

    /**
     * Get site.
     *
     * @return SiteExperimental
     */
    public function getSite()
    {
        return $this->site;
    }

    /**
     * {@inheritdoc}
     */
    protected function isTarget($target)
    {
        return $target instanceof SiteExperimental && $target->getId() === $this->site->getId();
    }

    /**
     * {@inheritdoc}
     */
    public function getTarget()
    {
        return $this->site;
    }

    /**
     * {@inheritdoc}
     */
    public function setTarget($target)
    {
        $this->setSite($target);
    }

    /**
     * {@inheritdoc}
     */
    public function getSites()
    {
        return [$this->site];
    }
}
