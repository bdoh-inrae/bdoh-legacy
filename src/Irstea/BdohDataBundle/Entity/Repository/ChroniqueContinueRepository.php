<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohDataBundle\Entity\Repository;

use Irstea\BdohDataBundle\Entity\ChroniqueContinue;
use Irstea\BdohDataBundle\Entity\Echantillonnage;
use Irstea\BdohDataBundle\Entity\Mesure;
use Irstea\BdohDataBundle\Entity\PasEchantillonnage;
use Irstea\BdohDataBundle\Entity\PointControle;
use Irstea\BdohDataBundle\Util\JeuToJeu;
use Irstea\BdohSecurityBundle\Entity\Utilisateur;

/**
 * Class ChroniqueContinueRepository.
 */
class ChroniqueContinueRepository extends ChroniqueRepository
{
    /**
     * @return MesureRepositoryInterface
     */
    public function getMeasureRepo()
    {
        /** @var MesureRepositoryInterface $repo */
        $repo = $this->getEntityManager()->getRepository(Mesure::class);

        return $repo;
    }

    /**
     * Shortcut to MesureRepository::getFirstLastDatesByChronique().
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
     * According to a start date and an end date, chooses and returns a time step for cumulative data.
     *
     * @param string $startDate Start date
     * @param string $endDate   End date
     *
     * @throws \Exception
     *
     * @return int The time steps in seconds
     */
    private function getCumulativeTimeStep($startDate, $endDate)
    {
        if ($startDate && $endDate) {
            // si la periode est inférieure ou égale à 1 mois le pas de temps est de 1h (3600 secondes)
            // sinon le pas de temps est de 1j (86400 secondes)
            $oneMonthAfterStart = \date_modify(new \DateTime($startDate, new \DateTimeZone('UTC')), '+1 month');
            $lapse = strtotime($endDate) - $oneMonthAfterStart->getTimestamp();
            $timeStep = ($lapse > 0) ? 86400 : 3600;
        } else {
            $timeStep = 86400;
        }

        return $timeStep;
    }

    /**getFillingRatesByYearAndMonth
     * For a given 'chronique', returns an array whose structure is :
     *  => Keys   = years
     *  => Values = array(
     *      -> 'month'      => array of rate (float)
     *      -> 'totalTaux'  => sum of weighted rates (float)
     *      -> 'totalPoids' => sum of weights
     *  )
     *  See, for use, "src/Irstea/BdohConsultBundle/Resources/views/Main/chronique.html.twig".
     *
     * @param ChroniqueContinue $chronique
     *
     * @return array
     */
    public function getFillingRatesByYearAndMonth(ChroniqueContinue $chronique)
    {
        $sql = 'SELECT 100 * taux as taux, poids, (100 * taux * poids) as weightedrate, ' .
            "date_part('year', debut) as year, " .
            "date_part('month', debut) as month " .
            'FROM tauxremplissage ' .
            'WHERE chronique_id = ' . $chronique->getId() . ' ' .
            'ORDER BY year DESC, month ASC';

        $results = $this->getEntityManager()->getConnection()->fetchAllAssociative($sql);
        $fillingRates = [];

        foreach ($results as $result) {
            if (false === array_key_exists($result['year'], $fillingRates)) {
                $fillingRates[$result['year']] = [
                    'month'      => [],
                    'totalTaux'  => 0,
                    'totalPoids' => 0,
                ];
            }
            $currentYear = &$fillingRates[$result['year']];

            $currentYear['month'][$result['month']] = ['taux' => $result['taux'], 'overlap'  => false];

            $currentYear['totalTaux'] += $result['weightedrate'];
            $currentYear['totalPoids'] += $result['poids'];
            $currentYear['overlap'] = false;
        }

        return $fillingRates;
    }

