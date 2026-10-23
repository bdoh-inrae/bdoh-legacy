<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohSecurityBundle\Security\Voter;

use Irstea\BdohDataBundle\Entity\Observatoire;
use Irstea\BdohSecurityBundle\Entity\Role;
use Irstea\BdohSecurityBundle\Entity\Utilisateur;
use JMS\DiExtraBundle\Annotation as DI;
use JMS\JobQueueBundle\Entity\Job;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Contrôle d'accès sur les jobs.
 *
 * Gère les droits de la forme JOBS_* sur les Job.
 *
 * @DI\Service
 * @DI\Tag("security.voter")
 */
class JobVoter extends Voter
{
    const SHOW_PERM = 'JOB_SHOW';
    const LIST_PERM = 'JOB_LIST';
    const RETRY_PERM = 'JOB_RETRY';
    const CANCEL_PERM = 'JOB_CANCEL';

    /**
     * {@inheritdoc}
     */
    protected function supports($attribute, $subject)
    {
        switch ($attribute) {
            case self::LIST_PERM:
                return $subject instanceof Observatoire;

            case self::SHOW_PERM:
            case self::RETRY_PERM:
            case self::CANCEL_PERM:
                return $subject instanceof Job;

            default:
                return false;
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function voteOnAttribute($attribute, $subject, TokenInterface $token)
    {
        $user = $token->getUser();
        if (!$user instanceof Utilisateur) {
            return false;
        }

        if ($user->isGestionnaireDesObservatoires()) {
            return true;
        }

        if ($attribute === self::LIST_PERM) {
            return $user->securedHasRoleObservatoire(Role::GESTIONNAIRE, [$subject]) ||
                $user->securedHasRoleSite([Role::GESTIONNAIRE, Role::CONTRIBUTEUR], $subject->getSites());
        }

        if ($this->isAdminOfObservatoire($user, $subject)) {
            return true;
        }

        if ($attribute === self::RETRY_PERM) {
            return false;
        }

        return $this->isOwnerOfJobs($user, $subject);
    }

    /**
     * @param Utilisateur $user
     * @param Job         $job
     *
     * @return bool
     */
    private function isOwnerOfJobs(Utilisateur $user, Job $job)
    {
        $owner = $job->findRelatedEntity(Utilisateur::class);

        return $owner && $owner->getId() === $user->getId() && !$owner->isAccountExpired();
    }

    /**
     * @param Utilisateur $user
     * @param Job         $job
     *
     * @return bool
     */
    private function isAdminOfObservatoire(Utilisateur $user, Job $job)
    {
        $observatoire = $job->findRelatedEntity(Observatoire::class);

        return $user->securedHasRoleObservatoire(Role::GESTIONNAIRE, [$observatoire]);
    }
}
