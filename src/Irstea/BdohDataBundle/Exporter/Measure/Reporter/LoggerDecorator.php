<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohDataBundle\Exporter\Measure\Reporter;

use Irstea\BdohDataBundle\Entity\Chronique;
use Irstea\BdohDataBundle\Entity\PasEchantillonnage;
use Irstea\BdohDataBundle\Exporter\Measure\ReporterInterface;
use Irstea\BdohLoggerBundle\Logger\BdohLogger;

/**
 * Class LoggerDecorator.
 */
class LoggerDecorator implements ReporterInterface
{
    /**
     * @var BdohLogger
     */
    private $logger;

    /**
     * @var ReporterInterface
     */
    private $inner;

    /**
     * @var array
     */
    private $parameters = [];

    /**
     * LoggerDecorator constructor.
     *
     * @param BdohLogger        $logger
     * @param ReporterInterface $inner
     */
    public function __construct(BdohLogger $logger, ReporterInterface $inner)
    {
        $this->logger = $logger;
        $this->inner = $inner;
    }

    /**
     * {@inheritdoc}
     */
    public function addChronique(Chronique $chronique, array $report)
    {
        $this->inner->addChronique($chronique, $report);

        if (empty($report['number'])) {
            return;
        }

        $timestep = ['libelle' => '', 'libelleEn' => ''];
        if (isset($this->parameters['timestep'])) {
            if (is_integer($this->parameters['timestep'])) {
                $minutes = ' minute';
                if ($this->parameters['timestep'] > 1) {
                    $minutes = ' minutes';
                }
                $timestep['libelle'] = $this->parameters['timestep'] . $minutes;
                $timestep['libelleEn'] = $this->parameters['timestep'] . $minutes;
            } elseif ($this->parameters['timestep'] instanceof PasEchantillonnage) {
                $timestep['libelle'] = $this->parameters['timestep']->getLibelle();
                $timestep['libelleEn'] = $this->parameters['timestep']->getLibelleEn();
            }
        }

        $this->logger->createHistoriqueDonneesExport(
            $this->parameters['user'],
            $this->parameters['date'],
            $chronique->getObservatoire(),
            $chronique,
            $this->parameters['begin']->format(\DateTime::ATOM),
            $this->parameters['end']->format(\DateTime::ATOM),
            $report['number'],
            $this->parameters['format'],
            $this->parameters['extractor'],
            \json_encode($timestep),
            $this->parameters['timezone']->getName()
        );
    }

    /**
     * {@inheritdoc}
     */
    public function addParameters(array $parameters)
    {
        $this->parameters = array_merge($this->parameters, $parameters);
        $this->inner->addParameters($parameters);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function writeTo($resource)
    {
        $this->inner->writeTo($resource);
    }
}
