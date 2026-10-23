<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohDataBundle\Exporter\Measure\Archiver;

use Irstea\BdohDataBundle\Exporter\Measure\ArchiverInterface;
use RuntimeException;

class ZipArchiver implements ArchiverInterface
{
    /**
     * @var string
     */
    private $filePath;

    /**
     * @var \ZipArchive
     */
    private $archive;

    /**
     * ZipArchiver constructor.
     *
     * @param string $filePath
     */
    public function __construct($filePath = null)
    {
        $this->filePath = $filePath ?: tempnam(sys_get_temp_dir(), 'bdoh-archive-');
        @unlink($this->filePath);

        $this->archive = new \ZipArchive();

        $error = $this->archive->open($this->filePath, \ZipArchive::CREATE | \ZipArchive::EXCL);
        if (true !== $error) {
            throw new RuntimeException(sprintf('%s: %d', $this->filePath, $error));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function addFile($path, $name = null)
    {
        if ($name === null) {
            $name = basename($path);
        }
        if (!$this->archive->addFile($path, $name)) {
            throw new RuntimeException(
                sprintf('Adding %s (%s) to %s: %s', $path, $name, $this->filePath, $this->archive->getStatusString())
            );
        }
    }

    /**
     * {@inheritdoc}
     */
    public function close()
    {
        $this->archive->close();
    }

    /**
     * {@inheritdoc}
     */
    public function getArchivePath()
    {
        return $this->filePath;
    }
}
