<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohAdminBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;

class IrsteaBdohAdminBundle extends Bundle
{
    /**
     * Extracts the first file of a zip archive in a destination directory.
     *
     * @param string $zipPath
     * @param mixed  $destDir
     *
     * @parap string $destDir
     *
     * @return string|null Extracted file path OR null if an error occurred
     */
    public static function unzipFirst($zipPath, $destDir = '/tmp')
    {
        $zip = new \ZipArchive();
        $firstFile = null;

        // Opens the zip archive
        if (true === $zip->open($zipPath)) {
            // Gets the first file name
            if ($firstFile = $zip->getNameIndex(0)) {
                // Extracts this first file
                $firstFile = $zip->extractTo($destDir, $firstFile) ?
                    $destDir . DIRECTORY_SEPARATOR . $firstFile :
                    null;
            } else {
                $firstFile = null;
            }
            $zip->close();
        }

        return $firstFile;
    }

    /**
     * Extracts the file of a zip archive in a destination directory.
     *
     * @param string $zipPath
     * @param mixed  $destDir
     *
     * @parap string $destDir
     *
     * @return string|null Extracted file path OR null if an error occurred
     */
    public static function unzipAll($zipPath, $destDir = '/tmp')
    {
        $zip = new \ZipArchive();

        // Opens the zip archive
        if (true === $zip->open($zipPath)) {
            // Extracts this first file
            $zip->extractTo($destDir);
            $zip->close();

            return true;
        }

        return false;
    }
}
