<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohDataBundle\Entity;

use Irstea\BdohBundle\Model\LabelledEntityInterface;
use Irstea\BdohBundle\Model\LabelledEntityTrait;

/**
 * Class PasEchantillonnage.
 */
class PasEchantillonnage implements LabelledEntityInterface
{
    use LabelledEntityTrait;

    /**
     * @var int
     */
    private $id;

    /**
     * @var int
     */
    protected $approxSecondes;

    /**
     * @var string
     */
    private $unite;

    /**
     * @var int
     */
    private $valeur;

    /**
     * Get id.
     *
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * Set unite.
     *
     * @param string $unite
     *
     * @return self
     */
    public function setUnite(string $unite): self
    {
        $this->unite = $unite;

        return $this;
    }

    /**
     * Get unite.
     *
     * @return string
     */
    public function getUnite(): string
    {
        return $this->unite;
    }

    /**
     * Set valeur.
     *
     * @param int $valeur
     *
     * @return self
     */
    public function setValeur(int $valeur): self
    {
        $this->valeur = $valeur;

        return $this;
    }

    /**
     * Get valeur.
     *
     * @return int
     */
    public function getValeur(): int
    {
        return $this->valeur;
    }

    /**
     * @return string
     */
    public function __toString(): string
    {
        return $this->valeur . ' ' . $this->unite;
    }

    /**
     * Set approxSecondes.
     *
     * @param int $approxSecondes
     *
     * @return self
     */
    public function setApproxSecondes(int $approxSecondes): self
    {
        $this->approxSecondes = $approxSecondes;

        return $this;
    }

    /**
     * Get approxSecondes.
     *
     * @return int
     */
    public function getApproxSecondes(): int
    {
        return $this->approxSecondes;
    }
}
