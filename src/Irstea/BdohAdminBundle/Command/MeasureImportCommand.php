<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohAdminBundle\Command;

use Irstea\BdohAdminBundle\Importer\Measure\ImporterInterface;
use Irstea\BdohBundle\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Import measures ('Mesure' or 'Plage') from a file into some 'chroniques'.
 */
class MeasureImportCommand extends Command
{
    protected $observatoire;

    protected $station;

    protected $filePath;

    protected $importerClass;

    protected function configure()
    {
        $this->setName('bdoh:import:measure');
    }

    /**
     * Very first interactions with user. Asks him to select :
     *    1) An 'observatoire' ;
     *    2) A file containing measures ;
     *    3) A file format .
     */
    protected function interact(InputInterface $input, OutputInterface $output)
    {
        $dialog = $this->getDialogHelper();
        $manager = $this->getContainer()->get('irstea_bdoh_admin.manager.measure_import');

        $dialog->writeSection($output, $this->trans('configuration_import'));

        /**
         * Selects the 'observatoire' in which to import measures.
         */
        $title = $this->trans('Observatoire', [], 'messages');
        $allObs = $this->getAllObservatoires();
        list(, $this->observatoire) = $dialog->getSelection($output, $title, $allObs);
        $this->observatoire->defineAsCurrent();
        $output->writeln('');

        /**
         * Path of file containing measures to import.
         */
        $files = $manager->getFiles();
        list(, $this->filePath) = $dialog->getSelection($output, $this->trans('file_path'), $files, false, true);
        $output->writeln('');

        /**
         * File format.
         */
        $formats = array_values($manager->importers->all());
        list(, $this->importerClass) = $dialog->getSelection($output, $this->trans('file_format'), $formats);
        $output->writeln('');
    }

    /**
     * Performs the import of measures.
     * If necessary, interacts with user.
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $dialog = $this->getDialogHelper();
        $importer = new $this->importerClass(
            $this->getContainer()->get('doctrine.orm.entity_manager'),
            $this->getContainer()->get('translator'),
            $this->filePath
        );

        /*
         * Step 1 : 'station', timezone and 'chroniques'
         */
        try {
            $importer->loadHeader();

            /* Possible 'station' selection */
            if (null === $importer->getStation()) {
                $title = $this->trans('Station', [], 'messages');
                $allStations = $this->getAllStations();
                list(, $this->station) = $dialog->getSelection($output, $title, $allStations);
                $importer->setStation($this->station);
            }

            /* Timezone selection */
            if ($importer->getTimezone() === '') {
                $timezone = $dialog->askAndValidate(
                    $output,
                    '<question>' . $this->trans('select_timezone') . '</question> ',
                    function ($timezone) {
                        $timezone = \Irstea\BdohBundle\DataTransformer\ToUtcDiffTransformer::bdohTimezone($timezone);
                        if (!$timezone) {
                            throw new \Exception('Bad timezone format');
                        }

                        return $timezone;
                    }
                );
                $importer->setTimezone($timezone);
            }

            /* 'Chroniques' selection */
            if ($importer->hasChroniques()) {
                $this->selectChroniques($output, $importer);
            } else {
                $this->selectChroniquesFromEmpty($output, $importer);
            }

            if (false === $importer->hasChroniques()) {
                throw new \RuntimeException($this->trans('no_chronique_selected'));
            }
        } catch (\Exception $error) {
            $output->writeln("<error>$error</error>");
        }

        $t1 = time();
        $importer->loadData();
        echo time() - $t1;

        return true;
    }

    /**
     * Selects some 'chroniques' in which to import measures :
     *    => depending on 'chroniques' number that importer expects (from 1 to +Inf) ;
     *    => user is allowed to skips / not select a 'chronique'.
     */
    protected function selectChroniquesFromEmpty(OutputInterface $output, ImporterInterface $importer)
    {
        $dialog = $this->getDialogHelper();
        $title = $this->trans('Chronique', [], 'messages');
        $noMoreKey = $this->trans('selection.no_more.key');
        $noMoreMsg = $this->trans('selection.no_more.msg');
        $expectedChron = $importer->getExpectedChroniques();
        $i = 0;

        $dialog->writeSection($output, $this->trans('select_chroniques'));

        /**
         * Selection list :
         *    => all 'chroniques' of the current 'observatoire' ;
         *    => as the second last choice : the "not select" choice ;
         *    => as the last choice, only in case of "an infinity" : the "stop selection" choice .
         */
        $elements = $this->getAllChroniques();

        while ($i !== $expectedChron) {
            list(
                $key, $chronique
                ) =
                $dialog->getSelection($output, $title . ' N°' . ($i + 1), $elements, true, false, $expectedChron === INF);

            // User stops the selection process
            if ($key === -1 && $chronique === -1) {
                $i = INF;
            } // User skips the i-th 'chronique'
            elseif ($key === -1 && $chronique === null) {
                $importer->setChronique(null, $i++);
            } // Adds selected 'chronique' and removes it to selection list
            else {
                $importer->setChronique($chronique, $i++);
                unset($elements[$key]);
            }
        }
    }

    /**
     * From an existing list of 'chroniques', asks user which ones to keep.
     */
    public function selectChroniques(OutputInterface $output, ImporterInterface $importer)
    {
        $dialog = $this->getDialogHelper();
        $dialog->writeSection($output, $this->trans('select_chroniques'));

        foreach ($importer->getChroniques() as $i => $chronique) {
            $question = $this->trans(
                'ask.keep_chronique(%n%, %chronique%)',
                ['%n%' => $i, '%chronique%' => (string) $chronique]
            );

            if (false === $dialog->askConfirmation($output, "<question>$question</question> ")) {
                $importer->setChronique(null, $i);
            }
        }
    }

    /**
     * @see \Irstea\BdohDataBundle\Entity\ObservatoireRepository
     */
    protected function getAllObservatoires()
    {
        return $this->getContainer()->get('doctrine')->getEntityManager()
            ->getRepository('Irstea\\BdohDataBundle\\Entity\\Observatoire')
            ->findAll();
    }

    /**
     * @see \Irstea\BdohDataBundle\Entity\StationRepository
     */
    protected function getAllStations()
    {
        return $this->getContainer()->get('doctrine')->getEntityManager()
            ->getRepository('Irstea\\BdohDataBundle\\Entity\\Station')
            ->findAll();
    }

    /**
     * @see \Irstea\BdohDataBundle\Entity\ChroniqueRepository
     */
    protected function getAllChroniques()
    {
        $repChronique = $this->getContainer()->get('doctrine')->getEntityManager()
            ->getRepository('Irstea\\BdohDataBundle\\Entity\\Chronique');
        if ($this->station) {
            return $repChronique->findByStation($this->station);
        }

        return $repChronique->findAll();
    }
}