    /**
     * Selects measures for a given 'chronique', between two dates.
     * Note : this query is intended for "curves viewer" purpose.
     *
     * @param ChroniqueContinue $chronique
     * @param mixed             $beginDate
     * @param mixed             $endDate
     * @param bool              $rightsForCheckpoints
     * @param mixed             $maxValidPoints
     *
     * @throws \Exception
     *
     * @return array
     */
    public function getOneChronicleViewerData(ChroniqueContinue $chronique, $beginDate, $endDate, $rightsForCheckpoints, $maxValidPoints = 50000)
    {
        if (!($beginDate instanceof \DateTime)) {
            $beginDate = new \DateTime($beginDate ?: '0000-01-01 UTC');
        }
        if (!($endDate instanceof \DateTime)) {
            $endDate = new \DateTime($endDate ?: '9999-12-31 UTC');
        }

        // Might need to specify that $beginDate & $endDate are in UTC
        $utc = new \DateTimeZone('UTC');
        $beginDate->setTimezone($utc);
        $endDate->setTimezone($utc);

        $beginDate = $beginDate->format(\DateTime::ATOM);
        $endDate = $endDate->format(\DateTime::ATOM);

        // For the current observatory, get the IDs of "gap" qualities
        $jeuToJeu = new JeuToJeu($this->getEntityManager());
        $gapIds = $jeuToJeu->getGapTypeIds();

        // A few necessary values
        $sampling = $chronique->getEchantillonnage();
        $samplingType = $sampling ? $sampling->getCode() : 'instantaneous';
        switch ($samplingType) {
            case 'mean':
                $tableOfMeasures = $this->seriesInstantaneousOrMeanSampling(
                    $chronique,
                    $beginDate,
                    $endDate,
                    true,
                    // on privilégie *ahead* pour les moyennes si la direction n'est pas définie
                    $chronique->getDirectionMesure() !== 'backward',
                    $maxValidPoints
                );
                break;
            case 'cumulative':
                $tableOfMeasures = $this->seriesCumulativeSampling(
                    $chronique,
                    $beginDate,
                    $endDate,
                    // on privilégie *backward* pour les cumuls si la direction n'est pas définie
                    $chronique->getDirectionMesure() === 'ahead'
                );
                break;
            default:
                $tableOfMeasures = $this->seriesInstantaneousOrMeanSampling(
                    $chronique,
                    $beginDate,
                    $endDate,
                    false,
                    false,
                    $maxValidPoints
                );
        }

        // Indicate the sampling type (continuous/mean/cumulative) in the return value
        $tableOfMeasures['samplingType'] = $samplingType;

        // Whether the Y axis should be reversed for this time series
        $tableOfMeasures['reverseAxis'] = $chronique->getReverseAxis();

        // on n'affiche pas les points de contrôle sur les graphes en cumulé
        // mais on affiche l'information de l'existance de ptc sur la periode
        $tableOfMeasures['checkpointsOnCumulative'] = false;

        // Add the possible control points to the output data
        if ($rightsForCheckpoints) {
            /** @var PointControleRepository $controlPointsRepo */
            $controlPointsRepo = $this->getEntityManager()->getRepository(PointControle::class);
            $controlPoints = $controlPointsRepo->getControlesFromDates($chronique, $beginDate, $endDate);
            if ($controlPoints && ($nCP = sizeof($controlPoints))) {
                if ($samplingType !== 'cumulative') {
                    $controlSeries = \array_fill(0, $nCP, [0.0, 0.0]);
                    $i = 0;
                    foreach ($controlPoints as $cp) {
                        $controlSeries[$i][0] = 1000.0 * (float) strtotime($cp['date']);
                        $controlSeries[$i][1] = (float) $cp['valeur'];
                        ++$i;
                    }
                    $tableOfMeasures['series'][] = [
                        'seriesFlag' => 'controlPoints',
                        'values'     => $controlSeries,
                        'style'      => $chronique->getObservatoire()->getJeu()->getCheckpointStyle(),
                        'label'      => 'checkpoints', // traduit dans le contrôleur
                        'showLabel'  => true,
                    ];
                } else {
                    $tableOfMeasures['checkpointsOnCumulative'] = true;
                }
            }
        }

        $nPts = 0;
        foreach ($tableOfMeasures['series'] as $s) {
            if ($s['seriesFlag'] !== 'controlPoints') {
                $nPts += sizeof($s['values']);
            }
        }
        $tableOfMeasures['nPts'] = $nPts;

        return $tableOfMeasures;
    }

