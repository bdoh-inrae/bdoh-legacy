<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohDataBundle\Entity\Repository;

/**
 * Class QualiteRepository.
 */
class QualiteRepository extends EntityRepository
{
    /**
     * @return array
     */
    public function securedOrdresToCodes()
    {
        $jeu = $this->getCurrentObservatoire()->getJeu();
        $result = $this->createQueryBuilder('q')
            ->where('q.jeu = :jeu')
            ->andWhere("q.code <> 'gap'")
            ->setParameter('jeu', $jeu)
            ->getQuery()->getResult();

        $ordreToQualite = [];
        foreach ($result as $qualite) {
            $ordreToQualite[$qualite->getOrdre()] = $qualite->getCode();
        }

        return $ordreToQualite;
    }
}
