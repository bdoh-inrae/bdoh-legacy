<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohSecurityBundle\Security\Voter;

use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\RoleHierarchyVoter;
use Symfony\Component\Security\Core\Role\RoleHierarchyInterface;

/**
 * Class SuperAdminVoter.
 */
class SuperAdminVoter extends RoleHierarchyVoter
{
    /**
     * SuperAdminVoter constructor.
     *
     * @param RoleHierarchyInterface $roleHierarchy
     */
    public function __construct(RoleHierarchyInterface $roleHierarchy)
    {
        // Sous Symfony 7, le préfixe filtre les rôles demandés : ROLE_ laisse passer ROLE_SUPER_ADMIN, que
        // vote() demande (le '*' de l'ancien ne jouait pas, supportsAttribute() passant outre).
        parent::__construct($roleHierarchy, 'ROLE_');
    }

    /**
     * {@inheritdoc}
     */
    public function supportsAttribute(string $attribute): bool
    {
        return is_string($attribute);
    }

    /**
     * {@inheritdoc}
     */
    public function vote(TokenInterface $token, mixed $object, array $attributes): int
    {
        // L'expression d'un #[IsGranted] arrive ici comme attribut ; l'ancien @Security l'évaluait sans
        // les voteurs : le super administrateur n'y passe pas outre.
        if (!array_filter($attributes, is_string(...))) {
            return self::ACCESS_ABSTAIN;
        }

        $vote = parent::vote($token, $object, ['ROLE_SUPER_ADMIN']);

        return $vote === self::ACCESS_GRANTED ? $vote : self::ACCESS_ABSTAIN;
    }
}
