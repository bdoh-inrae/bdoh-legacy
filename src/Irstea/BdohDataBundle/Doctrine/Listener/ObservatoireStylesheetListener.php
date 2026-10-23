<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohDataBundle\Doctrine\Listener;

use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostRemoveEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Irstea\BdohBundle\Manager\CssManager;
use Irstea\BdohDataBundle\Entity\Observatoire;

/**
 * Crée/Met à jour/supprime la feuille de style d'un observatoire quand il est créé/modifié/supprimé.
 */
class ObservatoireStylesheetListener
{
    /**
     * @var CssManager
     */
    public $cssManager;

    public function __construct(CssManager $cssManager)
    {
        $this->cssManager = $cssManager;
    }

    /**
     * @param Observatoire         $obs
     * @param PostPersistEventArgs $event
     *
     * @throws \Less_Exception_Parser
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function postPersist(Observatoire $obs, PostPersistEventArgs $unused)
    {
        $this->cssManager->removeObservatoireCssFile($obs);
    }

    /**
     * @param Observatoire        $obs
     * @param PostUpdateEventArgs $event
     *
     * @throws \Less_Exception_Parser
     */
    public function postUpdate(Observatoire $obs, PostUpdateEventArgs $event)
    {
        $changes = $event->getObjectManager()->getUnitOfWork()->getEntityChangeSet($obs);

        if (isset($changes['couleurPrimaire']) || isset($changes['couleurSecondaire'])) {
            $this->cssManager->removeObservatoireCssFile($obs);
        }
    }

    /**
     * @param Observatoire        $obs
     * @param PostRemoveEventArgs $event
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function postRemove(Observatoire $obs, PostRemoveEventArgs $unused)
    {
        $this->cssManager->removeObservatoireCssFile($obs);
    }
}
