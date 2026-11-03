<?php
// SPDX-FileCopyrightText: The BDOH authors
// SPDX-License-Identifier: AGPL-3.0-or-later

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
