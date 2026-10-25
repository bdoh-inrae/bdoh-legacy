<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohAdminBundle\Importer\Controle;

use Doctrine\ORM\EntityManagerInterface;
use Irstea\BdohAdminBundle\Errors\ImportErrors;
use Irstea\BdohAdminBundle\Importer\Measure\Exception\StopImportException;
use Irstea\BdohBundle\DataTransformer\FromDateTimeMsTransformer;
use Irstea\BdohDataBundle\Entity\Chronique;
use Irstea\BdohDataBundle\Entity\ChroniqueContinue;
use Irstea\BdohDataBundle\Entity\Observatoire;
use Irstea\BdohDataBundle\Entity\Qualite;
use Irstea\BdohDataBundle\Entity\Station;
use Irstea\BdohDataBundle\Exporter\Exception\StopExportException;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Class Manager.
 */
class Manager
{
    const GAP_VALUE = '-9999';

    const TMP_DIR = '/tmp/';

    const MAX_ERRORS_IN_LOAD_DATA = 10;

    const COMMENT = '#';

    /***************************************************************************
     * Some dependencies of this class, and their getter
     **************************************************************************/

    protected $em;

    /**
     * @var TranslatorInterface
     */
    protected $translator;

    /**
     * @var string
     */
    protected $delimiter = ';';

    /**
     * Errors manager.
     *
     * @var Irstea\BdohAdminBundle\Errors\ImportErrors
     */
    protected $errors;

    /**
     * @return ImportErrors|Irstea\BdohAdminBundle\Errors\ImportErrors
     */
    public function getErrors()
    {
        return $this->errors;
    }

    /**
     * @var null
     */
    public $firstDate = null;

    /**
     * @var null
     */
    public $lastDate = null;

    /**
     * Path of file to import.
     *
     * @var string
     */
    protected $filePath;

    /**
     * Handler on file to import.
     *
     * @var resource
     */
    protected $file;

    /**
     * Path of CSV file.
     *
     * @var string
     */
    protected $csvPath;

    /**
     * Handler on CSV file.
     *
     * @var resource
     */
    protected $csv;

    /**
     * Name of the temporary SQL table.
     *
     * @var string
     */
    public $tmpSqlTable;

    /**
     * 'Station' for import.
     *
     * @var Irstea\BdohDataBundle\Entity\Station
     */
    protected $station = null;

    /**
     * 'Chronique' for which file data should be inserted.
     *
     * @var Irstea\BdohDataBundle\Entity\Chronique
     */
    protected $chronique = null;

    /**
     * 'Unite' in which file data should be inserted.
     *
     * @var array
     */
    protected $unite = null;

    /**
     * Timezone of file dates.
     *
     * @var string Format : +/-hhmm (+02:00, -10:00, -04:30, +00:30...)
     */
    protected $timezone = '';

    /**
     * @var \Doctrine\ORM\EntityRepository|null
     */
    protected $controleRepo = null;

    /**
     * @var \Doctrine\ORM\EntityRepository|null
     */
    protected $chroniqueRepo = null;

    /**
     * @var \Doctrine\ORM\EntityRepository|null
     */
    protected $mesureRepo = null;

    /**
     * @var array
     */
    protected $qualityMap;

    /**
     * @var array
     */
    protected $qualityCodes;

    /**
     * @var array
     */
    protected $allowedQualityCodes;

    /**
     * @var \DateTime
     */
    protected $now;

    /**
     * @var string
     */
    protected $nowAsString;

    /**
     * Pour l'export : dossier du fichier écrit, et ce que le dépôt dit des points exportés.
     *
     * @var string
     */
    protected $dirPath;

    /**
     * @var array
     */
    protected $infoSelection;

