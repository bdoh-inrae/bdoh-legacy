<?php
// SPDX-FileCopyrightText: The BDOH authors
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace Irstea\BdohDataBundle\Entity\Repository;

/**
 * Class JeuQualiteRepository.
 */
class JeuQualiteRepository extends EntityRepository
{
    /**
     * This function returns *only* assignable 'JeuQualite'.
     *
     * @param mixed $includeNotAssignable
     *
     * @return array
     */
    public function findAll($includeNotAssignable = false): array
    {
        $qb = $this->createQueryBuilder('jq');
        if (!$includeNotAssignable) {
            $qb = $qb->where('jq.estAffectable = true');
        }

        return $qb->getQuery()->getResult();
    }
}
