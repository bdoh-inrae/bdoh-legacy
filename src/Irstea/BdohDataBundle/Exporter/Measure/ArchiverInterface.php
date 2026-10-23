<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohDataBundle\Exporter\Measure;

interface ArchiverInterface
{
    /**
     * @param string      $path
     * @param string|null $name
     */
    public function addFile($path, $name = null);

    public function close();

    /**
     * @return string
     */
    public function getArchivePath();
}
