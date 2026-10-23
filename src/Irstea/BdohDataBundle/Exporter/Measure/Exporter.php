<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohDataBundle\Exporter\Measure;

use Irstea\BdohDataBundle\Entity\Chronique;
use Irstea\BdohDataBundle\Entity\Observatoire;

class Exporter implements ExporterInterface
{
    /**
     * @var ExtractorInterface
     */
    private $extractor;

    /**
     * @var ReporterInterface
     */
    private $reporter;

    /**
     * @var ArchiverInterface
     */
    private $archiver;

    /**
     * @var string[]
     */
    private $tempFiles = [];

    /**
     * @var string
     */
    private $documentBasePath;

    /**
     * @var string
     */
    private $defaultTosPath;

    /**
     * @var string
     */
    private $formatDocPath;

    /** Set extractor.
     * @param ExtractorInterface $extractor
     *
     * @return self
     */
    public function setExtractor($extractor)
    {
        $this->extractor = $extractor;

        return $this;
    }

    /** Set reporter.
     * @param ReporterInterface $reporter
     *
     * @return self
     */
    public function setReporter($reporter)
    {
        $this->reporter = $reporter;

        return $this;
    }

    /** Set archiver.
     * @param ArchiverInterface $archiver
     *
     * @return self
     */
    public function setArchiver($archiver)
    {
        $this->archiver = $archiver;

        return $this;
    }

    /** Set documentBasePath.
     * @param string $documentBasePath
     *
     * @return self
     */
    public function setDocumentBasePath($documentBasePath)
    {
        $this->documentBasePath = $documentBasePath;

        return $this;
    }

    /** Set defaultTosPath.
     * @param string $defaultTosPath
     *
     * @return self
     */
    public function setDefaultTosPath($defaultTosPath)
    {
        $this->defaultTosPath = $defaultTosPath;

        return $this;
    }

    /** Set formatDocPath.
     * @param string $formatDocPath
     *
     * @return self
     */
    public function setFormatDocPath($formatDocPath)
    {
        $this->formatDocPath = $formatDocPath;

        return $this;
    }

    public function __destruct()
    {
        foreach ($this->tempFiles as $file) {
            @unlink($file);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function export(array $chroniques)
    {
        /** @var Observatoire $observatoire */
        $observatoire = $chroniques[0]->getObservatoire();

        $this->addTermsOfUse($observatoire);

        $this->addExportDoc();

        foreach ($chroniques as $chronique) {
            $this->addMeasures($chronique);
        }

        $this->addReport();

        $this->archiver->close();
    }

    /**
     * @param Observatoire $observatoire
     */
    private function addTermsOfUse(Observatoire $observatoire)
    {
        $observtoryTosPath = $observatoire->getConditionsUtilisation();
        $this->archiver->addFile(
            $this->documentBasePath . DIRECTORY_SEPARATOR . ($observtoryTosPath ?: $this->defaultTosPath)
        );
    }

    private function addExportDoc()
    {
        $this->archiver->addFile($this->documentBasePath . DIRECTORY_SEPARATOR . $this->formatDocPath);
    }

    /**
     * @param Chronique $chronique
     *
     * @return bool
     */
    private function addMeasures(Chronique $chronique)
    {
        $filename = sprintf('%s_%s.txt', $chronique->getStation()->getCode(), $chronique->getCode());

        return $this->generateFile(
            $filename,
            function ($output) use ($chronique, $filename) {
                try {
                    $report = $this->extractor->extract($chronique, $output);
                    $report['filename'] = $filename;
                } catch (\Exception $ex) {
                    $report = ['number' => 0, 'error' => $ex->getMessage()];
                }
                $this->reporter->addChronique($chronique, $report);

                return $report['number'] > 0;
            }
        );
    }

    /**
     * @return bool
     */
    private function addReport()
    {
        return $this->generateFile(
            'Report.txt',
            function ($output) {
                $this->reporter->writeTo($output);

                return true;
            }
        );
    }

    /**
     * @param string   $name
     * @param callable $callback
     *
     * @return bool
     */
    private function generateFile($name, callable $callback)
    {
        $path = tempnam(sys_get_temp_dir(), 'bdoh-exporter-');
        $this->tempFiles[] = $path;

        $handle = fopen($path, 'wb');
        try {
            $success = $callback($handle);
        } finally {
            fclose($handle);
        }
        if ($success) {
            $this->archiver->addFile($path, $name);
        }

        return $success;
    }
}
