<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohDataBundle\Entity\Repository;

/**
 * TypeParametreRepository.
 */
class TypeParametreRepository extends EntityRepository
{
    /**
     * Returns all 'TypeParametre' entities that are linked with the given unite.
     *
     * @param \Irstea\BdohDataBundle\Entity\Unite $unite
     *
     * @return array
     */
    public function findByUnite($unite)
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.unites', 'u')
            ->where('u = :unite')
            ->setParameter('unite', $unite)
            ->getQuery()
            ->getResult();
    }
}
