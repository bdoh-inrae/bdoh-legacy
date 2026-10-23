<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohDataBundle\EventListener;

use Irstea\BdohDataBundle\Entity\Repository\ChroniqueRepository;
use Irstea\BdohDataBundle\Events as BdohEvents;
use JMS\DiExtraBundle\Annotation as DI;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * Class MeasuresMetadataListener.
 *
 * @DI\Service()
 */
class MeasuresMetadataListener
{
    /**
     * @var RegistryInterface
     *
     * @DI\Inject("doctrine")
     */
    public $doctrine;

    /**
     * @param MeasuresUpdateEvent $event
     *
     * @DI\Observe(BdohEvents::MEASURES_UPDATE)
     */
    public function onMeasuresUpdate(MeasuresUpdateEvent $event)
    {
        $chronique = $event->getChronique();

        /** @var ChroniqueRepository $repo */
        $repo = $this->doctrine
            ->getEntityManagerForClass(get_class($chronique))
            ->getRepository(get_class($chronique));

        $repo->updateMeasuresMetadata($chronique);
    }
}
