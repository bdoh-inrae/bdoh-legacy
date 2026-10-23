<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohDataBundle\Entity\Repository;

use Irstea\BdohDataBundle\Entity\DataSet;
use Irstea\BdohDataBundle\Entity\FamilleParametres;
use Irstea\BdohDataBundle\Entity\Station;

/**
 * FamilleParametresRepository.
 */
class FamilleParametresRepository extends EntityRepository
{
    /**
     * Le tableaux des familles de paramètres utilisées sur l'observatoire courant ou sur une stations donnée
     * avec leurs filles et leurs types de paramètres utilisés sur l'observatoire courant ou sur la station.
     *
     * Utilisé pour générer l'arborescence familles / types sur l'accueil de l'observatoire ou
     * l'arborescence familles / types / chroniques sur la page de la station.
     *
     * @param bool         $isLocaleEn
     * @param DataSet|null $dataset
     *
     * @throws \Exception
     *
     * @return array
     */
    public function getFamillesAndTypesDataset($isLocaleEn = false, $dataset = null)
    {
        $tablesLienObservatoire = '';
        if (!$dataset || !$dataset->getId()) {
            $tablesLienObservatoire = ', dataset ds, station st, stations_sites ss, siteexperimental si';
            $currentObsId = $this->getCurrentObservatoire()->getId();
            $clauseLienObservatoireOuStation =
                "ch.dataset_id = ds.id AND ch.station_id = st.id AND st.id = ss.station_id AND ss.site_id = si.id AND si.observatoire_id = $currentObsId";
        } else {
            $datasetId = $dataset->getId();
            $clauseLienObservatoireOuStation = "ch.dataset_id = $datasetId";
        }

        $nom = $isLocaleEn ? 'nomen' : 'nom';

        $sql = <<<SQL
WITH RECURSIVE
-- les types utilisés sur l'observatoires ou sur la station regroupés dans un tableau par famille
-- les types sont triés par "nom" ou "nomen"
types_observatoire_ou_dataset(fid, noms) AS (
    SELECT ty.familleparametres_id, ARRAY_AGG(DISTINCT ty.$nom ORDER BY ty.$nom ASC)
    FROM chronique ch $tablesLienObservatoire, typeparametre ty
    WHERE $clauseLienObservatoireOuStation AND ch.parametre_id = ty.id
    GROUP BY ty.familleparametres_id
),
-- la jointure entre toutes les familles et leur tableau de types éventuel
familles_types(id, pid, prefix, nom, types) AS (
    SELECT f.id, f.familleparente_id, f.prefix, f.$nom, t.noms AS types
    FROM familleparametres f LEFT OUTER JOIN types_observatoire_ou_dataset t ON f.id = t.fid
    ORDER BY f.id
),
-- la construction recursive des familles parentes de celles ayant des types uniquement
r_familles_types(id, pid, prefix, nom, types) AS (
    SELECT f.id, f.pid, f.prefix, f.nom, f.types
    FROM familles_types f WHERE ARRAY_LENGTH(types, 1) > 0
    UNION DISTINCT
    SELECT f.id, f.pid, f.prefix, f.nom, f.types
    FROM familles_types f, r_familles_types r
    WHERE f.id = r.pid
),
-- la construction du tableau des familles filles utilisées pour chaque famille utilisée
-- les familles filles sont triées par "prefix || nom" ou "prefix || nomen"
familles_type_children(id, pid, prefix, nom, types, children) AS (
    SELECT r1.id, r1.pid, r1.prefix, r1.nom, r1.types,
        CASE ARRAY_AGG(r2.id ORDER BY r2.prefix || r2.nom ASC)
            WHEN ARRAY[NULL]::INTEGER[] THEN ARRAY[]::INTEGER[]
            ELSE ARRAY_AGG(r2.id ORDER BY r2.prefix || r2.nom ASC)
        END AS children
    FROM r_familles_types r1 LEFT OUTER JOIN r_familles_types r2 ON r1.id = r2.pid
    GROUP BY r1.id, r1.pid, r1.prefix, r1.nom, r1.types
)
-- conversion des tableaux en json pour pouvoir les récupérer en php
-- les familles sont également triées par "prefix || nom" ou "prefix || nomen"
-- pour extraire les familles racines déjà ordonnées
SELECT id, pid, prefix, nom, array_to_json(types) AS types, array_to_json(children) AS children
FROM familles_type_children ORDER BY prefix ASC, nom ASC
SQL;
        $stmt = $this->getEntityManager()->getConnection()->executeQuery($sql);
        $familles = $stmt->fetchAllAssociative();

        $familiesIndexed = [];
        foreach ($familles as &$famille) {
            $famille['types'] = \json_decode($famille['types']);
            $famille['children'] = \json_decode($famille['children']);
            $familiesIndexed[$famille['id']] = $famille;
        }

        return $familiesIndexed;
    }

