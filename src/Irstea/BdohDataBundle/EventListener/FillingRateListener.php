<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohDataBundle\EventListener;

use Irstea\BdohBundle\Manager\JobManagerInterface;
use Irstea\BdohDataBundle\Events as BdohEvents;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Class FillingRateListener.
 */
class FillingRateListener
{
    /**
     * @var JobManagerInterface
     */
    public $jobManager;

    public function __construct(JobManagerInterface $jobManager)
    {
        $this->jobManager = $jobManager;
    }

    /**
     * @param MeasuresUpdateEvent $event
     *
     * La priorité garde l'ordre de l'ancien BDOH : taux de remplissage, métadonnées, chroniques filles.
     */
    #[AsEventListener(event: BdohEvents::MEASURES_UPDATE, priority: 2)]
    public function onMeasuresUpdate(MeasuresUpdateEvent $event)
    {
        $chronique = $event->getChronique();
        if ($chronique->isContinue()) {
            $this->jobManager->enqueueComputeFillingRates(
                $chronique,
                $event->getDebut(),
                $event->getFin()
            );
        }
    }
}
