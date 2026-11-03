<?php

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
