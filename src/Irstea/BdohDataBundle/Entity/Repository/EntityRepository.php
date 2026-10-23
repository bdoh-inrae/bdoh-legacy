<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohDataBundle\Entity\Repository;

use Doctrine\ORM\EntityRepository as BaseEntityRepository;
use Irstea\BdohDataBundle\Entity\Observatoire;

/**
 * Class EntityRepository.
 */
class EntityRepository extends BaseEntityRepository
{
    /**
     * @param $entity
     *
     * @deprecated
     */
    public function getRepository($entity)
    {
        return $this->getEntityManager()->getRepository($entity);
    }

    /**
     * Shortcut to return the Doctrine repository suitable for an entity class.
     *
     * @param object $entity
     *
     * @deprecated
     */
    public function getClassRepo($entity)
    {
        return $this->getRepository(get_class($entity));
    }

    /**
     * Shortcut to return a Doctrine repository of a BDOH entity.
     *
     * @param string $bdohEntity
     *
     * @deprecated
     */
    public function getBdohRepo($bdohEntity)
    {
        return $this->getRepository('Irstea\BdohDataBundle\Entity\\' . $bdohEntity);
    }

    /**
     * Shortcut to return a Doctrine repository of a "BDOH Security" entity.
     *
     * @param string $securityEntity
     *
     * @deprecated
     */
    public function getSecurityRepo($securityEntity)
    {
        return $this->getRepository('Irstea\BdohSecurityBundle\Entity\\' . $securityEntity);
    }

    /**
     * Returns the current 'observatoire'.
     * If it doesn't exist or if it's the special "all observatoire", returns false.
     *
     * @return \Irstea\BdohDataBundle\Entity\Observatoire|false
     */
    public function getCurrentObservatoire()
    {
        $currentObs = Observatoire::getCurrent();

        return (!$currentObs) ? false : $currentObs;
    }

    /**
     * In a given array, replaces eventual entities by their ids.
     *
     * @param array  $array       Reference
     * @param string $classEntity Type of entities searched
     */
    public function extractId(array &$array, $classEntity)
    {
        // Gets id. of eventual $classEntity instances
        $array = array_map(
            function ($e) use ($classEntity) {
                return ($e instanceof $classEntity) ? $e->getId() : $e;
            },
            $array
        );

        return $array;
    }

    /**
     * Drops a temporary 'Shape' SQL table.
     * Empties the variable containing the SQL table name.
     *
     * @param string $tableName
     */
    public function dropTemporaryShapeTable($tableName)
    {
        try {
            $tmpTable = explode('.', $tableName);
            $this->getEntityManager()->getConnection()->executeStatement("DROP TABLE $tableName");
            $this->getEntityManager()->getConnection()->executeStatement("DELETE FROM geometry_columns WHERE f_table_name='$tmpTable[1]'");
            $tableName = null;
        } catch (\Exception $e) {
            //Table does not exist ; nothing to do.
        }
    }
}
