<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohDataBundle\Entity\Repository;

use Doctrine\ORM\QueryBuilder;
use Irstea\BdohBundle\Util\DateTimeUtil;
use Irstea\BdohDataBundle\Entity\Chronique;
use Irstea\BdohDataBundle\Entity\ChroniqueContinue;
use Irstea\BdohDataBundle\Entity\ChroniqueDiscontinue;
use Irstea\BdohDataBundle\Entity\DataSet;
use Irstea\BdohDataBundle\Entity\Echantillonnage;
use Irstea\BdohDataBundle\Entity\Station;
use Irstea\BdohDataBundle\Entity\Transformation;
use Irstea\BdohDataBundle\Entity\TypeParametre;
use Irstea\BdohDataBundle\Entity\Unite;
use Irstea\BdohSecurityBundle\Entity\Role;
use Irstea\BdohSecurityBundle\Entity\Utilisateur;

/**
 * Class ChroniqueRepository.
 */
class ChroniqueRepository extends EntityRepository
{
    const MEASURE_TABLE = 'mesure';

    const BEGIN_DATE_FIELD = 'date';

    const END_DATE_FIELD = 'date';

    /**
     * Returns the good measure repository, of a given 'chronique'.
     *
     * @param Chronique $chronique
     *
     * @return MesureRepositoryInterface
     */
    public function getMeasureRepoByChronique(Chronique $chronique)
    {
        /** @var ChroniqueContinueRepository|ChroniqueDiscontinueRepository $repo */
        $repo = $this->getEntityManager()->getRepository(get_class($chronique));

        return $repo->getMeasureRepo();
    }

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
        if (!$currentObs = $this->getCurrentObservatoire()) {
            return parent::createQueryBuilder($alias);
        }