    /**
     * @param EntityManagerInterface                          $em
     * @param TranslatorInterface $translator
     * @param string                                            $file       Path of a readable file
     * @param Irstea\BdohAdminBundle\Errors\ImportErrors        $errors
     * @param mixed                                             $isExport
     * @param mixed|null                                        $timezone
     *
     * @throws \RuntimeException if $file isn't a real file
     */
    public function __construct(
        EntityManagerInterface $em,
        TranslatorInterface $translator,
        $file,
        ?ImportErrors $errors = null,
        $isExport = false,
        $timezone = null
    ) {
        $this->em = $em;
        $this->translator = $translator;
        $this->filePath = $file;
        $this->errors = (null === $errors) ? new ImportErrors() : $errors;

        $this->controleRepo = $this->getBdohRepo('PointControle');
        $this->chroniqueRepo = $this->getBdohRepo('ChroniqueContinue');
        $this->mesureRepo = $this->getBdohRepo('Mesure');
        $this->timezone = $timezone;

        $obsQualities = Observatoire::getCurrent()->getJeu()->getQualites();
        $this->qualityCodes = [];
        $this->allowedQualityCodes = [];
        $this->qualityMap = [];
        foreach ($obsQualities as $q) {
            $this->qualityCodes[] = $q->getCode();
            if (!\in_array($q->getOrdre(), Qualite::$ordresInvalides, true) &&
                !\in_array($q->getOrdre(), Qualite::$ordresLimites, true)) {
                $this->allowedQualityCodes[] = $q->getCode();
            }
            $this->qualityMap[$q->getCode()] = $q->getId();
        }

        if ($isExport) {
            // Creates a directory, randomly named
            if (!mkdir($this->dirPath = static::TMP_DIR . rand() . '/')) {
                throw new StopExportException();
            }
        } else {
            /* $file is really a file  ? */
            if (!is_file($file)) {
                throw new \RuntimeException(
                    $this->translator->trans(
                        'Errors.notFile(%file%)',
                        ['%file%' => $file]
                    )
                );
            }
        }

        $this->now = new \DateTime();
        $this->nowAsString = $this->now->format('Y-m-d H:i:s');
    }

    // Function allowing to validate/transform a timezone

    /**
     * @param $timezone
     *
     * @return bool|string
     */
    protected function validateTimezone($timezone)
    {
        return \Irstea\BdohBundle\DataTransformer\ToUtcDiffTransformer::bdohTimezone($timezone);
    }

    // Function allowing to validate/transform a datetime

    /**
     * @param $dateTimeMs
     *
     * @return bool|string
     */
    protected function validateDateTimeMs($dateTimeMs)
    {
        return \Irstea\BdohBundle\DataTransformer\ToDateTimeMsTransformer::varFrenchDateTimeMs($dateTimeMs);
    }

    // Sets metadata of import by reading the file header.

    /**
     * @throws StopImportException
     */
    protected function setMetadataFromHeader()
    {
        try {
            // Three-line header, but only first and second lines are checked
            for ($i = 0; $i < 2; ++$i) {
                $data[$i] = explode(';', fgets($this->file));
            }
        } catch (\Exception $e) {
            $this->errors->addError($e->getMessage());
            throw new StopImportException();
        }

        // First and second line should have the same columns number
        if (count($data[0]) !== count($data[1])) {
            $this->errors->badHeaderFirstTwoLines();
            throw new StopImportException();
        }

        // First line should be : Station ; Fuseau ; Chronique ; Unité

        if (count($data[0]) !== 4 ||
            'station' !== trim(strtolower($data[0][0])) ||
            'fuseau' !== trim(strtolower($data[0][1])) ||
            'chronique' !== trim(strtolower($data[0][2])) ||
            'unite' !== trim(strtolower($data[0][3]))) {
            $this->errors->badHeaderSyntax();
            throw new StopImportException();
        }

        // Second line should be : codeStation ; valeurFuseau ; codeChronique ; valeurUnite
        // Try to assign attributes from this line
        if (!$this->setStation(trim($data[1][0]))
            || !$this->setTimezone($data[1][1])
            || !$this->setChronique(trim($data[1][2]))
            || !$this->setUnite(trim($data[1][3]))
        ) {
            throw new StopImportException();
        }
    }

    // Reads file data.

    /**
     * @throws StopImportException
     */
    protected function read()
    {
        //We skip the header
        $numLine = 4;

        while (($line = fgets($this->file)) !== false) {
            // If line begins with self::COMMENT => it's a comment
            if ($line[0] !== self::COMMENT && $numLine > 3) {
                // If line contains self::COMMENT => rest of line is a comment
                $splitLine = strtok($line, self::COMMENT);
                $data = explode(';', $splitLine);

                if (($found = count($data)) > 5) {
                    $this->errors->badLineSize($numLine, $found, '5');
                    throw new StopImportException();
                }

                if (($found = count($data)) < 3) {
                    $this->errors->badMinLineSize($numLine, $found, '3');
                    throw new StopImportException();
                }

                $this->treatLine($data, $numLine);
            }
            ++$numLine;
        }
    }

