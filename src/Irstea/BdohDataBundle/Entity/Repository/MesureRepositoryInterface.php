<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohDataBundle\Entity\Repository;

use Irstea\BdohDataBundle\Entity\Chronique;

/**
 * Class MesureRepositoryInterface.
 */
interface MesureRepositoryInterface
{
    /**
     * Creates a 'Mesure' SQL table whose goal is to be temporary.
     *
     * @return string The SQL table name
     */
    public function createTemporaryTable();

    /**
     * @param $tableName
     *
     * @return mixed
     */
    public function dropTemporaryTable(&$tableName);

    /**
     * Copies 'Mesure' FROM a CSV file INTO a temporary 'Mesure' SQL table.
     *
     * @param string $tableName
     * @param string $csvPath
     * @param string $delimiter
     * @param string $null
     */
    public function copyIntoTemporaryTable($tableName, $csvPath, $delimiter = ';', $null = '"\\\\N"');

    /**
     * Gets the first and last dates of a temporary 'Mesure' SQL table.
     *
     * @param mixed $tableName
     *
     * @return array [0] => first date ; [1] => last date
     */
    public function getTemporaryFirstLastDates($tableName);

    /**
     * Imports measures of a 'chronique' :
     *    => FROM a temporary 'Mesure' SQL table ;
     *    => TO   the real 'Mesure' SQL table ;
     *    => ONLY measures which doesn't overlap the existing .
     *
     * @param string $tableName   Name of the temporary 'Mesure' table
     * @param int    $chroniqueId The considered 'chronique'
     *
     * @return int The number of INSERT realized
     */
    public function keepExistingImport($tableName, $chroniqueId);

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
     * @return array [0] => num. of DELETE ; [1] => num. of INSERT
     */
    public function overwriteExistingImport($tableName, $chroniqueId, $firstDate, $lastDate);

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
    public function detectDateRedundancies($tableName);

    /**
     * For a temporary 'Plage' SQL table, detects the range overlaps.
     *
     * @param string $tableName
     *
     * Returns an array whose structure is :
     *      => Key   == 'chronique' numset ;
     *      => Value == array(
     *          -> Value == array(
     *              'plages'    => string ; overlapping ranges
     *              'numLines'  => string  ; line numbers
     *          )
     *      )
     *
     * @return array
     */
    public function detectRangeOverlaps($tableName);

    /***************************************************************************
     * Methods using directly Doctrine DBAL (without using the ORM layer)
     **************************************************************************/

    /**
     * Adds a time to the dates of a 'Mesure' table.
     *
     * @param string $time      Format : +/-hhmm (+02:00, -10:00, -04:30, +00:30...)
     * @param string $tableName Name of the 'Mesure' table
     */
    public function adjustDates($time, $tableName);

    /**
     * For a 'Mesure' SQL table and by 'chronique',
     * gets the first and last dates, and the number of measures whose :
     *    =>    'valeur' lower  than the 'minimumValide' of the 'chronique' ;
     *    => OR 'valeur' higher than the 'maximumValide' of the 'chronique' .
     *
     * @param string $tableName  Name of the 'Mesure' table
     * @param string $type       'tooSmall' or 'tooLarge'
     * @param mixed  $gapQuality
     *
     * Returns an array whose structure is :
     *    => Key   == 'chronique id.' ;
     *    => Value == array(
     *      'first' => the first date,
     *      'last'  => the last date,
     *      'count' => the number of measures concerned
     *    )
     */
    public function getValuesOut($tableName, $type, $gapQuality);

    /**
     * From a given 'chronique', retrieves all 'Mesure' beetween two given dates.
     *
     * @param Chronique  $chronique
     * @param string     $begin     SQL date
     * @param string     $end       SQL date
     * @param array|null $gapIds
     *
     * @throws \Exception
     *
     * @return array
     */
    public function getMeasuresFromDates(Chronique $chronique, $begin, $end, ?array $gapIds = null);

    /**
     * Gets the first and last dates by 'chronique'.
     *
     * @param int[] $chroniqueIds 'ChroniqueContinue' instances OR ids
     *
     * @return array Key = 'chronique' id. ; Value = array of ['first', 'last'].
     */
    public function getFirstLastDatesByChronique(array $chroniqueIds);

    /**
     * Gets the first and last dates of a 'chronique'.
     *
     * @param int $chroniqueId
     *
     * @return array [0] => first date ; [1] => last date
     */
    public function getFirstLastDates($chroniqueId);

    /**
     * @param int $chroniqueId
     *
     * @throws \Exception
     *
     * @return mixed
     */
    public function deleteFromChronique($chroniqueId);

    /** Supprime les mesures d'une chronique sur une période de temps donnée.
     * Insère éventuellement des lacunes techniques pour le (re)calcul de lacunes.
     *
     * @param Chronique $chronique
     * @param \DateTime $beginDate
     * @param \DateTime $endDate
     *
     * @return array ['changed'=> boolean, 'count' => integer]
     */
    public function emptyChroniquePeriode(Chronique $chronique, \DateTime $beginDate, \DateTime $endDate);

    /**
     * @param Chronique $chronique
     *
     * @return \Doctrine\ORM\QueryBuilder
     */
    public function createQueryBuilderByChronique(Chronique $chronique);

    /**
     * @return \Doctrine\ORM\QueryBuilder
     */
    public function createSimpleQueryBuilder();
}
