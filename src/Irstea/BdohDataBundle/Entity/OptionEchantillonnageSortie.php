<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohDataBundle\Entity;

use Irstea\BdohBundle\Model\LabelledEntityInterface;

/**
 * Class OptionEchantillonnageSortie.
 */
class OptionEchantillonnageSortie implements LabelledEntityInterface
{
    /**
     * @var int
     */
    private $id;

    /**
     * Get id.
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @var Echantillonnage
     */
    private $echantillonnageEntree;

    /**
     * @var PasEchantillonnage
     */
    private $pasEchantillonnage;

    /**
     * Set echantillonnageEntree.
     *
     * @param Echantillonnage $echantillonnageEntree
     *
     * @return OptionEchantillonnageSortie
     */
    public function setEchantillonnageEntree(?Echantillonnage $echantillonnageEntree = null)
    {
        $this->echantillonnageEntree = $echantillonnageEntree;

        return $this;
    }

    /**
     * Get echantillonnageEntree.
     *
     * @return Echantillonnage
     */
    public function getEchantillonnageEntree()
    {
        return $this->echantillonnageEntree;
    }

    /**
     * Set pasEchantillonnage.
     *
     * @param PasEchantillonnage $pasEchantillonnage
     *
     * @return OptionEchantillonnageSortie
     */
    public function setPasEchantillonnage(?PasEchantillonnage $pasEchantillonnage = null)
    {
        $this->pasEchantillonnage = $pasEchantillonnage;

        return $this;
    }

    /**
     * Get pasEchantillonnage.
     *
     * @return PasEchantillonnage
     */
    public function getPasEchantillonnage()
    {
        return $this->pasEchantillonnage;
    }

    /**
     * @return string
     */
    public function __toString(): string
    {
        return $this->getLibelle();
    }

    /**
     * @param string $lang
     *
     * @return string
     */
    public function getLibelle(string $lang = 'fr'): string
    {
        return $this->pasEchantillonnage ? $this->pasEchantillonnage->getLibelle($lang) : '';
    }

    /**
     * @return string
     */
    public function getLibelleEn(): string
    {
        return $this->getLibelle('en');
    }
}