    /**
     * @param $line
     * @param $numLine
     *
     * @throws StopImportException
     */
    protected function treatLine($line, $numLine)
    {
        /**
         * One line should be : DateTime ; value ; quality ; min ; max.
         */
        $nData = \sizeof($line);

        $date = trim($line[0]);
        $value = trim($line[1]);
        $quality = ($nData > 2) ? trim($line[2]) : null;
        $min = ($nData > 3) ? trim($line[3]) : null;
        $max = ($nData > 4) ? trim($line[4]) : null;

        $this->processControle($date, $value, $quality, $min, $max, $numLine);
    }

    /**
     * Loads metadata from file header.
     */
    final public function loadHeader()
    {
        try {
            // Can't continue if there are errors
            if ($this->errors->isDirty()) {
                throw new \RuntimeException(
                    $this->translator->trans(
                        'controle.import.thereAreErrors.cantContinue'
                    )
                );
            }

            // Opens the file
            if (!$this->file = fopen($this->filePath, 'r')) {
                throw new \RuntimeException(
                    $this->translator->trans(
                        'Error.file.cantRead(%file%)',
                        ['%file%' => $this->filePath]
                    )
                );
            }

            $this->setMetadataFromHeader();

            fclose($this->file);
            $this->file = null;
        } catch (\Exception $e) {
            if (false === ($e instanceof StopImportException)) {
                $this->errors->addError($e->getMessage());
            }
            $this->clean();
        }
    }

    /**
     * Loads file data.
     */
    final public function loadData()
    {
        try {
            // Can't continue if there are errors
            if ($this->errors->isDirty()) {
                throw new \RuntimeException(
                    $this->translator->trans(
                        'controle.import.thereAreErrors.cantContinue'
                    )
                );
            }

            // Opens the file
            if (!($this->file = fopen($this->filePath, 'r'))) {
                throw new \RuntimeException(
                    $this->translator->trans(
                        'Error.file.cantRead(%file%)',
                        ['%file%' => $this->filePath]
                    )
                );
            }

            // Creates a temporary CSV file
            $this->csvPath = tempnam('/tmp', 'import_');

            if (!($this->csv = fopen($this->csvPath, 'w'))) {
                throw new \RuntimeException(
                    $this->translator->trans(
                        'Error.file.cantCreate(%file%)',
                        ['%file%' => $this->csvPath]
                    )
                );
            }
            if (false === chmod($this->csvPath, 0755)) {
                throw new \RuntimeException(
                    $this->translator->trans(
                        'Error.file.chmodFailed(%file%,%mode%)',
                        ['%file%' => $this->csvPath, '%mode%' => '0755']
                    )
                );
            }

            // Skips the file header
            for ($i = 0; $i < 3;
                ++$i) {
                fgets($this->file);
            }

            // Reads each line of the file, and writes the CSV file
            $this->read();

            if ($this->errors->isClean()) {
                // Creates a temporary SQL table
                $this->tmpSqlTable = $this->controleRepo->createTemporaryTable();
                // And inserts controles in it (from the CSV file)
                $this->controleRepo->copyIntoTemporaryTable($this->tmpSqlTable, $this->csvPath);

                // Detects date redundancies
                if ([] !== $redundancies = $this->controleRepo->detectDateRedundancies($this->tmpSqlTable)) {
                    // If there are some, creates corresponding errors
                    foreach ($redundancies as $date => $redundancy) {
                        $this->errors->dateRedundancy(
                            $this->chronique->getCode(),
                            $date,
                            $redundancy['count'],
                            $redundancy['numLines']
                        );
                    }
                    throw new StopImportException();
                }

                // Adjusts the SQL table dates depending on timezone
                $tmpTz = $this->timezone;
                $tmpTz[0] = $tmpTz[0] === '+' ? '-' : '+';
                $this->mesureRepo->adjustDates($tmpTz, $this->tmpSqlTable);

                // Gets the first and last dates from the SQL table
                list($this->firstDate, $this->lastDate) =
                    $this->mesureRepo->getTemporaryFirstLastDates($this->tmpSqlTable);
            }

            fclose($this->file);
            $this->file = null;
            fclose($this->csv);
            $this->csv = null;
        } catch (\Exception $e) {
            if (!($e instanceof StopImportException)) {
                $this->errors->addError($e->getMessage());
            }
            $this->clean();
        }
    }

