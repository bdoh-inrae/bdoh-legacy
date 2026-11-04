<?php
// SPDX-FileCopyrightText: The BDOH authors
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace Irstea\BdohAdminBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;

class IrsteaBdohAdminBundle extends Bundle
{
    /**
     * Extracts the first file of a zip archive in a destination directory, under a name of its own: an import
     * reads its file at a later step, and two archives holding a file of the same name would overwrite each
     * other in between (dev/differences.md, « Archives de même nom »).
     *
     * @param string $zipPath
     * @param mixed  $destDir
     *
     * @return string|null Extracted file path OR null if an error occurred
     */
    public static function unzipFirst($zipPath, $destDir = '/tmp')
    {
        $zip = new \ZipArchive();
        $firstFile = null;

        // Opens the zip archive
        if (true === $zip->open($zipPath)) {
            $name = $zip->getNameIndex(0);
            if (false !== $name && str_ends_with($name, '/')) {
                // A directory first: the directory itself, a type which the caller refuses
                $firstFile = $destDir;
            } elseif (false !== $name) {
                $firstFile = self::copyEntry($zip, 0, $destDir);
            }
            $zip->close();
        }

        return $firstFile;
    }

    /**
     * @return string|null the new file, complete and checked against the archive, OR null
     */
    private static function copyEntry(\ZipArchive $zip, int $index, string $destDir): ?string
    {
        $stat = $zip->statIndex($index);
        $source = $zip->getStreamIndex($index);
        $path = tempnam($destDir, 'unzip_');
        $target = false !== $path ? fopen($path, 'w') : false;
        $copied = $source && $target ? stream_copy_to_stream($source, $target) : false;
        if ($target) {
            fclose($target);
        }
        if ($source) {
            fclose($source);
        }

        if ($stat && $copied === $stat['size'] && hash_file('crc32b', $path) === sprintf('%08x', $stat['crc'])) {
            return $path;
        }
        if (false !== $path) {
            unlink($path);
        }

        return null;
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
