<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohDataBundle\Entity\Repository;

use Doctrine\ORM\QueryBuilder;
use Irstea\BdohDataBundle\Entity\Chronique;
use Irstea\BdohDataBundle\Entity\ChroniqueContinue;
use Irstea\BdohDataBundle\Entity\Mesure;

/**
 * Overview of 'MesureRepository'.
 *
 *  => Methods for the import of 'Mesure' :
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
 *      -> getPreviousMeasure
 *      -> getNextMeasure
 *      -> getFirstLastDatesByChronique
 *      -> getFirstLastDates
 */
class MesureRepository extends EntityRepository implements MesureRepositoryInterface
{
    /***************************************************************************
     * Methods for the import of 'Mesure'
     **************************************************************************/

    const EXTRACTED_DATE_FORMAT = 'Y-m-d H:i:s T';

    /**
     * Creates a 'Mesure' SQL table whose goal is to be temporary.
     *
     * @throws \Exception
     *
     * @return string The SQL table name
     */
    public function createTemporaryTable()
    {
        $randomName = 'temp.tmp_mesure_' . mt_rand(1, 9999999);
        $sql = "CREATE TABLE $randomName (
                numline          integer                        NOT NULL ,
                chronique_numset integer                        NOT NULL ,
                chronique_id     integer                        NOT NULL ,
                qualite_id       integer                        NOT NULL ,
                date             timestamp(3) without time zone NOT NULL ,
                valeur           double precision               ,
                minimum          double precision               ,
                maximum          double precision
        )";
        $this->getEntityManager()->getConnection()->executeStatement($sql);

