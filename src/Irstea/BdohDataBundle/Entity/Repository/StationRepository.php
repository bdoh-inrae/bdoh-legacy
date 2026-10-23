<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohDataBundle\Entity\Repository;

use Doctrine\ORM\QueryBuilder;
use Irstea\BdohDataBundle\Entity\Station;
use Irstea\BdohSecurityBundle\Entity\Role;
use Irstea\BdohSecurityBundle\Entity\Utilisateur;

/**
 * Class StationRepository.
 */
class StationRepository extends EntityRepository
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
        $qb = parent::createQueryBuilder($alias, $indexBy);

        if (!$currentObs = $this->getCurrentObservatoire()) {
            return $qb;
        }

        return $qb
            ->leftJoin($alias . '.sites', 'si')
            ->where('si.observatoire = :observatoire')
            ->setParameter('observatoire', $currentObs);
    }

    /**
     * Selects only entities linked to the current 'observatoire'
     * on which current user has rights to import data.
     *
     * @param mixed      $user
     * @param mixed      $alias
     * @param mixed|null $qb
     *
     * @return QueryBuilder
     */
    public function createSecuredQueryBuilderForMesure($user, $alias = 'st', $qb = null)
    {
        $needSite = false;
        if ($qb === null) {
            $qb = $this->createQueryBuilder($alias);
        } else {
            $needSite = true;
        }

        if ($user instanceof Utilisateur) {
            $rolesOnSites = $user->getRolesSite();
            $rolesOnObs = $user->getRolesObservatoire();

            $clauses = [];
            $params = [];

            if ($rolesOnSites instanceof \Traversable) {
                $i = 0;
                foreach ($rolesOnSites as $role) {
                    if ($role->getValeur() === Role::GESTIONNAIRE || $role->getValeur() === Role::CONTRIBUTEUR) {
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
                if ($needSite) {
                    $qb = $qb->leftJoin($alias . '.sites', 'si');
                }
                $qb = $qb->andWhere(implode(' or ', $clauses));
                foreach ($params as $key => $param) {
                    $qb = $qb->setParameter($key, $param);
                }
            } else { //No right => no station to be returned
                $qb = $qb->andWhere('true = false');
            }
        } else { //No user => no station to be returned
            $qb = $qb->andWhere('true = false');
        }

        $qb->orderBy('st.nom', 'ASC');

        return $qb;
    }

    /**
     * @return array
     */
    public function findAll(): array
    {
        return $this->createQueryBuilder('st')->orderBy('st.nom', 'ASC')->getQuery()->getResult();
    }

    /**
     * @param $user
     *
     * @return array
     */
    public function securedFindAllForMesure($user)
    {
        return $this->createSecuredQueryBuilderForMesure($user, 'st')->orderBy('st.nom', 'ASC')->getQuery()->getResult();
    }

    /**
     * @param string $code
     *
     * @throws \Exception
     *
     * @return Station
     */
    public function findOneByCode($code)
    {
        return $this->createQueryBuilder('st')
            ->andWhere('st.code = :code')->setParameter('code', $code)
            ->getQuery()
            ->getSingleResult();
    }

    /**
     * Gets the first and last dates by 'chronique', for a given 'station'.
     *
     * @param Station $station
     *
     * @return array Key = 'chronique' id. ; Value = array of ['first', 'last'].
     */
    public function getFirstLastDatesByChronique(Station $station)
    {
        // First and last dates by 'chronique', only for 'ChroniqueContinue'
        $qbMesure = $this->getBdohRepo('Mesure')
            ->createFirstLastDates()
            ->addSelect('ch.id')->groupBy('ch.id')
            ->andWhere('st.id = :station')->setParameter('station', $station);

        $datesMesure = $qbMesure->getQuery()->getResult();

        // First and last dates by 'chronique', only for 'ChroniqueDiscontinue'
        $qbPlage = $this->getBdohRepo('Plage')
            ->createFirstLastDates()
            ->addSelect('ch.id')->groupBy('ch.id')
            ->andWhere('st.id = :station')->setParameter('station', $station);

        $datesPlage = $qbPlage->getQuery()->getResult();

        // Puts the result in form : Key = 'chronique' id. ; Value = array of ['first', 'last']
        $results = array_merge($datesMesure, $datesPlage);
        $dates = [];

        foreach ($results as $result) {
            $dates[$result['id']] = [
                'first' => $result['first'],
                'last'  => $result['last'],
            ];
        }

        return $dates;
    }

    /**
     * @param $station
     *
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    public function updatePosition($station)
    {
        if ($station instanceof Station) {
            if ($station->getLatitude() !== null && $station->getLongitude() !== null) {
                $station->setPoint(
                    'SRID=' . Station::SRID . ';POINT(' . $station->getLatitude() . ' ' . $station->getLongitude() . ')'
                );
            } else {
                $station->setLatitude(null);
                $station->setLongitude(null);
                $station->setPoint(null);
            }
            $this->getEntityManager()->persist($station);
            $this->getEntityManager()->flush();
        }
    }

    /**
     * @param string $tableName
     *
     * @return array
     */
    public function getDataFromShapeTable($tableName)
    {
        try {
            // récupération des stations de l'observatoire courant
            $allStations = $this->findAll();
            $allStationsCodes = [];
            $allStationsGeoCodes = [];
            foreach ($allStations as $station) {
                /* @var Station $station */
                $allStationsCodes[] = $station->getCode();
                if ($station->getPoint() !== null) {
                    $allStationsGeoCodes[] = $station->getCode();
                }
            }

            // requêtes d'analyse des stations importées dans la table temporaire crée par shp2pgsql

            // requête de base
            $baseQuery =
                "SELECT UPPER(code) AS code, ST_X(ST_GeometryN(geom, 1)) AS latitude, ST_Y(ST_GeometryN(geom, 1)) AS longitude
                FROM $tableName";
            $baseCodeOk = ' AND code IS NOT NULL';
            $baseOrderBy = ' ORDER BY code ASC';

            // requête sur les station existantes SANS position géographique
            $sqlParamsArray = [];
            $sqlTypesArray = [];
            $stationsQuery = $baseQuery;
            if ($allStationsCodes !== []) {
                $stationsQuery .= ' WHERE UPPER(code) IN (?)';
                $sqlParamsArray[] = $allStationsCodes;
                $sqlTypesArray[] = \Doctrine\DBAL\ArrayParameterType::STRING;
                if ($allStationsGeoCodes !== []) {
                    $stationsQuery .= ' AND UPPER(code) NOT IN (?)';
                    $sqlParamsArray[] = $allStationsGeoCodes;
                    $sqlTypesArray[] = \Doctrine\DBAL\ArrayParameterType::STRING;
                }
            } else {
                $stationsQuery .= ' WHERE FALSE';
            }
            $stationsQuery .= $baseCodeOk . $baseOrderBy;
            $stations = $this->getEntityManager()->getConnection()->fetchAllAssociative($stationsQuery, $sqlParamsArray, $sqlTypesArray);

            // requête sur les station existantes AVEC position géographique
            $sqlParamsArray = [];
            $sqlTypesArray = [];
            $stationsGeoQuery = $baseQuery;
            if ($allStationsGeoCodes !== []) {
                $stationsGeoQuery .= ' WHERE UPPER(code) IN (?)';
                $sqlParamsArray[] = $allStationsGeoCodes;
                $sqlTypesArray[] = \Doctrine\DBAL\ArrayParameterType::STRING;
            } else {
                $stationsGeoQuery .= ' WHERE FALSE';
            }
            $stationsGeoQuery .= $baseCodeOk . $baseOrderBy;
            $stationsGeo = $this->getEntityManager()->getConnection()->fetchAllAssociative($stationsGeoQuery, $sqlParamsArray, $sqlTypesArray);

            // requête sur les station inexistantes
            $sqlParamsArray = [];
            $sqlTypesArray = [];
            $stationsNopeQuery = $baseQuery;
            if ($allStationsCodes !== []) {
                $stationsNopeQuery .= ' WHERE UPPER(code) NOT IN (?)';
                $sqlParamsArray[] = $allStationsCodes;
                $sqlTypesArray[] = \Doctrine\DBAL\ArrayParameterType::STRING;
            } else {
                $stationsNopeQuery .= ' WHERE TRUE';
            }
            $stationsNopeQuery .= $baseCodeOk . $baseOrderBy;
            $stationsNope = $this->getEntityManager()->getConnection()->fetchAllAssociative($stationsNopeQuery, $sqlParamsArray, $sqlTypesArray);
        } catch (\Exception $e) {
            $this->dropTemporaryShapeTable($tableName);

            return ['error' => 'station'];
        }

        return [
            'cible'                    => 'station',
            'existingWithData'         => $stationsGeo,
            'countExistingWithData'    => count($stationsGeo),
            'existingWithoutData'      => $stations,
            'countExistingWithoutData' => count($stations),
            'nonExisting'              => $stationsNope,
            'countNonExisting'         => count($stationsNope),
        ];
    }

    /**
     * @param string $tableName
     *
     * @throws \Doctrine\DBAL\Exception
     *
     * @return array
     */
    public function shapeKeepExistingImport($tableName)
    {
        // récupération de l'id de l'observatoire courant
        $currentObsId = $this->getCurrentObservatoire()->getId();

        // requêtes de mise à jour des stations de l'observatoire courant SANS position géographique uniquement
        // et à partir des stations importées dans la table temporaire crée par shp2pgsql
        $updateQuery = <<<SQL
UPDATE station st SET point = ST_GeometryN(t.geom, 1), latitude=ST_X(ST_GeometryN(t.geom, 1)), longitude=ST_Y(ST_GeometryN(t.geom, 1))
FROM $tableName t, stations_sites ss, siteexperimental si
WHERE UPPER(t.code)=st.code AND st.id = ss.station_id AND ss.site_id = si.id AND si.observatoire_id = $currentObsId AND st.point IS NULL
SQL;

        $inserts = $this->getEntityManager()->getConnection()->executeStatement($updateQuery);

        return [$inserts, 0];
    }

    /**
     * @param string $tableName
     *
     * @throws \Doctrine\DBAL\Exception
     *
     * @return array
     */
    public function shapeOverwriteExistingImport($tableName)
    {
        // récupération de l'id de l'observatoire courant
        $currentObsId = $this->getCurrentObservatoire()->getId();

        // requêtes de mise à jour des stations de l'observatoire courant AVEC et SANS position géographique
        // et à partir des stations importées dans la table temporaire crée par shp2pgsql
        $updateQuery = <<<SQL
UPDATE station st SET point = ST_GeometryN(t.geom, 1), latitude=ST_X(ST_GeometryN(t.geom, 1)), longitude=ST_Y(ST_GeometryN(t.geom, 1))
FROM $tableName t, stations_sites ss, siteexperimental si
WHERE UPPER(t.code)=st.code AND st.id = ss.station_id AND ss.site_id = si.id AND si.observatoire_id = $currentObsId AND st.point IS NOT NULL
SQL;

        // faire l'update avant l'insert sinon le résultat est faussé
        $updates = $this->getEntityManager()->getConnection()->executeStatement($updateQuery);

        return [$this->shapeKeepExistingImport($tableName)[0], $updates];
    }
}
