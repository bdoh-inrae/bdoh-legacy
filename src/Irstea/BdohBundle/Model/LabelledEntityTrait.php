<?php declare(strict_types=1);
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohBundle\Model;

use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

#[UniqueEntity(fields: 'libelle')]
#[UniqueEntity(fields: 'libelleEn')]
trait LabelledEntityTrait
{
    /**
     * @var string
     */
    protected $libelle;

    /**
     * @var string
     */
    protected $libelleEn;

    /**
     * Set libelle.
     *
     * @param string|null $libelle
     * @param string      $lang
     *
     * @return $this
     */
    public function setLibelle(string $libelle, string $lang = 'fr')
    {
        if (\strpos($lang, 'fr') === 0) {
            $this->libelle = $libelle;
        } else {
            $this->libelleEn = $libelle;
        }

        return $this;
    }

    /**
     * Get libelle.
     *
     * @param string $lang
     *
     * @return string
     */
    public function getLibelle(string $lang = 'fr'): string
    {
        if (\strpos($lang, 'fr') === 0) {
            return $this->libelle ?? $this->libelleEn ?? '';
        }

        return $this->libelleEn ?? $this->libelle;
    }

    /**
     * Set libelleEn.
     *
     * @param string|null $libelleEn
     */
    public function setLibelleEn(?string $libelleEn = null)
    {
        $this->libelleEn = $libelleEn;
    }

    /**
     * Get libelleEn.
     *
     * @return string|null
     */
    public function getLibelleEn()
    {
        return $this->libelleEn;
    }
}
