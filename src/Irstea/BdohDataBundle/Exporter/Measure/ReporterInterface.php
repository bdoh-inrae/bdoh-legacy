<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohDataBundle\Exporter\Measure;

use Irstea\BdohDataBundle\Entity\Chronique;

interface ReporterInterface
{
    /**
     * @param Chronique $chronique
     * @param array     $report
     */
    public function addChronique(Chronique $chronique, array $report);

    /**
     * @param array $parameters
     *
     * @return self
     */
    public function addParameters(array $parameters);

    /**
     * @param resource $resource
     */
    public function writeTo($resource);
}
