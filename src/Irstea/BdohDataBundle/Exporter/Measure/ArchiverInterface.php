<?php
// SPDX-FileCopyrightText: The BDOH authors
// SPDX-License-Identifier: AGPL-3.0-or-later

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
