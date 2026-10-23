<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohDataBundle\Entity\Repository;

use Doctrine\ORM\QueryBuilder;
use Irstea\BdohDataBundle\Entity\Bassin;
use Irstea\BdohDataBundle\Entity\Observatoire;
use Irstea\BdohDataBundle\Entity\Station;

/**
 * Class BassinRepository.
 */
class BassinRepository extends EntityRepository
{
    /**
     * Selects only entities linked to the current 'observatoire' for the list in Sonata Admin.
     *
     * @param mixed      $alias
     * @param mixed|null $indexBy
     *
     * @return \Doctrine\ORM\QueryBuilder
     */
    public function createQueryBuilder(string $alias = 'ba', ?string $indexBy = null): QueryBuilder
    {
        if (!$currentObs = $this->getCurrentObservatoire()) {
            return parent::createQueryBuilder($alias);
        }

        return parent::createQueryBuilder($alias, $indexBy)
            ->where($alias . '.observatoire = :observatoire')
            ->setParameter('observatoire', $currentObs);
    }

    /**
     * @return array
     */
    public function findAll(): array
    {
        return $this->createQueryBuilder('ba')->orderBy('ba.nom', 'ASC')->getQuery()->getResult();
    }

    /**
     * @param string $tableName
     *
     * @return array
     */
    public function getDataFromShapeTable($tableName)
    {
        try {
            // récupération des stations et des bassins de l'observatoire courant
            $allStations = $this->getBdohRepo('Station')->findAll();
            $allStationsCodes = [];
            foreach ($allStations as $station) {
                /* @var Station $station */
                $allStationsCodes[] = $station->getCode();
            }
            $allBassins = $this->findAll();
            $allBassinsNoms = [];
            foreach ($allBassins as $bassin) {
                /* @var Bassin $bassin */
                $allBassinsNoms[] = $bassin->getNom();
            }

            // requêtes d'analyse des bassins importés dans la table temporaire crée par shp2pgsql

            // requête de base
            $baseSqlParamsArray = [];
            $baseSqlTypesArray = [];
            $baseQuery = "SELECT nom, st_area(geom)::INTEGER AS aire, UPPER(code_exu) AS code_exu FROM $tableName";
            $baseNomOk = ' AND nom IS NOT NULL';
            $baseStationOk = ' AND code_exu IS NULL';
            if ($allStationsCodes !== []) {
                $baseStationOk = ' AND (code_exu IS NULL OR UPPER(code_exu) IN (?))';
                $baseSqlParamsArray[] = $allStationsCodes;
                $baseSqlTypesArray[] = \Doctrine\DBAL\Connection::PARAM_STR_ARRAY;
            }
            $baseOrderBy = ' ORDER BY nom ASC';

            // requête sur les nouveaux bassins
            $sqlParamsArray = [];
            $sqlTypesArray = [];
            $newBassinsQuery = $baseQuery;
            if ($allBassinsNoms !== []) {
                $newBassinsQuery .= ' WHERE nom NOT IN (?)';
                $sqlParamsArray[] = $allBassinsNoms;
                $sqlTypesArray[] = \Doctrine\DBAL\Connection::PARAM_STR_ARRAY;
            } else {
                $newBassinsQuery .= ' WHERE TRUE';
            }
            $newBassinsQuery .= $baseNomOk . $baseStationOk;
            $sqlParamsArray = \array_merge($sqlParamsArray, $baseSqlParamsArray);
            $sqlTypesArray = \array_merge($sqlTypesArray, $baseSqlTypesArray);
            $newBassinsQuery .= $baseOrderBy;
            $newBassins = $this->getEntityManager()->getConnection()->fetchAllAssociative($newBassinsQuery, $sqlParamsArray, $sqlTypesArray);

            // requête sur les bassins existants
            $sqlParamsArray = [];
            $sqlTypesArray = [];
            $existingBassinsQuery = $baseQuery;
            if ($allBassinsNoms !== []) {
                $existingBassinsQuery .= ' WHERE nom IN (?)';
                $sqlParamsArray[] = $allBassinsNoms;
                $sqlTypesArray[] = \Doctrine\DBAL\Connection::PARAM_STR_ARRAY;
            } else {
                $existingBassinsQuery .= ' WHERE FALSE';
            }
            $existingBassinsQuery .= $baseNomOk . $baseStationOk;
            $sqlParamsArray = \array_merge($sqlParamsArray, $baseSqlParamsArray);
            $sqlTypesArray = \array_merge($sqlTypesArray, $baseSqlTypesArray);
            $existingBassinsQuery .= $baseOrderBy;
            $existingBassins = $this->getEntityManager()->getConnection()->fetchAllAssociative($existingBassinsQuery, $sqlParamsArray, $sqlTypesArray);

            // requête sur les bassins avec station exutoire inconnue
            $sqlParamsArray = [];
            $sqlTypesArray = [];
            $bassinsNopeStationQuery = $baseQuery;
            if ($allStationsCodes !== []) {
                $bassinsNopeStationQuery .= ' WHERE code_exu IS NOT NULL AND UPPER(code_exu) NOT IN (?)';
                $sqlParamsArray[] = $allStationsCodes;
                $sqlTypesArray[] = \Doctrine\DBAL\Connection::PARAM_STR_ARRAY;
            } else {
                $bassinsNopeStationQuery .= ' WHERE code_exu IS NOT NULL';
            }
            $bassinsNopeStationQuery .= $baseNomOk;
            $bassinsNopeStationQuery .= $baseOrderBy;
            $bassinsNopeStation = $this->getEntityManager()->getConnection()->fetchAllAssociative($bassinsNopeStationQuery, $sqlParamsArray, $sqlTypesArray);
        } catch (\Exception $e) {
            $this->dropTemporaryShapeTable($tableName);

            return ['error' => 'bassin'];
        }

        return [
            'cible'            => 'bassin',
            'new'              => $newBassins,
            'countNew'         => \count($newBassins),
            'existing'         => $existingBassins,
            'countExisting'    => \count($existingBassins),
            'nopeStation'      => $bassinsNopeStation,
            'countNopeStation' => \count($bassinsNopeStation),
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
        // récupération des bassins de l'observatoire courant
        $allBassins = $this->findAll();
        $allBassinsNoms = [];
        foreach ($allBassins as $bassin) {
            /* @var Bassin $bassin */
            $allBassinsNoms[] = $bassin->getNom();
        }

        // récupération de l'id de l'observatoire courant
        $currentObsId = $this->getCurrentObservatoire()->getId();

        // requetes de selection des bassins importés dans la table temporaire crée par shp2pgsql

        // filtrage sur les nouveaux bassins uniquement
        $sqlParamsArray = [];
        $sqlTypesArray = [];
        $newBassinsFilter = 'AND t.nom IS NOT NULL';
        if ($allBassinsNoms !== []) {
            $newBassinsFilter .= ' AND t.nom NOT IN (?)';
            $sqlParamsArray[] = $allBassinsNoms;
            $sqlTypesArray[] = \Doctrine\DBAL\Connection::PARAM_STR_ARRAY;
        }

        // requête de selection des nouveaux bassins AVEC station exutoire existante
        // note : le DISTINCT est necessaire au cas ou la station est sur plusieurs sites
        $newBassinsWithExistingStationQuery = <<<SQL
SELECT NEXTVAL('bassin_id_seq'), $currentObsId, a.nom, a.aire, a.geom, a.stid FROM (
  SELECT DISTINCT t.nom, st_area(t.geom)::INTEGER AS aire, t.geom, st.id AS stid
  FROM $tableName t, station st, stations_sites ss, siteexperimental si
  WHERE UPPER(t.code_exu) = st.code AND st.id = ss.station_id AND ss.site_id = si.id AND si.observatoire_id = $currentObsId
    $newBassinsFilter
) a
SQL;

        // requête de selection des nouveaux bassins SANS station exutoire
        $newBassinsWithoutStationQuery = <<<SQL
SELECT NEXTVAL('bassin_id_seq'), $currentObsId, t.nom, st_area(t.geom)::INTEGER, t.geom, NULL
FROM $tableName t WHERE t.code_exu IS NULL $newBassinsFilter
SQL;

        // requêtes d'insertion
        $baseInsertQueryBegin = 'INSERT INTO bassin (id, observatoire_id, nom, aire, perimetre, stationexutoire_id) ';
        $baseInsertQueryEnd = ' ON CONFLICT DO NOTHING';
        $newBassinsWithExistingStationInsertQuery =
            $baseInsertQueryBegin . '(' . $newBassinsWithExistingStationQuery . ')' . $baseInsertQueryEnd;
        $newBassinsWithoutStationInsertQuery =
            $baseInsertQueryBegin . '(' . $newBassinsWithoutStationQuery . ')' . $baseInsertQueryEnd;

        $inserts = $this->getEntityManager()->getConnection()->executeStatement($newBassinsWithExistingStationInsertQuery, $sqlParamsArray, $sqlTypesArray)
            + $this->getEntityManager()->getConnection()->executeStatement($newBassinsWithoutStationInsertQuery, $sqlParamsArray, $sqlTypesArray);

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

        // requetes de selection des bassins importés dans la table temporaire crée par shp2pgsql

        // filtrage sur les bassins existants uniquement
        $existingBassinsFilter = "AND t.nom = b.nom AND b.observatoire_id = $currentObsId";

        // requête de selection des bassins existants AVEC station exutoire existante
        // note : le DISTINCT est necessaire au cas ou la station est sur plusieurs sites
        $existingBassinsWithExistingStationQuery = <<<SQL
SELECT DISTINCT b.id AS bid, st_area(t.geom)::INTEGER AS aire, t.geom, st.id AS stid
FROM $tableName t, station st, stations_sites ss, siteexperimental si, bassin b
WHERE UPPER(t.code_exu) = st.code AND st.id = ss.station_id AND ss.site_id = si.id AND si.observatoire_id = $currentObsId
  $existingBassinsFilter
SQL;

        // requête de selection des bassins existants SANS station exutoire
        $existingBassinsWithoutStationQuery = <<<SQL
SELECT b.id AS bid, st_area(t.geom)::INTEGER AS aire, t.geom, NULL::INTEGER AS stid
FROM $tableName t, bassin b
WHERE t.code_exu IS NULL $existingBassinsFilter
SQL;

        // requêtes de mise à jour
        $baseUpdateQueryBegin =
            'UPDATE bassin SET (aire, perimetre, stationexutoire_id) = (temp.aire, temp.geom, temp.stid) FROM (';
        $baseUpdateQueryEnd = ') temp WHERE bassin.id = temp.bid';
        $existingBassinsWithExistingStationUpdateQuery =
            $baseUpdateQueryBegin . $existingBassinsWithExistingStationQuery . $baseUpdateQueryEnd;
        $existingBassinsWithoutStationUpdateQuery =
            $baseUpdateQueryBegin . $existingBassinsWithoutStationQuery . $baseUpdateQueryEnd;

        // faire l'update avant l'insert sinon le résultat est faussé
        $updates = $this->getEntityManager()->getConnection()->executeStatement($existingBassinsWithExistingStationUpdateQuery)
            + $this->getEntityManager()->getConnection()->executeStatement($existingBassinsWithoutStationUpdateQuery);

        return [$this->shapeKeepExistingImport($tableName)[0], $updates];
    }
}
