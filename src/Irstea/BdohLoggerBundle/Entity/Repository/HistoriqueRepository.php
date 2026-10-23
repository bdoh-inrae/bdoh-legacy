<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohLoggerBundle\Entity\Repository;

use Doctrine\ORM\EntityRepository as BaseEntityRepository;

/**
 * HistoriqueRepository.
 */
class HistoriqueRepository extends BaseEntityRepository
{
    /**
     * Alteration of the default method 'findByObservatoire'
     * to chose the order
     * to limit the results in time
     * and to chose to get the ones before or after the given date
     * or to get the ones from the given year.
     *
     * $observatoire : observatoire
     * $year         : the year  to limit the result set
     * $date         : the date to limit the result set
     * $after        : true : [date to the most recent entry]; false : [least recent entry to the date[
     * $order        : 'ASC' or 'DESC'
     *
     * @param mixed  $observatoire
     * @param int    $year
     * @param mixed  $date
     * @param bool   $after
     * @param string $order
     *
     * @return mixed
     */
    public function findByObservatoire($observatoire, $year = 0, ?\DateTime $date = null, $after = true, $order = 'DESC')
    {
        $parameters = ['observatoire' => $observatoire];

        if (!($order === 'ASC' || $order === 'DESC')) {
            $order = 'DESC';
        }

        if (!($after === true || $after === false)) {
            $after = true;
        }

        if ($after) {
            $compareDate = ' AND (h.date >= :date)';
        } else {
            $compareDate = ' AND (h.date < :date)';
        }

        if ($date) {
            $parameters['date'] = $date;
        } else {
            $compareDate = '';
        }

        if ($year && \is_int($year) && $year >= 1900 && $year < 2100) { // TODO mettre à jour quand on sera en 2100
            $compareDate = ' AND (h.date >= :date) AND (h.date < :dateEnd)';
            $date = new \DateTimeImmutable($year . '-01-01', new \DateTimeZone('UTC'));
            $parameters['date'] = $date;
            $parameters['dateEnd'] = $date->add(new \DateInterval('P1Y'));
        }

        $builder = $this->createQueryBuilder('h')
            ->where('(h.observatoire = :observatoire'/* . ' or h.observatoire IS NULL'*/ . ')' . $compareDate)
            ->orderBy('h.date', $order);
        foreach ($parameters as $name => $value) {
            $builder->setParameter($name, $value);
        }
        $query = $builder->getQuery();

        $result = $query->getResult();

        return $result;
    }

    /**
     * La liste des annnées couvertes par les historiques de l'observatoire donné.
     *
     * $observatoire : observatoire
     *
     * @param mixed $observatoire
     *
     * @return array
     */
    final public function getYears($observatoire)
    {
        $sqlYears = <<<'SQL'
SELECT DISTINCT EXTRACT(YEAR FROM h.date) as year 
FROM historique h 
WHERE h.observatoire_id = :observatoire 
ORDER BY year DESC;
SQL;
        $yearsResult = $this->getEntityManager()->getConnection()->executeQuery($sqlYears, [$observatoire->getId()])->fetchAllAssociative();

        $years = [];
        foreach ($yearsResult as $row) {
            $years[] = (int) $row['year'];
        }

        return $years;
    }
}