    /**
     * @param ChroniqueContinue $chronique
     * @param string            $beginDate
     * @param string            $endDate
     * @param bool              $mean
     * @param bool              $ahead
     * @param int               $maxAllowedPoints
     *
     * @throws \Exception
     *
     * @return array
     */
    private function seriesInstantaneousOrMeanSampling(
        $chronique,
        $beginDate,
        $endDate,
        $mean,
        $ahead,
        $maxAllowedPoints = 50000
    ) {
        // vérification de la présence de mesures sur l'intervalle
        $nbMesures = $chronique->getNbMesures();
        $dateDebut = $chronique->getDateDebutMesures();
        $dateFin = $chronique->getDateFinMesures();
        $beginDateTime = new \DateTime($beginDate, new \DateTimeZone('UTC'));
        $endDateTime = new \DateTime($endDate, new \DateTimeZone('UTC'));
        $debutDateTime = new \DateTime($dateDebut, new \DateTimeZone('UTC'));
        $finDateTime = new \DateTime($dateFin, new \DateTimeZone('UTC'));
        if (!$nbMesures || !$dateDebut || !$dateFin ||
            $endDateTime < $debutDateTime || $finDateTime < $beginDateTime) {
            return [
                'series'            => [],
                'validMeasureCount' => 0,
                'tooManyPoints'     => false,
                'ahead'             => $ahead,
            ];
        }

        // l'affichage en moyennes nécéssite 2 points par mesure
        if ($mean) {
            $maxAllowedPoints /= 2;
        }

        $chroniqueId = $chronique->getId();

        // détermination des plages de qualités indentiques pour evaluer le
        // nombre de points "valides" et la nécéssité d'un sous échantillonage
        // avec récupération d'informations utiles pour le sous-échantillonnage

        $sqlBoundaries = <<<SQL
WITH flags(i, date, epochms, flag) AS (
    SELECT
        ROW_NUMBER() OVER () AS i,
        date,
        1000 * EXTRACT(EPOCH FROM m.date AT TIME ZONE 'UTC') AS epochms,
        CASE q.ordre
            WHEN 100 THEN 'gap'
            WHEN 200 THEN 'invalid'
            ELSE 'valid_' || q.code
        END AS flag
    FROM mesure m INNER JOIN qualite q ON (m.qualite_id = q.id)
    WHERE
        chronique_id = $chroniqueId
        AND m.date >= (SELECT COALESCE(MAX(date), '$beginDate') FROM mesure WHERE chronique_id = $chroniqueId AND date <= '$beginDate')
        AND m.date <= (SELECT COALESCE(MIN(date), '$endDate') FROM mesure WHERE chronique_id = $chroniqueId AND date >= '$endDate')
    ORDER BY m.date ASC
)
SELECT * FROM (
    SELECT
        *,
        COALESCE(flag <> LAG(flag) OVER(), true) AS debut,
        COALESCE(flag <> LEAD(flag) OVER(), true) AS fin
    FROM flags
) f WHERE debut or fin
SQL;

        $boundariesStmt = $this->getEntityManager()->getConnection()->executeQuery($sqlBoundaries);
        $boundaries = $boundariesStmt->fetchAllNumeric();

        // le nombre de bornes
        $nbBoundaries = \count($boundaries);

        $boudariesData = [];
        $nbValid = 0;
        $spanValid = 0.0;
        $dataIndex = 0;
        foreach ($boundaries as $boundary) {
            // récupération des données de la plage de qualité
            list($i, $date, $epochms, $flag, $debut, $fin) = $boundary;
            $epochms = (float) $epochms;
            if ($debut) {
                $boudariesData[$dataIndex] = [];
                $boudariesData[$dataIndex]['i_debut'] = $i;
                $boudariesData[$dataIndex]['date_debut'] = $date;
                $boudariesData[$dataIndex]['epochms_debut'] = $epochms;
            }
            if ($fin) {
                $boudariesData[$dataIndex]['i_fin'] = $i;
                $boudariesData[$dataIndex]['date_fin'] = $date;
                $boudariesData[$dataIndex]['epochms_fin'] = $epochms;
                if ($flag !== 'invalid' && $flag !== 'gap') {
                    $nbValid += $boudariesData[$dataIndex]['i_fin'] - $boudariesData[$dataIndex]['i_debut'] + 1;
                    $spanValid += $boudariesData[$dataIndex]['epochms_fin'] - $boudariesData[$dataIndex]['epochms_debut'];
                }
                ++$dataIndex;
            }
        }

        if ($nbValid <= $maxAllowedPoints) { // pas de sous-échantillonnage
            // toutes les mesures
            $sqlMesures = <<<SQL
SELECT
    1000 * EXTRACT(EPOCH FROM m.date AT TIME ZONE 'UTC') AS date,
    m.valeur AS valeur,
    m.qualite_id AS qid,
    q.ordre AS ordre,
    CASE q.ordre
        WHEN 100 THEN 'gap'
        WHEN 200 THEN 'invalid'
        ELSE 'valid_' || q.code
    END AS flag
FROM mesure m INNER JOIN qualite q ON (m.qualite_id = q.id)
WHERE
    m.chronique_id = $chroniqueId
    AND m.date >= (SELECT COALESCE(MAX(date), '$beginDate') FROM mesure WHERE chronique_id = $chroniqueId AND date <= '$beginDate')
    AND m.date <= (SELECT COALESCE(MIN(date), '$endDate') FROM mesure WHERE chronique_id = $chroniqueId AND date >= '$endDate')
ORDER BY m.date ASC
SQL;
        } else { // on sous-échantillonne
            // reduction du nombre de points max autorisés par le nombre de bornes
            // (qui, elles, ne disparaissent pas pendant le sous échantillonnage)
            $maxAllowedPointsRedux = \max($maxAllowedPoints - $nbBoundaries, 1);

            // détermination de la durée d'échantillonnage de base sachant que l'on prend
            // 2 points par echantillonnage (le min et le max pour chaque échantillonnage)
            $baseSpliceSpan = $spanValid * 2.0 / (float) $maxAllowedPointsRedux;

            // les données d'echantillonnage
            $datesDebuts = [];
            $datesFins = [];
            $sliceSpans = [];
            foreach ($boudariesData as $boudaryData) {
                $datesDebuts[] = $boudaryData['date_debut'];
                $datesFins[] = $boudaryData['date_fin'];
                $span = $boudaryData['epochms_fin'] - $boudaryData['epochms_debut'];
                $nbSlices = \max($span / $baseSpliceSpan, 1.0);
                $sliceSpans[] = \max(\ceil($span / $nbSlices), 1.0) / 1000.0;
            }
            $datesDebuts = "'{" . \implode(',', $datesDebuts) . "}'";
            $datesFins = "'{" . \implode(',', $datesFins) . "}'";
            $sliceSpans = "'{" . \implode(',', $sliceSpans) . "}'";

            // les mesures sous-echantillonnées
            $sqlMesures = <<<SQL
SELECT * FROM bdoh_subsampled_instantaneous_all_series_measures_with_flag($chroniqueId, $datesDebuts, $datesFins, $sliceSpans)
SQL;
        }

        $mesures = $this->getEntityManager()->getConnection()->executeQuery($sqlMesures);

        $series = [];
        /* $series = [
            qid => [
                'seriesFlag' => flag,
                'values' => [[date, valeur], ...],
                'qualiteId' => qid
            ],
            ...
        ] */
        $lastMesure = null;
        $lastValueNotAdded = false;

        /* Note sur l'affrontement des qualités :

        on ne suit pas le tableau d'affrontement des qualités de la documentation sur les lq/ld

        seul l'ordre inférieur gagne

        donc lq (600) vs ld (700) -> lq (et pas éstimé)
        pareil pour lq vs lq -> lq et ld vs ld -> ld
        et v (800) vs lq/ld -> lq/ld (et pas éstimé) */

        foreach ($mesures->iterateNumeric() as $mesure) {
            $lastValueNotAdded = false;

            // récupération des données de la mesure
            list($date, $valeur, $qid, $ordre, $flag) = $mesure;
            $date = (float) $date;
            $valeur = $valeur === null ? 0.0 : (float) $valeur;

            if (!isset($series[$qid])) {
                $series[$qid] = [
                    'seriesFlag' => $flag,
                    'values'     => [],
                    'qualiteId'  => $qid,
                ];
            }

            if ($lastMesure) {
                // récupération des données de la mesure précédente
                list($last_date, $last_valeur, $last_qid, $last_ordre, $last_flag) = $lastMesure;
                $last_date = (float) $last_date;
                $last_valeur = $last_valeur === null ? 0.0 : (float) $last_valeur;

                // gestion de la propagation des qualités si la qualité change
                if ($last_flag !== $flag) {
                    if ($last_ordre > $ordre) {
                        // propagation de l'actuelle sur la précédente (amont)
                        $last_valeur_corrected = $last_valeur;
                        if ($flag === 'invalid' || $flag === 'gap') {
                            $last_valeur_corrected = 0.0;
                        }
                        // point de propagation de la qualité
                        $series[$qid]['values'][] = [$last_date, $last_valeur_corrected];
                        // point intermédiaire pour le plateau des moyennes
                        if ($mean && $flag !== 'invalid' && $flag !== 'gap') {
                            if ($ahead) {
                                $series[$qid]['values'][] = [$date, $last_valeur];
                            } else {
                                $series[$qid]['values'][] = [$last_date, $valeur];
                            }
                        }
                    } elseif ($last_ordre < $ordre) {
                        // propagation de la précédente sur l'actuelle (aval)
                        $valeur_corrected = $valeur;
                        if ($last_flag === 'invalid' || $last_flag === 'gap') {
                            $valeur_corrected = 0.0;
                        }
                        // point intermédiaire pour le plateau des moyennes
                        if ($mean && $flag !== 'invalid' && $flag !== 'gap') {
                            if ($ahead) {
                                $series[$last_qid]['values'][] = [$date, $last_valeur];
                            } else {
                                $series[$last_qid]['values'][] = [$last_date, $valeur];
                            }
                        }
                        // point de propagation de la qualité
                        $series[$last_qid]['values'][] = [$date, $valeur_corrected];
                    }
                    // intéruption de la continuité de la série précédente
                    $series[$last_qid]['values'][] = [null, null];
                }

                // pas de changement de qualité
                else {
                    // point intermédiaire pour le plateau des moyennes
                    if ($mean && $flag !== 'invalid' && $flag !== 'gap') {
                        if ($ahead) {
                            $series[$qid]['values'][] = [$date, $last_valeur];
                        } else {
                            $series[$qid]['values'][] = [$last_date, $valeur];
                        }
                    }
                }

                // enregistrement du point courant si on n'est pas sur un invalid ni un gap
                // ou si on est sur un invalid qui suit un gap
                if (($flag !== 'invalid' && $flag !== 'gap') || ($last_ordre < $ordre)) {
                    $series[$qid]['values'][] = [$date, $valeur];
                } else {
                    $lastValueNotAdded = true;
                }
            } else {
                // enregistrement du tout premier point
                $series[$qid]['values'][] = [$date, $valeur];
            }

            // enregistrement de la mesure dans la mesure précédente
            $lastMesure = $mesure;
        }

        // ajout du tout dernier point s'il n'a pas été ajouté
        if ($lastValueNotAdded === true) {
            // récupération des données de la dernière mesure
            list($last_date, $last_valeur, $last_qid, $last_ordre, $last_flag) = $lastMesure;
            $last_date = (float) $last_date;
            $last_valeur = $last_valeur === null ? 0.0 : (float) $last_valeur;
            $series[$last_qid]['values'][] = [$last_date, $last_valeur];
        }

        // pas de recalcul des bornes aux bornes du graphe (le graphe n'en a pas besoin)

        // suppression des points en dehors de la periode affichée s'ils ne sont pas en continuité
        // au début des séries
        foreach ($series as &$serie) {
            if ($serie['values'][0][0] > (float) (1000 * $endDateTime->getTimestamp())) {
                \array_shift($serie['values']);
            }
            if (count($serie['values']) === 0) {
                unset($series[$serie['qualiteId']]);
            }
        }
        // les [null, null] à la fin des séries
        foreach ($series as &$serie) {
            if ($serie['values'][\count($serie['values']) - 1] === [null, null]) {
                \array_pop($serie['values']);
            }
            if (count($serie['values']) === 0) {
                unset($series[$serie['qualiteId']]);
            }
        }
        // à la fin des séries
        foreach ($series as &$serie) {
            if ($serie['values'][\count($serie['values']) - 1][0] < (float) (1000 * $beginDateTime->getTimestamp())) {
                \array_pop($serie['values']);
            }
            if (count($serie['values']) === 0) {
                unset($series[$serie['qualiteId']]);
            }
        }

        // trie des series suivant le qid pour que la légende soit dans l'ordre des qualités
        \ksort($series, SORT_NUMERIC);

        return [
            'series'            => \array_values($series),
            'validMeasureCount' => $nbValid,
            'tooManyPoints'     => $nbValid > $maxAllowedPoints,
            'ahead'             => $ahead,
        ];
    }

