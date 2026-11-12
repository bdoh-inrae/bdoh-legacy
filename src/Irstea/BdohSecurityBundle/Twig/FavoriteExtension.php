<?php
// SPDX-FileCopyrightText: The BDOH authors
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace Irstea\BdohSecurityBundle\Twig;

use Doctrine\ORM\EntityManagerInterface;
use Irstea\BdohSecurityBundle\Entity\Favorite;
use Irstea\BdohSecurityBundle\Entity\Utilisateur;
use Symfony\Bundle\SecurityBundle\Security;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * L'étoile d'une chronique (@IrsteaBdohSecurity/Favorite/_star.html.twig) : les chroniques que le compte connecté a
 * mises en favori, lues une fois par page (dev/plans/2026-11-12_favorites.md).
 */
class FavoriteExtension extends AbstractExtension
{
    /** @var array<int, true>|null */
    private ?array $ids = null;

    public function __construct(private Security $security, private EntityManagerInterface $em)
    {
    }

    public function getFunctions(): array
    {
        return [new TwigFunction('is_favorite', $this->isFavorite(...))];
    }

    public function isFavorite(int $chroniqueId): bool
    {
        if (null === $this->ids) {
            $user = $this->security->getUser();
            $this->ids = [];
            if ($user instanceof Utilisateur) {
                $ids = $this->em->createQuery(
                    'SELECT IDENTITY(f.chronique) FROM ' . Favorite::class . ' f WHERE f.utilisateur = :user'
                )->setParameter('user', $user)->getSingleColumnResult();
                $this->ids = array_fill_keys(array_map('intval', $ids), true);
            }
        }

        return isset($this->ids[$chroniqueId]);
    }
}
