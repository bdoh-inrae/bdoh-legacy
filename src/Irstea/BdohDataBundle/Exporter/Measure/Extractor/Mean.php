<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohDataBundle\Exporter\Measure\Extractor;

use Irstea\BdohDataBundle\Entity\Chronique;
use Irstea\BdohDataBundle\Entity\ChroniqueContinue;
use Irstea\BdohDataBundle\Entity\PasEchantillonnage;
use Irstea\BdohDataBundle\Entity\Repository\ChroniqueContinueRepository;
use Irstea\BdohDataBundle\Entity\Repository\ChroniqueRepository;
use Irstea\BdohDataBundle\Exporter\Measure\FormatterInterface;
use Doctrine\Persistence\ManagerRegistry;

class Mean extends AbstractExtractor
{
    /**
     * @var PasEchantillonnage
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
     * {@inheritdoc}
     */
    public function supportsChronique(Chronique $chronique)
    {
        return $chronique->isContinue();
    }

    /**
     * {@inheritdoc}
     */
    public function supportsFormatter(FormatterInterface $formatter)
    {
        return $formatter->supportsRange();
    }

    /**
     * {@inheritdoc}
     */
    protected function extractMeasures(ChroniqueRepository $repo, Chronique $chronique)
    {
        assert($repo instanceof ChroniqueContinueRepository);
        assert($chronique instanceof ChroniqueContinue);

        $result = $this->doExtractMeasure($repo, $chronique);
        $result[1]['isRange'] = true;

        return $result;
    }

    /**
     * @param ChroniqueContinueRepository $repo
     * @param ChroniqueContinue           $chronique
     *
     * @return array
     */
    protected function doExtractMeasure(ChroniqueContinueRepository $repo, ChroniqueContinue $chronique)
    {
        /* @var ChroniqueContinueRepository $repo */
        return $repo->getDataChangeStepMean(
            $chronique,
            $this->begin,
            $this->end,
            $this->timestep,
            $this->timezone
        );
    }
}
