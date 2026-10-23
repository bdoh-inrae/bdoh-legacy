<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohDataBundle\Entity\Repository;

/**
 * Class UniteRepository.
 */
class UniteRepository extends EntityRepository
{
    /**
     * @param string $alias
     * @param null   $indexBy
     *
     * @return \Doctrine\ORM\QueryBuilder
     */
    public function createOrderedQueryBuilder($alias = 'u', $indexBy = null)
    {
        return parent::createQueryBuilder($alias, $indexBy)->orderBy($alias . '.libelle', 'ASC');
    }

    /**
     * @return array
     */
    public function findAll(): array
    {
        return $this->createOrderedQueryBuilder()->getQuery()->getResult();
    }
}
