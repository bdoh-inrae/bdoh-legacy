<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohSecurityBundle\Security\Voter;

use JMS\DiExtraBundle\Annotation as DI;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\RoleHierarchyVoter;
use Symfony\Component\Security\Core\Role\RoleHierarchyInterface;

/**
 * Class SuperAdminVoter.
 *
 * @DI\Service
 * @DI\Tag("security.voter")
 */
class SuperAdminVoter extends RoleHierarchyVoter
{
    /**
     * SuperAdminVoter constructor.
     *
     * @param RoleHierarchyInterface $roleHierarchy
     * @DI\InjectParams({"roleHierarchy" = @DI\Inject("security.role_hierarchy")})
     */
    public function __construct(RoleHierarchyInterface $roleHierarchy)
    {
        parent::__construct($roleHierarchy, '*');
    }

    /**
     * {@inheritdoc}
     */
    public function supportsAttribute($attribute)
    {
        return is_string($attribute);
    }

    /**
     * {@inheritdoc}
     */
    public function vote(TokenInterface $token, $object, array $attributes)
    {
        $vote = parent::vote($token, $object, ['ROLE_SUPER_ADMIN']);

        return $vote === self::ACCESS_GRANTED ? $vote : self::ACCESS_ABSTAIN;
    }
}
