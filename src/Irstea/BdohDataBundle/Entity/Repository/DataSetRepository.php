<?php

namespace Irstea\BdohDataBundle\Entity\Repository;

use Doctrine\ORM\QueryBuilder;
use Irstea\BdohDataBundle\Entity\DataSet;

class DataSetRepository extends EntityRepository
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
        if (!$currentObs = $this->getCurrentObservatoire()) {
            return parent::createQueryBuilder($alias);
        }

        return parent::createQueryBuilder($alias = 'ds', $indexBy)
            ->leftJoin('ds.observatoire', 'o')
            ->where('o = :observatoire')
            ->setParameter('observatoire', $currentObs);
    }

    /**
     * Selects only entities linked to the current 'observatoire'.
     *
     * @param \Irstea\BdohDataBundle\Entity\Observatoire $observatoire
     *
     * @return QueryBuilder
     */
    public function findByObservatoire($observatoire)
    {
        return parent::createQueryBuilder('ds')
            ->leftJoin('ds.observatoire', 'o')
            ->where('o = :observatoire')
            ->setParameter('observatoire', $observatoire->getId());
    }

    /**
     * @param int $id
     *
     * @throws \Exception
     *
     * @return DataSet
     */
    public function findOneById($id)
    {
        return $this->createQueryBuilder('ds')
            ->andWhere('ds.id = :id')->setParameter('id', $id)
            ->getQuery()
            ->getSingleResult();
    }

    /**
     * @param int $datasetId
     *
     * @return array
     */
    public function getReclangleEnglobant(int $datasetId): array
    {
        $query = <<<SQL
            WITH env AS (
                SELECT
                   ST_Transform(st.point, 4326) AS env -- WGS 84 (GeoJSON) ; l'ancien écrivait 4236 (dev/differences.md)
                FROM station st
                    INNER JOIN chronique c ON (c.station_id = st.id)
                WHERE
                    c.dataset_id = :datasetId
            )
            SELECT
                ST_XMin(env) as xmin,
                ST_YMin(env) as ymin,
                ST_XMax(env) as xmax,
                ST_YMax(env) as ymax
            FROM env
SQL;

        $row = $this->getEntityManager()->getConnection()->fetchNumeric($query, ['datasetId' => $datasetId]);
        [$rectangleXmin, $rectangleYmin, $rectangleXmax, $rectangleYmax] = $row;

        $rectangle0 = [(float) $rectangleXmin, (float) $rectangleYmax];
        $rectangle1 = [(float) $rectangleXmin, (float) $rectangleYmin];
        $rectangle2 = [(float) $rectangleXmax, (float) $rectangleYmin];
        $rectangle3 = [(float) $rectangleXmax, (float) $rectangleYmax];

        $rectangle = [$rectangle0, $rectangle1, $rectangle2, $rectangle3, $rectangle0];

        return $rectangle;
    }

    /**
     * @param string $titre
     *
     * @throws \Exception
     *
     * @return DataSet
     */
    public function findOneByTitre($titre)
    {
        return $this->createQueryBuilder('ds')
            ->andWhere('ds.titre = :titre')->setParameter('titre', $titre)
            ->getQuery()
            ->getSingleResult();
    }

    /**
     * @param string $uuid
     *
     * @throws \Exception
     *
     * @return DataSet
     */
    public function findOneByUuid($uuid)
    {
        return $this->createQueryBuilder('ds')
            ->andWhere('ds.uuid = :uuid')->setParameter('uuid', $uuid)
            ->getQuery()
            ->getSingleResult();
    }

    /**
     * Gets the first and last dates by 'chronique', for a given 'dataset'.
     *
     * @param DataSet $dataset
     *
     * @return array Key = 'chronique' id. ; Value = array of ['first', 'last'].
     */
    public function getFirstLastDatesByChronique(DataSet $dataset)
    {
        // First and last dates by 'chronique', only for 'ChroniqueContinue'
        $qbMesure = $this->getBdohRepo('Mesure')
            ->createFirstLastDates()
            ->addSelect('ch.id')->groupBy('ch.id')
            ->andWhere('ds.id = :dataset')->setParameter('dataset', $dataset);

        $datesMesure = $qbMesure->getQuery()->getResult();

        // First and last dates by 'chronique', only for 'ChroniqueDiscontinue'
        $qbPlage = $this->getBdohRepo('Plage')
            ->createFirstLastDates()
            ->addSelect('ch.id')->groupBy('ch.id')
            ->andWhere('ds.id = :dataset')->setParameter('dataset', $dataset);

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
     * @param DataSet $dataset
     *
     * @throws \Exception
     *
     * @return DataSet
     */
    public function findById($dataset)
    {
        return $this->createQueryBuilder('ds')
            ->andWhere('ds.id = :id')->setParameter('id', $dataset->getId())
            ->getQuery()
            ->getSingleResult();
    }
}
