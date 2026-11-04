<?php
// SPDX-FileCopyrightText: The BDOH authors
// SPDX-License-Identifier: AGPL-3.0-or-later

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
     * @var string[]
     */
    private $openLicenceFiles = [];

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

    /**
     * @param string[] $openLicenceFiles
     *
     * @return $this
     */
    public function setOpenLicenceFiles(array $openLicenceFiles)
    {
        $this->openLicenceFiles = $openLicenceFiles;

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

        $this->addLicences($chroniques, $observatoire);

        $this->addExportDoc();

        foreach ($chroniques as $chronique) {
            $this->addMeasures($chronique);
        }

        $this->addReport();

        $this->archiver->close();
    }

    /**
     * La licence suit les chroniques de l'archive : Licence Ouverte pour une visible, conditions d'utilisation pour
     * une cachée, les deux, et la liste de ce qui relève de chacune, pour une archive mêlée (dev/decisions.md,
     * « Ouverture des données »).
     *
     * @param Chronique[] $chroniques
     */
    private function addLicences(array $chroniques, Observatoire $observatoire)
    {
        $visible = array_filter($chroniques, fn (Chronique $chronique) => $chronique->getEstVisible());
        $hidden = array_filter($chroniques, fn (Chronique $chronique) => !$chronique->getEstVisible());

        if ($visible) {
            foreach ($this->openLicenceFiles as $file) {
                $this->archiver->addFile($this->documentBasePath . DIRECTORY_SEPARATOR . $file);
            }
        }
        if ($hidden) {
            $this->addTermsOfUse($observatoire);
        }
        if ($visible && $hidden) {
            $names = fn (array $list) => implode("\n", array_map(fn (Chronique $chronique) => '- ' . $chronique, $list));
            $this->generateFile('Licences.txt', function ($output) use ($names, $visible, $hidden) {
                return false !== fwrite($output, "Licence Ouverte 2.0 (Licence-Ouverte-2.0-fr.pdf, Open-Licence-2.0-en.pdf) :\n" . $names($visible)
                    . "\n\nConditions générales d'utilisation (" . basename($this->defaultTosPath) . ") :\n" . $names($hidden) . "\n");
            });
        }
    }

    /**
     * Les conditions propres à l'observatoire, si leur fichier est là, sinon celles de BDOH : un fichier déposé
     * ne vit que sur le serveur, jamais dans le dépôt.
     */
    private function addTermsOfUse(Observatoire $observatoire)
    {
        $observatoryTos = $observatoire->getConditionsUtilisation();
        $path = $this->documentBasePath . DIRECTORY_SEPARATOR . $observatoryTos;
        $this->archiver->addFile(
            $observatoryTos && is_file($path) ? $path : $this->documentBasePath . DIRECTORY_SEPARATOR . $this->defaultTosPath
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
