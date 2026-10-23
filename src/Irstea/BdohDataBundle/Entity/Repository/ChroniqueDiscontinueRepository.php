<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohDataBundle\Entity\Repository;

use Irstea\BdohDataBundle\Entity\ChroniqueDiscontinue;
use Irstea\BdohDataBundle\Entity\Plage;

/**
 * Class ChroniqueDiscontinueRepository.
 */
class ChroniqueDiscontinueRepository extends ChroniqueRepository
{
    const MEASURE_TABLE = 'plage';

    const BEGIN_DATE_FIELD = 'debut';

    const END_DATE_FIELD = 'fin';

    /**
     * @return MesureRepositoryInterface
     */
    public function getMeasureRepo()
    {
        /** @var MesureRepositoryInterface $repo */
        $repo = $this->getEntityManager()->getRepository(Plage::class);

        return $repo;
    }

    /**
     * For a given 'chronique', returns an array whose structure is :
     *  => Keys   = years
     *  => Values = array(
     *      -> 'month'      => array of count (int)
     *      -> 'totalTaux'  => sum of count (int)
     *      -> 'totalPoids' => 1
     *  )
     *  See, for use, "src/Irstea/BdohConsultBundle/Resources/views/Main/chronique.html.twig".
     *
     * @param ChroniqueDiscontinue $chronique
     *
     * @throws \Exception
     *
     * @return array
     */
    public function getFillingRatesByYearAndMonth(ChroniqueDiscontinue $chronique)
    {
        $sql = <<<'SQL'
WITH mois(debut, fin) AS (SELECT m, m + '1 month' FROM generate_series(date_trunc('month',
    (SELECT datedebutmesures FROM chronique WHERE id = :chronique_id)),
    (SELECT datefinmesures FROM chronique WHERE id = :chronique_id), '1 month') m)
SELECT
    count(p.debut) FILTER (WHERE p.debut >= m.debut) as count,
    count(p.debut) > 0 as overlap,
    date_part('year', m.debut) as year,
    date_part('month', m.debut) as month
FROM mois m LEFT OUTER JOIN plage p ON (p.debut, p.fin) OVERLAPS (m.debut, m.fin) AND p.chronique_id = :chronique_id
GROUP BY m.debut
ORDER BY m.debut ASC;
SQL;

        $stmt = $this->getEntityManager()->getConnection()->executeQuery(
            $sql,
            [
                'chronique_id' => $chronique->getId(),
            ]
        );

        $fillingRates = [];

        foreach ($stmt->iterateAssociative() as $row) {
            if (false === array_key_exists($row['year'], $fillingRates)) {
                $fillingRates[$row['year']] = [
                    'month'      => [],
                    'totalTaux'  => 0,
                    'totalPoids' => 1,
                    'overlap'    => false,
                ];
            }
            $currentYear = &$fillingRates[$row['year']];

            $currentYear['month'][$row['month']] = ['taux' => $row['count'], 'overlap' => $row['overlap']];

            $currentYear['totalTaux'] += $row['count'];
            $currentYear['overlap'] = $currentYear['overlap'] || $row['overlap'];
        }

        return $fillingRates;
    }

    /**
     * Shortcut to PlageRepository::getFirstLastDatesByChronique().
     *
     * @param array $chroniqueIds
     *
     * @return array|array[]
     */
    public function getFirstLastDatesByChronique(array $chroniqueIds = [])
    {
        return $this->getMeasureRepo()->getFirstLastDatesByChronique($chroniqueIds);
    }

    /**
     * @param ChroniqueDiscontinue $chronique
     * @param \DateTime|null       $beginDate
     * @param \DateTime|null       $endDate
     *
     * @throws \Exception
     *
     * @return array
     */
    public function getOneChronicleViewerData(
        ChroniqueDiscontinue $chronique,
        ?\DateTime $beginDate = null,
        ?\DateTime $endDate = null
    ) {
        $sql = <<<SQL
SELECT
    1000 * EXTRACT('epoch' FROM p.debut AT TIME ZONE 'UTC') AS debut,
    1000 * EXTRACT('epoch' FROM p.fin AT TIME ZONE 'UTC') AS fin,
    p.valeur AS valeur,
    q.id AS qid,
    CASE q.ordre
        WHEN 100 THEN 'gap'
        WHEN 200 THEN 'invalid'
        ELSE 'valid_' || q.code
    END AS flag
FROM plage p INNER JOIN qualite q ON (p.qualite_id = q.id)
WHERE p.chronique_id = :id AND (p.debut, p.fin) OVERLAPS (:beginDate, :endDate)
ORDER BY p.debut ASC
SQL;

        $stmt = $this->getEntityManager()->getConnection()->executeQuery(
            $sql,
            [
                'id'        => $chronique->getId(),
                'beginDate' => $beginDate !== null ? $beginDate->format(\DateTime::ATOM) : '-infinity',
                'endDate'   => $endDate !== null ? $endDate->format(\DateTime::ATOM) : 'infinity',
            ]
        );

        $series = [];
        /* $series = [
            qid => [
                'seriesFlag' => flag,
                'values' => [[date, valeur], ...],
                'qualiteId' => qid
            ],
            ...
        ] */
        $validMeasureCount = 0;
        $pointCount = 0;

        foreach ($stmt->iterateAssociative() as $row) {
            $debut = (float) $row['debut'];
            $fin = (float) $row['fin'];
            $valeur = $row['valeur'] === null ? 0.0 : (float) $row['valeur'];
            $qid = $row['qid'];
            $flag = $row['flag'];

            if ($flag !== 'invalid' && $flag !== 'gap') {
                ++$validMeasureCount;
            }
            ++$pointCount;

            if (!isset($series[$qid])) {
                $series[$qid] = [
                    'seriesFlag' => $flag,
                    'values'     => [],
                    'qualiteId'  => $qid,
                ];
            }

            $series[$qid]['values'][] = [$debut, $valeur];
            $series[$qid]['values'][] = [$fin, $valeur];
            $series[$qid]['values'][] = [null, null];
        }

        // trie des series suivant le qid pour que la légende soit dans l'ordre des qualités
        \ksort($series, SORT_NUMERIC);

        return [
            'samplingType'            => 'discontinuous',
            'discontinuousStyle'      => $chronique->getObservatoire()->getJeu()->getDiscontinuousStyle(),
            'reverseAxis'             => $chronique->getReverseAxis(),
            'series'                  => array_values($series),
            'validMeasureCount'       => $validMeasureCount,
            'nPts'                    => $pointCount,
            'tooManyPoints'           => false,
            'checkpointsOnCumulative' => false,
        ];
    }
}
