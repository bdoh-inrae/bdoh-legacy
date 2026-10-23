<?php

namespace Irstea\BdohDataBundle\Entity\Repository;

class TheiaCategoriesRepository extends EntityRepository
{
    /**
     * @return array
     */
    public function findAll(): array
    {
        return $this->createQueryBuilder('tc')->getQuery()->getResult();
    }
}
