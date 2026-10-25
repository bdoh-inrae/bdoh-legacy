<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohDataBundle\EventListener;

use Irstea\BdohBundle\Manager\JobManagerInterface;
use Irstea\BdohDataBundle\Events as BdohEvents;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Class ChronicleListener.
 */
class ChronicleListener
{
    /**
     * @var JobManagerInterface
     */
    private $jobManager;

    /**
     * @var ManagerRegistry
     */
    private $doctrine;

    /**
     * ChronicleListener constructor.
     *
     * @param JobManagerInterface $jobManager
     * @param ManagerRegistry     $doctrine
     */
    public function __construct(JobManagerInterface $jobManager, ManagerRegistry $doctrine)
    {
        $this->jobManager = $jobManager;
        $this->doctrine = $doctrine;
    }

    /**
     * @param MeasuresUpdateEvent $event
     */
    #[AsEventListener(event: BdohEvents::MEASURES_UPDATE)]
    public function onMeasuresUpdate(MeasuresUpdateEvent $event)
    {
        if (!$event->doesPropagate()) {
            return;
        }

        $chronique = $event->getChronique();

        // gestion des chroniques filles ayant 2 fois la même mère -> ne lance le calcul qu'une fois
        $sorties = [];
        foreach ($chronique->getTransformations() as $transfo) {
            if (!in_array($transfo->getSortie(), $sorties)) {
                $sorties[] = $transfo->getSortie();
                if ($transfo->getSortie()->isCalculee()) {
                    $this->jobManager->enqueueChronicleComputation($transfo->getSortie(), $event->getUser(), true);
                }
                if ($transfo->getSortie()->isConvertie()) {
                    $this->jobManager->enqueueChronicleConversion($transfo->getSortie(), $event->getUser(), true);
                }
            }
        }
    }
}
