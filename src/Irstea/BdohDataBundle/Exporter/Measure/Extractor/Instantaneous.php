<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohDataBundle\Exporter\Measure\Extractor;

use Irstea\BdohDataBundle\Entity\Chronique;
use Irstea\BdohDataBundle\Entity\ChroniqueContinue;
use Irstea\BdohDataBundle\Entity\Repository\ChroniqueContinueRepository;
use Irstea\BdohDataBundle\Entity\Repository\ChroniqueRepository;
use Irstea\BdohDataBundle\Exporter\Measure\FormatterInterface;
use Doctrine\Persistence\ManagerRegistry;

class Instantaneous extends Identical
{
    /**
     * @var int
     */
    protected $timestep;

    /**
     * {@inheritdoc}
     */
    public function __construct(ManagerRegistry $doctrine, FormatterInterface $formatter, array $parameters)
    {
        parent::__construct($doctrine, $formatter, $parameters);
        $this->timestep = $parameters['timestep'];
    }

    /**
     * @param Chronique $chronique
     *
     * @return bool
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameters)
     */
    public function supportsChronique(Chronique $chronique)
    {
        return $chronique->isContinue();
    }

    /**
     * {@inheritdoc}
     */
    protected function extractMeasures(ChroniqueRepository $repo, Chronique $chronique)
    {
        assert($repo instanceof ChroniqueContinueRepository);
        assert($chronique instanceof ChroniqueContinue);

        /* @var ChroniqueContinueRepository $repo */
        return $repo->getDataChangeStepInstant(
            $chronique,
            $this->begin,
            $this->end,
            $this->timestep,
            $this->timezone
        );
    }
}
