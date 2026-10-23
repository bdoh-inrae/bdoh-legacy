<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohDataBundle\Exporter\Measure\Archiver;

use Irstea\BdohDataBundle\Exporter\Measure\ArchiverInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ConsoleArchiver implements ArchiverInterface
{
    /**
     * @var OutputInterface
     */
    private $output;

    /**
     * ConsoleArchiver constructor.
     *
     * @param OutputInterface $output
     */
    public function __construct(OutputInterface $output)
    {
        $this->output = $output;
    }

    /**
     * {@inheritdoc}
     */
    public function addFile($path, $name = null)
    {
        if ($name === null) {
            $name = basename($path);
        }
        if (substr($name, strlen($name) - 4) === '.pdf') {
            $this->output->writeln("\n<info># Ignoring $name</info>\n");

            return;
        }
        $this->output->writeln("\n<info># Printing $name from $path</info>\n");
        $handle = fopen($path, 'rb');
        try {
            while ($line = fgets($handle)) {
                $this->output->write($line);
            }
        } finally {
            fclose($handle);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function close()
    {
    }

    /**
     * {@inheritdoc}
     */
    public function getArchivePath()
    {
        return '/dev/null';
    }
}