    /**
     * @param ChroniqueContinue $chronique
     * @param string            $beginDate
     * @param string            $endDate
     * @param bool              $ahead
     *
     * @throws \Exception
     *
     * @return array
     */
    private function seriesCumulativeSampling(
        $chronique,
        $beginDate,
        $endDate,
        $ahead
    ) {
        $chroniqueId = $chronique->getId();
        $timeStep = $this->getCumulativeTimeStep($beginDate, $endDate);

        if ($ahead) {
            $math = 'FLOOR';
            $whereDate = <<<SQL
        AND m.date >= '$beginDate'::TIMESTAMP WITHOUT TIME ZONE AT TIME ZONE 'UTC' - (INTERVAL '1 second' * $timeStep)
        AND m.date < '$endDate'::TIMESTAMP WITHOUT TIME ZONE AT TIME ZONE 'UTC' + (INTERVAL '1 second' * $timeStep)
SQL;
        } else {
            $math = 'CEIL';
            $whereDate = <<<SQL
        AND m.date > '$beginDate'::TIMESTAMP WITHOUT TIME ZONE AT TIME ZONE 'UTC' - (INTERVAL '1 second' * $timeStep)
        AND m.date <= '$endDate'::TIMESTAMP WITHOUT TIME ZONE AT TIME ZONE 'UTC' + (INTERVAL '1 second' * $timeStep)
SQL;
        }

        $sqlCumuls = <<<SQL
WITH qualites(qid, ordre, flag) AS (
    SELECT DISTINCT
        m.qualite_id AS qid,
        q.ordre AS ordre,
        CASE q.ordre
            WHEN 100 THEN 'gap'
            WHEN 200 THEN 'invalid'
            ELSE 'valid_' || q.code
        END AS flag
    FROM mesure m INNER JOIN qualite q ON (m.qualite_id = q.id)
    WHERE q.code <> 'gap' AND chronique_id = $chroniqueId
),
mesures(stepbefore, step, stepafter, valeur, ordre) AS (
    SELECT
        1000 * EXTRACT(EPOCH FROM ('$beginDate'::TIMESTAMP WITHOUT TIME ZONE AT TIME ZONE 'UTC' +
            (INTERVAL '1 second' * (($math(EXTRACT(EPOCH FROM AGE(m.date AT TIME ZONE 'UTC',
                '$beginDate' AT TIME ZONE 'UTC')) / $timeStep) - 1) * $timeStep)))) AS stepbefore,
        1000 * EXTRACT(EPOCH FROM ('$beginDate'::TIMESTAMP WITHOUT TIME ZONE AT TIME ZONE 'UTC' +
            (INTERVAL '1 second' * ($math(EXTRACT(EPOCH FROM AGE(m.date AT TIME ZONE 'UTC',
                '$beginDate' AT TIME ZONE 'UTC')) / $timeStep) * $timeStep)))) AS step,
        1000 * EXTRACT(EPOCH FROM ('$beginDate'::TIMESTAMP WITHOUT TIME ZONE AT TIME ZONE 'UTC' +
            (INTERVAL '1 second' * (($math(EXTRACT(EPOCH FROM AGE(m.date AT TIME ZONE 'UTC',
                '$beginDate' AT TIME ZONE 'UTC')) / $timeStep) + 1) * $timeStep)))) AS stepafter,
        m.valeur::NUMERIC AS valeur,
        q.ordre AS ordre
    FROM mesure m INNER JOIN qualite q ON (m.qualite_id = q.id)
    WHERE
        chronique_id = $chroniqueId
        $whereDate
    ORDER BY m.date
),
cumuls(stepbefore, step, stepafter, sum_valeur, min_ordre) AS (
    SELECT stepbefore, step, stepafter, sum(valeur), min(ordre)
    FROM mesures
    GROUP BY stepbefore, step, stepafter
    ORDER BY step
)
SELECT stepbefore, step, stepafter, sum_valeur, qid, flag
FROM cumuls INNER JOIN qualites ON (min_ordre = ordre)
ORDER BY step
SQL;

        $cumuls = $this->getEntityManager()->getConnection()->executeQuery($sqlCumuls);

        $series = [];
        /* $series = [
            qid => [
                'seriesFlag' => flag,
                'values' => [[date, valeur], ...],
                'qualiteId' => qid
            ],
            ...
        ] */
        $nbValid = 0;
        $lastFlag = null;

        foreach ($cumuls->iterateNumeric() as $cumul) {
            // récupération des données du cumul
            list($stepbefore, $step, $stepafter, $sum_valeur, $qid, $flag) = $cumul;
            $stepbefore = (float) $stepbefore;
            $step = (float) $step;
            $stepafter = (float) $stepafter;
            $sum_valeur = (float) $sum_valeur;

            if (!isset($series[$qid])) {
                $series[$qid] = [
                    'seriesFlag' => $flag,
                    'values'     => [],
                    'qualiteId'  => $qid,
                ];
            }

            if ($flag === 'invalid' || $flag === 'gap') {
                if ($lastFlag && $lastFlag !== $flag) {
                    $series[$qid]['values'][] = [null, null];
                }
                if ($ahead) {
                    $series[$qid]['values'][] = [$step, 0.0];
                    $series[$qid]['values'][] = [$stepafter, 0.0];
                } else {
                    $series[$qid]['values'][] = [$stepbefore, 0.0];
                    $series[$qid]['values'][] = [$step, 0.0];
                }
            } else {
                $series[$qid]['values'][] = [$step, $sum_valeur];
                ++$nbValid;
            }

            $lastFlag = $flag;
        }

        // trie des series suivant le qid pour que la légende soit dans l'ordre des qualités
        \ksort($series, SORT_NUMERIC);

        return [
            'series'            => \array_values($series),
            'validMeasureCount' => $nbValid,
            'tooManyPoints'     => false,
            'timeStep'          => $timeStep,
            'ahead'             => $ahead,
        ];
    }

