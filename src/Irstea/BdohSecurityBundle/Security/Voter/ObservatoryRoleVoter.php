<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohSecurityBundle\Security\Voter;

use Irstea\BdohSecurityBundle\Entity\Utilisateur;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\RoleHierarchyVoter;
use Symfony\Component\Security\Core\Role\RoleHierarchyInterface;

/**
 * Le voteur des rôles (ROLE_…) de Symfony, dont il prend la place (Resources/config/services.yml).
 *
 * Les rôles d'un compte BDOH dépendent de l'observatoire courant (Utilisateur::getRoles : gestionnaire
 * d'un observatoire, ROLE_ADMIN sur celui-là seulement), quand ceux du jeton sont figés à la connexion.
 * L'ancien BDOH jugeait sur ceux du jeton : les rôles de l'observatoire où l'on s'était connecté.
 * Avec le cookie de 30 jours, la connexion se refait en silence sur la première page d'une session,
 * de n'importe quel observatoire : ce voteur juge donc sur les rôles de l'observatoire de la page.
 */
class ObservatoryRoleVoter extends RoleHierarchyVoter
{
    public function __construct(private RoleHierarchyInterface $hierarchy)
    {
        parent::__construct($hierarchy);
    }

    protected function extractRoles(TokenInterface $token): array
    {
        $user = $token->getUser();
        if ($user instanceof Utilisateur) {
            return $this->hierarchy->getReachableRoleNames($user->getRoles());
        }

        return parent::extractRoles($token);
    }
}
