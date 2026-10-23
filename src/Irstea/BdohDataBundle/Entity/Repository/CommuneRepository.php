<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohDataBundle\Entity\Repository;

/**
 * Class CommuneRepository.
 */
class CommuneRepository extends EntityRepository
{
    /**
     * @param string $alias
     * @param null   $indexBy
     *
     * @return \Doctrine\ORM\QueryBuilder
     */
    public function createOrderedQueryBuilder($alias = 'c', $indexBy = null)
    {
        return parent::createQueryBuilder($alias, $indexBy)->orderBy($alias . '.nom', 'ASC');
    }

    /**
     * @return array
     */
    public function findAll(): array
    {
        return $this->createOrderedQueryBuilder()->getQuery()->getResult();
    }
}
