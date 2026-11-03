<?php
// SPDX-FileCopyrightText: The BDOH authors
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace Irstea\BdohDataBundle\Entity;

/**
 * Class ChroniqueRelatedInterface.
 */
interface ChroniqueRelatedInterface extends SiteRelatedInterface
{
    /**
     * @return Chronique
     */
    public function getChronique();
}
