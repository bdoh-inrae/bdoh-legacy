<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohDataBundle;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class IrsteaBdohDataBundle extends Bundle
{
    const LOCAL = 'fr_FR.UTF-8';

    /**
     * Modifies a string to remove all non ASCII characters and spaces.
     *
     * @param mixed $text
     */
    public static function slugify($text)
    {
        // replace non letter or digits by -
        $text = preg_replace('~[^\\pL\d]+~u', '-', $text);

        // trim
        $text = trim($text, '-');

        // transliterate
        // La langue règle la translittération d'iconv ; seule LC_CTYPE compte ici, LC_ALL changeait aussi
        // l'écriture des nombres sous PHP 7 (dev/differences.md, « Nombres écrits avec une virgule »).
        setlocale(LC_CTYPE, self::LOCAL);
        $text = iconv('utf-8', 'ascii//TRANSLIT', $text);

        // remove unwanted characters
        $text = preg_replace('~[^-\w]+~', '', $text);

        if (empty($text)) {
            return 'n-a';
        }

        return strtoupper($text);
    }

    /**
     * Uploading function :
     *  1) If $path points to an existing file (relative to $rootDir), removes it.
     *  2) Moves the $file in the directory "$rootDir.$destDir"
     *     with a new file name randomly generated or the provided one if any.
     *  3) Stores its new path (relative to $rootDir) in $path.
     *  4) Then, clears $file variable.
     *
     * @var string
     * @var string
     * @var \Symfony\Component\HttpFoundation\File\UploadedFile|null (reference)
     * @var string                                                   (reference)
     * @var string
     *
     * @param mixed      $rootDir
     * @param mixed      $destDir
     * @param mixed|null $fileName
     * @param mixed      $file
     * @param mixed      $path
     */
    public static function uploadFile($rootDir, $destDir, &$file, &$path, $fileName = null)
    {
        // Nothing to do without $file to upload !
        if (null === $file || !($file instanceof UploadedFile)) {
            return;
        }

        // Nothing to do without a directory where to upload !
        if (!$rootDir || !$destDir) {
            return;
        }

        // Removes file at $path
        self::removeFile($rootDir, $path);

        // New file name randomly generated
        if (!$extension = $file->guessExtension()) {
            $extension = 'bin';
        }
        if (null !== $fileName) {
            $newFileName = $fileName . '.' . $extension;
        } else {
            $newFileName = rand(1, 9999999) . '.' . $extension;
        }

        @$file->move($rootDir . $destDir, $newFileName);
        $path = $destDir . $newFileName;
        $file = null;
    }

    /**
     * Removes file at "$rootDir.$path" and clears $path.
     *
     * @var string
     * @var string (reference)
     *
     * @param mixed $rootDir
     * @param mixed $path
     */
    public static function removeFile($rootDir, &$path)
    {
        if ($path) {
            @unlink($rootDir . $path);
            $path = '';
        }
    }
}
