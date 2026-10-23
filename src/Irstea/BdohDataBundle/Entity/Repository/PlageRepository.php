<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohDataBundle\Entity\Repository;

use Doctrine\ORM\QueryBuilder;
use Irstea\BdohDataBundle\Entity\Chronique;

/**
 * Overview of 'PlageRepository' :.
 *
 *  => Methods for the import of 'Plage' :
 *      -> createTemporaryTable
 *      -> dropTemporaryTable
 *      -> copyIntoTemporaryTable
 *      -> getTemporaryFirstLastDates
 *      -> keepExistingImport
 *      -> overwriteExistingImport
 *      -> detectDateRedundancies
 *
 *  => Methods using directly Doctrine DBAL (without using the ORM layer) :
 *      -> adjustDates
 *      -> getValuesOut
 *
 *  => Methods returning a QueryBuilder :
 *      -> createSimpleQueryBuilder
 *      -> createQueryBuilderByChronique
 *      -> createQueryBuilder
 *      -> createFirstLastDates
 *
 *  => Methods using Doctrine ORM :
 *      -> getMeasuresFromDates
 *      -> getFirstLastDatesByChronique
 *      -> getFirstLastDates
 */
class PlageRepository extends EntityRepository implements MesureRepositoryInterface
{
    /***************************************************************************
     * Methods for the import of 'Plage'
     **************************************************************************/

    /**
     * Creates a 'Plage' SQL table whose goal is to be temporary.
     *
     * @throws \Exception
     *
     * @return string The SQL table name
     */
    public function createTemporaryTable()
    {
        $randomName = 'temp.tmp_plage_' . mt_rand(1, 9999999);
        $sql = "CREATE TABLE $randomName (
                numline          integer                        NOT NULL ,
                chronique_numset integer                        NOT NULL ,
                chronique_id     integer                        NOT NULL ,
                qualite_id       integer                        NOT NULL ,
                debut            timestamp(3) without time zone NOT NULL ,
                fin              timestamp(3) without time zone NOT NULL ,
                valeur           double precision               ,
                minimum          double precision               ,
                maximum          double precision
        )";
        $this->getEntityManager()->getConnection()->executeStatement($sql);

