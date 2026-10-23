<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

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