    //-----------------------------------------------------------------------------------------------------------------

    /**
     * Le tableaux des familles de paramètres utilisées sur l'observatoire courant ou sur une stations donnée
     * avec leurs filles et leurs types de paramètres utilisés sur l'observatoire courant ou sur la station.
     *
     * Utilisé pour générer l'arborescence familles / types sur l'accueil de l'observatoire ou
     * l'arborescence familles / types / chroniques sur la page de la station.
     *
     * @param bool         $isLocaleEn
     * @param Station|null $station
     *
     * @throws \Exception
     *
     * @return array
     */
    public function getFamillesAndTypes($isLocaleEn = false, $station = null)
    {
        $tablesLienObservatoire = '';
        if (!$station || !$station->getId()) {
            $tablesLienObservatoire = ', station st, stations_sites ss, siteexperimental si';
            $currentObsId = $this->getCurrentObservatoire()->getId();
            $clauseLienObservatoireOuStation =
                "ch.station_id = st.id AND st.id = ss.station_id AND ss.site_id = si.id AND si.observatoire_id = $currentObsId";
        } else {
            $stationId = $station->getId();
            $clauseLienObservatoireOuStation = "ch.station_id = $stationId";
        }

        $nom = $isLocaleEn ? 'nomen' : 'nom';

        $sql = <<<SQL
WITH RECURSIVE
-- les types utilisés sur l'observatoires ou sur la station regroupés dans un tableau par famille
-- les types sont triés par "nom" ou "nomen"
types_observatoire_ou_station(fid, noms) AS (
    SELECT ty.familleparametres_id, ARRAY_AGG(DISTINCT ty.$nom ORDER BY ty.$nom ASC)
    FROM chronique ch $tablesLienObservatoire, typeparametre ty
    WHERE $clauseLienObservatoireOuStation AND ch.parametre_id = ty.id
    GROUP BY ty.familleparametres_id
),
-- la jointure entre toutes les familles et leur tableau de types éventuel
familles_types(id, pid, prefix, nom, types) AS (
    SELECT f.id, f.familleparente_id, f.prefix, f.$nom, t.noms AS types
    FROM familleparametres f LEFT OUTER JOIN types_observatoire_ou_station t ON f.id = t.fid
    ORDER BY f.id
),
-- la construction recursive des familles parentes de celles ayant des types uniquement
r_familles_types(id, pid, prefix, nom, types) AS (
    SELECT f.id, f.pid, f.prefix, f.nom, f.types
    FROM familles_types f WHERE ARRAY_LENGTH(types, 1) > 0
    UNION DISTINCT
    SELECT f.id, f.pid, f.prefix, f.nom, f.types
    FROM familles_types f, r_familles_types r
    WHERE f.id = r.pid
),
-- la construction du tableau des familles filles utilisées pour chaque famille utilisée
-- les familles filles sont triées par "prefix || nom" ou "prefix || nomen"
familles_type_children(id, pid, prefix, nom, types, children) AS (
    SELECT r1.id, r1.pid, r1.prefix, r1.nom, r1.types,
        CASE ARRAY_AGG(r2.id ORDER BY r2.prefix || r2.nom ASC)
            WHEN ARRAY[NULL]::INTEGER[] THEN ARRAY[]::INTEGER[]
            ELSE ARRAY_AGG(r2.id ORDER BY r2.prefix || r2.nom ASC)
        END AS children
    FROM r_familles_types r1 LEFT OUTER JOIN r_familles_types r2 ON r1.id = r2.pid
    GROUP BY r1.id, r1.pid, r1.prefix, r1.nom, r1.types
)
-- conversion des tableaux en json pour pouvoir les récupérer en php
-- les familles sont également triées par "prefix || nom" ou "prefix || nomen"
-- pour extraire les familles racines déjà ordonnées
SELECT id, pid, prefix, nom, array_to_json(types) AS types, array_to_json(children) AS children
FROM familles_type_children ORDER BY prefix ASC, nom ASC
SQL;
        $stmt = $this->getEntityManager()->getConnection()->executeQuery($sql);
        $familles = $stmt->fetchAllAssociative();

        $familiesIndexed = [];
        foreach ($familles as &$famille) {
            $famille['types'] = \json_decode($famille['types']);
            $famille['children'] = \json_decode($famille['children']);
            $familiesIndexed[$famille['id']] = $famille;
        }

        return $familiesIndexed;
    }

