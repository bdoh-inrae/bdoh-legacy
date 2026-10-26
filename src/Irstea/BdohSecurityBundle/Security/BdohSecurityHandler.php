<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohSecurityBundle\Security;

use Irstea\BdohDataBundle\Entity\Commune;
use Sonata\AdminBundle\Admin\AdminInterface;
use Sonata\AdminBundle\Security\Handler\SecurityHandlerInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationCredentialsNotFoundException;

/**
 * Class BdohSecurityHandler.
 */
class BdohSecurityHandler implements SecurityHandlerInterface
{
    /**
     * @var AuthorizationCheckerInterface
     */
    protected $authorizationChecker;

    /**
     * @param AuthorizationCheckerInterface $authorizationChecker
     */
    public function __construct(AuthorizationCheckerInterface $authorizationChecker)
    {
        $this->authorizationChecker = $authorizationChecker;
    }

    /**
     * {@inheritdoc}
     */
    public function isGranted(AdminInterface $admin, $attributes, ?object $object = null): bool
    {
        if ($attributes instanceof \Traversable) {
            $attributes = iterator_to_array($attributes);
        } elseif (!\is_array($attributes)) {
            $attributes = [$attributes];
        }

        // Transforme les demande de droit LIST en demande de droit AJAX
        // pour la liste des Communes faite en autocomplete (AJAX)
        if ($admin->hasRequest()
            && $admin->getRequest()->attributes->get('_route') === 'sonata_admin_retrieve_autocomplete_items'
            && in_array('LIST', $attributes, true)
            && $admin->getClass() === Commune::class) {
            $attributes[] = 'AJAX';
        }

        $baseRole = $this->getBaseRole($admin);
        $actualAttributes = array_map(
            function ($role) use ($baseRole) {
                return sprintf($baseRole, $role);
            },
            $attributes
        );

        // Si on a un AdminInterface, c'est que Sonata n'a pas d'instance d'objet (i.e. pour CREATE)
        // alors on utilise le nom de la classe à la place.
        if ($object instanceof AdminInterface) {
            $object = $object->getClass();
        }

        // Symfony 2 jugeait tous les attributs en une fois, accordés si l'un l'était ; Symfony 7
        // n'en juge qu'un à la fois.
        try {
            foreach ($actualAttributes as $attribute) {
                if ($this->authorizationChecker->isGranted($attribute, $object)) {
                    return true;
                }
            }

            return false;
        } catch (AuthenticationCredentialsNotFoundException $e) {
            return false;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getBaseRole(AdminInterface $admin): string
    {
        return '%s';
    }

    /**
     * {@inheritdoc}
     */
    public function buildSecurityInformation(AdminInterface $admin): array
    {
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function createObjectSecurity(AdminInterface $admin, object $object): void
    {
    }

    /**
     * {@inheritdoc}
     */
    public function deleteObjectSecurity(AdminInterface $admin, object $object): void
    {
    }
}
