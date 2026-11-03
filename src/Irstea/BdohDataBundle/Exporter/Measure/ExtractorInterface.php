<?php
// SPDX-FileCopyrightText: The BDOH authors
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace Irstea\BdohDataBundle\Exporter\Measure;

use Irstea\BdohDataBundle\Entity\Chronique;

interface ExtractorInterface
{
    /**
     * @param Chronique $chronique
     * @param resource  $output
     *
     * @return array
     */
    public function extract(Chronique $chronique, $output);

    /**
     * @param Chronique $chronique
     *
     * @return bool
     */
    public function supportsChronique(Chronique $chronique);

    /**
     * @param FormatterInterface $formatter
     *
     * @return bool
     */
    public function supportsFormatter(FormatterInterface $formatter);
}