    /**
     * Les familles de paramètre triées dans leur ordre hierarchique puis par "prefix || nom" ou "prefix || nomen"
     * en excluant la famille $famille et ses familles filles si $famille est fourni.
     *
     * Utilisé pour générer la liste arborescente des familles pour les selecteurs de familles des pages d'edition
     * des familles de paramètres et des types de paramètres dans sonata.
     *
     * @param FamilleParametres|null $famille
     * @param bool                   $isLocaleEn
     *
     * @throws \Exception
     *
     * @return array
     */
    public function findAllHierachy($famille = null, $isLocaleEn = false)
    {
        $familleClause = '';
        if ($famille && $famille->getId()) {
            $familleClause = 'AND f.id <> ' . $famille->getId();
        }

        $nom = $isLocaleEn ? 'nomen' : 'nom';

        $sql = <<<SQL
WITH RECURSIVE r_familles(id, pid, nom, depth, path) AS (
    SELECT f.id, f.familleparente_id, f.$nom, 0, ARRAY[COALESCE(f.prefix, '') || f.$nom]::CHARACTER VARYING(510)[]
    FROM familleparametres f
    WHERE f.familleparente_id IS NULL $familleClause
    UNION ALL
    SELECT f.id, f.familleparente_id, f.$nom, r.depth + 1,
        (r.path || (COALESCE(f.prefix, '') || f.$nom)::CHARACTER VARYING(510))::CHARACTER VARYING(510)[]
    FROM familleparametres f, r_familles r
    WHERE f.familleparente_id = r.id $familleClause
)
SELECT r.id, r.depth FROM r_familles r ORDER BY r.path
SQL;

        $stmt = $this->getEntityManager()->getConnection()->executeQuery($sql);
        $hierarchies = $stmt->fetchAllAssociative();

        $hierarchyIds = [];
        $hierarchyDepths = [];
        foreach ($hierarchies as $hierarchy) {
            $hierarchyIds[] = $hierarchy['id'];
            $hierarchyDepths[$hierarchy['id']] = $hierarchy['depth'];
        }

        $allFamilies = $this->findAll();

        $allFamiliesFilter = array_filter(
            $allFamilies,
            function ($family) use ($hierarchyIds) {
                /* @var FamilleParametres $family */
                return \in_array($family->getId(), $hierarchyIds);
            }
        );

        $allFamiliesFilterWithId = [];
        /* @var FamilleParametres $family */
        foreach ($allFamiliesFilter as $family) {
            $allFamiliesFilterWithId[$family->getId()] = $family;
        }

        $hierarchyFamilies = [];
        foreach ($hierarchyIds as $id) {
            $hierarchyFamilies[$id] = $allFamiliesFilterWithId[$id];
            $allFamiliesFilterWithId[$id]->depth = $hierarchyDepths[$id];
        }

        return $hierarchyFamilies;
    }
}
