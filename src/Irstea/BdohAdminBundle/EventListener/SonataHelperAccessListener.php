<?php
// SPDX-FileCopyrightText: The BDOH authors
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace Irstea\BdohAdminBundle\EventListener;

use Sonata\AdminBundle\Request\AdminFetcherInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Sonata's form helpers check no right of their own: they ask here what the form that calls them needs.
 */
final class SonataHelperAccessListener
{
    private const SHORT_DESCRIPTION = 'sonata_admin_short_object_information';
    private const FORM_FIELDS = ['sonata_admin_retrieve_form_element', 'sonata_admin_append_form_element'];

    public function __construct(private readonly AdminFetcherInterface $adminFetcher)
    {
    }

    public function onKernelController(ControllerEvent $event): void
    {
        $request = $event->getRequest();
        $route = $request->attributes->get('_route');
        if (self::SHORT_DESCRIPTION !== $route && !\in_array($route, self::FORM_FIELDS, true)) {
            return;
        }

        try {
            $admin = $this->adminFetcher->get($request);
        } catch (\InvalidArgumentException) {
            return; // the helper itself answers 404
        }

        if (self::SHORT_DESCRIPTION === $route) {
            // The list where the entity is picked
            $granted = $admin->hasAccess('list');
        } else {
            $objectId = $request->query->get('objectId');
            if (null === $objectId) {
                $granted = $admin->hasAccess('create');
            } else {
                $object = $admin->getObject($objectId);
                if (null === $object) {
                    return; // the helper itself answers 404
                }
                $granted = $admin->hasAccess('edit', $object);
            }
        }

        if (!$granted) {
            throw new AccessDeniedHttpException();
        }
    }
}
