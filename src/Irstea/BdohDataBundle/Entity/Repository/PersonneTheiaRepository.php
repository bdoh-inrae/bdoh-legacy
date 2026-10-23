<?php

namespace Irstea\BdohDataBundle\Entity\Repository;

use Doctrine\ORM\QueryBuilder;

class PersonneTheiaRepository extends EntityRepository
{
    /**
     * @param string      $alias
     * @param string|null $indexBy
     *
     * @return QueryBuilder
     */
    public function createQueryBuilder(string $alias, ?string $indexBy = null): QueryBuilder
    {
        $qb = parent::createQueryBuilder($alias, $indexBy);

        $currentObs = $this->getCurrentObservatoire();
        if (!$currentObs) {
            return $qb;
        }

        return $qb
                ->leftJoin($alias . '.partenaire', 'pa')
                ->where('pa.observatoire = :observatoire')
                ->setParameter('observatoire', $currentObs);
    }
}