    /**
     * @param ChroniqueContinue $chronique
     * @param \DateTime         $debut
     * @param \DateTime         $fin
     * @param $timestep
     * @param \DateTimeZone $timezone
     *
     * @throws \Exception
     *
     * @return array
     */
    public function getDataChangeStepInstant(
        ChroniqueContinue $chronique,
        \DateTime $debut,
        \DateTime $fin,
        $timestep,
        \DateTimeZone $timezone
    ) {
        $timestepSeconds = (60 * $timestep);

        return $this->extractResampledMeasures(
            <<<'SQL'
                SELECT
                    s.step%TZCORRECTION% AS date,
                    bdoh_interpolate_linear(s.start_date, s.start_valeur, s.end_date, s.end_valeur, s.step) AS valeur,
                    q.code AS qualite
                FROM
                    bdoh_export_interpolate_linear(:chronique_id, :debut::TIMESTAMP, :fin::TIMESTAMP, :timestep::INTERVAL) s
                    INNER JOIN interp_qualite iq
                        ON (iq.input1_id = s.start_qualite_id AND iq.input2_id = s.end_qualite_id)
                    INNER JOIN qualite q
                        ON (q.jeu_id = :jeu_id AND q.id =
                            CASE s.step
                                WHEN s.start_date THEN
                                    s.start_qualite_id
                                ELSE
                                    iq.output_id
                            END)
                ORDER BY s.step;
SQL
            ,
            $chronique,
            $debut,
            $fin,
            sprintf('%d minutes', $timestep),
            $timezone,
            ['jeu_id' => $chronique->getObservatoire()->getJeu()->getId()]
        );
    }

