<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohDataBundle\Entity\Repository;

/**
 * Class EchantillonnageRepository.
 */
class EchantillonnageRepository extends EntityRepository
{
    /**
     * @return \Doctrine\ORM\QueryBuilder
     */
    public function createOrderedQueryBuilder()
    {
        return parent::createQueryBuilder('e')->orderBy('e.ordreSortie', 'ASC');
    }

    /**
     * @return array
     */
    public function findAll(): array
    {
        return $this->createOrderedQueryBuilder()->getQuery()->getResult();
    }

    /**
     * @return array
     */
    public function getTypesEchant()
    {
        $all = $this->findAll();
        $result = [];
        foreach ($all as $echant) {
            $result[$echant->getCode()] = $echant;
        }

        return $result;
    }
}
