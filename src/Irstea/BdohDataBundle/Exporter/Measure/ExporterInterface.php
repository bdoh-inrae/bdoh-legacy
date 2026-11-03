<?php

namespace Irstea\BdohDataBundle\Exporter\Measure;

interface ExporterInterface
{
    /**
     * @param array $chroniques
     */
    public function export(array $chroniques);
}
