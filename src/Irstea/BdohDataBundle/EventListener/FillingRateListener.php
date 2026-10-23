<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohDataBundle\EventListener;

use Irstea\BdohBundle\Manager\JobManagerInterface;
use Irstea\BdohDataBundle\Events as BdohEvents;
use JMS\DiExtraBundle\Annotation as DI;

/**
 * Class FillingRateListener.
 *
 * @DI\Service()
 */
class FillingRateListener
{
    /**
     * @var JobManagerInterface
     *
     * @DI\Inject("irstea_bdoh.job_manager")
     */
    public $jobManager;

    /**
     * @param MeasuresUpdateEvent $event
     *
     * @DI\Observe(BdohEvents::MEASURES_UPDATE)
     */
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