        return $randomName;
    }

    /**
     * Drops a temporary 'Plage' SQL table.
     * Empties the variable containing the SQL table name.
     *
     * @param string $tableName
     *
     * @throws \Exception
     */
    public function dropTemporaryTable(&$tableName)
    {
        $this->getEntityManager()->getConnection()->executeStatement("DROP TABLE $tableName");
        $tableName = null;
    }

    /**
     * {@inheritdoc}
     */
    public function copyIntoTemporaryTable($tableName, $csvPath, $delimiter = ';', $null = '"\\\\N"')
    {
        $columns = implode(',', ['numline', 'chronique_numset', 'chronique_id', 'qualite_id', 'debut', 'fin', 'valeur', 'minimum', 'maximum']);
        $conn = $this->getEntityManager()->getConnection();
        $pdo = $conn->getNativeConnection();
        $pdo->copyFromFile($tableName, $csvPath, $delimiter, $null, $columns);
    }

    /**
     * Gets the first and last dates of a temporary 'Plage' SQL table.
     *
     * @param mixed $tableName
     *
     * @return array [0] => first date ; [1] => last date
     */
    public function getTemporaryFirstLastDates($tableName)
    {
        $sql = "SELECT (MIN(debut) || ' UTC'), (MAX(fin) || ' UTC') FROM $tableName";

        return $this->getEntityManager()->getConnection()->fetchNumeric($sql);
    }

    /**
     * Check if the first 'Plage' of a chronique is a point.
     *
     * @param string $beginDate
     * @param int    $chroniqueId
     *
     * @return bool
     */
    public function isFirstPlageAPoint($beginDate, $chroniqueId)
    {
        $sql = "SELECT * FROM plage WHERE chronique_id = $chroniqueId AND debut = fin AND debut = '$beginDate'";

        return $this->getEntityManager()->getConnection()->fetchNumeric($sql) !== false;
    }

    /**
     * Check if the last 'Plage' of a chronique is a point.
     *
     * @param string $endDate
     * @param int    $chroniqueId
     *
     * @return bool
     */
    public function isLastPlageAPoint($endDate, $chroniqueId)
    {
        $sql = "SELECT * FROM plage WHERE chronique_id = $chroniqueId AND debut = fin AND fin = '$endDate'";

        return $this->getEntityManager()->getConnection()->fetchNumeric($sql) !== false;
    }

    /**
     * Imports measures of a 'chronique' :
     *    => FROM a temporary 'Plage' SQL table ;
     *    => TO   the real 'Plage' SQL table ;
     *    => ONLY measures which doesn't overlap the existing .
     *
     * @param string $tableName   Name of the temporary 'Plage' table
     * @param int    $chroniqueId The considered 'chronique'
     *
     * @throws \Exception
     *
     * @return int The number of INSERT realized
     */
    public function keepExistingImport($tableName, $chroniqueId)
    {
        // Columns to SELECT and INSERT
        $columns = 'chronique_id, qualite_id, debut, fin, valeur, minimum, maximum';

        // Generates ids. for new measures
        $genId = "nextval('plage_id_seq')";

        // Dates of first and last existing measures.
        list($firstExistingDate, $lastExistingDate) = $this->getFirstLastDates($chroniqueId);

        // Selects the measures to import
        $sqlSelect = "SELECT $genId, $columns FROM $tableName " .
            "WHERE chronique_id = $chroniqueId";

        if ($firstExistingDate && $lastExistingDate) {
            $firstPlageIsAPoint = $this->isFirstPlageAPoint($firstExistingDate, $chroniqueId);
            $lastPlageIsAPoint = $this->isLastPlageAPoint($lastExistingDate, $chroniqueId);

            $sqlSelect .= ' AND (';
            if ($firstPlageIsAPoint) {
                $sqlSelect .= "(fin < '$firstExistingDate')";
            } else {
                $sqlSelect .= "((fin <= '$firstExistingDate' AND debut <> fin) OR fin < '$firstExistingDate')";
            }
            $sqlSelect .= ' OR ';
            if ($lastPlageIsAPoint) {
                $sqlSelect .= "(debut > '$lastExistingDate')";
            } else {
                $sqlSelect .= "((debut >= '$lastExistingDate' AND debut <> fin) OR debut > '$lastExistingDate')";
            }
            $sqlSelect .= ')';
        }

        $sqlSelect .= ' ORDER BY debut ASC';

        // Inserts them into the real 'Plage' SQL table
        $sqlInsert = "INSERT INTO plage (id, $columns) ($sqlSelect)";

        // Executes the SQL query
        return $this->getEntityManager()->getConnection()->executeStatement($sqlInsert);
    }

    /**
     * Imports ALL measures of a 'chronique' :
     *    => FROM     a temporary 'Plage' SQL table ;
     *    => TO       the real 'Plage' SQL table ;
     *    => DELETING existing measures overlapped by the new ones.
     *
     * @param string $tableName   Name of the temporary 'Plage' table
     * @param int    $chroniqueId The considered 'chronique'
     * @param string $firstDate   Date of the first measure to import
     * @param string $lastDate    Date of the last measure to import
     *
     * @throws \Exception
     *
     * @return array [0] => num. of DELETE ; [1] => num. of INSERT
     */
    public function overwriteExistingImport($tableName, $chroniqueId, $firstDate, $lastDate)
    {
        // Deletes existing measures overlapped by new ones
        $sqlDelete = "DELETE FROM plage WHERE ((fin > '$firstDate' AND debut < '$lastDate') OR " .
            "((debut = fin OR '$firstDate' = '$lastDate') AND (debut = '$firstDate' OR fin = '$lastDate'))) " .
            "AND chronique_id = $chroniqueId";

        // Columns to SELECT and INSERT
        $columns = 'chronique_id, qualite_id, debut, fin, valeur, minimum, maximum';

        // Generates ids. for new measures
        $genId = "nextval('plage_id_seq')";

        // Selects ALL new measures
        $sqlSelect = "SELECT $genId, $columns FROM $tableName " .
            "WHERE chronique_id = $chroniqueId ORDER BY debut ASC";

        // Inserts them into the real 'Plage' SQL table
        $sqlInsert = "INSERT INTO plage (id, $columns) ($sqlSelect)";

        // Executes the SQL queries : DELETE first ; INSERT next
        $connection = $this->getEntityManager()->getConnection();

        return [$connection->executeStatement($sqlDelete), $connection->executeStatement($sqlInsert)];
    }

    /**
     * For a temporary 'Plage' SQL table, detects the date redundancies.
     *
     * @param string $tableName
     *
     * Returns an array whose structure is :
     *      => Key   == 'chronique' numset ;
     *      => Value == array(
     *          -> Key   == begin date ;
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
        $sql = "SELECT chronique_numset, debut, fin, count(*), array_to_string(array_agg(numline), ', ') as numlines " .
            "FROM $tableName " .
            'GROUP BY chronique_numset, debut, fin ' .
            'HAVING count(*) > 1';

        $sqlResults = $this->getEntityManager()->getConnection()->fetchAllAssociative($sql);
        $redundancies = [];

        foreach ($sqlResults as $result) {
            $redundancies[$result['chronique_numset']][$result['debut']] = [
                'count'    => $result['count'],
                'numLines' => $result['numlines'],
            ];
        }

        return $redundancies;
    }

    /**
     * For a temporary 'Plage' SQL table, detects the range overlaps.
     *
     * @param string $tableName
     *
     * Returns an array whose structure is :
     *      => Key   == 'chronique' numset ;
     *      => Value == array(
     *          -> Value == array(
     *              'plages'    => sting  ; overlapping ranges
     *              'numLines'  => string  ; line numbers
     *          )
     *      )
     *
     * @return array
     */
    public function detectRangeOverlaps($tableName)
    {
        $sql = 'SELECT DISTINCT ON (LEAST(p1.numline, p2.numline), GREATEST(p1.numline, p2.numline)) ' .
            "p1.chronique_numset, p1.numline || ', ' || p2.numline as numlines, " .
            "p1.debut || ' - ' || p1.fin || ', ' || p2.debut || ' - ' || p2.fin as plages " .
            "FROM $tableName p1, $tableName p2 " .
            'WHERE p1.chronique_numset = p2.chronique_numset ' .
            'AND (((p1.debut, p1.fin) OVERLAPS (p2.debut, p2.fin)) OR ' .
            '((p1.debut = p1.fin OR p2.debut = p2.fin) AND (p1.debut = p2.debut OR p1.fin = p2.fin)))' .
            'AND p1.numline <> p2.numline ' .
            'ORDER BY LEAST(p1.numline, p2.numline), GREATEST(p1.numline, p2.numline), p1.numline, p2.numline';

        $sqlResults = $this->getEntityManager()->getConnection()->fetchAllAssociative($sql);
        $overlaps = [];

        foreach ($sqlResults as $result) {
            $overlaps[$result['chronique_numset']][] = [
                'plages'   => $result['plages'],
                'numLines' => $result['numlines'],
            ];
        }

        return $overlaps;
    }

    /***************************************************************************
     * Methods using directly Doctrine DBAL (without using the ORM layer)
     **************************************************************************/

    /**
     * Adds a time to the dates of a 'Plage' table.
     *
     * @param string $time      Format : +/-hhmm (+02:00, -10:00, -04:30, +00:30...)
     * @param string $tableName Name of the 'Mesure' table
     *
     * @throws \Exception
     */
    public function adjustDates($time, $tableName)
    {
        $sql = "UPDATE $tableName set debut = debut + '$time', fin = fin + '$time'";
        $this->getEntityManager()->getConnection()->executeStatement($sql);
    }

    /**
     * For a 'Plage' SQL table (created by createTemporaryTable() function)
     * and by 'chronique', gets the first and last dates, and the number of measures whose :
     *    =>    'valeur' lower  than the 'minimumValide' of the 'chronique' ;
     *    => OR 'valeur' higher than the 'maximumValide' of the 'chronique' .
     *
     * Returns an array whose structure is :
     *    => Key   == 'chronique id.' ;
     *    => Value == array(
     *      'first' => the first date,
     *      'last'  => the last date,
     *      'count' => the number of measures concerned
     *    )
     *
     * @param string $tableName  Name of the 'Plage' table
     * @param string $type       'tooSmall' or 'tooLarge'
     * @param null   $gapQuality
     *
     * @throws \Exception
     *
     * @return array
     */
    public function getValuesOut($tableName, $type, $gapQuality = null)
    {
        // What type of check ?
        switch ($type) {
            case 'tooSmall':
                $whereClause = 'valeur < (SELECT minimumValide FROM chronique WHERE id = chronique_id)';
                break;
            case 'tooLarge':
                $whereClause = 'valeur > (SELECT maximumValide FROM chronique WHERE id = chronique_id)';
                break;
            default:
                return [];
        }

        $sql = "SELECT chronique_id, (MIN(debut) || ' UTC') as min, (MAX(fin) || ' UTC') as max, count(*) " .
            "FROM $tableName WHERE $whereClause GROUP BY chronique_id";

        $results = $this->getEntityManager()->getConnection()->fetchAllAssociative($sql);
        $valuesOutByChronique = [];

        // For each 'chronique', returns first and last dates, and number of measures concerned.
        foreach ($results as $result) {
            $valuesOutByChronique[$result['chronique_id']] = [
                'first' => new \DateTime($result['min']),
                'last'  => new \DateTime($result['max']),
                'count' => $result['count'],
            ];
        }

        return $valuesOutByChronique;
    }

    /**
     * @return \Doctrine\ORM\QueryBuilder
     */
    public function createSimpleQueryBuilder()
    {
        return parent::createQueryBuilder('m')
            ->select(
                "m.id, m.qualite, m.chronique, CONCAT(m.debut, ' UTC') as debut, CONCAT(m.fin, ' UTC') as fin, m.valeur, m.minimum, m.maximum"
            );
    }

    /**
     * @param Chronique $chronique
     *
     * @return \Doctrine\ORM\QueryBuilder
     */
    public function createQueryBuilderByChronique(Chronique $chronique)
    {
        return $this->createSimpleQueryBuilder()
            ->select("CONCAT(m.debut, ' UTC') as debut, CONCAT(m.fin, ' UTC') as fin, m.valeur, q.id as qualite")
            ->leftJoin('m.qualite', 'q')
            ->where('m.chronique = ' . $chronique->getId())
            ->orderBy('m.debut', 'ASC');
    }

    /**
     * Selects only entities linked to the current 'observatoire'.
     *
     * @param mixed      $alias
     * @param mixed|null $indexBy
     *
     * @return \Doctrine\ORM\QueryBuilder
     */
    public function createQueryBuilder(string $alias = 'm', ?string $indexBy = null): QueryBuilder
    {
        if (!$currentObs = $this->getCurrentObservatoire()) {
            return self::createSimpleQueryBuilder();
        }

        return self::createSimpleQueryBuilder()
            ->leftJoin($alias . '.chronique', 'ch')
            ->leftJoin('ch.station', 'st')
            ->leftJoin('ch.dataset', 'ds')
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
            ->select("CONCAT(MIN(m.debut),' UTC') as first, CONCAT(MAX(m.fin), ' UTC') as last");
    }

    /***************************************************************************
     * Methods using Doctrine ORM
     **************************************************************************/

    /**
     * From a given 'chronique', retrieves all 'Plage' beetween two given dates.
     *
     * @param Chronique  $chronique
     * @param string     $begin     SQL date
     * @param string     $end       SQL date
     * @param array|null $gapIds
     *
     * @return array
     */
    public function getMeasuresFromDates(Chronique $chronique, $begin, $end, ?array $gapIds = null)
    {
        $qb = $this->createQueryBuilderByChronique($chronique);

        if ($begin) {
            $qb->andWhere("m.debut >= '$begin'");
        }
        if ($end) {
            $qb->andWhere("m.fin   <= '$end'");
        }

        return $qb->getQuery()->getArrayResult();
    }

    /**
     * Gets the first and last dates by 'chronique'.
     *
     * @param int[] $chroniques 'ChroniqueDiscontinue' instances OR ids
     *
     * @return array Key = 'chronique' id. ; Value = array of ['first', 'last'].
     */
    public function getFirstLastDatesByChronique(array $chroniques)
    {
        // Gets id. of eventual 'ChroniqueDiscontinue' instances
        $this->extractId($chroniques, 'Irstea\BdohDataBundle\Entity\ChroniqueDiscontinue');

        $qb = $this->createFirstLastDates()->addSelect('ch.id')->groupBy('ch.id');

        // Restriction on 'chroniques'
        if ([] !== $chroniques) {
            $qb->andWhere($qb->expr()->in('ch.id', $chroniques));
        }

        // Puts the result in form : Key = 'chronique' id. ; Value = array of ['first', 'last']
        $results = $qb->getQuery()->getResult();
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
     * {@inheritdoc}
     */
    public function getFirstLastDates($chroniqueId)
    {
        $dates = current($this->getFirstLastDatesByChronique([$chroniqueId]));

        return $dates ? [$dates['first'], $dates['last']] : [null, null];
    }

    /**
     * {@inheritdoc}
     */
    public function deleteFromChronique($chroniqueId)
    {
        $this->getEntityManager()->getConnection()->executeStatement('DELETE FROM plage WHERE chronique_id = ' . $chroniqueId);
    }

    /**
     * {@inheritdoc}
     */
    public function emptyChroniquePeriode(Chronique $chronique, \DateTime $beginDate, \DateTime $endDate)
    {
        $count = $this->getEntityManager()
            ->getConnection()
            ->executeStatement(
                'DELETE FROM plage WHERE chronique_id = :id AND ' .
                    '((fin > :begin AND debut < :end) OR (debut = fin AND (debut = :begin OR fin = :end )))',
                [
                    ':id'    => $chronique->getId(),
                    ':begin' => $beginDate->format(DATE_ATOM),
                    ':end'   => $endDate->format(DATE_ATOM),
                ]
            );

        return ['changed' => false, 'count' => $count];
    }
}
