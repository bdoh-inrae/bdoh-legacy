<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohDataBundle\Entity\Repository;

use Doctrine\ORM\QueryBuilder;
use Irstea\BdohDataBundle\Entity\ChroniqueContinue;

/**
 * Class PointControleRepository.
 */
class PointControleRepository extends EntityRepository
{
    /***************************************************************************
     * Methods for the import of 'Controle'
     **************************************************************************/

    /**
     * Creates a 'Controle' SQL table whose goal is to be temporary.
     *
     * @return string The SQL table name
     */
    public function createTemporaryTable()
    {
        $randomName = 'temp.tmp_controle_' . rand(1, 9999999);
        $sql = "CREATE TABLE $randomName (
                numline      integer NOT NULL ,
                chronique_id integer NOT NULL ,
                date         timestamp(3) without time zone NOT NULL ,
                valeur       double precision,
                qualite_id   integer,
                minimum      double precision,
                maximum      double precision
        )";
        $this->getEntityManager()->getConnection()->executeStatement($sql);

        return $randomName;
    }

    /**
     * Copies 'Controle' FROM a CSV file INTO a temporary 'Controle' SQL table.
     *
     * @param mixed $tableName
     * @param mixed $csvPath
     * @param mixed $delimiter
     * @param mixed $null
     */
    public function copyIntoTemporaryTable($tableName, $csvPath, $delimiter = ';', $null = '"\\\\N"')
    {
        $columns = implode(',', ['numline', 'chronique_id', 'date', 'valeur', 'qualite_id', 'minimum', 'maximum']);
        $conn = $this->getEntityManager()->getConnection();
        $pdo = $conn->getNativeConnection();
        $pdo->copyFromFile($tableName, $csvPath, $delimiter, $null, $columns);
    }

    /**
     * For a temporary SQL table, detects the date redundancies.
     *
     * @param string $tableName
     *
     * Returns an array whose structure is :
     *          -> Key   == date ;
     *          -> Value == array(
     *              'count'     => integer ; number of times this date appears
     *              'numLines'  => string  ; contains the date line numbers
     *          )
     *      )
     *
     * @return array
     */
    public function detectDateRedundancies($tableName)
    {
        $sql = "SELECT date, count(*), array_to_string(array_agg(numline), ', ') as numlines " .
            "FROM $tableName " .
            'GROUP BY date ' .
            'HAVING count(*) > 1';

        $sqlResults = $this->getEntityManager()->getConnection()->fetchAllAssociative($sql);
        $redundancies = [];

        foreach ($sqlResults as $result) {
            $redundancies[$result['date']] = [
                'count'    => $result['count'],
                'numLines' => $result['numlines'],
            ];
        }

        return $redundancies;
    }

    /**
     * Imports ALL controles of a 'chronique' :
     *    => FROM     a temporary 'Controle' SQL table ;
     *    => TO       the real 'Controle' SQL table ;
     *    => DELETING existing controles.
     *
     * @param string $tableName   Name of the temporary 'Mesure' table
     * @param int    $chroniqueId The considered 'chronique'
     * @param string $firstDate   Date of the first controle to import
     * @param string $lastDate    Date of the last controle to import
     *
     * @return array [0] => num. of DELETE ; [1] => num. of INSERT
     */
    public function import($tableName, $chroniqueId)
    {
        // Deletes existing controles
        $sqlDelete = "DELETE FROM pointcontrole WHERE chronique_id = $chroniqueId";

        // Columns to SELECT and INSERT
        $columns = 'chronique_id, date, valeur, qualite_id, minimum, maximum';

        // Generates ids. for new controles
        $genId = "nextval('pointcontrole_id_seq')";

        // Selects ALL new controles
        $sqlSelect = "SELECT $genId, $columns FROM $tableName " .
            "WHERE  chronique_id = $chroniqueId ORDER BY date ASC";

        // Inserts them into the real 'Controle' SQL table
        $sqlInsert = "INSERT INTO pointcontrole (id, $columns) ($sqlSelect)";

        // Executes the SQL queries : DELETE first ; INSERT next
        $connection = $this->getEntityManager()->getConnection();

        return [$connection->executeStatement($sqlDelete), $connection->executeStatement($sqlInsert)];
    }

    /**
     * @param $tableName
     * @param $chroniqueId
     *
     * @throws \Doctrine\DBAL\Exception
     *
     * @return array
     */
    public function importStats($tableName, $chroniqueId)
    {
        // Count existing controles
        $sqlNbPointControle = "SELECT count(*) as nbPointControle FROM pointcontrole WHERE chronique_id = $chroniqueId";

        // Count ALL new controles
        $sqlNbNewPointControle = "SELECT count(*) as nbNewPointControle FROM $tableName ";

        $connection = $this->getEntityManager()->getConnection();

        return [
            $connection->executeQuery($sqlNbNewPointControle)->fetchOne(),
            $connection->executeQuery($sqlNbPointControle)->fetchOne(),
        ];
    }

    /**
     * For a 'Controle' SQL table
     * gets the first and last dates.
     *
     * @param string $tableName Name of the 'Controle' table
     *
     * Returns an array whose structure is :
     *    => Key   == 'chronique id.' ;
     *    => Value == array(
     *      'first' => the first date,
     *      'last'  => the last date
     *    )
     *
     * @return array
     */
    public function getValuesOut($tableName)
    {
        $sql = "SELECT chronique_id, (MIN(date) || ' UTC') as min, (MAX(date) || ' UTC') as max, " .
            "FROM $tableName GROUP BY chronique_id";

        $results = $this->getEntityManager()->getConnection()->fetchAllAssociative($sql);
        $valuesOut = [];

        // For each 'chronique', returns first and last dates, and number of controles concerned.
        foreach ($results as $result) {
            $valuesOut[$result['chronique_id']] = [
                'first' => new \DateTime($result['min']),
                'last'  => new \DateTime($result['max']),
            ];
        }

        return $valuesOut;
    }

    /***************************************************************************
     * Methods returning a QueryBuilder.
     **************************************************************************/

    public function createSimpleQueryBuilder()
    {
        return parent::createQueryBuilder('c')
            ->select("c.id, c.chronique, CONCAT(c.date, ' UTC') as date, c.valeur, c.minimum, c.maximum");
    }

    /**
     * @param ChroniqueContinue $chronique
     *
     * @return \Doctrine\ORM\QueryBuilder
     */
    public function createQueryBuilderByChronique(ChroniqueContinue $chronique)
    {
        return $this->createSimpleQueryBuilder()
            ->select("CONCAT(c.date, ' UTC') as date, c.valeur, c.minimum, c.maximum")
            ->leftJoin('c.chronique', 'ch')
            ->where('ch.id = ' . $chronique->getId())
            ->orderBy('c.date', 'ASC');
    }

    /**
     * Selects only entities linked to the current 'observatoire'.
     *
     * @param mixed      $alias
     * @param mixed|null $indexBy
     */
    public function createQueryBuilder(string $alias = 'c', ?string $indexBy = null): QueryBuilder
    {
        if (!$currentObs = $this->getCurrentObservatoire()) {
            return self::createSimpleQueryBuilder();
        }

        return self::createSimpleQueryBuilder()
            ->leftJoin('c.chronique', 'ch')
            ->leftJoin('ch.station', 'st')
            ->leftJoin('st.sites', 'si')
            ->where('si.observatoire = :observatoire')
            ->setParameter('observatoire', $currentObs);
    }

    /**
     * Returns a QueryBuilder pre-selecting the first and last dates.
     */
    public function createFirstLastDates()
    {
        return $this->createQueryBuilder()
            ->select("CONCAT(MIN(c.date),' UTC') as first, CONCAT(MAX(c.date), ' UTC') as last");
    }

    /***************************************************************************
     * Methods using Doctrine ORM
     **************************************************************************/

    /**
     * From a given 'chronique', retrieves all 'Controle' beetween two given dates.
     *
     * @param ChroniqueContinue $chronique
     * @param string            $begin     SQL date
     * @param string            $end       SQL date
     *
     * @return array
     */
    public function getControlesFromDates(ChroniqueContinue $chronique, $begin, $end)
    {
        $qb = $this->createQueryBuilderByChronique($chronique);

        if ($begin) {
            $qb->andWhere("c.date >= '$begin'");
        }
        if ($end) {
            $qb->andWhere("c.date <= '$end'");
        }

        return $qb->getQuery()->getArrayResult();
    }

    /**
     * From a given 'chronique', retrieves the immediat previous 'Controle' to a given date.
     *
     * @param ChroniqueContinue $chronique
     * @param string            $date      SQL date
     *
     * @return mixed
     */
    public function getPreviousControle(ChroniqueContinue $chronique, $date)
    {
        $qb = $this->createQueryBuilderByChronique($chronique)
            ->andWhere("c.date < '$date'")
            ->orderBy('c.date', 'DESC')
            ->setMaxResults(1);

        $result = $qb->getQuery()->getArrayResult();

        return $result ? $result[0] : null;
    }

    /**
     * From a given 'chronique', retrieves the immediat next 'Controle' to a given date.
     *
     * @param ChroniqueContinue $chronique
     * @param string            $date      SQL date
     *
     * @return mixed
     */
    public function getNextControle(ChroniqueContinue $chronique, $date)
    {
        $qb = $this->createQueryBuilderByChronique($chronique)
            ->andWhere("c.date > '$date'")
            ->orderBy('c.date', 'ASC')
            ->setMaxResults(1);

        $result = $qb->getQuery()->getArrayResult();

        return $result ? $result[0] : null;
    }

    /**
     * @param $chronique
     *
     * @return array
     */
    public function getFirstLastDates($chronique)
    {
        $qb = $this->createQueryBuilder()
            ->select("CONCAT(MIN(c.date),' UTC') as first, CONCAT(MAX(c.date), ' UTC') as last, count(c) as number")
            ->andWhere('c.chronique =:chronique')
            ->setParameter('chronique', $chronique);

        $result = $qb->getQuery()->getResult();

        return $result ? $result[0] : ['first' => null, 'last' => null, 'number' => 0];
    }

    /**
     * @param $chronique
     *
     * @throws \Doctrine\DBAL\Exception
     */
    public function deleteFromChronique($chronique)
    {
        $this->getEntityManager()->getConnection()->executeStatement('DELETE FROM pointcontrole WHERE chronique_id = ' . $chronique->getId());
    }
}