        return $randomName;
    }

    /**
     * Drops a temporary 'Mesure' SQL table.
     * Empties the variable containing the SQL table name.
     *
     * @param string|null $tableName
     *
     * @throws \Exception
     *
     * @return mixed|void
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
        $columns = implode(',', ['numline', 'chronique_numset', 'chronique_id', 'qualite_id', 'date', 'valeur', 'minimum', 'maximum']);
        $conn = $this->getEntityManager()->getConnection();
        $pdo = $conn->getNativeConnection();
        $pdo->pgsqlCopyFromFile($tableName, $csvPath, $delimiter, $null, $columns);
    }

    /**
     * Gets the first and last dates of a temporary 'Mesure' SQL table.
     *
     * @param mixed $tableName
     *
     * @return array [0] => first date ; [1] => last date
     */
    public function getTemporaryFirstLastDates($tableName)
    {
        $sql = "SELECT (MIN(date) || ' UTC'), (MAX(date) || ' UTC') FROM $tableName";

        return $this->getEntityManager()->getConnection()->fetchNumeric($sql);
    }

    /**
     * Imports measures of a 'chronique' :
     *    => FROM a temporary 'Mesure' SQL table ;
     *    => TO   the real 'Mesure' SQL table ;
     *    => ONLY measures which doesn't overlap the existing .
     *
     * @param string $tableName   Name of the temporary 'Mesure' table
     * @param int    $chroniqueId The considered 'chronique'
     *
     * @throws \Exception
     *
     * @return int The number of INSERT realized
     */
    public function keepExistingImport($tableName, $chroniqueId)
    {
        // Columns to SELECT and INSERT
        $columns = 'chronique_id, qualite_id, date, valeur, minimum, maximum';

        // Generates ids. for new measures
        $genId = "nextval('mesure_id_seq')";

        // Dates of first and last existing measures.
        list($firstExistingDate, $lastExistingDate) = $this->getFirstLastDates($chroniqueId);

        // Selects the measures to import
        $sqlSelect = "SELECT $genId, $columns, FALSE FROM $tableName " .
            "WHERE chronique_id = $chroniqueId";

        if ($firstExistingDate && $lastExistingDate) {
            $sqlSelect .= " AND ((date < '$firstExistingDate') OR (date > '$lastExistingDate'))";
        }

        $sqlSelect .= ' ORDER BY date ASC';

        // Inserts them into the real 'Mesure' SQL table
        $sqlInsert = "INSERT INTO mesure (id, $columns, estCalculee) ($sqlSelect)";

        // Executes the SQL query
        return $this->getEntityManager()->getConnection()->executeStatement($sqlInsert);
    }

    /**
     * Imports ALL measures of a 'chronique' :
     *    => FROM     a temporary 'Mesure' SQL table ;
     *    => TO       the real 'Mesure' SQL table ;
     *    => DELETING existing measures overlapped by the new ones.
     *
     * @param string $tableName   Name of the temporary 'Mesure' table
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
        $sqlDelete = "DELETE FROM mesure WHERE (date >= '$firstDate') AND (date <= '$lastDate') " .
            "AND chronique_id = $chroniqueId";

        // Columns to SELECT and INSERT
        $columns = 'chronique_id, qualite_id, date, valeur, minimum, maximum';

        // Generates ids. for new measures
        $genId = "nextval('mesure_id_seq')";

        // Selects ALL new measures
        $sqlSelect = "SELECT $genId, $columns, FALSE FROM $tableName " .
            "WHERE chronique_id = $chroniqueId ORDER BY date ASC";

        // Inserts them into the real 'Mesure' SQL table
        $sqlInsert = "INSERT INTO mesure (id, $columns, estCalculee) ($sqlSelect)";

        // Executes the SQL queries : DELETE first ; INSERT next
        $connection = $this->getEntityManager()->getConnection();

        return [$connection->executeStatement($sqlDelete), $connection->executeStatement($sqlInsert)];
    }

    /**
     * For a temporary 'Mesure' SQL table, detects the date redundancies.
     *
     * @param string $tableName
     *
     * Returns an array whose structure is :
     *      => Key   == 'chronique' numset ;
     *      => Value == array(
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
        $sql = "SELECT chronique_numset, date, count(*), array_to_string(array_agg(numline), ', ') as numlines " .
            "FROM $tableName " .
            'GROUP BY chronique_numset, date ' .
            'HAVING count(*) > 1';

        $sqlResults = $this->getEntityManager()->getConnection()->fetchAllAssociative($sql);
        $redundancies = [];

        foreach ($sqlResults as $result) {
            $redundancies[$result['chronique_numset']][$result['date']] = [
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
     * @return array (empty)
     */
    public function detectRangeOverlaps($tableName)
    {
        return [];
    }

    /***************************************************************************
     * Methods using directly Doctrine DBAL (without using the ORM layer)
     **************************************************************************/

    /**
     * Adds a time to the dates of a 'Mesure' table.
     *
     * @param string $time      Format : +/-hhmm (+02:00, -10:00, -04:30, +00:30...)
     * @param string $tableName Name of the 'Mesure' table
     *
     * @throws \Exception
     */
    public function adjustDates($time, $tableName)
    {
        $sql = "UPDATE $tableName set date = date + '$time'";
        $this->getEntityManager()->getConnection()->executeStatement($sql);
    }

    /**
     * For a 'Mesure' SQL table and by 'chronique',
     * gets the first and last dates, and the number of measures whose :
     *    =>    'valeur' lower  than the 'minimumValide' of the 'chronique' ;
     *    => OR 'valeur' higher than the 'maximumValide' of the 'chronique' .
     *
     * @param string $tableName Name of the 'Mesure' table
     * @param string $type      'tooSmall' or 'tooLarge'
     *
     * Returns an array whose structure is :
     *    => Key   == 'chronique id.' ;
     *    => Value == array(
     *      'first' => the first date,
     *      'last'  => the last date,
     *      'count' => the number of measures concerned
     *    )
     * @param mixed $gapQuality
     *
     * @throws \Exception
     *
     * @return array
     */
    public function getValuesOut($tableName, $type, $gapQuality)
    {
        // What type of check ?
        switch ($type) {
            case 'tooSmall':
                $whereClause = "valeur < (SELECT minimumValide FROM chronique WHERE id = chronique_id) and qualite_id <> $gapQuality";
                break;
            case 'tooLarge':
                $whereClause = "valeur > (SELECT maximumValide FROM chronique WHERE id = chronique_id) and qualite_id <> $gapQuality";
                break;
            default:
                return [];
        }

        $sql = "SELECT chronique_id, (MIN(date) || ' UTC') as min, (MAX(date) || ' UTC') as max, count(*) " .
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
            ->select("m.id, m.qualite, m.chronique, CONCAT(m.date, ' UTC') as date, m.valeur, m.minimum, m.maximum, m.estCalculee");
    }

    /**
     * {@inheritdoc}
     */
    public function createQueryBuilderByChronique(Chronique $chronique)
    {
        return $this->createSimpleQueryBuilder()
            ->select("CONCAT(m.date, ' UTC') as date, m.valeur, q.id as qualite")
            ->leftJoin('m.qualite', 'q')
            ->where('m.chronique = ' . $chronique->getId())
            ->orderBy('m.date', 'ASC');
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
            return $this->createSimpleQueryBuilder();
        }

        return $this->createSimpleQueryBuilder()
            ->leftJoin('m.chronique', 'ch')
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
            ->select("CONCAT(MIN(m.date),' UTC') as first, CONCAT(MAX(m.date), ' UTC') as last");
    }

    /***************************************************************************
     * Methods using Doctrine ORM
     **************************************************************************/

    /**
     * {@inheritdoc}
     */
    public function getMeasuresFromDates(Chronique $chronique, $begin, $end, ?array $gapIds = null)
    {
        $chroniqueId = $chronique->getId();
        if ($gapIds) {
            $sql = "WITH dvv(date, value, valid, qualite) AS
                  (SELECT (m.date || ' UTC') as date, m.valeur, m.qualite_id IN ( " . implode(',', $gapIds) . ") , m.qualite_id
                   FROM mesure m WHERE m.chronique_id = '$chroniqueId'
                    AND m.date >= '$begin' AND m.date < '$end'
                    ORDER BY m.date
                    )
                    SELECT dvvc.date as date, dvvc.value as value, dvvc.qualite as qualite
                    FROM (SELECT date, value,  valid, qualite, COALESCE(dvv.valid <> LAG(dvv.valid) OVER(), true) AS continuous FROM dvv) dvvc
                    WHERE dvvc.continuous
                    ;";

            return $this->getEntityManager()->getConnection()->executeQuery($sql)->fetchAllAssociative();
        }
        $qb = $this->createQueryBuilderByChronique($chronique);
        if ($begin) {
            $qb->andWhere("m.date >= '$begin'");
        }
        if ($end) {
            $qb->andWhere("m.date <= '$end'");
        }

        return $qb->getQuery()->getArrayResult();
    }

    /**
     * From a given 'chronique', retrieves the immediat previous 'Mesure' to a given date.
     *
     * @param ChroniqueContinue $chronique
     * @param string            $date      SQL date
     *
     * @return mixed Irstea\BdohDataBundle\Entity\Mesure|null
     */
    public function getPreviousMeasure(ChroniqueContinue $chronique, $date)
    {
        $qb = $this->createQueryBuilderByChronique($chronique)
            ->andWhere("m.date < '$date'")
            ->orderBy('m.date', 'DESC')
            ->setMaxResults(1);

        $result = $qb->getQuery()->getArrayResult();

        return $result ? $result[0] : null;
    }

    /**
     * From a given 'chronique', retrieves the immediat next 'Mesure' to a given date.
     *
     * @param ChroniqueContinue $chronique
     * @param string            $date      SQL date
     *
     * @return mixed Irstea\BdohDataBundle\Entity\Mesure|null
     */
    public function getNextMeasure(ChroniqueContinue $chronique, $date)
    {
        $qb = $this->createQueryBuilderByChronique($chronique)
            ->andWhere("m.date > '$date'")
            ->orderBy('m.date', 'ASC')
            ->setMaxResults(1);

        $result = $qb->getQuery()->getArrayResult();

        return $result ? $result[0] : null;
    }

    /**
     * From a given 'chronique', retrieves the immediat next 'Mesure' to a given date.
     *
     * @param ChroniqueContinue $chronique
     * @param string            $date      SQL date
     *
     * @throws \Exception
     *
     * @return mixed Irstea\BdohDataBundle\Entity\Mesure|null
     */
    public function getNextMeasure2(ChroniqueContinue $chronique, $date)
    {
        $chroniqueId = $chronique->getId();
        $sql = "SELECT m.qualite_id FROM mesure m
               WHERE m.chronique_id=$chroniqueId
               AND m.date >'$date'
               ORDER BY m.date ASC LIMIT 1;";

        $result = $this->getEntityManager()->getConnection()->executeQuery($sql)->fetchAllAssociative();

        return $result ? $result[0] : null;
    }

    /**
     * {@inheritdoc}
     */
    public function getFirstLastDatesByChronique(array $chroniques = [])
    {
        // Gets id. of eventual 'ChroniqueContinue' instances
        $this->extractId($chroniques, ChroniqueContinue::class);

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
        $allDates = $this->getFirstLastDatesByChronique([$chroniqueId]);
        $dates = current($allDates);

        return $dates ? [$dates['first'], $dates['last']] : [null, null];
    }

    /**
     * {@inheritdoc}
     */
    public function deleteFromChronique($chroniqueId)
    {
        $this->getEntityManager()->getConnection()->executeStatement('DELETE FROM mesure WHERE chronique_id = ' . $chroniqueId);
    }

    /**
     * {@inheritdoc}
     */
    public function emptyChroniquePeriode(Chronique $chronique, \DateTime $beginDate, \DateTime $endDate)
    {
        $em = $this->getEntityManager();

        // Get the current bondaries of the time series
        list($firstExtractedDate, $lastExtractedDate) = $this->getFirstLastDates($chronique->getId());
        $firstDateTime = $this->parseExtractedDate($firstExtractedDate);
        $lastDateTime = $this->parseExtractedDate($lastExtractedDate);

        // The removal period is inside the time series' bondaries -> the data are changed into gap values
        // BETWEEN treats the endpoint values as included in the range
        if ($firstDateTime < $beginDate && $endDate < $lastDateTime) {
            $gapQualite = $chronique->getDefaultGapQualite();
            $count = $em->createQueryBuilder()
                ->update(Mesure::class, 'm')
                ->set('m.qualite', ':qualite')
                ->set('m.valeur', ':valeur')
                ->set('m.minimum', ':minimum')
                ->set('m.maximum', ':maximum')
                ->where('m.chronique = :chronique')
                ->andWhere('m.date BETWEEN :begin AND :end')
                ->setParameter('qualite', $gapQualite)
                ->setParameter('valeur', null)
                ->setParameter('minimum', null)
                ->setParameter('maximum', null)
                ->setParameter('chronique', $chronique)
                ->setParameter('begin', $beginDate->format(DATE_ATOM))
                ->setParameter('end', $endDate->format(DATE_ATOM))
                ->getQuery()
                ->execute();
            $changed = true;
        }
        // The removal period includes one or both of the bondaries of the time series -> the data are removed
        // BETWEEN treats the endpoint values as included in the range
        else {
            $count = $em->createQueryBuilder()
                ->delete()
                ->from(Mesure::class, 'm')
                ->where('m.chronique = :chronique')
                ->andWhere('m.date BETWEEN :begin AND :end')
                ->setParameter('chronique', $chronique)
                ->setParameter('begin', $beginDate->format(DATE_ATOM))
                ->setParameter('end', $endDate->format(DATE_ATOM))
                ->getQuery()
                ->execute();
            $changed = false;
        }

        return ['changed' => $changed, 'count' => $count];
    }

    /**
     * @param string $extractedDate
     *
     * dates are extracted from the database as a string concatanated with ' UTC':
     * CONCAT(date, ' UTC')
     *
     * @return \DateTime
     */
    private function parseExtractedDate($extractedDate)
    {
        return \DateTime::createFromFormat(
            self::EXTRACTED_DATE_FORMAT,
            $extractedDate,
            new \DateTimeZone('UTC')
        );
    }
}
