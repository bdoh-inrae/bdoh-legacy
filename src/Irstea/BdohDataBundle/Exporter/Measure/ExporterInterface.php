<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohDataBundle\Exporter\Measure;

interface ExporterInterface
{
    /**
     * @param array $chroniques
     */
    public function export(array $chroniques);
}
