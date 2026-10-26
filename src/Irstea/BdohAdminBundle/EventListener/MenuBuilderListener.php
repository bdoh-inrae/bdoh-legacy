<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohAdminBundle\EventListener;

use Sonata\AdminBundle\Event\ConfigureMenuEvent;

class MenuBuilderListener
{
    public function addMenuItems(ConfigureMenuEvent $event): void
    {
        $menu = $event->getMenu();

        $child = $menu->addChild(
            'reports',
            [
                'route' => 'bdoh_consult_observatoire',
            ]
        );

        $child->setLabel('backToBdoh');
    }
}
