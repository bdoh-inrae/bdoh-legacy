<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohDataBundle\Entity\Repository;

/**
 * Class BaremeRepository.
 */
use Doctrine\ORM\QueryBuilder;

class BaremeRepository extends EntityRepository
{
    /**
     * Selects only entities linked to the current 'observatoire' for the list in Sonata Admin.
     *
     * @param mixed      $alias
     * @param mixed|null $indexBy
     *
     * @return \Doctrine\ORM\QueryBuilder
     */
    public function createQueryBuilder(string $alias = 'b', ?string $indexBy = null): QueryBuilder
    {
        return parent::createQueryBuilder($alias, $indexBy)
            ->where($alias . '.observatoire = :observatoire')
            ->setParameter('observatoire', $this->getCurrentObservatoire());
    }

    /**
     * @return \Doctrine\ORM\QueryBuilder
     */
    public function createOrderedQueryBuilder()
    {
        return parent::createQueryBuilder('b')->orderBy('b.dateCreation', 'DESC');
    }

    /**
     * @return array
     */
    public function findAll(): array
    {
        return $this->createOrderedQueryBuilder()->getQuery()->getResult();
    }

    /**
     * @param bool $includeTechnical
     *
     * @return array
     */
    public function securedFindAll($includeTechnical = true)
    {
        $where = 'b.observatoire = :observatoire';
        if ($includeTechnical) {
            $where .= ' OR b.observatoire IS NULL';
        }

        return $this->createOrderedQueryBuilder()
            ->andWhere($where)
            ->setParameter('observatoire', $this->getCurrentObservatoire())
            ->getQuery()->getResult();
    }

    /**
     * Returns all 'scales' entities that use the given unit.
     *
     * @param \Irstea\BdohDataBundle\Entity\Unite $unite
     *
     * @return array
     */
    public function findByUnite($unite)
    {
        return parent::createQueryBuilder('b')
            ->leftJoin('b.uniteEntree', 'ue')
            ->leftJoin('b.uniteSortie', 'us')
            ->where('ue = :unite or us = :unite')
            ->setParameter('unite', $unite)
            ->getQuery()
            ->getResult();
    }
}
