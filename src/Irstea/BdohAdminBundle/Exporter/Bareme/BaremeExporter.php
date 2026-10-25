<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohAdminBundle\Exporter\Bareme;

use Doctrine\ORM\EntityManagerInterface;
use Irstea\BdohDataBundle\Entity\Observatoire;
use Irstea\BdohDataBundle\Exporter\Exception\StopExportException;
use Irstea\BdohDataBundle\IrsteaBdohDataBundle;
use Symfony\Contracts\Translation\TranslatorInterface;

class BaremeExporter
{
    const GAP_VALUE = '-9999';

    /***************************************************************************
     * Some dependencies of this class, and their getter
     **************************************************************************/

    private $em;

    private $translator;

    /**
     * Manager of 'qualites' translation.
     *
     * @var Irstea\BdohDataBundle\Util\JeuToJeu
     */
    private $ordreToCode;

    /***************************************************************************
     * Some format specific attributes, and their getter.
     * Each attribute could be or should be (if no default value) set in the specific exporters.
     **************************************************************************/

    /**
     * Name of a specific format : used in the report.
     * It makes doubloon with that in src/Irstea/BdohDataBundle/Resources/config/services.yml : sorry :-(.
     */
    private $formatName;

    // Field delimiter
    private $delimiter = ';';

    /***************************************************************************
     * Attributes and their getters, about generated directory and files.
     **************************************************************************/

    // Path of the directory containing all generated files
    private $dirPath;

    // File containing the Terms of Uses
    private $termsOfUsesPath;

    public function getTermsOfUsesPath()
    {
        return $this->termsOfUsesPath;
    }

    // File containing the report of export, and its path
    private $report;

    private $reportPath;

    public function getReportPath()
    {
        return $this->reportPath;
    }

    // Files of measures (one by successful export)
    private $baremeFiles = [];

    public function getBaremeFiles()
    {
        return $this->baremeFiles;
    }

    // 'Bareme' currently exported, and its measures file
    private $bareme;

    public function getBareme()
    {
        return $this->bareme;
    }

    private $file;

    private $filePath;

    // Information about the current measures selection
    private $infoSelection;

    public function getInfoSelection()
    {
        return $this->infoSelection;
    }

    // Useful little functions
    private function getStringDate($date)
    {
        if ($date) {
            return (is_string($date)) ? $date : $date->format($this->translator->trans('transformation.bareme.dateBareme'));
        }

        return $this->translator->trans('noneFeminine');
    }

    private function getUnitLabel($unit)
    {
        return ($unit) ? $unit->getLibelle() : $this->translator->trans('noneFeminine');
    }

    /***************************************************************************
     * Other attributes and methods
     **************************************************************************/

    public function __construct(EntityManagerInterface $em, TranslatorInterface $translator, $childChronicle)
    {
        $this->em = $em;
        $this->translator = $translator;
        $observatoire = Observatoire::getCurrent();

        // Prepares the manager of 'qualites' translation
        $this->ordreToCode = $this->getBdohRepo('Qualite')->securedOrdresToCodes();

        // Creates a directory, randomly named
        if (!mkdir($this->dirPath = sys_get_temp_dir() . '/' . rand() . '/')) {
            throw new StopExportException();
        }

        // Retrieves the quality set name
        $this->formatName = $observatoire->getJeu()->getNom();

        // Creates and fills "Terms of Uses" file
        $this->termsOfUsesPath = $this->dirPath . 'TermsOfUses.txt';

        $termsOfUses = strip_tags(html_entity_decode($observatoire->getConditionsUtilisation(), ENT_QUOTES, 'UTF-8'));
        if (!$termsOfUses) {
            $termsOfUses = $translator->trans('TermsOfUses.none');
        }

        if (!file_put_contents($this->termsOfUsesPath, $termsOfUses)) {
            throw new StopExportException();
        }

        // Creates and fills report file
        $this->reportPath = $this->dirPath . 'Report.txt';

        if (!($this->report = fopen($this->reportPath, 'w'))) {
            throw new StopExportException();
        }

        // Main line
        fwrite(
            $this->report,
            $translator->trans(
                'bareme.export.report.main(%format%, %date%, %chronicle%)',
                [
                    '%format%'    => $this->formatName,
                    '%date%'      => (new \DateTime('now', new \DateTimeZone('UTC')))
                        ->format($this->translator->trans('transformation.bareme.dateBareme')),
                    '%chronicle%' => $childChronicle,
                ]
            )
        );
    }

    /**
     * Destructor : removes all generated files and directory.
     */
    public function __destruct()
    {
        fclose($this->report);

        @unlink($this->termsOfUsesPath);
        @unlink($this->reportPath);
        foreach ($this->baremeFiles as $file) {
            @unlink($file);
        }

        @rmdir($this->dirPath);
    }