    /**
     * Proceeds to the final controles import, depending on an importType :
     *       -> 'doNotImport' == do not import measures
     *       -> 'import' == delete existing controles and import all.
     *
     * @param mixed $importType
     */
    final public function importData($importType)
    {
        try {
            // Can't continue if there are errors
            if ($this->errors->isDirty()) {
                throw new \RuntimeException(
                    $this->translator->trans(
                        'controle.import.thereAreErrors.cantContinue'
                    )
                );
            }

            // Gets the entity of each 'chronique'
            $chronique = $this->chronique;
            $chroniqueId = $this->chronique->getId();

            // Some stats : number of INSERT and DELETE.
            $numInsertDelete = [];

            // depending on its "import type", imports the controles

            $delete = 0;
            $insert = 0;
            $effectiveBeginDate = $this->firstDate;
            $effectiveEndDate = $this->lastDate;

            if ('import' === $importType) {
                list($delete, $insert) = $this->controleRepo->import($this->tmpSqlTable, $chroniqueId);
            }

            $numInsertDelete[(string) $chronique] = [
                'entity'    => $chronique,
                'delete'    => $delete,
                'insert'    => $insert,
                'beginDate' => $effectiveBeginDate,
                'endDate'   => $effectiveEndDate,
            ];

            // Controles import is now finished ! So, cleans the importer.
            $this->clean();

            return $numInsertDelete;
        } catch (\Exception $e) {
            $this->errors->addError($e->getMessage());
            $this->clean();
        }
    }

    /**
     * If validation/transformation of given timezone by the validateTimezone() function is OK :
     *      => sets "timezone" attribute with the transformed/validated timezone.
     * Else => sets an error.
     *
     * @param string $timezone
     */
    final public function setTimezone($timezone)
    {
        if (($tmpTz = $this->validateTimezone($timezone))) {
            $this->timezone = $tmpTz;

            return true;
        }
        $this->errors->invalidTimezone($timezone);

        return false;
    }

    /**
     * Gets the timezone.
     *
     * @return string
     */
    final public function getTimezone()
    {
        return $this->timezone;
    }

    /**
     * Sets the 'station' for import.
     * If 'station' not found by code => sets an error.
     *
     * @param Station|string $station may be an instance of 'Station' or a Station's code
     */
    final public function setStation($station)
    {
        if (is_string($station)) {
            try {
                $station = $this->getBdohRepo('Station')->findOneByCode($station);
                $ok = true;
            } catch (\Exception $e) {
                $ok = false;
            }
        } else {
            $ok = ($station instanceof Station);
        }

        if ($ok) {
            $this->station = $station;
        } else {
            $this->errors->badStation($station);
            $this->station = null;
        }

        return $ok;
    }

    /**
     * Gets the 'station' for import.
     *
     * @return Irstea\BdohDataBundle\Entity\Station
     */
    final public function getStation()
    {
        return $this->station;
    }

    /**
     * Sets the 'chronique'.
     *
     * If 'chronique' not found by a 'station' and a code => sets an error.
     *
     * Note : at this point, a 'station' should be defined.
     *
     * @param Chronique|string $chronique May be an instance of 'Chronique'or a Chronique's code
     */
    final public function setChronique($chronique)
    {
        if (is_string($chronique)) {
            try {
                $chronique = $this->chroniqueRepo->findOneByStationAndCode($this->station, $chronique);
                $ok = $chronique && !$chronique->isConvertie();
            } catch (\Exception $e) {
                $ok = false;
            }
        } else {
            $ok = ($chronique instanceof ChroniqueContinue) && !$chronique->isConvertie();
        }

        if ($ok) {
            $this->chronique = $chronique;
        } else {
            if (($chronique instanceof ChroniqueContinue) && $chronique->isConvertie()) {
                $this->errors->noCheckpointOnConverted($chronique);
            } else {
                $this->errors->badChroniqueContinue($chronique, $this->station);
            }
            $this->chronique = null;
        }

        return $ok;
    }

    /**
     * Gets 'chronique'.
     *
     * @return Chronique
     */
    final public function getChronique()
    {
        return $this->chronique;
    }

