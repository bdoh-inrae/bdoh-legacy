<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohBundle\Util;

/**
 * This class allows to send zip archive via Http protocol.
 */
class HttpZipResponse extends HttpFileResponse
{
    /**
     * Constructor.
     *
     * @param string $basePath    Directory in which write the zip archive
     * @param string $zipName     Name of the zip archive
     * @param array  $files       Paths of files to zip
     * @param bool   $removeFiles If true, the files will be removed after zipping
     */
    public function __construct($basePath, $zipName, $files, $removeFiles = false)
    {
        $zipPath = $basePath . '/' . $zipName . '.zip';
        $zip = new \ZipArchive();

        // Creates zip archive
        if (true !== $zip->open($zipPath, \ZipArchive::CREATE)) {
            throw new \RuntimeException("Unable to create zip archive : $zipPath");
        }

        // Adds a main folder
        $zip->addEmptyDir($zipName);

        // Adds each file in it
        foreach ($files as $file) {
            //Verify if file exists to prevent the close() to fail if ever a file were to be missing
            if (file_exists($file)) {
                $fileName = str_replace('//', '/', $zipName . '/' . basename($file));
                $zip->addFile($file, $fileName);
            }
        }

        // Saves zip archive
        if (false === $zip->close()) {
            throw new \RuntimeException("Unable to save zip archive : $zipPath");
        }

        // Removes all files, if asked
        if ($removeFiles) {
            foreach ($files as $file) {
                @unlink($file);
            }
        }

        parent::__construct($zipPath, 'application/zip', true);
    }
}
