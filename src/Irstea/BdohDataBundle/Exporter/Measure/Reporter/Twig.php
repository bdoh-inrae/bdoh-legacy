<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohDataBundle\Exporter\Measure\Reporter;

use Irstea\BdohDataBundle\Entity\Chronique;
use Irstea\BdohDataBundle\Exporter\Measure\ReporterInterface;
use Twig\Environment;

/**
 * Class Twig.
 */
class Twig implements ReporterInterface
{
    /**
     * @var Environment
     */
    private $twig;

    /**
     * @var array
     */
    private $parameters = ['chroniques' => []];

    /**
     * Twig constructor.
     *
     * @param Environment $twig
     */
    public function __construct(Environment $twig)
    {
        $this->twig = $twig;
    }

    /**
     * {@inheritdoc}
     */
    public function addChronique(Chronique $chronique, array $report)
    {
        $this->parameters['chroniques'][] = ['chronique' => $chronique, 'report' => $report];
    }

    /**
     * {@inheritdoc}
     */
    public function addParameters(array $parameters)
    {
        $this->parameters = array_replace($this->parameters, $parameters);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function writeTo($resource)
    {
        $lines = explode("\n", $this->twig->render('@IrsteaBdohData/Exporter/report.text.twig', $this->parameters));
        $lines = array_map('rtrim', $lines);
        fwrite($resource, implode("\n", $lines));
    }
}
