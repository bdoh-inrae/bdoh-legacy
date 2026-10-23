<?php declare(strict_types=1);
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

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