    /**
     * Proceeds to the export of a given 'chronique'.
     *
     * @param array Array containing a bareme ID and, optionally, a parent chronicle ID, a start date and an end date
     * @param mixed $baremeData
     *
     * @return string returns the path of a file containing the exported measures
     */
    public function run($baremeData)
    {
        // Retrieves the 'chronique' entity from its id.
        $this->bareme = $bareme = $this->getBdohRepo('Bareme')->findOneById($baremeData[0]);
        if (!$bareme) {
            fwrite(
                $this->report,
                $this->translator->trans(
                    'bareme.export.error.badBareme(%$baremeId%)',
                    ['%$baremeId%' => $baremeData[0]]
                )
            );
            throw new StopExportException();
        }

        // Creates the data file
        $fileName = sprintf(
            '%s_%s.txt',
            IrsteaBdohDataBundle::slugify($bareme->getNom()),
            $bareme->getDateCreation()->format('Y-m-d_H-i-s')
        );
        $this->filePath = $filePath = $this->dirPath . $fileName;

        if (!($this->file = $file = fopen($filePath, 'w'))) {
            fwrite(
                $this->report,
                $this->translator->trans(
                    'Error.file.cantCreate(%file%)',
                    ['%file%' => $filePath]
                )
            );
            throw new StopExportException();
        }

        // Writes the file header
        $this->writeHeader();

        foreach ($bareme->getValeurs() as $line) {
            $this->writeBaremeLine($line);
        }

        $nData = sizeof($baremeData);
        if ($nData > 1) {
            $parentChronicle = $this->getBdohRepo('ChroniqueContinue')->findOneById($baremeData[1]);
        } else {
            $parentChronicle = null;
        }

        $values = $bareme->getValeurs();
        $n = sizeof($values);
        $this->infoSelection = [
            'name'            => $bareme->getNom(),
            'dateCreation'    => $bareme->getDateCreation(),
            'inputUnit'       => $this->getUnitLabel($bareme->getUniteEntree()),
            'outputUnit'      => $this->getUnitLabel($bareme->getUniteSortie()),
            'number'          => $n,
            'min'             => $values[0][0],
            'max'             => $values[$n - 1][0],
            'parentChronicle' => ($parentChronicle) ? $parentChronicle->__toString() : null,
            'dateBegin'       => ($nData > 2) ? $this->getStringDate($baremeData[2]) : null,
            'dateEnd'         => ($nData > 3 && $baremeData[3]) ? $this->getStringDate($baremeData[3]) : null,
        ];

        // Writes main report line about the 'chronique' export
        $this->writeMainReportLine();

        // Saves the measures file path, and returns it
        fclose($file);

        return $this->baremeFiles[] = $filePath;
    }

    /**
     * Writes some data in one line of the current file.
     *
     * @param mixed $data
     */
    protected function writeLine($data)
    {
        fwrite($this->file, implode($this->delimiter, $data) . "\n");
    }

    /**
     * Writes the main report line, about export of current 'chronique'.
     */
    protected function writeMainReportLine()
    {
        fwrite(
            $this->report,
            $this->translator->trans(
                'bareme.export.report.bareme.base(%bareme%, %dateCreation%, %inputUnit%, %outputUnit%, %number%, %min%, %max%)',
                [
                    '%bareme%'       => $this->infoSelection['name'],
                    '%dateCreation%' => $this->getStringDate($this->infoSelection['dateCreation']),
                    '%inputUnit%'    => $this->infoSelection['inputUnit'],
                    '%outputUnit%'   => $this->infoSelection['outputUnit'],
                    '%number%'       => $this->infoSelection['number'],
                    '%min%'          => $this->infoSelection['min'],
                    '%max%'          => $this->infoSelection['max'],
                ]
            )
        );

        if ($this->infoSelection['parentChronicle']) {
            fwrite(
                $this->report,
                $this->translator->trans(
                    'bareme.export.report.bareme.parentChronicle(%parentChronicle%)',
                    ['%parentChronicle%' => $this->infoSelection['parentChronicle']]
                )
            );
            if ($this->infoSelection['dateEnd']) {
                fwrite(
                    $this->report,
                    $this->translator->trans(
                        'bareme.export.report.bareme.applyFromTo(%dateBegin%, %dateEnd%)',
                        [
                            '%dateBegin%' => $this->infoSelection['dateBegin'],
                            '%dateEnd%'   => $this->infoSelection['dateEnd'],
                        ]
                    )
                );
            } else {
                fwrite(
                    $this->report,
                    $this->translator->trans(
                        'bareme.export.report.bareme.applyFrom(%dateBegin%)',
                        [
                            '%dateBegin%' => $this->infoSelection['dateBegin'],
                        ]
                    )
                );
            }
        } else {
            fwrite($this->report, $this->translator->trans('bareme.export.report.bareme.notApplied'));
        }

        fwrite(
            $this->report,
            $this->translator->trans(
                'bareme.export.report.bareme.outputFile(%file%)',
                ['%file%' => basename($this->filePath)]
            )
        );
    }

    /**
     * Shortcut to return a Doctrine repository.
     *
     * @param string $entity
     */
    private function getRepository($entity)
    {
        return $this->em->getRepository($entity);
    }

    /**
     *  Shortcut to return a Doctrine repository of a BDOH entity.
     *
     * @param string $bdohEntity
     */
    private function getBdohRepo($bdohEntity)
    {
        return $this->getRepository('Irstea\\BdohDataBundle\\Entity\\' . $bdohEntity);
    }

    protected function writeHeader()
    {
        $line_1 = ['Nom', 'Unite entree', 'Unite sortie', 'Commentaire'];

        $line_2 = [
            $this->bareme->getNom(),
            $this->getUnitLabel($this->bareme->getUniteEntree()),
            $this->getUnitLabel($this->bareme->getUniteSortie()),
            $this->bareme->getCommentaire(),
        ];

        $line_3 = ['X', 'Y', 'Qualité', 'Min (optionnel)', 'Max (optionnel)'];

        $this->writeLine($line_1);
        $this->writeLine($line_2);
        $this->writeLine($line_3);
    }

    private function writeBaremeLine(array $line)
    {
        $line[2] = $this->ordreToCode[intval($line[2])];
        $line = array_diff($line, [null, '', 'null', 'NULL']);

        $this->writeLine($line);
    }
}