        return parent::createQueryBuilder($alias, $indexBy)
            ->leftJoin($alias . '.station', 'st')
            ->leftJoin('st.sites', 'si')
            ->where('si.observatoire = :observatoire')
            ->setParameter('observatoire', $currentObs);
    }

    /**
     * Selects only entities linked to the current 'observatoire'.
     *
     * @param mixed      $alias
     * @param mixed|null $indexBy
     * @param DataSet    $dataset
     *
     * @return QueryBuilder
     */
    public function createQueryBuilderFilter($alias, $indexBy = null, $dataset)
    {
        if (!$currentObs = $this->getCurrentObservatoire()) {
            return parent::createQueryBuilder($alias);
        }

        return parent::createQueryBuilder($alias, $indexBy)
            ->leftJoin($alias . '.station', 'st')
            ->leftJoin('st.sites', 'si')
            ->where('si.observatoire = :observatoire')
            ->andWhere($alias . '.dataset IS NULL')
            ->andWhere($alias . '.dateDebutMesures IS NOT NULL')
            ->andWhere($alias . '.dateFinMesures IS NOT NULL')
            ->orWhere($alias . '.dataset = :dataset')
            ->setParameter('observatoire', $currentObs)
            ->setParameter('dataset', $dataset->getId());
    }

    /**
     * Selects only entities linked to the current 'observatoire'
     * on which current user has sufficient rights.
     *
     * @param Utilisateur|mixed $user
     * @param string            $alias
     *
     * @return QueryBuilder
     */
    public function createSecuredQueryBuilderForEdit($user, $alias = 'ch')
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
     * Selects only entities linked to the current 'observatoire'
     * on which current user has sufficient rights.
     *
     * @param Utilisateur|mixed $user
     * @param string            $alias
     *
     * @return QueryBuilder
     */
    public function createSecuredQueryBuilderForView($user, $alias = 'ch')
    {
        $qb = $this->createQueryBuilder($alias);

        if ($user instanceof Utilisateur) {
            if (!in_array('ROLE_ADMIN', $user->getRoles()) && !$user->isATrustedUserOfCurrentObservatory()) {
                $rolesOnChroniques = $user->getRolesChronique();

                $clauses = ['ch.estVisible = true'];
                $params = [];

                if ($rolesOnChroniques instanceof \Traversable) {
                    $i = 0;
                    foreach ($rolesOnChroniques as $role) {
                        if ($role->getValeur() === Role::UTILISATEUR) {
                            $chronique = $role->getChronique();
                            $clauses[] = 'ch = :chronique' . $i;
                            $params['chronique' . $i++] = $chronique;
                        }
                    }
                }

                if ([] !== $clauses) {
                    $qb = $qb->andWhere(implode(' or ', $clauses));
                    foreach ($params as $key => $param) {
                        $qb = $qb->setParameter($key, $param);
                    }
                }
            }
        } else { // anonymous user
            $qb = $qb->andWhere('ch.estVisible = true');
        }

        return $qb;
    }

    /**
     * @param int $chroniqueId
     *
     * @return array
     */
    public function getPoint(int $chroniqueId): array
    {
        $query = <<<SQL
            WITH env AS (
                SELECT
                        ST_Transform(st.point, 4236) AS env
                FROM station st
                    INNER JOIN chronique c ON (c.station_id = st.id)
                WHERE
                    c.id = :chroniqueId
            )
            SELECT
                ST_X(env) as x,
                ST_Y(env) as y,
                ST_Z(env) as z
            FROM env;
SQL;

        $row = $this->getEntityManager()->getConnection()->fetchNumeric($query, ['chroniqueId' => $chroniqueId]);
        [$rectangleX, $rectangleY, $rectangleZ] = $row;

        if ($rectangleZ === null) {
            $rectangle = [(float) $rectangleX, (float) $rectangleY];
        } else {
            $rectangle = [(float) $rectangleX, (float) $rectangleY, (float) $rectangleZ];
        }

        return $rectangle;
    }

    /**
     * All 'chroniques' of current 'observatoire'.
     *
     * @return Chronique[]
     */
    public function findAll(): array
    {
        return $this->createQueryBuilder('ch')
            ->addOrderBy('ch.code', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * A "findAll" with all jointures, for performance purpose and according to user's rights.
     *
     * @param Utilisateur|mixed $user
     *
     * @return Chronique[]
     */
    public function securedBigFindAll($user = null)
    {
        return $this->createSecuredQueryBuilderForEdit($user, 'ch')
            ->select('ch, st, si, co')
            ->leftJoin('st.commune', 'co')
            ->orderBy('st.nom', 'ASC')
            ->addOrderBy('ch.code', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * A "findAll" with all jointures, for performance purpose.
     *
     * @return Chronique[]
     */
    public function bigFindAll()
    {
        return $this->createQueryBuilder('ch')
            ->select('ch, st, si, co')
            ->leftJoin('st.commune', 'co')
            ->orderBy('st.nom', 'ASC')
            ->addOrderBy('ch.code', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @param int $obsId
     *
     * @throws \Exception
     *
     * @return array
     */
    public function findContainingBassins($obsId)
    {
        // note : le DISTINCT est necessaire au cas ou la station est sur plusieurs sites
        $sql = <<<SQL
SELECT ch.id AS id, ARRAY_TO_STRING(ARRAY_AGG(DISTINCT ba.nom), '##') as bassins
FROM chronique ch, station st, stations_sites ss, siteexperimental si, bassin ba
WHERE ch.station_id=st.id AND st.id = ss.station_id AND ss.site_id = si.id AND si.observatoire_id = '$obsId'
    AND ba.observatoire_id='$obsId' AND st_distance(ba.perimetre, st.point) < 20 AND st_srid(st.point) > 0
GROUP BY ch.id ORDER BY ch.id
SQL;
        $returns = $this->getEntityManager()->getConnection()->executeQuery($sql)->fetchAllAssociative();
        $bassins = [];
        foreach ($returns as $row) {
            $bassins[$row['id']] = $row['bassins'];
        }

        return $bassins;
    }

    /**
     * @param int $obsId
     *
     * @throws \Exception
     *
     * @return array
     */
    public function findNeighbouringCoursEaux($obsId)
    {
        // note : le DISTINCT est necessaire au cas ou la station est sur plusieurs sites
        $sql = <<<SQL
SELECT ch.id AS id, ARRAY_TO_STRING(ARRAY_AGG(DISTINCT ce.nom), '##') as courseaux
FROM chronique ch, station st, stations_sites ss, siteexperimental si, courseau ce
WHERE ch.station_id=st.id AND st.id = ss.station_id AND ss.site_id = si.id AND si.observatoire_id = '$obsId'
    AND ce.observatoire_id='$obsId' AND st_distance(ce.trace, st.point) < 50 AND st_srid(st.point) > 0
GROUP BY ch.id ORDER BY ch.id
SQL;
        $returns = $this->getEntityManager()->getConnection()->executeQuery($sql)->fetchAllAssociative();
        $coursEaux = [];
        foreach ($returns as $row) {
            $coursEaux[$row['id']] = $row['courseaux'];
        }

        return $coursEaux;
    }

    /**
     * @param int  $obsId
     * @param bool $isLocaleEn
     *
     * @throws \Exception
     *
     * @return array
     */
    public function findParentFamilies($obsId, $isLocaleEn)
    {
        $nom = $isLocaleEn ? 'nomen' : 'nom';

        // note : le DISTINCT est necessaire au cas ou la station est sur plusieurs sites
        $sql = <<<SQL
WITH RECURSIVE r_familles(id, pid, first, noms) AS (
    SELECT f.id, f.familleparente_id, f.id, ARRAY[f.$nom]
    FROM familleparametres f
    UNION ALL
    SELECT f.id, f.familleparente_id, r.first, ARRAY_APPEND(r.noms, f.$nom)::CHARACTER VARYING(255)[]
    FROM familleparametres f, r_familles r
    WHERE f.id = r.pid
)
SELECT ch.id AS id, ARRAY_TO_STRING(MAX(DISTINCT r.noms), '##') AS familles
FROM chronique ch, station st, stations_sites ss, siteexperimental si, typeparametre t, r_familles r
WHERE ch.station_id=st.id AND st.id = ss.station_id AND ss.site_id = si.id AND si.observatoire_id = '$obsId'
    AND ch.parametre_id = t.id AND t.familleparametres_id = r.first
GROUP BY ch.id ORDER BY ch.id
SQL;
        $returns = $this->getEntityManager()->getConnection()->executeQuery($sql)->fetchAllAssociative();
        $familles = [];
        foreach ($returns as $row) {
            $familles[$row['id']] = $row['familles'];
        }

        return $familles;
    }

    /**
     * Returns all 'chroniques' of a given 'dataset'.
     *
     * @param DataSet $dataset
     *
     * @return Chronique[]
     */
    public function findByDataSet(DataSet $dataset)
    {
        return $this->createQueryBuilder('ch')
            ->andWhere('ch.dataset = :dataset')
            ->setParameter('dataset', $dataset)
            ->orderBy('ch.station', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Returns all 'chroniques' of a given 'station'.
     *
     * @param Station $station
     *
     * @return Chronique[]
     */
    public function findByStation(Station $station)
    {
        return $this->createQueryBuilder('ch')
            ->andWhere('ch.station = :station')
            ->setParameter('station', $station)
            ->orderBy('ch.code', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Returns all 'chroniques' of a given 'station' with the given 'unite'.
     *
     * @param Station $station
     * @param Unite   $unite
     *
     * @return Chronique[]
     */
    public function findByStationAndUnite(Station $station, Unite $unite)
    {
        return $this->createQueryBuilder('ch')
            ->andWhere('ch.station = :station')
            ->andWhere('ch.unite = :unite')
            ->setParameter('station', $station)
            ->setParameter('unite', $unite)
            ->orderBy('ch.code', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Returns all 'chroniques' of a given 'station'.
     *
     * @param Station     $station
     * @param Utilisateur $user
     *
     * @return Chronique[]
     */
    public function securedFindByStation(Station $station, ?Utilisateur $user = null)
    {
        return $this->createSecuredQueryBuilderForEdit($user, 'ch')
            ->andWhere('ch.station = ' . $station->getId())
            ->orderBy('ch.code', 'ASC')
            ->getQuery()->getResult();
    }

    /**
     * Returns a specific 'chronique' (by id.), for the current 'observatoire'.
     *
     * @param int $chroniqueId
     *
     * @throws \Exception
     *
     * @return Chronique
     */
    public function findOneById($chroniqueId)
    {
        return $this->createQueryBuilder('ch')
            ->andWhere("ch.id = $chroniqueId")
            ->getQuery()
            ->getSingleResult();
    }

    /**
     * Returns a specific 'chronique' (by 'station' and code), for the current 'observatoire'.
     *
     * @param Station $station
     * @param string  $code
     *
     * @throws \Exception
     *
     * @return Chronique
     */
    public function findOneByStationAndCode(Station $station, $code)
    {
        return $this->createQueryBuilder('ch')
            ->andWhere('ch.station = ' . $station->getId())
            ->andWhere("ch.code = '$code'")
            ->getQuery()
            ->getSingleResult();
    }

    /**
     * Retrouve une Chronique (quelque soit son type exact) par station et code.
     *
     * Note: cette méthode est utilisée pour assurer l'unicité des couples (station, code)
     * dans un observatoire.
     *
     * @param array $criteria
     *
     * @return Chronique[]
     */
    public function findAnyByStationAndCode(array $criteria): array
    {
        $builder = $this->getEntityManager()->getRepository(Chronique::class)
            ->createQueryBuilder('ch')
            ->where('ch.station = :station')
            ->andWhere('ch.code = :code');
        foreach ($criteria as $name => $value) {
            $builder->setParameter($name, $value);
        }

        return $builder->getQuery()->getResult();
    }

    /**
     * Gets the first and last dates by 'chronique',
     * for 'ChroniqueContinue' and 'ChroniqueDiscontinue'.
     *
     * @param int[] $chroniques
     *
     * @return array[]
     */
    public function getFirstLastDatesByChronique(array $chroniques = [])
    {
        return array_merge(
            $this->getEntityManager()->getRepository(ChroniqueContinue::class)->getFirstLastDatesByChronique($chroniques),
            $this->getEntityManager()->getRepository(ChroniqueDiscontinue::class)->getFirstLastDatesByChronique($chroniques)
        );
    }

    /**
     * Returns the 'chroniques' number, for a given 'Station' and 'TypeParametre'.
     *
     * @param Station       $station
     * @param TypeParametre $parametre
     *
     * @throws \Exception
     *
     * @return int
     */
    public function countByStationAndParametre(Station $station, TypeParametre $parametre)
    {
        return $this->createQueryBuilder('ch')
            ->select('count(DISTINCT ch)')
            ->andWhere('ch.station = ' . $station->getId())
            ->andWhere('ch.parametre = ' . $parametre->getId())
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Shortcut to getFirstLastDates() function, of MesureRepository or PlageRepository.
     *
     * @param Chronique $chronique
     *
     * @return \DateTime[]
     */
    public function getFirstLastDates(Chronique $chronique)
    {
        return $this->getMeasureRepoByChronique($chronique)->getFirstLastDates($chronique->getId());
    }

    /**
     * Returns the number of measures, for a given 'chronique'.
     *
     * @param Chronique $chronique
     *
     * @throws \Exception
     *
     * @return int
     */
    public function getMeasuresNumber(Chronique $chronique)
    {
        return $this->getMeasureRepoByChronique($chronique)
            ->createSimpleQueryBuilder()
            ->select('COUNT(m)')
            ->andWhere('m.chronique = ' . $chronique->getId())
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @param array          $chroniquesAndRights
     * @param \DateTime|null $beginDate
     * @param \DateTime|null $endDate
     * @param int            $maxValidPoints
     *
     * @throws \Exception
     *
     * @return array
     */
    public function getMultipleChroniquesViewerData(
        $chroniquesAndRights,
        ?\DateTime $beginDate = null,
        ?\DateTime $endDate = null,
        $maxValidPoints = 50000
    ) {
        $data = [
            'xMin'       => $this->formatTimestampAtUtc($beginDate),
            'xMax'       => $this->formatTimestampAtUtc($endDate),
            'chronicles' => [],
        ];

        /** @var Chronique $chronique */
        /** @var bool $rightsForCheckpoints */
        foreach ((array) $chroniquesAndRights as list($chronique, $rightsForCheckpoints)) {
            $data['chronicles'][] =
                $this->getChronicleViewerDataForChronicle($chronique, $beginDate, $endDate, $rightsForCheckpoints, $maxValidPoints);
        }

        return $data;
    }

    /**
     * @param Chronique $chronique
     * @param \DateTime $beginDate
     * @param \DateTime $endDate
     * @param bool      $rightsForCheckpoints
     * @param int       $maxValidPoints
     *
     * @throws \Exception
     *
     * @return mixed
     */
    private function getChronicleViewerDataForChronicle(
        Chronique $chronique,
        \DateTime $beginDate,
        \DateTime $endDate,
        bool $rightsForCheckpoints,
        $maxValidPoints = 50000
    ) {
        /** @var ChroniqueContinue|ChroniqueDiscontinue $chronique */
        /** @var ChroniqueContinueRepository|ChroniqueDiscontinueRepository $chroRepo */
        $chroRepo = $this->getEntityManager()->getRepository(get_class($chronique));
        $tableOfMeasures =
            $chroRepo->getOneChronicleViewerData($chronique, $beginDate, $endDate, $rightsForCheckpoints, $maxValidPoints);

        $tableOfMeasures['chronicleId'] = $chronique->getId();

        $station = $chronique->getStation();
        $tableOfMeasures['station'] = ($station) ? $station->getNom() : '';

        $dataType = $chronique->getParametre();
        $tableOfMeasures['dataType'] = ($dataType) ? $dataType->getNom() : '';

        $tableOfMeasures['name'] = $chronique->__toString();

        $dataUnit = $chronique->getUnite();
        $tableOfMeasures['dataUnit'] = ($dataUnit) ? $dataUnit->getLibelle() : '';

        $tableOfMeasures['stationCode'] = ($station) ? $station->getCode() : '';

        $tableOfMeasures['chroniqueCode'] = $chronique->getCode();

        $chroniqueClass = get_class($chronique);
        $chroniqueClass = explode('\\', $chroniqueClass);
        $tableOfMeasures['chroniqueClass'] = array_pop($chroniqueClass);

        return $tableOfMeasures;
    }

    /**
     * @param $id
     *
     * @throws \Exception
     */
    public function updateDatesMesuresByChronique($id)
    {
        $query = "SELECT c.id, bdoh_chronique_calcul_dates_mesures(c.id, c.dtype)
            FROM chronique c
            WHERE c.id = $id";
        $this->getEntityManager()->getConnection()->executeStatement($query);
    }

    /**
     * @param Utilisateur|null $user
     * @param Station[]|int[]  $stations
     * @param string           $startDate
     * @param string           $endDate
     *
     * @throws \Exception
     *
     * @return array|bool
     */
    public function getSecuredChroniqueIDsWithMeasures($user, $stations = null, $startDate = null, $endDate = null)
    {
        // Check that the dates are REALLY dates & convert them to string
        if ($startDate) {
            list($valid, $startDate) = DateTimeUtil::dateValidationAndString($startDate);
            if (!$valid) {
                return false;
            }
        }
        if ($endDate) {
            list($valid, $endDate) = DateTimeUtil::dateValidationAndString($endDate);
            if (!$valid) {
                return false;
            }
        }

        // List of station IDs
        $stationIDs = [];
        foreach ((array) $stations as $s) {
            $stationIDs[] = \is_int($s) ? $s : $s->getId();
        }

        // Lines for each sampling type
        $sqlLinesForSamplings = [];
        // Get sampling types by code
        $samplingTypes = $this->getEntityManager()->getRepository(Echantillonnage::class)->findAll();
        $samplingIDsByCode = [];
        /** @var Echantillonnage $s */
        foreach ($samplingTypes as $s) {
            $samplingIDsByCode[$s->getCode()] = $s->getId();
        }

        // Instantaneous sampling type: default type. If sampling type is not defined and
        // time series is not discontinuous, sampling is assumed to be instantaneous.
        // We need at least one value in the time range defined by $startDate & $endDate.
        // !!! Same processing now applied to time series with cumulative sampling
        $dateFilter = '';
        if ($startDate) {
            $dateFilter .= " AND EXISTS (SELECT date FROM mesure WHERE chronique_id = c.id AND date >= '$startDate')";
        }
        if ($endDate) {
            $dateFilter .= " AND EXISTS (SELECT date FROM mesure WHERE chronique_id = c.id AND date <= '$endDate')";
        }
        $sqlLinesForSamplings[] = '(c.echantillonnage_id IN '
            . '(' . \implode(',', [$samplingIDsByCode['instantaneous'], $samplingIDsByCode['cumulative']]) . ')'
            . " OR (c.echantillonnage_id IS NULL AND dtype <> 'discontinue'))$dateFilter";

        // Mean sampling type: there must be at least two measures, of which at least one
        // after $startDate & at least one before $endDate.
        $startFilter = $startDate ? " AND EXISTS (SELECT date FROM mesure WHERE chronique_id = c.id AND date >= '$startDate')" : '';
        $endFilter = $endDate ? " AND EXISTS (SELECT date FROM mesure WHERE chronique_id = c.id AND date <= '$endDate')" : '';
        $sqlLinesForSamplings[] = 'c.echantillonnage_id = ' . $samplingIDsByCode['mean']
            . ' AND (SELECT COUNT(*) FROM mesure WHERE chronique_id = c.id) > 1'
            . $startFilter . $endFilter;

        // Cumulative sampling type: we consider that there are always data
        // !!! OR NOT!
        // We could argue that, but we'll act like no measure in the database
        // within our time range means that the time series IS empty within the latter.
        // Consequently, time series with cumulative sampling are filtered the same way
        // as those with instantaneous sampling: see code a few lines above.

        /*$sqlLinesForSamplings[] = 'c.echantillonnage_id = '
            . $samplingIDsByCode['cumulative'];*/

        // Discontinuous time series: there must be at least one range,
        // of which the end tip is after $startDate & the start tip is before $endDate.
        $discontSubStatement = function ($append) {
            return " AND EXISTS(SELECT * FROM plage WHERE chronique_id = c.id$append)";
        };
        if ($startDate) {
            $s = $discontSubStatement(" AND fin >= '$startDate'");
            if ($endDate) {
                $s .= $discontSubStatement(" AND debut <= '$endDate'");
            }
        } elseif ($endDate) {
            $s = $discontSubStatement(" AND debut <= '$endDate'");
        } else {
            $s = $discontSubStatement('');
        }
        $sqlLinesForSamplings[] = "dtype = 'discontinue'" . $s;

        // chronique IDs that are allowed to be viewed by that $user
        $allowedChroniques = $this->createSecuredQueryBuilderForView($user, 'ch')
            ->select('ch.id')
            ->getQuery()
            ->getResult();
        $allowedChroniqueIds = [];
        foreach ($allowedChroniques as $allowedChronique) {
            $allowedChroniqueIds[] = $allowedChronique['id'];
        }

        // Overall SQL query
        $sqlQuery = 'SELECT DISTINCT c.id FROM chronique c';
        if (\sizeof($stationIDs) > 0) {
            $stationIDsString = implode(',', $stationIDs);
            $sqlQuery .= " WHERE c.station_id IN ($stationIDsString)";
        }
        if (\sizeof($allowedChroniqueIds) > 0) {
            $allowedChroniqueIdsString = implode(',', $allowedChroniqueIds);
            $sqlQuery .= " AND c.id IN ($allowedChroniqueIdsString)";
        }
        $sqlQuery .= ' AND (('
            . \implode(') OR (', $sqlLinesForSamplings)
            . '))';
        //echo($sqlQuery);exit;
        // Execute query & return array of IDs
        $statement = $this->getEntityManager()->getConnection()->executeQuery($sqlQuery);

        return $statement->fetchFirstColumn();
    }

    /**
     * Selects measures for a given 'chronique', between two dates.
     * Note : this query is intended for HTML table displaying purpose.
     *
     * @param ChroniqueContinue|ChroniqueDiscontinue $chronique
     * @param \DateTime|null                         $beginDate
     * @param \DateTime|null                         $endDate
     *
     * @throws \Doctrine\DBAL\Exception
     *
     * @return array
     */
    public function getChroniqueTableData($chronique, ?\DateTime $beginDate = null, ?\DateTime $endDate = null)
    {
        /** @var QueryBuilder $qb */
        $qb = $this
            ->getMeasureRepoByChronique($chronique)
            ->createQueryBuilderByChronique($chronique)
            ->select('m.date', 'm.valeur', 'q.code');

        $params = [];

        if ($beginDate !== null) {
            $qb->andWhere('m.date >= :beginDate');
            $params[] = $this->formatTimestampAtUtc($beginDate);
        }
        if ($endDate !== null) {
            $qb->andWhere('m.date < :endDate');
            $params[] = $this->formatTimestampAtUtc($endDate);
        }

        return $this->getEntityManager()->getConnection()
            ->executeQuery($qb->getQuery()->getSQL(), $params)
            ->fetchAllNumeric();
    }

    /**
     * @param Chronique     $chronique
     * @param \DateTime     $beginDate
     * @param \DateTime     $endDate
     * @param \DateTimeZone $timeZone
     *
     * @throws \Exception
     *
     * @return array
     */
    public function getMeasuresForExport(
        Chronique $chronique,
        ?\DateTime $beginDate = null,
        ?\DateTime $endDate = null,
        ?\DateTimeZone $timeZone = null
    ) {
        $dbal = $this->getEntityManager()->getConnection();

        $begin = static::BEGIN_DATE_FIELD;
        $end = static::END_DATE_FIELD;

        list($withTzCorrection, $parameters) = $this->getTzCorrectionForSql($timeZone);

        $builder = $dbal->createQueryBuilder()
            ->setParameters($parameters)
            ->from(static::MEASURE_TABLE, 'm')
            ->orderBy(static::BEGIN_DATE_FIELD, 'ASC')
            ->select("$begin $withTzCorrection AS $begin")
            ->leftJoin('m', 'qualite', 'q', 'm.qualite_id = q.id')
            ->addSelect('ROUND(m.valeur::NUMERIC, 3)::DOUBLE PRECISION AS valeur')
            ->addSelect('ROUND(m.minimum::NUMERIC, 3)::DOUBLE PRECISION AS minimum')
            ->addSelect('ROUND(m.maximum::NUMERIC, 3)::DOUBLE PRECISION AS maximum')
            ->addSelect('q.code AS qualite')
            ->where('m.chronique_id = :chroniqueId')
            ->setParameter('chroniqueId', $chronique->getId());

        if ($begin !== $end) {
            $builder->addSelect("$end $withTzCorrection AS $end");
        }

        $this->addTimeLimits($builder, $beginDate, $endDate);

        return [
            $dbal->executeQuery($builder->getSQL(), $builder->getParameters()),
            $this->getExportReport($chronique, $beginDate, $endDate),
        ];
    }

    /**
     * @param \DateTimeZone|null $timeZone
     *
     * @throws \Exception
     *
     * @return array
     */
    protected function getTzCorrectionForSql(?\DateTimeZone $timeZone = null)
    {
        $tzOffset = $timeZone !== null ? $timeZone->getOffset(new \DateTime('2001-01-01T00:00:00Z')) : 0;

        if ($tzOffset === 0) {
            return ['', []];
        }

        return ['+ :tzOffset', ['tzOffset' => sprintf('%d seconds', $tzOffset)]];
    }

    /**
     * @param \Doctrine\DBAL\Query\QueryBuilder $builder
     * @param \DateTime|null                    $beginDate
     * @param \DateTime|null                    $endDate
     */
    protected function addTimeLimits(
        \Doctrine\DBAL\Query\QueryBuilder $builder,
        ?\DateTime $beginDate = null,
        ?\DateTime $endDate = null
    ) {
        if ($beginDate !== null) {
            $builder
                ->andWhere(static::BEGIN_DATE_FIELD . ' >= :begin')
                ->setParameter('begin', $this->formatTimestampAtUtc($beginDate));
        }

        if ($endDate !== null) {
            $builder
                ->andWhere(static::END_DATE_FIELD . ' <= :end')
                ->setParameter('end', $this->formatTimestampAtUtc($endDate));
        }
    }

    /**
     * @param \DateTime $timestamp
     *
     * @return string
     */
    protected function formatTimestampAtUtc(\DateTime $timestamp)
    {
        $copy = clone $timestamp;

        return $copy
            ->setTimezone(new \DateTimeZone('UTC'))
            ->format('Y-m-d\TH:i:s');
    }

    /**
     * @param Chronique      $chronique
     * @param \DateTime|null $beginDate
     * @param \DateTime|null $endDate
     *
     * @return array
     */
    protected function getExportReport(
        Chronique $chronique,
        ?\DateTime $beginDate = null,
        ?\DateTime $endDate = null
    ) {
        $dbal = $this->getEntityManager()->getConnection();

        $begin = static::BEGIN_DATE_FIELD;
        $end = static::END_DATE_FIELD;

        $builder = $dbal->createQueryBuilder()
            ->from(static::MEASURE_TABLE, 'm')
            ->select("TO_CHAR(MIN($begin), 'YYYY-MM-DD\"T\"HH24:MI:SS\"Z\"') AS first")
            ->addSelect("TO_CHAR(MAX($end), 'YYYY-MM-DD\"T\"HH24:MI:SS\"Z\"') AS last")
            ->addSelect('COUNT(id) AS number')
            ->where('chronique_id = :chroniqueId')
            ->setParameter('chroniqueId', $chronique->getId());

        $this->addTimeLimits($builder, $beginDate, $endDate);

        return $dbal->fetchAssociative($builder->getSQL(), $builder->getParameters());
    }

    /**
     * Computes and saves filling rates :
     *   -> on a monthly base ;
     *   -> on a given period (between $startDate and $endDate) ;
     *   -> for a given 'chronique'.
     *
     * @param Chronique $chronique
     *
     * @throws \Exception
     *
     * @return array
     */
    public function updateMeasuresMetadata(Chronique $chronique)
    {
        $stmt = $this
            ->getEntityManager()
            ->getConnection()
            ->executeQuery('SELECT bdoh_update_measures_metadata(:id)', ['id' => $chronique->getId()]);

        $recordStr = $stmt->fetchOne();
        $stmt->free();

        $record = json_decode('[' . substr($recordStr, 1, -1) . ']');
        $utc = new \DateTimeZone('UTC');

        return [
            'start' => new \DateTime($record[0], $utc),
            'end'   => new \DateTime($record[1], $utc),
            'nbMes' => (int) $record[2],
        ];
    }

    /**
     * Computes and saves filling rates :
     *   -> on a monthly base ;
     *   -> on a given period (between $startDate and $endDate) ;
     *   -> for a given 'chronique'.
     *
     * @param Chronique $chronique
     * @param \DateTime $startDate
     * @param \DateTime $endDate
     *
     * @throws \Exception
     *
     * @return int
     */
    public function updateFillingRates(Chronique $chronique, ?\DateTime $startDate = null, ?\DateTime $endDate = null)
    {
        $stmt = $this
            ->getEntityManager()
            ->getConnection()
            ->executeQuery(
                'SELECT bdoh_update_filling_rates(:id, :start, :end)',
                [
                    'id'    => $chronique->getId(),
                    'start' => $startDate ? $startDate->format(DATE_ATOM) : '-infinity',
                    'end'   => $endDate ? $endDate->format(DATE_ATOM) : 'infinity',
                ]
            );

        $nbTaux = $stmt->fetchOne();
        $stmt->free();

        return (int) $nbTaux;
    }

    /**
     * Les chroniques filles de la chronique $parent.
     *
     * @param Chronique $parent
     *
     * @return array
     */
    public function findChildren(Chronique $parent)
    {
        $children = [];
        /** @var Transformation[] $transformations */
        $transformations = $parent->getTransformations()->toArray();
        foreach ($transformations as $transformation) {
            if ($transformation) {
                $sortie = $transformation->getSortie();
                if ($sortie && !in_array($sortie, $children)) {
                    $children[] = $sortie;
                }
            }
        }

        return $children;
    }

    /**
     * Les chroniques filles de la chronique $parent
     * et toutes leur chroniques filles.
     *
     * @param Chronique $parent
     *
     * @return array
     */
    public function findAllChildren(Chronique $parent)
    {
        $allChildren = [];

        $children = $this->findChildren($parent);
        while (0 !== count($children)) {
            $allChildren = array_merge($allChildren, $children);
            $tempChildren = [];
            foreach ($children as $child) {
                $currentChildren = $this->findChildren($child);
                if (0 !== count($currentChildren)) {
                    $tempChildren = array_merge($tempChildren, $currentChildren);
                }
            }
            $children = $tempChildren;
        }

        $allChildrenUnique = [];
        foreach ($allChildren as $children) {
            if (!in_array($children, $allChildrenUnique)) {
                $allChildrenUnique[] = $children;
            }
        }

        return $allChildrenUnique;
    }
}
