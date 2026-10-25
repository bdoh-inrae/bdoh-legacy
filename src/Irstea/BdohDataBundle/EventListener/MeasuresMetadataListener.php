<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohDataBundle\EventListener;

use Irstea\BdohDataBundle\Entity\Repository\ChroniqueRepository;
use Irstea\BdohDataBundle\Events as BdohEvents;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Class MeasuresMetadataListener.
 */
class MeasuresMetadataListener
{
    /**
     * @var ManagerRegistry
     */
    public $doctrine;

    public function __construct(ManagerRegistry $doctrine)
    {
        $this->doctrine = $doctrine;
    }

    /**
     * @param MeasuresUpdateEvent $event
     *
     * La priorité garde l'ordre de l'ancien BDOH : taux de remplissage, métadonnées, chroniques filles.
     */
    #[AsEventListener(event: BdohEvents::MEASURES_UPDATE, priority: 1)]
    public function onMeasuresUpdate(MeasuresUpdateEvent $event)
    {
        $chronique = $event->getChronique();

        /** @var ChroniqueRepository $repo */
        $repo = $this->doctrine
            ->getManagerForClass(get_class($chronique))
            ->getRepository(get_class($chronique));

        $repo->updateMeasuresMetadata($chronique);
    }
}