    /**
     * @param string            $query
     * @param ChroniqueContinue $chronique
     * @param \DateTime         $debut
     * @param \DateTime         $fin
     * @param int|string        $timestep
     * @param \DateTimeZone     $timezone
     * @param array             $moreParameters
     *
     * @throws \Exception
     *
     * @return array
     */
    private function extractResampledMeasures(
        $query,
        ChroniqueContinue $chronique,
        \DateTime $debut,
        \DateTime $fin,
        $timestep,
        \DateTimeZone $timezone,
        array $moreParameters = []
    ) {
        list($withTzCorrection, $parameters) = $this->getTzCorrectionForSql($timezone);

        $parameters = array_merge(
            $moreParameters,
            $parameters,
            [
                'chronique_id' => $chronique->getId(),
                'debut'        => $this->formatTimestampAtUtc($debut),
                'fin'          => $this->formatTimestampAtUtc($fin),
                'timestep'     => $timestep,
            ]
        );

        $queryWithTz = str_replace('%TZCORRECTION%', $withTzCorrection, $query);

        return [
            $this->getEntityManager()->getConnection()->executeQuery($queryWithTz, $parameters)->iterateAssociative(),
            $this->getExportReport($chronique, $debut, $fin),
        ];
    }

    /**
     * @param ChroniqueContinue  $chronique
     * @param \DateTime          $debut
     * @param \DateTime          $fin
     * @param PasEchantillonnage $timestep
     * @param \DateTimeZone      $timezone
     *
     * @throws \Exception
     *
     * @return array
     */
    public function getDataChangeStepMean(
        ChroniqueContinue $chronique,
        \DateTime $debut,
        \DateTime $fin,
        PasEchantillonnage $timestep,
        \DateTimeZone $timezone
    ) {
        return [
            $this->getResampledMeasures(
                $chronique,
                $debut,
                $fin,
                $timestep->getId(),
                $timezone,
                [
                    'boundary_start %TZCORRECTION% AS debut',
                    'boundary_end %TZCORRECTION% AS fin',
                    'value AS valeur',
                    'quality AS qualite',
                ],
                "bdoh_interp_to_mean_or_cumul(:chronique_id, :debut, :fin, :timestep, true, 'round-time')"
            ),
            $this->getExportReport($chronique, $debut, $fin),
        ];
    }

