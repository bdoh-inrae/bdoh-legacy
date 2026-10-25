<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohDataBundle\Exporter\Measure\Extractor;

use Irstea\BdohDataBundle\Entity\Chronique;
use Irstea\BdohDataBundle\Entity\Repository\ChroniqueRepository;
use Irstea\BdohDataBundle\Exporter\Measure\ExtractorInterface;
use Irstea\BdohDataBundle\Exporter\Measure\FormatterInterface;
use Doctrine\Persistence\ManagerRegistry;

abstract class AbstractExtractor implements ExtractorInterface
{
    /**
     * @var ManagerRegistry
     */
    private $doctrine;

    /**
     * @var FormatterInterface
     */
    protected $formatter;

    /**
     * @var \DateTime
     */
    protected $begin;

    /**
     * @var \DateTime
     */
    protected $end;

    /**
     * @var \DateTimeZone
     */
    protected $timezone;

    /**
     * @var array
     */
    protected $report;

    /**
     * @var int
     */
    protected $precision;

    /**
     * AbstractExtractor constructor.
     *
     * @param ManagerRegistry    $doctrine
     * @param FormatterInterface $formatter
     * @param array              $parameters
     */
    public function __construct(ManagerRegistry $doctrine, FormatterInterface $formatter, array $parameters)
    {
        $this->doctrine = $doctrine;

        assert($this->supportsFormatter($formatter));
        $this->formatter = $formatter;

        $this->begin = $parameters['begin'];
        $this->end = $parameters['end'];
        $this->timezone = $parameters['timezone'];
        $this->precision = $parameters['precision'] ?? 3;
    }

    /**
     * {@inheritdoc}
     */
    public function extract(Chronique $chronique, $output)
    {
        assert($this->supportsChronique($chronique));

        /** @var ChroniqueRepository $repo */
        $repo = $this->doctrine->getRepository(get_class($chronique));

        $this->report = [
            'isRange'    => !$chronique->isContinue(),
            'isContinue' => $chronique->isContinue(),
            'isComputed' => $chronique->isCalculee(),
            'timezone'   => $this->timezone,
            'unite'      => $this->getUnite($chronique),
        ];

        list($measures, $report) = $this->extractMeasures($repo, $chronique);

        $this->report = array_merge($this->report, $report);

        $this->formatter->start($chronique, $output, $this->report);

        foreach ($measures as $measure) {
            $this->writeMeasure($measure);
        }

        $formatterReport = $this->formatter->done();

        return array_merge($this->report, $formatterReport);
    }

    /**
     * @param Chronique $chronique
     *
     * @return string
     */
    protected function getUnite(Chronique $chronique)
    {
        return $chronique->getUnite();
    }

    /**
     * @param ChroniqueRepository $repo
     * @param Chronique           $chronique
     *
     * @return array
     */
    abstract protected function extractMeasures(ChroniqueRepository $repo, Chronique $chronique);

    /**
     * {@inheritdoc}
     */
    protected function writeMeasure(array $measure)
    {
        $this->formatter->writeMeasure(
            $measure['debut'] ?? $measure['date'],
            $measure['fin'] ?? null,
            $this->formatNumber($measure['valeur']),
            $measure['qualite'],
            isset($measure['minimum']) ? $this->formatNumber($measure['minimum']) : null,
            isset($measure['maximum']) ? $this->formatNumber($measure['maximum']) : null
        );
    }

    /**
     * La valeur est d'abord ramenée à 15 chiffres significatifs, comme le faisaient PHP 7 en
     * arrondissant et PostgreSQL 11 en écrivant ses float8 : sans cela, une valeur juste sous la
     * moitié (2.8674999999999997) s'écrirait 2.867 et non 2.868 (dev/differences.md).
     *
     * @param mixed $value
     *
     * @return string
     */
    private function formatNumber($value)
    {
        return is_numeric($value) ? number_format((float) sprintf('%.15h', $value), $this->precision, '.', '') : null;
    }
}
