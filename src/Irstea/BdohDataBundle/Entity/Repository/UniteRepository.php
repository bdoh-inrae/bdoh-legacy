<?php
// SPDX-FileCopyrightText: The BDOH authors
// SPDX-License-Identifier: AGPL-3.0-or-later

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
