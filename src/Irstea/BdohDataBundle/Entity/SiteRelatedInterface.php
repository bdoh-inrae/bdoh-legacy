<?php
// SPDX-FileCopyrightText: The BDOH authors
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace Irstea\BdohDataBundle\Entity;

/**
 * Interface SiteRelatedInterface.
 */
interface SiteRelatedInterface extends ObservatoireRelatedInterface
{
    /**
     * @return SiteExperimental[]
     */
    public function getSites();
}
