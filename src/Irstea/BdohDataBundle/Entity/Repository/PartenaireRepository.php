<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohDataBundle\Entity\Repository;

use Doctrine\ORM\QueryBuilder;

/**
 * Class PartenaireRepository.
 */
class PartenaireRepository extends EntityRepository
{
    /**
     * @param string $alias
     * @param null   $indexBy
     *
     * @return \Doctrine\ORM\QueryBuilder
     */
    public function createQueryBuilder(string $alias = 'p', ?string $indexBy = null): QueryBuilder
    {
        if (!$currentObs = $this->getCurrentObservatoire()) {
            return parent::createQueryBuilder($alias, $indexBy);
        }

        return parent::createQueryBuilder($alias = 'p', $indexBy)
            ->leftJoin('p.observatoire', 'o')
            ->where('o = :observatoire')
            ->setParameter('observatoire', $currentObs);
    }

    /**
     * Selects only entities linked to the current 'observatoire'.
     *
     * @param \Irstea\BdohDataBundle\Entity\Observatoire $observatoire
     *
     * @return QueryBuilder
     */
    public function findByObservatoire($observatoire)
    {
        return parent::createQueryBuilder('p')
            ->leftJoin('p.observatoire', 'o')
            ->where('o = :observatoire')
            ->andWhere('p.estFinanceur = true')
            ->setParameter('observatoire', $observatoire->getId());
    }

    /**
     * @return array
     */
    public function findAll(): array
    {
        return $this->createQueryBuilder()->getQuery()->getResult();
    }
}
