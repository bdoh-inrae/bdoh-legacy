<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohDataBundle\Entity\Repository;

use Doctrine\ORM\QueryBuilder;
use Irstea\BdohDataBundle\Entity\CoursEau;

/**
 * Class CoursEauRepository.
 */
class CoursEauRepository extends EntityRepository
{
    /**
     * Selects only entities linked to the current 'observatoire'.
     *
     * @param mixed      $alias
     * @param mixed|null $indexBy
     *
     * @return QueryBuilder
     */
    public function createQueryBuilder(string $alias = 'ce', ?string $indexBy = null): QueryBuilder
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
        return $this->createQueryBuilder('ce')->orderBy('ce.nom', 'ASC')->getQuery()->getResult();
    }

    /**
     * @param $tableName
     * @param $user
     *
     * @return array
     */
    public function getDataFromShapeTable($tableName, $user)
    {
        try {
            // récupération des cours d'eau de l'observatoire courant
            $allRivers = $this->findAll();
            $allRiversNoms = [];
            foreach ($allRivers as $river) {
                /* @var CoursEau $river */
                $allRiversNoms[] = $river->getNom();
            }

            // requêtes d'analyse des cours d'eau importés dans la table temporaire crée par shp2pgsql

            // requête de base
            $baseQuery = "SELECT toponyme, code_hydro, classifica FROM $tableName";
            $baseToponymeOk = ' AND toponyme IS NOT NULL';
            $baseOrderBy = ' ORDER BY toponyme ASC';

            // requête sur les nouveaux cours d'eau (selon le nom)
            $sqlParamsArray = [];
            $sqlTypesArray = [];
            $newRiversQuery = $baseQuery;
            if ($allRiversNoms !== []) {
                $newRiversQuery .= ' WHERE toponyme NOT IN (?)';
                $sqlParamsArray[] = $allRiversNoms;
                $sqlTypesArray[] = \Doctrine\DBAL\ArrayParameterType::STRING;
            } else {
                $newRiversQuery .= ' WHERE TRUE';
            }
            $newRiversQuery .= $baseToponymeOk . $baseOrderBy;
            $newRivers = $this->getEntityManager()->getConnection()->fetchAllAssociative($newRiversQuery, $sqlParamsArray, $sqlTypesArray);

            // requête sur les cours d'eau existants (selon le nom)
            $sqlParamsArray = [];
            $sqlTypesArray = [];
            $existingRiversQuery = $baseQuery;
            if ($allRiversNoms !== []) {
                $existingRiversQuery .= ' WHERE toponyme IN (?)';
                $sqlParamsArray[] = $allRiversNoms;
                $sqlTypesArray[] = \Doctrine\DBAL\ArrayParameterType::STRING;
            } else {
                $existingRiversQuery .= ' WHERE FALSE';
            }
            $existingRiversQuery .= $baseToponymeOk . $baseOrderBy;
            $existingRivers = $this->getEntityManager()->getConnection()->fetchAllAssociative($existingRiversQuery, $sqlParamsArray, $sqlTypesArray);
        } catch (\Exception $e) {
            $this->dropTemporaryShapeTable($tableName);

            return ['error' => 'coursEau'];
        }

        return [
            'cible'         => 'coursEau',
            'new'           => $newRivers,
            'countNew'      => \count($newRivers),
            'existing'      => $existingRivers,
            'countExisting' => \count($existingRivers),
        ];
    }

    /**
     * @param $tableName
     *
     * @throws \Doctrine\DBAL\Exception
     *
     * @return array
     */
    public function shapeKeepExistingImport($tableName)
    {
        // récupération des cours d'eau de l'observatoire courant
        $allRivers = $this->findAll();
        $allRiversNoms = [];
        foreach ($allRivers as $river) {
            /* @var CoursEau $river */
            $allRiversNoms[] = $river->getNom();
        }

        // récupération de l'id de l'observatoire courant
        $currentObsId = $this->getCurrentObservatoire()->getId();

        // requete de selection des nouveaux cours d'eau importés dans la table temporaire crée par shp2pgsql

        // filtrage sur les nouveaux cours d'eau uniquement (selon le nom)
        $sqlParamsArray = [];
        $sqlTypesArray = [];
        $newRiversFilter = 'WHERE t.toponyme IS NOT NULL';
        if ($allRiversNoms !== []) {
            $newRiversFilter .= ' AND t.toponyme NOT IN (?)';
            $sqlParamsArray[] = $allRiversNoms;
            $sqlTypesArray[] = \Doctrine\DBAL\ArrayParameterType::STRING;
        }

        // requête de selection des nouveaux cours d'eau
        $newRiversQuery = <<<SQL
SELECT NEXTVAL('courseau_id_seq'), $currentObsId, t.toponyme, t.code_hydro, t.classifica::INTEGER, t.geom
FROM $tableName t $newRiversFilter
SQL;

        // requête d'insertion
        $newRiversInsertQuery = 'INSERT INTO courseau (id, observatoire_id, nom, codehydro, classification, trace) ' .
            '(' . $newRiversQuery . ') ON CONFLICT DO NOTHING';

        $inserts = $this->getEntityManager()->getConnection()->executeStatement($newRiversInsertQuery, $sqlParamsArray, $sqlTypesArray);

        return [$inserts, 0];
    }

    /**
     * @param $tableName
     *
     * @throws \Doctrine\DBAL\Exception
     *
     * @return array
     */
    public function shapeOverwriteExistingImport($tableName)
    {
        // récupération de l'id de l'observatoire courant
        $currentObsId = $this->getCurrentObservatoire()->getId();

        // requete de selection des cours d'eau existants importés dans la table temporaire crée par shp2pgsql

        // filtrage sur les cours d'eau existants uniquement (selon le nom)
        $existingRiversFilter = "WHERE t.toponyme = c.nom AND c.observatoire_id = $currentObsId";

        // requête de selection des cours d'eau existants
        $existingRiversQuery = <<<SQL
SELECT c.id, t.code_hydro, t.classifica::INTEGER, t.geom FROM $tableName t, courseau c
$existingRiversFilter
SQL;

        // requête de mise à jour
        $existingRiversUpdateQuery =
            'UPDATE courseau SET (codehydro, classification, trace) = (temp.code_hydro, temp.classifica, temp.geom) FROM ' .
            '(' . $existingRiversQuery . ') temp WHERE courseau.id = temp.id';

        // faire l'update avant l'insert sinon le résultat est faussé
        $updates = $this->getEntityManager()->getConnection()->executeStatement($existingRiversUpdateQuery);

        return [$this->shapeKeepExistingImport($tableName)[0], $updates];
    }
}
