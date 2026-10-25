<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohAdminBundle\Manager;

use Symfony\Component\HttpFoundation\ParameterBag;

/**
 * Manages the import of shape files.
 */
class ShapeImportManager
{
    /**
     * @var string
     */
    protected $appAbsolutePath;

    /**
     * @var string
     */
    protected $directory;

    /**
     * @var array
     */
    protected $mimeTypes;

    /**
     * @var Symfony\Component\HttpFoundation\ParameterBag
     */
    public $importers;

    /**
     * Constructor.
     *
     * @param string $appAbsolutePath
     * @param array  $shapeImportParams See src/Irstea//BdohAdminBundle/Resources/config/services.yml
     */
    public function __construct($appAbsolutePath, array $shapeImportParams)
    {
        $this->appAbsolutePath = $appAbsolutePath;
        $this->directory = $shapeImportParams['directory'];
        $this->mimeTypes = $shapeImportParams['mimeTypes'];
        $this->importers = new ParameterBag($shapeImportParams['importers']);
    }

    /**
     * Checks that given mime type is valid for given category.
     * If no category given, checks that it is valid for ALL categories.
     *
     * @param string $mime
     * @param string $category
     *
     * @return bool
     */
    public function isValidMimeType($mime, $category = '')
    {
        if ($category && array_key_exists($category, $this->mimeTypes)) {
            return in_array($mime, $this->mimeTypes[$category]);
        }
        foreach ($this->mimeTypes as $mimeTypes) {
            if (in_array($mime, $mimeTypes)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Returns directory containing shape files to import.
     *
     * @return string Absolute path
     */
    public function getDir()
    {
        return $this->appAbsolutePath . DIRECTORY_SEPARATOR . $this->directory;
    }

    /**
     * Returns sorted array of shape files to import.
     *
     * @return array
     */
    public function getFiles()
    {
        $files = [];
        $all = scandir($dirname = $this->getDir());

        foreach ($all as $e) {
            $e = $dirname . $e;
            if (is_file($e)) {
                $files[] = $e;
            }
        }

        return $files;
    }
}
