<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohDataBundle\Entity\Repository;

/**
 * Description of DoiRepository.
 */
use Doctrine\ORM\QueryBuilder;

class DoiRepository extends EntityRepository
{
    /**
     * Selects only entities linked to the current 'observatoire'.
     *
     * @param mixed      $alias
     * @param mixed|null $indexBy
     *
     * @return \Doctrine\ORM\QueryBuilder
     */
    public function createQueryBuilder(string $alias, ?string $indexBy = null): QueryBuilder
    {
        $qb = parent::createQueryBuilder($alias, $indexBy);

        if (!$currentObs = $this->getCurrentObservatoire()) {
            return $qb;
        }

        return $qb
            ->where($alias . '.observatoire = :observatoire')
            ->setParameter('observatoire', $currentObs);
    }
}
