<?php
// SPDX-FileCopyrightText: The BDOH authors
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace Irstea\BdohSecurityBundle\Entity\Repository;

use Irstea\BdohDataBundle\Entity\Chronique;
use Irstea\BdohDataBundle\Entity\Observatoire;
use Irstea\BdohDataBundle\Entity\Repository\EntityRepository;
use Irstea\BdohSecurityBundle\Entity\Favorite;
use Irstea\BdohSecurityBundle\Entity\Utilisateur;

/**
 * Les favoris d'un compte, qui traversent les observatoires : aucune requête ici ne se limite à l'observatoire de la
 * page, contrairement aux dépôts des données.
 */
class FavoriteRepository extends EntityRepository
{
    public function findOneFor(Utilisateur $user, Chronique $chronique): ?Favorite
    {
        return $this->findOneBy(['utilisateur' => $user, 'chronique' => $chronique]);
    }

    /**
     * Les identifiants des chroniques qu'un compte a mises en favori, parmi celles-ci.
     *
     * @param int[] $chroniqueIds
     *
     * @return array<int, true>
     */
    public function chroniqueIdsFor(Utilisateur $user, array $chroniqueIds): array
    {
        if (!$chroniqueIds) {
            return [];
        }
        $ids = $this->getEntityManager()->createQuery(
            'SELECT IDENTITY(f.chronique) AS id FROM ' . Favorite::class . ' f
              WHERE f.utilisateur = :user AND f.chronique IN (:chroniques)'
        )
            ->setParameter('user', $user)
            ->setParameter('chroniques', $chroniqueIds)
            ->getSingleColumnResult();

        return array_fill_keys(array_map('intval', $ids), true);
    }

    /**
     * Les favoris d'un compte, rangés par observatoire (par nom), puis par station et par chronique (par code).
     *
     * @return array<int, array{observatoire: Observatoire, favorites: Favorite[]}>
     */
    public function findGroupedFor(Utilisateur $user): array
    {
        $favorites = $this->getEntityManager()->createQuery(
            'SELECT f, c, s FROM ' . Favorite::class . ' f JOIN f.chronique c JOIN c.station s
              WHERE f.utilisateur = :user ORDER BY s.code, c.code, f.id'
        )
            ->setParameter('user', $user)
            ->getResult();
        if (!$favorites) {
            return [];
        }

        // L'observatoire d'une station, par ses sites, qui sont tous du même observatoire
        $rows = $this->getEntityManager()->createQuery(
            'SELECT s.id AS station, o.id AS observatoire FROM Irstea\BdohDataBundle\Entity\Station s
               JOIN s.sites si JOIN si.observatoire o WHERE s.id IN (:stations)'
        )
            ->setParameter('stations', array_unique(array_map(fn (Favorite $f) => $f->getChronique()->getStation()->getId(), $favorites)))
            ->getArrayResult();
        $observatoireOf = array_column($rows, 'observatoire', 'station');
        $observatoires = [];
        foreach ($this->getEntityManager()->getRepository(Observatoire::class)->findBy(['id' => array_values(array_unique($observatoireOf))]) as $observatoire) {
            $observatoires[$observatoire->getId()] = $observatoire;
        }

        $groups = [];
        foreach ($favorites as $favorite) {
            $id = $observatoireOf[$favorite->getChronique()->getStation()->getId()] ?? null;
            if (null === $id || !isset($observatoires[$id])) {
                continue; // une station sans site n'est d'aucun observatoire : sa chronique ne s'affiche nulle part
            }
            $groups[$id] ??= ['observatoire' => $observatoires[$id], 'favorites' => []];
            $groups[$id]['favorites'][] = $favorite;
        }
        uasort($groups, fn ($a, $b) => strnatcasecmp((string) $a['observatoire']->getNom(), (string) $b['observatoire']->getNom()));

        return array_values($groups);
    }

    /**
     * Les favoris d'un compte parmi ces identifiants de favoris : ceux d'un autre compte n'y sont jamais.
     *
     * @param int[] $ids
     *
     * @return Favorite[]
     */
    public function findSomeFor(Utilisateur $user, array $ids): array
    {
        if (!$ids) {
            return [];
        }

        return $this->findBy(['utilisateur' => $user, 'id' => $ids]);
    }
}
