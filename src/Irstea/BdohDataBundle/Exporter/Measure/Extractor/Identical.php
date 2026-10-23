<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohDataBundle\Exporter\Measure\Extractor;

use Irstea\BdohDataBundle\Entity\Chronique;
use Irstea\BdohDataBundle\Entity\Repository\ChroniqueRepository;
use Irstea\BdohDataBundle\Exporter\Measure\FormatterInterface;

class Identical extends AbstractExtractor
{
    /**
     * @param Chronique $chronique
     *
     * @return bool
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameters)
     */
    public function supportsChronique(Chronique $chronique)
    {
        return true;
    }

    /**
     * @param FormatterInterface $formatter
     *
     * @return bool
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameters)
     */
    public function supportsFormatter(FormatterInterface $formatter)
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    protected function extractMeasures(ChroniqueRepository $repo, Chronique $chronique)
    {
        return $repo->getMeasuresForExport(
            $chronique,
            $this->begin,
            $this->end,
            $this->timezone
        );
    }
}