    /**
     * @param ChroniqueContinue  $chronique
     * @param \DateTime          $debut
     * @param \DateTime          $fin
     * @param PasEchantillonnage $timestep
     * @param \DateTimeZone      $timezone
     *
     * @throws \Exception
     *
     * @return array
     */
    public function getDataChangeStepCumulative(
        ChroniqueContinue $chronique,
        \DateTime $debut,
        \DateTime $fin,
        PasEchantillonnage $timestep,
        \DateTimeZone $timezone
    ) {
        return [
            $this->getResampledMeasures(
                $chronique,
                $debut,
                $fin,
                $timestep->getId(),
                $timezone,
                [
                    'boundary_start %TZCORRECTION% AS debut',
                    'boundary_end %TZCORRECTION% AS fin',
                    'valeur',
                    'qualite',
                ],
                "bdoh_interp_to_cumulative(:chronique_id, :debut, :fin, :timestep, 'round-time')"
            ),
            $this->getExportReport($chronique, $debut, $fin),
        ];
    }

    /**
     * @param ChroniqueContinue $chronique
     * @param \DateTime         $debut
     * @param \DateTime         $fin
     * @param $timestep
     * @param \DateTimeZone $timezone
     * @param array         $columns
     * @param $from
     *
     * @throws \Exception
     *
     * @return \Traversable les mesures, ligne à ligne
     */
    private function getResampledMeasures(
        ChroniqueContinue $chronique,
        \DateTime $debut,
        \DateTime $fin,
        $timestep,
        \DateTimeZone $timezone,
        array $columns,
        $from
    ) {
        $dbal = $this->getEntityManager()->getConnection();

        list($withTzCorrection, $parameters) = $this->getTzCorrectionForSql($timezone);

        $parameters = array_merge(
            $parameters,
            [
                'chronique_id' => $chronique->getId(),
                'debut'        => $this->formatTimestampAtUtc($debut),
                'fin'          => $this->formatTimestampAtUtc($fin),
                'timestep'     => $timestep,
            ]
        );

        $columnsWithTz = array_map(
            function ($column) use ($withTzCorrection) {
                return str_replace('%TZCORRECTION%', $withTzCorrection, $column);
            },
            $columns
        );

        $builder = $dbal
            ->createQueryBuilder()
            ->select(...$columnsWithTz)
            ->from($from);

        return $dbal->executeQuery($builder->getSQL(), $parameters)->iterateAssociative();
    }

    /**
     * @param Utilisateur|mixed $user
     *
     * @return array
     */
    public function securedFindAllHavingControlePoints($user)
    {
        $sql = 'select distinct chronique_id from pointcontrole';
        $results = $this->getEntityManager()->getConnection()->fetchAllAssociative($sql);

        $ids = [];
        foreach ($results as $result) {
            $ids[] = $result['chronique_id'];
        }

        $qb = $this->createSecuredQueryBuilderForEdit($user, 'ch');

        if ($ids !== []) {
            $qb = $qb->andWhere('ch.id in (' . implode(',', $ids) . ')');
        } else {
            $qb = $qb->andWhere('false = true');
        }

        return $qb
            ->orderBy('st.nom', 'ASC')
            ->addOrderBy('ch.code', 'ASC')
            ->getQuery()->getResult();
    }

