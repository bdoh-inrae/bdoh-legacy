<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohDataBundle\Entity\Repository;

use Irstea\BdohDataBundle\Entity\Observatoire;
use Irstea\BdohDataBundle\Entity\Partenaire;

/**
 * ObservatoireRepository.
 *
 * @method findOneBySlug($slug): Observatoire
 */
class ObservatoireRepository extends EntityRepository
{
    /**
     * Returns an array containing all 'observatoires' entities (with some related data).
     * NOTE : the "all observatoire" is not selected !
     */
    public function findAll(): array
    {
        $dql = 'SELECT o, s, p FROM ' . $this->getEntityName() . ' o'
            . ' LEFT OUTER JOIN o.sites s'
            . ' LEFT OUTER JOIN o.partenaires p'
            . ' ORDER BY o.slug';

        $query = $this->getEntityManager()->createQuery($dql);

        return $query->getResult();
    }

    /**
     * @param $alias
     * @param null $indexBy
     *
     * @return Observatoire[]
     */
    public function findObservatoireToSendAutomatically()
    {
        return $this->createQueryBuilder('o')
            ->andWhere('o.theiaCode IS not NULL')
            ->andWhere('o.theiaPassword IS not NULL')
            ->andWhere('o.miseajourAutomatique = TRUE')
            ->getQuery()
            ->getResult();
    }

    /**
     * @param int $id
     *
     * @throws \Exception
     *
     * @return Observatoire
     */
    public function findOneById($id)
    {
        return $this->createQueryBuilder('o')
            ->andWhere('o.id = :id')->setParameter('id', $id)
            ->getQuery()
            ->getSingleResult();
    }

    /**
     * @param string $theiaCode
     *
     * @throws \Exception
     *
     * @return Observatoire
     */
    public function findOneByTheiaCode($theiaCode)
    {
        return $this->createQueryBuilder('o')
            ->andWhere('o.theiaCode = :theiaCode')->setParameter('theiaCode', $theiaCode)
            ->getQuery()
            ->getSingleResult();
    }

    /**
     * Returns a QueryBuilder returning all 'TypeParametres' of current 'Observatoire'
     * (ie : that have at least one 'Chronique').
     * Utilisé pour la liste des paramètres dans le formulaire des besoins.
     *
     * @param bool $isLocaleEn
     *
     * @return \Doctrine\ORM\QueryBuilder
     */
    public function createParametresQueryBuilder($isLocaleEn = false)
    {
        $chroniques = $this->getBdohRepo('Chronique')->findAll();

        return $this->getBdohRepo('TypeParametre')->createQueryBuilder('p')
            ->leftJoin('p.chroniques', 'ch')
            ->where('ch in (:chroniques)')->setParameter('chroniques', $chroniques)
            ->orderBy($isLocaleEn ? 'p.nomEn' : 'p.nom', 'ASC');
    }

    /**
     * Returns all 'TypeParametres' of current 'Observatoire'
     * (ie : that have at least one 'Chronique').
     *
     * Uniquement les types sans famille pour la transition vers les familles de paramètres.
     *
     * Utilisé pour afficher la liste des types sur l'accueil de l'observatoire et pour
     * avoir la liste des types sans familles pour la page des station.
     *
     * @param bool $isLocaleEn
     *
     * @return array
     */
    public function getParametres($isLocaleEn = false)
    {
        $qb = $this->getBdohRepo('Chronique')->createQueryBuilder('c');
        $qb->select('DISTINCT p.nom, p.nomEn')
            ->leftJoin('c.parametre', 'p')
            // Uniquement les types sans famille pour la transition vers les familles de paramètres.
            ->andWhere($qb->expr()->isNull('p.familleParametres'))
            ->orderBy($isLocaleEn ? 'p.nomEn' : 'p.nom', 'ASC');

        return $qb->getQuery()->getResult();
    }

    /**
     * Returns all 'observatoires' entities that are partenered with the given partenaire.
     *
     * @param \Irstea\BdohDataBundle\Entity\Partenaire $partenaire
     *
     * @return array
     */
    public function findByPartenaire($partenaire)
    {
        return parent::createQueryBuilder('o')
            ->leftJoin('o.partenaires', 'p')
            ->where('p = :partenaires')
            ->setParameter('partenaires', $partenaire)
            ->getQuery()
            ->getResult();
    }

    /**
     * @param int $id
     *
     * @throws \Exception
     *
     * @return Observatoire
     */
    public function findStationByObservatoire($id)
    {
        return $this->createQueryBuilder('o')
            ->leftJoin('o.dataset', 'ds')
            ->leftJoin('ds.chronique', 'ch')
            ->leftJoin('ch.station', 'st')
            ->andWhere('o.id = :id')->setParameter('id', $id)
            ->getQuery()
            ->getSingleResult();
    }
}
