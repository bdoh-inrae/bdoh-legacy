<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohDataBundle\Entity\Repository;

use Doctrine\ORM\QueryBuilder;
use Irstea\BdohSecurityBundle\Entity\Role;
use Irstea\BdohSecurityBundle\Entity\Utilisateur;

/**
 * Class SiteExperimentalRepository.
 */
class SiteExperimentalRepository extends EntityRepository
{
    /**
     * Selects only entities linked to the current 'observatoire'.
     *
     * @param mixed      $alias
     * @param mixed|null $indexBy
     *
     * @return QueryBuilder
     */
    public function createQueryBuilder(string $alias, ?string $indexBy = null): QueryBuilder
    {
        $qb = parent::createQueryBuilder($alias, $indexBy = null);

        if (!$currentObs = $this->getCurrentObservatoire()) {
            return $qb;
        }

        return $qb
            ->where($alias . '.observatoire = :observatoire')
            ->setParameter('observatoire', $currentObs);
    }

    /**
     * Selects only entities linked to the current 'observatoire'
     * on which current user has sufficient rights.
     *
     * @param mixed $user
     * @param mixed $alias
     *
     * @return QueryBuilder
     */
    public function createSecuredQueryBuilder($user, $alias = 'si')
    {
        $qb = $this->createQueryBuilder($alias);

        if ($user instanceof Utilisateur) {
            $rolesOnSites = $user->getRolesSite();
            $rolesOnObs = $user->getRolesObservatoire();

            $clauses = [];
            $params = [];

            if ($rolesOnSites instanceof \Traversable) {
                $i = 0;
                foreach ($rolesOnSites as $role) {
                    if ($role->getValeur() === Role::GESTIONNAIRE) {
                        $site = $role->getSite();
                        $clauses[] = 'si = :site' . $i;
                        $params['site' . $i++] = $site;
                    }
                }
            }

            //This is a little too much ; kept here only for "logic explanation"
            //Only actual useful test would be against Observatoire::getCurrent()
            if ($rolesOnObs instanceof \Traversable) {
                $i = 0;
                foreach ($rolesOnObs as $role) {
                    if ($role->getValeur() === Role::GESTIONNAIRE) {
                        $obs = $role->getObservatoire();
                        $clauses[] = 'si.observatoire = :obs' . $i;
                        $params['obs' . $i++] = $obs;
                    }
                }
            }

            if ([] !== $clauses) {
                $qb = $qb->andWhere(implode(' or ', $clauses));
                foreach ($params as $key => $param) {
                    $qb = $qb->setParameter($key, $param);
                }
            } else { //No right => no site to be returned
                $qb = $qb->andWhere('true = false');
            }
        } else { //No user => no site to be returned
            $qb = $qb->andWhere('true = false');
        }

        return $qb;
    }

    /**
     * @return array
     */
    public function findAll(): array
    {
        return $this->createQueryBuilder('si')->orderBy('si.slug', 'ASC')->getQuery()->getResult();
    }

    /**
     * @param $user
     *
     * @return array
     */
    public function securedFindAll($user)
    {
        return $this->createSecuredQueryBuilder($user, 'si')->orderBy('si.slug', 'ASC')->getQuery()->getResult();
    }

    /**
     * Returns the 'chroniques' number, for all 'SiteExperimental' of current 'Observatoire'.
     *
     * Returns an array whose structure is :
     *      => Key   = id. of SiteExperimental ;
     *      => Value = number of linked chroniques
     */
    public function countChroniquesBySite()
    {
        $sql = 'SELECT si.id, COUNT(ch) ' .
            'FROM siteexperimental as si ' .
            'LEFT OUTER JOIN stations_sites as ss ON si.id = ss.site_id ' .
            'LEFT OUTER JOIN chronique as ch ON ch.station_id = ss.station_id ' .
            'WHERE si.observatoire_id = ' . $this->getCurrentObservatoire()->getId() . ' ' .
            'GROUP BY si.id';

        $results = $this->getEntityManager()->getConnection()->fetchAllAssociative($sql);

        $countChroniques = [];

        foreach ($results as $result) {
            $countChroniques[$result['id']] = $result['count'];
        }

        return $countChroniques;
    }
}
