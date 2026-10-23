<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohDataBundle\Exporter\Measure\Extractor;

use Irstea\BdohDataBundle\Entity\Chronique;
use Irstea\BdohDataBundle\Entity\ChroniqueContinue;
use Irstea\BdohDataBundle\Entity\Repository\ChroniqueContinueRepository;

class Cumulative extends Mean
{
    /**
     * @param Chronique $chronique
     *
     * @return string
     */
    protected function getUnite(Chronique $chronique)
    {
        /* @var ChroniqueContinue $chronique */
        return $chronique->getUniteCumul();
    }

    /**
     * {@inheritdoc}
     */
    protected function doExtractMeasure(ChroniqueContinueRepository $repo, ChroniqueContinue $chronique)
    {
        return $repo->getDataChangeStepCumulative(
            $chronique,
            $this->begin,
            $this->end,
            $this->timestep,
            $this->timezone
        );
    }
}