    /**
     * Selects PointControle for a given 'chronique'.
     *
     * Returns an array whose structure is:
     *      => 0 == "Doctrine statement" (Doctrine\DBAL\Driver\Statement)
     *      => 1 == array(
     *          "first"  => SQL date
     *          "last"   => SQL date
     *          "number" => number of measures selected
     *      )
     *
     * Note : this query is intended for export purpose.
     *
     * @param ChroniqueContinue $chronique
     * @param mixed             $timezone
     * @param mixed             $defaultQualityCode
     *
     * @throws \Exception
     *
     * @return array
     */
    public function getControlePointsForExport(ChroniqueContinue $chronique, $timezone, $defaultQualityCode = '')
    {
        // SQL query for measures selection
        $qualityColumn = $defaultQualityCode ? "COALESCE(q.code, '$defaultQualityCode')" : 'q.code';
        $measuresSql = "SELECT (p.date + '$timezone') as date, p.valeur, $qualityColumn as qualite, p.minimum, p.maximum " .
            'FROM pointcontrole as p ' .
            'LEFT JOIN qualite q ON q.id = p.qualite_id ' .
            'WHERE p.chronique_id = ' . $chronique->getId() . ' ';
        $measuresSql .= 'ORDER BY p.date ASC ';

        // SQL query for information about this selection
        $infoSql = 'SELECT MIN(p.date) as first, ' .
            '       MAX(p.date) as last, ' .
            '       COUNT(p.id) as number ' .
            'FROM pointcontrole as p ' .
            'WHERE p.chronique_id = ' . $chronique->getId() . ' ';

        // Les mesures, ligne à ligne, et leur résumé
        return [
            $this->getEntityManager()->getConnection()->executeQuery($measuresSql)->iterateAssociative(),
            $this->getEntityManager()->getConnection()->fetchAssociative($infoSql),
        ];
    }

    /**
     * @param ChroniqueContinue $chronique
     * @param null              $defaultParams
     *
     * @return array
     */
    public function getAllowedExportParameters(ChroniqueContinue $chronique, $defaultParams = null)
    {
        $params = [
            'samplings' => [], // Allowed sampling types at regular time step
            'timeSteps' => [], // Allowed regular time steps
        ];

        $inputSampling = $chronique->getEchantillonnage();
        if (!$inputSampling) {
            $inputSampling = $this->getEntityManager()->getRepository(Echantillonnage::class)->findOneByCode('instantaneous');
        }
        $minOutputOrder = $inputSampling->getOrdreSortie();
        $inputSamplingCode = $inputSampling->getCode();

        // Allowed export parameters are set properly for this time series
        if (!$chronique->getMustEditExportOptions()) {
            // Get allowed sampling types at regular time step (making sure they are possible given the time series' sampling type)
            foreach ($chronique->getEchantillonnagesSortieLicites() as $sampling) {
                if ($sampling->getOrdreSortie() >= $minOutputOrder) {
                    $params['samplings'][] = $sampling;
                }
            }
            // Now, get allowed regular time steps (only those matching the time series' sampling type)
            foreach ($chronique->getOptionsEchantillonnageSortie() as $option) {
                if ($option->getEchantillonnageEntree()->getCode() === $inputSamplingCode) {
                    $params['timeSteps'][] = $option->getPasEchantillonnage();
                }
            }
        } // Allowed export parameters are not firmly set yet. Use default parameters instead.
        elseif ($defaultParams) {
            $defaultParams = $defaultParams[$inputSamplingCode];
            $allowedSamplingCodes = $defaultParams['samplings'];
            foreach ($this->getEntityManager()->getRepository(Echantillonnage::class)->findByCode($allowedSamplingCodes) as $sampling) {
                if ($sampling->getOrdreSortie() >= $minOutputOrder) {
                    $params['samplings'][] = $sampling;
                }
            }

            $defaultStepValues = [];
            $defaultStepUnits = [];
            foreach ($defaultParams['steps'] as $valueAndUnit) {
                $defaultStepValues[] = $valueAndUnit[0];
                $defaultStepUnits[] = $valueAndUnit[1];
            }
            // Alas, we won't be able to check consistency between the time series' sampling type
            // and the default time steps in a simple way. Take all defaults and just don't care.
            $params['timeSteps'] = $this->getEntityManager()->getRepository(PasEchantillonnage::class)->findBy(
                [
                    'valeur' => $defaultStepValues,
                    'unite'  => $defaultStepUnits,
                ]
            );
        }

        // Sort sampling types by output 'order' & time steps by approx. seconds
        if (\sizeof($params['samplings']) > 0) {
            usort(
                $params['samplings'],
                function ($s1, $s2) {
                    return $s1->getOrdreSortie() - $s2->getOrdreSortie();
                }
            );
        }
        if (\sizeof($params['timeSteps']) > 0) {
            usort(
                $params['timeSteps'],
                function ($ts1, $ts2) {
                    return $ts1->getApproxSecondes() - $ts2->getApproxSecondes();
                }
            );
        }

        return $params;
    }
}
