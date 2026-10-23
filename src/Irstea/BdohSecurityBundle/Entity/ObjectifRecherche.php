<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohSecurityBundle\Entity;

use Irstea\BdohBundle\Model\LabelledEntityInterface;
use Irstea\BdohBundle\Model\LabelledEntityTrait;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

/**
 * Irstea\BdohSecurityBundle\Entity\ObjectifRecherche.
 */
#[UniqueEntity(fields: 'libelle')]
#[UniqueEntity(fields: 'libelleEn')]
class ObjectifRecherche implements LabelledEntityInterface
{
    use LabelledEntityTrait;

    /**
     * @var int
     */
    protected $id;

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
     * @return string
     */
    public function __toString()
    {
        return $this->getLibelle();
    }
}
