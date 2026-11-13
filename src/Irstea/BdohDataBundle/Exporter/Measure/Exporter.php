<?php
// SPDX-FileCopyrightText: The BDOH authors
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace Irstea\BdohDataBundle\Exporter\Measure;

use Irstea\BdohDataBundle\Entity\Chronique;
use Irstea\BdohDataBundle\Entity\Observatoire;
use Irstea\BdohDataBundle\Exporter\Measure\Csv\Metadata;
use Irstea\BdohDataBundle\Exporter\Measure\Csv\Tables;

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

    /** @var string le format, en minuscules : bdoh, qtvar ou csv */
    private $format = 'bdoh';

    /** @var Metadata|null les métadonnées en CSV, pour ce format seulement */
    private $metadata;

    /** @var string[] les fichiers de l'archive, dans l'ordre où ils y entrent, pour le README */
    private $names = [];

    /** @var array<string, array> ce qu'a dit l'extraction de chaque chronique, par identifiant */
    private $reports = [];

    public function setFormat(string $format): self
    {
        $this->format = strtolower($format);

        return $this;
    }

    public function setMetadata(?Metadata $metadata): self
    {
        $this->metadata = $metadata;

        return $this;
    }

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

        // La description des formats BDOH et Qtvar ; celle du CSV est dans le README
        if ($this->format !== 'csv') {
            $this->addExportDoc();
        }

        foreach ($chroniques as $chronique) {
            $this->addMeasures($chronique);
        }

        if ($this->metadata !== null) {
            $this->addMetadata($chroniques, $observatoire);
        }

        $this->addReadme($chroniques);

        $this->archiver->close();
    }

    /**
     * Les chroniques, leurs stations et les codes de qualité, chacun dans son CSV, puis leur description CSVW.
     *
     * @param Chronique[] $chroniques
     */
    private function addMetadata(array $chroniques, Observatoire $observatoire)
    {
        $this->generateFile(Tables::SERIES_FILE, function ($output) use ($chroniques) {
            $this->metadata->writeSeries($chroniques, $this->reports, $output);

            return true;
        });
        $this->generateFile(Tables::STATION_FILE, function ($output) use ($chroniques) {
            $this->metadata->writeStations($chroniques, $output);

            return true;
        });
        $this->generateFile(Tables::QUALITY_FILE, function ($output) use ($observatoire) {
            $this->metadata->writeQualities($observatoire->getJeu(), $output);

            return true;
        });
        $measureFiles = [];
        foreach ($chroniques as $chronique) {
            if (isset($this->reports[$chronique->getId()]['filename'])) {
                $measureFiles[$this->reports[$chronique->getId()]['filename']] = (bool) $chronique->getEstVisible();
            }
        }
        $this->generateFile(Tables::CSVW_FILE, function ($output) use ($measureFiles, $observatoire) {
            return false !== fwrite($output, $this->metadata->csvw(
                $measureFiles,
                'BDOH, ' . $observatoire->getNom(),
                'https://www.data.gouv.fr/datasets/licence-ouverte-2-0'
            ));
        });
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
                $this->add($this->documentBasePath . DIRECTORY_SEPARATOR . $file);
            }
        }
        if ($hidden) {
            $this->add($this->documentBasePath . DIRECTORY_SEPARATOR . $this->defaultTosPath);
        }
        if ($visible && $hidden) {
            $names = fn (array $list) => implode("\n", array_map(fn (Chronique $chronique) => '- ' . $chronique, $list));
            $this->generateFile('Licences.txt', function ($output) use ($names, $visible, $hidden) {
                return false !== fwrite($output, "Licence Ouverte 2.0 (Licence-Ouverte-2.0-fr.pdf, Open-Licence-2.0-en.pdf) :\n" . $names($visible)
                    . "\n\nConditions générales d'utilisation (" . basename($this->defaultTosPath) . ") :\n" . $names($hidden) . "\n");
            });
        }
    }

    private function addExportDoc()
    {
        $this->add($this->documentBasePath . DIRECTORY_SEPARATOR . $this->formatDocPath);
    }

    /**
     * @param Chronique $chronique
     *
     * @return bool
     */
    private function addMeasures(Chronique $chronique)
    {
        $filename = sprintf('%s_%s.%s', $chronique->getStation()->getCode(), $chronique->getCode(), $this->format === 'csv' ? 'csv' : 'txt');

        return $this->generateFile(
            $filename,
            function ($output) use ($chronique, $filename) {
                try {
                    $report = $this->extractor->extract($chronique, $output);
                    $report['filename'] = $filename;
                } catch (\Exception $ex) {
                    $report = ['number' => 0, 'error' => $ex->getMessage()];
                }
                if ($report['number'] <= 0) {
                    unset($report['filename']);
                }
                $this->reports[$chronique->getId()] = $report;
                $this->reporter->addChronique($chronique, $report);

                return $report['number'] > 0;
            }
        );
    }

    /**
     * Le README, qui remplace l'ancien Report.txt (dev/plans/2026-11-13_fair-exports.md) : ce que disait le rapport,
     * et ce qu'il faut pour réutiliser les données sans rien d'autre (licence, citation, fichiers, colonnes).
     *
     * @param Chronique[] $chroniques
     *
     * @return bool
     */
    private function addReadme(array $chroniques)
    {
        $visible = array_values(array_filter($chroniques, fn (Chronique $chronique) => $chronique->getEstVisible()));
        $files = [...$this->names, 'README.txt'];
        sort($files, SORT_STRING | SORT_FLAG_CASE);
        $this->reporter->addParameters([
            'files'          => $files,
            'openFiles'      => $visible ? array_map('basename', $this->openLicenceFiles) : [],
            'termsFile'      => count($visible) < count($chroniques) ? basename($this->defaultTosPath) : null,
            'formatDocFile'  => $this->format !== 'csv' ? basename($this->formatDocPath) : null,
            'visibleCount'   => count($visible),
            'hiddenCount'    => count($chroniques) - count($visible),
            'tables'         => $this->format === 'csv' ? Tables::all() : [],
            'missing'        => Tables::MISSING,
        ]);

        return $this->generateFile(
            'README.txt',
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
            $this->add($path, $name);
        }

        return $success;
    }

    /** Un fichier de plus dans l'archive, dont le README donnera le nom. */
    private function add(string $path, ?string $name = null)
    {
        $this->archiver->addFile($path, $name);
        $this->names[] = $name ?? basename($path);
    }
}