    /**
     * @return ChroniqueRepository
     */
    final public function getChroniqueRepo()
    {
        return $this->chroniqueRepo;
    }

    /**
     * @return ChroniqueRepository
     */
    final public function getControleRepo()
    {
        return $this->controleRepo;
    }

    /**
     * Process of a 'Controle' :.
     *
     *  2) Validates/transforms 'date' with the validateDateTimeMs() function...
     *  3) and checks the result.
     *  4) Do generic checks and treatments.
     *  5) Writes 'Controle' in CSV file.
     *
     *  /!\ Are realized at the end of loadData(), directly in the temporary SQL table :
     *      => detection of date redundancies ;
     *      => transformation of date in UTC ;
     *      => computing of first and last dates.
     *
     * @param mixed $date
     * @param mixed $valeur
     * @param mixed $qualite
     * @param mixed $minimum
     * @param mixed $maximum
     * @param mixed $numLine
     */
    final protected function processControle($date, $valeur, $qualite, $minimum, $maximum, $numLine)
    {
        // Validates/transforms 'date' with the validateDateTimeMs() function, and checks the result
        if (!($tmpDate = $this->validateDateTimeMs($date))) {
            $this->errors->dateInvalidFormat($numLine, $date, $valeur);
        } elseif ($tmpDate > $this->now) {
            $this->errors->dateAfterNow($numLine, $date, $valeur);
        } else {
            $date = $tmpDate;
        }

        // Check value
        if (!$valeur) {
            $valeur = '';
        }
        if (!\is_numeric($valeur)) {
            $this->errors->valeurNotNumeric($numLine, $date, $valeur);
        }

        // Check quality
        if (!\in_array($qualite, $this->qualityCodes)) {
            $this->errors->notInSourceJeu($numLine, $date, $valeur, $qualite);
        } elseif (!\in_array($qualite, $this->allowedQualityCodes)) {
            $this->errors->qualityNotForCheckpoint($numLine, $date, $valeur, $qualite);
        }

        // Check minimum
        if ($minimum && !\is_numeric($minimum)) {
            $this->errors->minimumNotNumeric($numLine, $date, $valeur, $minimum);
        }

        // Check maximum
        if ($maximum && !\is_numeric($maximum)) {
            $this->errors->maximumNotNumeric($numLine, $date, $valeur, $maximum);
        }

        if ($this->errors->count > static::MAX_ERRORS_IN_LOAD_DATA) {
            $this->errors->tooManyErrors();
            throw new StopImportException();
        }

        //processGeneric($date, $valeur, $minimum, $maximum, $numLine)

        // Writes 'Controle' in CSV file
        $fields = [
            $numLine,
            $this->chronique->getId(),
            $date,
            is_numeric($valeur) ? $valeur : '\\N',
            isset($this->qualityMap[$qualite]) ? $this->qualityMap[$qualite] : '\\N',
            is_numeric($minimum) ? $minimum : '\\N',
            is_numeric($maximum) ? $maximum : '\\N',
        ];
        fputcsv($this->csv, $fields, ';', '"', '\\');
    }

    /**
     * Treatments to do when serializing.
     */
    public function __sleep()
    {
        /* Inlines 'chroniques' and 'station' */
        if ($this->chronique instanceof Chronique) {
            $this->chronique = $this->chronique->getId();
        }

        if ($this->station instanceof Station) {
            $this->station = $this->station->getId();
        }

        /* Attributes to serialize */
        return [
            'errors',
            'filePath',
            'csvPath',
            'tmpSqlTable',
            'station',
            'chronique',
            'timezone',
            'firstDate',
            'lastDate',
            'now',
            'nowAsString',
        ];
    }

    /**
     * Treatments to do when unserializing.
     * NOTE : run manually this function !
     *
     * @param EntityManagerInterface                          $em
     * @param TranslatorInterface $translator
     */
    public function wakeup(EntityManagerInterface $em, TranslatorInterface $translator)
    {
        $this->em = $em;
        $this->translator = $translator;

        $this->controleRepo = $this->getBdohRepo('PointControle');
        $this->chroniqueRepo = $this->getBdohRepo('ChroniqueContinue');
        $this->mesureRepo = $this->getBdohRepo('Mesure');

        /* Gets 'chronique' and 'station' entities */

        if ($this->chronique) {
            $this->chronique = $this->chroniqueRepo->find($this->chronique);
        }
        if ($this->station) {
            $this->station = $this->getBdohRepo('Station')->find($this->station);
        }
    }

