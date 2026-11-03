<?php
// SPDX-FileCopyrightText: The BDOH authors
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace Irstea\BdohDataBundle\Exporter\Measure;

interface ExporterInterface
{
    /**
     * @param array $chroniques
     */
    public function export(array $chroniques);
}
