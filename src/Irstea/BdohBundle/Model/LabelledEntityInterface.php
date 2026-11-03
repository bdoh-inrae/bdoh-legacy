<?php declare(strict_types=1);
// SPDX-FileCopyrightText: The BDOH authors
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace Irstea\BdohBundle\Model;

/**
 * Interface LabelledEntityInterface.
 */
interface LabelledEntityInterface
{
    /**
     * Get libelle.
     *
     * @param string $lang
     *
     * @return string
     */
    public function getLibelle(string $lang = 'fr'): string;

    /**
     * Get libelleEn.
     *
     * @return string|null
     */
    public function getLibelleEn();
}