    /**
     * Cleans the importer.
     */
    final public function clean()
    {
        // Closes file handlers
        if ($this->file) {
            fclose($this->file);
            $this->file = null;
        }
        if ($this->csv) {
            fclose($this->csv);
            $this->csv = null;
        }

        // Deletes imported file and CSV file
        if ($this->csvPath) {
            unlink($this->csvPath);
            $this->csvPath = null;
        }

        if ($this->filePath) {
            unlink($this->filePath);
            $this->filePath = null;
        }

        // Deletes temporary SQL table
        if ($this->tmpSqlTable) {
            $this->mesureRepo->dropTemporaryTable($this->tmpSqlTable);
        }
    }

    /**
     * Shortcut to return a Doctrine repository.
     *
     * @param string $entity
     */
    protected function getRepository($entity)
    {
        return $this->em->getRepository($entity);
    }

    /**
     *  Shortcut to return a Doctrine repository of a BDOH entity.
     *
     * @param string $bdohEntity
     */
    protected function getBdohRepo($bdohEntity)
    {
        return $this->getRepository('Irstea\\BdohDataBundle\\Entity\\' . $bdohEntity);
    }

    /**
     * @return array
     */
    public function getUnite()
    {
        return $this->unite;
    }

    /**
     * @param $unite
     *
     * @return bool
     */
    public function setUnite($unite)
    {
        if ($unite === $this->getChronique()->getUnite()->getLibelle()) {
            $this->unite = $unite;

            return true;
        }
        $this->errors->badHeaderUnit($unite, $this->getChronique()->getUnite()->getLibelle());
        $this->unite = null;

        return false;
    }

    /**
     * @param $chroniqueId
     * @param $defaultQualityCode
     *
     * @return string
     */
    public function run($chroniqueId, $defaultQualityCode)
    {
        // Retrieves the 'chronique' entity from its id.
        try {
            $this->chronique = $chronique = $this->getBdohRepo('Chronique')->findOneById($chroniqueId);
            $chronRepo = $this->getChroniqueRepo();
        } catch (\Doctrine\ORM\NoResultException $e) {
            throw new StopExportException();
        }

        // Creates the file
        $fileName = sprintf('%s_%s_%s.txt', 'Controle', $chronique->getStation()->getCode(), $chronique->getCode());
        $this->filePath = $filePath = $this->dirPath . $fileName;

        if (false === ($this->file = $file = fopen($filePath, 'w'))) {
            throw new StopExportException();
        }

        // Writes the file header
        $this->writeHeader();

        /*
         * Retrieves :
         *      => a "Doctrine statement" on measures selected ;
         *      => some interesting information about this selection .
         */
        list($stmt, $this->infoSelection) =
            $chronRepo->getControlePointsForExport($chronique, $this->timezone, $defaultQualityCode);

        foreach ($stmt as $controlePoint) {
            $this->writeControlePoint($controlePoint);
        }

        // Saves the measures file path, and returns it
        fclose($file);

        return $filePath;
    }

    /**
     * @return string
     */
    public function getFilePath()
    {
        return $this->filePath;
    }

    public function writeHeader()
    {
        $line_1 = ['Station', 'Fuseau', 'Chronique', 'Unite'];

        $line_2 = [
            $this->chronique->getStation()->getCode(),
            'UTC' . substr($this->timezone, 0, 3),
            $this->chronique->getCode(),
            $this->chronique->getUnite()->getLibelle(),
        ];

        $line_3 = ['DateHeure', 'Valeur', 'Qualite', 'Min (optionnel)', 'Max (optionnel)'];

        $this->writeLine($line_1);
        $this->writeLine($line_2);
        $this->writeLine($line_3);
    }

    /**
     * @param $controlePoint
     */
    public function writeControlePoint($controlePoint)
    {
        $line = [FromDateTimeMsTransformer::toFrenchDateTimeMs($controlePoint['date'])];

        $line[] = (null === $controlePoint['valeur']) ? static::GAP_VALUE : $controlePoint['valeur'];
        $line[] = $controlePoint['qualite'];
        $line[] = $controlePoint['minimum'];
        $line[] = $controlePoint['maximum'];

        $this->writeLine($line);
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
}
