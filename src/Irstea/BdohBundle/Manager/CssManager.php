<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohBundle\Manager;

use Irstea\BdohDataBundle\Entity\Observatoire;

/**
 * This class allows to :
 *   => load the 'observatoire' context ;
 *   => manage the current 'observatoire' ;
 *   => manage the css file of an 'observatoire' ;.
 */
class CssManager
{
    /**
     * @var array
     */
    protected $files;

    /**
     * @var string
     */
    protected $cssPath;

    /**
     * @var string
     */
    protected $webRoot;

    /**
     * @var array
     */
    protected $options;

    /**
     * @var array
     */
    protected $variables;

    /**
     * @var bool
     */
    protected $debug;

    /**
     * @var bool
     */
    protected $failsafe;

    /**
     * @param array  $files
     * @param string $cssPath
     * @param string $webRoot
     * @param array  $options
     * @param array  $variables
     * @param mixed  $debug
     */
    public function __construct(array $files, $cssPath, $webRoot, array $options, array $variables = [], $debug = false)
    {
        $this->files = $files;
        $this->cssPath = $cssPath;
        $this->webRoot = $webRoot;
        $this->options = $options;
        $this->variables = $variables;
        $this->debug = $debug;
    }

    /**
     * Gets CSS file path, relative to the web directory.
     * If this file doesn't exist, creates it !
     *
     * @param Observatoire|null $obs
     *
     * @return string
     */
    public function getObservatoireCssFile(?Observatoire $obs = null)
    {
        list($webPath, $localPath) = $this->getPaths($obs);
        if ($this->debug || !file_exists($localPath)) {
            $this->updateObservatoireCssFile($obs);
        }

        return $webPath;
    }

    /**
     * Gets CSS file path, relative to the web directory.
     * If this file doesn't exist, creates it !
     *
     * @param Observatoire|null $obs
     *
     * @throws \Less_Exception_Parser
     */
    public function updateObservatoireCssFile(?Observatoire $obs = null)
    {
        list(, $localPath) = $this->getPaths($obs);

        $opts = $this->options;
        $opts['output'] = $localPath;
        $opts['compress'] = !$this->debug;

        try {
            \Less_Cache::Get($this->files, $opts, $this->getVariables($obs));
        } catch (\Less_Exception_Parser $ex) {
            $ex->genMessage();
            file_put_contents($localPath, sprintf("/* LESS compilation error:\n\n%s\n*/", $ex->getMessage()));
            if (!$this->failsafe) {
                $this->failsafe = true;
                throw $ex;
            }
        }
    }

    /**
     * @param Observatoire|null $obs
     */
    public function removeObservatoireCssFile(?Observatoire $obs = null)
    {
        list(, $localPath) = $this->getPaths($obs);
        if (file_exists($localPath)) {
            unlink($localPath);
        }
    }

    /**
     * @param Observatoire|null $obs
     *
     * @return array
     */
    private function getVariables(?Observatoire $obs = null)
    {
        $vars = $this->variables;
        if ($obs !== null) {
            $vars['primaryColor'] = $obs->getCouleurPrimaire();
            $vars['secondaryColor'] = $obs->getCouleurSecondaire();
        }

        return $vars;
    }

    /**
     * @param Observatoire|null $obs
     *
     * @return array [string, string]
     */
    private function getPaths(?Observatoire $obs = null)
    {
        $slug = $obs !== null ? $obs->getSlug() : 'home';
        $webPath = $this->cssPath . $slug . ($this->debug ? '' : '.min') . '.css';
        $localPath = $this->webRoot . $webPath;

        return [$webPath, $localPath];
    }
}
