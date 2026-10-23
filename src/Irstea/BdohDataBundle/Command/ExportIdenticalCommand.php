<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohDataBundle\Command;

use JMS\DiExtraBundle\Annotation as DI;

/**
 * Class ExportIdenticalCommand.
 *
 * @DI\Service()
 * @DI\Tag("console.command")
 */
class ExportIdenticalCommand extends AbstractExportCommand
{
    const EXTRACTOR_TYPE = 'identical';
}
