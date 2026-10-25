<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohAdminBundle\Importer\Measure;

use Doctrine\ORM\EntityManagerInterface;
use Irstea\BdohAdminBundle\Errors\ImportErrors;
use Irstea\BdohAdminBundle\Importer\Measure\Exception\StopImportException;
use Irstea\BdohBundle\DataTransformer\ToDateTimeMsTransformer;
use Irstea\BdohBundle\DataTransformer\ToUtcDiffTransformer;
use Irstea\BdohDataBundle\Entity\Chronique;
use Irstea\BdohDataBundle\Entity\Repository\MesureRepositoryInterface;
use Irstea\BdohDataBundle\Entity\Station;
use Irstea\BdohDataBundle\EventListener\MeasuresUpdateEvent;
use Irstea\BdohDataBundle\Events;
use Irstea\BdohDataBundle\Util\JeuToJeu;
use Irstea\BdohSecurityBundle\Entity\Utilisateur;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

abstract class Importer implements ImporterInterface
{
    const MAX_ERRORS_IN_LOAD_DATA = 10;

    /***************************************************************************
     * Some dependencies of this class, and their getter
     **************************************************************************/

    /**
     * @var EntityManagerInterface
     */
    protected $em;

    /**
     * @var TranslatorInterface
     */
    protected $translator;

    /**
     * @var string
     */
    public $format;

    /**
     * Errors manager.
     *
     * @var ImportErrors
     */
    protected $errors;

    /**
     * {@inheritdoc}
     */
    public function getErrors()
    {
        return $this->errors;
    }

    /**
     * Manager of 'qualites' translation.
     *
     * @var JeuToJeu
     */
    protected $jeuTojeu;

    /***************************************************************************
     * Some format specific attributes, and their getter.
     * Each attribute could be or should be (if no default value) set in the specific importers.
     **************************************************************************/

    /**
     * Number of header lines.
     *
     * @var int
     */
    protected $headerSize = 1;

    /**
     * Type of measure : 'Mesure' or 'Plage'.
     *
     * @var string
     */
    protected $typeOfMeasure = 'Mesure';

    /**
     * {@inheritdoc}
     */
    public function getChroniqueType()
    {
        if ('Plage' === $this->typeOfMeasure) {
            return 'Discontinue';
        }

        return 'Continue';
    }

    /**
     * Number of 'chroniques' expected for import : default = an infinity.
     *
     * @var int
     */
    protected $expectedChroniques = PHP_INT_MAX;

    /**
     * {@inheritdoc}
     */
    public function getExpectedChroniques()
    {
        return $this->expectedChroniques;
    }

    /**
     * Name of source 'JeuQualite' : null === 'JeuQualite' of current 'observatoire'.
     *
     * @var string
     */
    protected $sourceJeuQualite = null;

    /***************************************************************************
     * Some format specific methods.
     * Each method could be or should be (if "abstract" method) set in the specific importers.
     **************************************************************************/

    /** Function allowing to validate/transform a timezone */
    protected function validateTimezone($timezone)
    {
        return ToUtcDiffTransformer::bdohTimezone($timezone);
    }

    /** Function allowing to validate/transform a datetime */
    protected function validateDateTimeMs($dateTimeMs)
    {
        return ToDateTimeMsTransformer::varFrenchDateTimeMs($dateTimeMs);
    }

    /** Sets metadata of import by reading the file header. */
    protected function setMetadataFromHeader()
    {
        // NOTE : Implement this method if specific importer has header.
    }

    /** Reads each file data. */
    abstract protected function read();

    /***************************************************************************
     * Attributes containing useful data for validation of import, and their getter.
     **************************************************************************/

    // The dates of the first and last measures
    public $firstDate = null;

    public $lastDate = null;

    /**
     * For each 'chronique', [first date, last date, number of measures]
     * where 'valeur' not in [chronique->minimumValide, chronique->maximumValide].
     * See : Irstea\BdohDataBundle\Entity\Repository\Mesure::getValuesOut().
     */
    protected $valeurTooSmall = [];

    protected $valeurTooLarge = [];

    /**
     * {@inheritdoc}
     */
    public function getValeurTooSmall($chroniqueId)
    {
        return array_key_exists($chroniqueId, $this->valeurTooSmall) ?
            $this->valeurTooSmall[$chroniqueId] :
            ['count' => 0, 'first' => new \DateTime(), 'last' => new \DateTime()]; // arbitrary first and last
    }

    /**
     * {@inheritdoc}
     */
    public function getValeurTooLarge($chroniqueId)
    {
        return array_key_exists($chroniqueId, $this->valeurTooLarge) ?
            $this->valeurTooLarge[$chroniqueId] :
            ['count' => 0, 'first' => new \DateTime(), 'last' => new \DateTime()]; // arbitrary first and last
    }

    /***************************************************************************
     * Other working attributes
     **************************************************************************/

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
    protected $tmpSqlTable;

    /**
     * 'Station' for import.
     *
     * @var Station
     */
    protected $station = null;

    /**
     * 'Chroniques' in which file data should be inserted.
     * Array structure :
     *    index === 'chronique' position in file ;
     *    value === 'chronique' entity OR NULL if user want to skip it.
     *
     * @var array
     */
    protected $chroniques = [];

    /**
     * Timezone of file dates.
     *
     * @var string Format : +/-hhmm (+02:00, -10:00, -04:30, +00:30...)
     */
    protected $timezone = '';

    protected $now;

    protected $nowAsString;

    /***************************************************************************
     * Treatments / methods
     **************************************************************************/

    /**
     * @param EntityManagerInterface $em
     * @param TranslatorInterface    $translator
     * @param string                 $file       Path of a readable file
     * @param ImportErrors           $errors
     *
     * @throws \RuntimeException if $file isn't a real file
     */
    public function __construct(EntityManagerInterface $em, TranslatorInterface $translator, $file, ?ImportErrors $errors = null)
    {
        $this->em = $em;
        $this->translator = $translator;
        $this->filePath = $file;
        $this->errors = (null === $errors) ? new ImportErrors() : $errors;

        /* $file is really a file  ? */
        if (false === is_file($file)) {
            throw new \RuntimeException(
                $this->translator->trans(
                    'Errors.notFile(%file%)',
                    ['%file%' => $file]
                )
            );
        }

        $this->now = new \DateTime();
        $this->nowAsString = $this->now->format('Y-m-d H:i:s');
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
                        'measure.import.thereAreErrors.cantContinue'
                    )
                );
            }

            // Opens the file
            if (!$this->file = fopen($this->filePath, 'rb')) {
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
            if (false === ($e instanceof Exception\StopImportException)) {
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
                        'measure.import.thereAreErrors.cantContinue'
                    )
                );
            }

            // Prepares the manager of 'qualites' translation
            $this->jeuTojeu = new JeuToJeu($this->em, $this->sourceJeuQualite, null);

            // Opens the file
            if (!$this->file = fopen($this->filePath, 'rt')) {
                throw new \RuntimeException(
                    $this->translator->trans(
                        'Error.file.cantRead(%file%)',
                        ['%file%' => $this->filePath]
                    )
                );
            }

            // Creates a temporary CSV file
            $this->csvPath = tempnam('/tmp', 'import_');

            if (!$this->csv = fopen($this->csvPath, 'wt')) {
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
            for ($i = 0; $i < $this->headerSize;
                ++$i) {
                fgets($this->file);
            }

            // Reads each line of the file, and writes the CSV file
            $this->read();

            if ($this->errors->isClean()) {
                // Gets the correct measures repository
                /** @var MesureRepositoryInterface $measureRepo */
                $measureRepo = $this->getBdohRepo($this->typeOfMeasure);

                // Creates a temporary SQL table
                $this->tmpSqlTable = $measureRepo->createTemporaryTable();

                // And inserts measures in it (from the CSV file)
                $measureRepo->copyIntoTemporaryTable($this->tmpSqlTable, $this->csvPath);

                // Detects date redundancies
                if ([] !== $redundancies = $measureRepo->detectDateRedundancies($this->tmpSqlTable)) {
                    // If there are some, creates corresponding errors
                    foreach ($redundancies as $numSet => $redunByChronique) {
                        foreach ($redunByChronique as $date => $redundancy) {
                            $this->errors->dateRedundancy(
                                $this->chroniques[$numSet]->getCode(),
                                $date,
                                $redundancy['count'],
                                $redundancy['numLines']
                            );
                        }
                    }
                    throw new Exception\StopImportException();
                }

                // Detects overlaps
                if ([] !== $overlaps = $measureRepo->detectRangeOverlaps($this->tmpSqlTable)) {
                    // If there are some, creates corresponding errors
                    foreach ($overlaps as $numSet => $overlapByChronique) {
                        foreach ($overlapByChronique as $overlap) {
                            $this->errors->rangeOverlap(
                                $this->chroniques[$numSet]->getCode(),
                                $overlap['plages'],
                                $overlap['numLines']
                            );
                        }
                    }
                    throw new Exception\StopImportException();
                }

                // Adjusts the SQL table dates depending on timezone
                $tmpTz = $this->timezone;
                $tmpTz[0] = $tmpTz[0] === '+' ? '-' : '+';
                $measureRepo->adjustDates($tmpTz, $this->tmpSqlTable);

                // Gets the first and last dates from the SQL table
                list($this->firstDate, $this->lastDate) =
                    $measureRepo->getTemporaryFirstLastDates($this->tmpSqlTable);

                $gapQuality = $this->jeuTojeu->codeToId('gap');

                /*
                 * By 'chronique', gets [first date, last date, number of measures] where
                 * 'valeur' not in [chronique->minimumValide, chronique->maximumValide].
                 */
                $this->valeurTooSmall = $measureRepo->getValuesOut($this->tmpSqlTable, 'tooSmall', $gapQuality);
                $this->valeurTooLarge = $measureRepo->getValuesOut($this->tmpSqlTable, 'tooLarge', $gapQuality);
            }

            fclose($this->file);
            $this->file = null;
            fclose($this->csv);
            $this->csv = null;
        } catch (StopImportException $e) {
            $this->clean();
        } catch (\Exception $e) {
            $this->errors->addError($e->getMessage());
            $this->clean();
        }
    }

    /**
     * @var bool
     */
    private $propagate = true;

    /** Set propagate.
     * @param bool $propagate
     */
    public function setPropagate($propagate)
    {
        $this->propagate = (bool) $propagate;
    }

    /**
     * Proceeds to the final measures import, depending on an array whose structure is :
     *    => Key   == 'chronique' id.
     *    => Value == how the measures must be imported :
     *       -> 'doNotImport'       == do not import measures for this 'chronique'.
     *       -> 'keepExisting'      == import only measures which doesn't overlap the existing.
     *       -> 'overwriteExisting' == delete existing measures overlapped by the new ones, and import all.
     * Computes, in background, the filling rates of each 'ChroniqueContinue' (depending on first and last dates).
     * For that, runs a UNIX command using "at" !
     *
     * @param mixed                    $importTypes
     * @param Utilisateur              $user
     * @param EventDispatcherInterface $dispatcher
     *
     * @return array
     */
    final public function importData($importTypes, $user, EventDispatcherInterface $dispatcher)
    {
        try {
            // Can't continue if there are errors
            if ($this->errors->isDirty()) {
                throw new \RuntimeException(
                    $this->translator->trans(
                        'measure.import.thereAreErrors.cantContinue'
                    )
                );
            }

            // Gets the entity of each 'chronique'
            $chroniques = [];
            foreach ($this->chroniques as $chronique) {
                if (null !== $chronique) {
                    $chroniques[$chronique->getId()] = $chronique;
                }
            }

            // Gets the correct measures repository
            /** @var MesureRepositoryInterface $measureRepo */
            $measureRepo = $this->getBdohRepo($this->typeOfMeasure);

            // Some stats : for each 'chronique', number of INSERT and DELETE.
            $numInsertDelete = [];

            // For each 'chronique' and depending on its "import type", imports its measures
            foreach ($importTypes as $chroniqueId => $importType) {
                if (false === array_key_exists($chroniqueId, $chroniques)) {
                    continue;
                }

                $delete = 0;
                $insert = 0;
                $effectiveBeginDate = $this->firstDate;
                $effectiveEndDate = $this->lastDate;

                if ('keepExisting' === $importType) {
                    // Dates of first and last existing measures (for Historique purpose)
                    list($firstExistingDate, $lastExistingDate) = $measureRepo->getFirstLastDates($chroniqueId);

                    $insert = $measureRepo->keepExistingImport($this->tmpSqlTable, $chroniqueId);

                    if ($firstExistingDate && $lastExistingDate) {
                        if (($this->lastDate < $firstExistingDate || $this->firstDate > $lastExistingDate) ||
                            ($this->firstDate < $firstExistingDate && $this->lastDate > $lastExistingDate)) {
                            //entirely before or after, or both before and after : no change
                        } elseif ($this->firstDate >= $firstExistingDate && $this->lastDate <= $lastExistingDate) {
                            //entirely in : we do not import anything
                            $effectiveBeginDate = null;
                            $effectiveEndDate = null;
                        } else { //two cases : we cross the begin or the end of existing measures
                            if ($this->firstDate < $firstExistingDate) {
                                //we are crossig the begin
                                $effectiveEndDate = $firstExistingDate;
                            } elseif ($this->lastDate > $lastExistingDate) {
                                //we cross the end
                                $effectiveBeginDate = $lastExistingDate;
                            }
                        }
                    }
                } elseif ('overwriteExisting' === $importType) {
                    list($delete, $insert) = $measureRepo->overwriteExistingImport(
                        $this->tmpSqlTable,
                        $chroniqueId,
                        $this->firstDate,
                        $this->lastDate
                    );
                }

                $numInsertDelete[(string) $chroniques[$chroniqueId]] = [
                    'entity'    => $chroniques[$chroniqueId],
                    'delete'    => $delete,
                    'insert'    => $insert,
                    'beginDate' => $effectiveBeginDate,
                    'endDate'   => $effectiveEndDate,
                ];

                if ($chroniques[$chroniqueId] && $delete + $insert > 0) {
                    $event = new MeasuresUpdateEvent(
                        $chroniques[$chroniqueId],
                        $user,
                        null,
                        null,
                        $this->propagate
                    );
                    $dispatcher->dispatch($event, Events::MEASURES_UPDATE);
                }
            }

            // Measures import is now finished ! So, cleans the importer.
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
        if ($tmpTz = $this->validateTimezone($timezone)) {
            $this->timezone = $tmpTz;
        } else {
            $this->errors->invalidTimezone($timezone);
        }
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
     * If 'station' not found by a code => sets an error.
     *
     * @param Station|string|null $station may be an instance of 'Station', a code of 'station' or null
     */
    final public function setStation($station)
    {
        if (is_string($station)) {
            try {
                $station = $this->getBdohRepo('Station')->findOneByCode($station);
            } catch (\Doctrine\ORM\Exception\ORMException $e) {
                $this->errors->badStation($station);
                $station = null;
            }
        } elseif (false === ($station instanceof Station)) {
            $station = null;
        }
        $this->station = $station;
    }

    /**
     * Gets the 'station' for import.
     *
     * @return Station
     */
    final public function getStation()
    {
        return $this->station;
    }

    /**
     * Sets the n-th 'chronique'.
     * If 'chronique' not found by a 'station' and a code => sets an error.
     * If 'chronique' is already used/set for import      => sets an error.
     * Note : at this point, a 'station' should be defined.
     *
     * @param Chronique|string|null $chronique may be an instance of 'Chronique', a code of 'chronique' or null
     * @param int                   $n         Position of 'chronique' to set, in range [0 ; expectedChroniques-1]
     * @param mixed|null            $unite
     */
    final public function setChronique($chronique, $n, $unite = null)
    {
        // $n must be in range [0 ; expectedChroniques-1]. Corrects it if necessary.
        $n = min($this->expectedChroniques - 1, max(0, $n));

        if (is_string($chronique)) {
            try {
                $chronique = $this->getChroniqueRepo()->findOneByStationAndCode($this->station, $chronique);
                if ($chronique && $chronique->isConvertie()) {
                    $this->errors->noImportOnConverted($chronique);
                    $chronique = null;
                }
            } catch (\Doctrine\ORM\Exception\ORMException $e) {
                $func = 'badChronique' . $this->getChroniqueType();
                $this->errors->$func($chronique, $this->station);
                $chronique = null;
            }
        } elseif (false === ($chronique instanceof Chronique)) {
            $func = 'badChronique' . $this->getChroniqueType();
            $this->errors->$func($chronique, $this->station);
            $chronique = null;
        } elseif ($chronique->isConvertie()) {
            $this->errors->noImportOnConverted($chronique);
            $chronique = null;
        }

        // Already set / used ? If yes => sets and errors.
        if (null !== $chronique) {
            foreach ($this->chroniques as $numSet => $existingChronique) {
                if ($n !== $numSet && null !== $existingChronique &&
                    $existingChronique->getId() === $chronique->getId()) {
                    $this->errors->chroniqueAlreadyExists($chronique);
                    break;
                }
            }

            //check if correct unit
            if ($unite !== null) {
                if ($chronique->getUnite()->getLibelle() !== $unite) {
                    $this->errors->badUniteForThisChronique($chronique, $unite);
                }
            }
            $this->chroniques[$n] = $chronique;
        }
    }

    /**
     * Gets all 'chroniques'.
     *
     * @return array
     */
    final public function getChroniques()
    {
        return $this->chroniques;
    }

    /**
     * Determines if some 'chroniques' have been added.
     *
     * @return bool
     */
    final public function hasChroniques()
    {
        return (false === empty($this->chroniques)) &&
            ([null] !== array_unique($this->chroniques));
    }

    /**
     * Determines if the n-th 'chronique' has been selected by user.
     *
     * @param int $n
     *
     * @return bool
     */
    final public function isSelectedChronique($n)
    {
        return isset($this->chroniques[$n]);
    }

    /**
     * Generic actions for processMesure() and processPlage() :
     *  1) Checks that 'valeur', 'minimum' and 'maximum' are numeric.
     *  2) Checks that 'qualite' exists in source 'JeuQualite'.
     *  3) Checks that 'qualite' has a translation in 'JeuQualite' of current 'observatoire'...
     *  4) and retrieves this translation.
     *  5) Checks that if 'qualite' is neither a gap or invalid, then 'valeur' exists.
     *
     * @param mixed $numSet
     * @param mixed $date
     * @param mixed $minimum
     * @param mixed $maximum
     * @param mixed $numLine
     * @param mixed $valeur
     * @param mixed $qualite
     */
    private function processGeneric($numSet, $date, &$valeur, &$qualite, $minimum, $maximum, $numLine)
    {
        //Checks that 'valeur', 'minimum' and 'maximum' are numeric
        if ($valeur !== null && $valeur !== '' && !\is_numeric($valeur)) {
            $this->errors->valeurNotNumeric($numLine, $date, $valeur);
        }
        if ($minimum && (false === is_numeric($minimum))) {
            $this->errors->minimumNotNumeric($numLine, $date, $valeur, $minimum);
        }
        if ($maximum && (false === is_numeric($maximum))) {
            $this->errors->maximumNotNumeric($numLine, $date, $valeur, $maximum);
        }

        //changes $valeur if quality is gap type
        if ($this->jeuTojeu->isGapType($qualite)) {
            $valeur = null;
        } // If $qualite is neither 'gap' nor 'invalid', then check that value exists
        elseif ($valeur === null || $valeur === '') {
            $this->errors->validButNoValue($numLine, $date, $qualite);
        }

        // Checks that $qualite exists in source 'JeuQualite'.
        if (false === $this->jeuTojeu->isInSource($qualite)) {
            $this->errors->notInSourceJeu($numLine, $date, $valeur, $qualite);
        } elseif (!$this->chroniques[$numSet]->getAllowValueLimits() && $this->jeuTojeu->isLimitType($qualite)) {
            $this->errors->forbiddenValueLimit($numLine, $date, $valeur, $qualite);
        } // Replaces $qualite by its translation id. Stores an error if none !
        elseif (null === ($idTrans = $this->jeuTojeu->codeToId($qualite))) {
            $this->errors->noTransInObservatoireJeu($numLine, $date, $valeur, $qualite);
        } else {
            $qualite = $idTrans;
        }

        // If too many errors => stops the measures import process !
        if ($this->errors->count > static::MAX_ERRORS_IN_LOAD_DATA) {
            $this->errors->tooManyErrors();
            throw new Exception\StopImportException();
        }
    }

    /**
     * Process of a 'Mesure' :
     *  1) Do nothing if associated 'chronique' hasn't been selected by user.
     *  2) Validates/transforms 'date' with the validateDateTimeMs() function...
     *  3) and checks the result.
     *  4) Do generic checks and treatments.
     *  5) Writes 'Mesure' in CSV file.
     *  /!\ Are realized at the end of loadData(), directly in the temporary SQL table :
     *      => detection of date redundancies ;
     *      => transformation of date in UTC ;
     *      => computing of first and last dates ;
     *      => check that 'valeur' is between "chronique->minimumValide" and "chronique->maximumValide" (included).
     *
     * @param mixed $numSet
     * @param mixed $date
     * @param mixed $valeur
     * @param mixed $qualite
     * @param mixed $minimum
     * @param mixed $maximum
     * @param mixed $numLine
     */
    final protected function processMesure($numSet, $date, $valeur, $qualite, $minimum, $maximum, $numLine)
    {
        if (false === $this->isSelectedChronique($numSet)) {
            return;
        }

        // Validates/transforms 'date' with the validateDateTimeMs() function, and checks the result
        if (false === ($tmpDate = $this->validateDateTimeMs($date))) {
            $this->errors->dateInvalidFormat($numLine, $date, $valeur);
        } elseif ($tmpDate > $this->nowAsString) {
            $this->errors->dateAfterNow($numLine, $date, $valeur);
        } else {
            $date = $tmpDate;
        }

        $this->processGeneric($numSet, $date, $valeur, $qualite, $minimum, $maximum, $numLine);

        // Writes 'Mesure' in CSV file
        $fields = [
            $numLine,
            $numSet,
            $this->chroniques[$numSet]->getId(),
            $qualite,
            $date,
            is_numeric($valeur) ? $valeur : '\\N',
            is_numeric($minimum) ? $minimum : '\\N',
            is_numeric($maximum) ? $maximum : '\\N',
        ];
        fputcsv($this->csv, $fields, ';', '"', '\\');
    }

    /**
     * Process of a 'Plage' : same chekcs and treatments as in processMesure().
     *
     * @param mixed $numSet
     * @param mixed $debut
     * @param mixed $fin
     * @param mixed $valeur
     * @param mixed $qualite
     * @param mixed $minimum
     * @param mixed $maximum
     * @param mixed $numLine
     */
    final protected function processPlage($numSet, $debut, $fin, $valeur, $qualite, $minimum, $maximum, $numLine)
    {
        if (false === $this->isSelectedChronique($numSet)) {
            return;
        }

        // Validates/transforms 'debut' with the validateDateTimeMs() function, and checks the result
        if (false === ($tmpDate = $this->validateDateTimeMs($debut))) {
            $this->errors->dateInvalidFormat($numLine, $debut, $valeur);
        } elseif ($tmpDate > $this->nowAsString) {
            $this->errors->dateAfterNow($numLine, $debut, $valeur);
        } else {
            $debut = $tmpDate;
        }

        // Validates/transforms 'fin' with the validateDateTimeMs() function, and checks the result
        if (false === ($tmpDate = $this->validateDateTimeMs($fin))) {
            $this->errors->dateInvalidFormat($numLine, $fin, $valeur);
        } elseif ($tmpDate > $this->nowAsString) {
            $this->errors->dateAfterNow($numLine, $fin, $valeur);
        } else {
            $fin = $tmpDate;
        }

        // Testing of 'debut' and 'fin' coherence
        if ($fin < $debut) {
            $this->errors->incoherentDates($numLine, $valeur);
        }

        $this->processGeneric($numSet, $debut, $valeur, $qualite, $minimum, $maximum, $numLine);

        // Writes 'Plage' in CSV file
        $fields = [
            $numLine,
            $numSet,
            $this->chroniques[$numSet]->getId(),
            $qualite,
            $debut,
            $fin,
            is_numeric($valeur) ? $valeur : '\\N',
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
        foreach ($this->chroniques as $key => $chronique) {
            if ($chronique instanceof Chronique) {
                $this->chroniques[$key] = $chronique->getId();
            }
        }
        if ($this->station instanceof Station) {
            $this->station = $this->station->getId();
        }

        /* Attributes to serialize */
        return [
            'errors',
            'jeuTojeu',
            'expectedChroniques',
            'headerSize',
            'filePath',
            'csvPath',
            'tmpSqlTable',
            'station',
            'chroniques',
            'timezone',
            'firstDate',
            'lastDate',
            'valeurTooSmall',
            'valeurTooLarge',
            'format',
            'now',
            'nowAsString',
        ];
    }

    /**
     * Treatments to do when unserializing.
     * NOTE : run manually this function !
     *
     * @param EntityManagerInterface $em
     * @param TranslatorInterface    $translator
     */
    public function wakeup(EntityManagerInterface $em, TranslatorInterface $translator)
    {
        $this->em = $em;
        $this->translator = $translator;

        /* Gets 'chroniques' and 'station' entities */
        foreach ($this->chroniques as $key => $chronique) {
            if ($chronique) {
                $this->chroniques[$key] = $this->getChroniqueRepo()->find($chronique);
            }
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
            @fclose($this->file);
            $this->file = null;
        }
        if ($this->csv) {
            @fclose($this->csv);
            $this->csv = null;
        }

        // Deletes imported file and CSV file
        if ($this->csvPath) {
            @unlink($this->csvPath);
            $this->csvPath = null;
        }

        if ($this->filePath) {
            @unlink($this->filePath);
            $this->filePath = null;
        }

        // Deletes temporary SQL table
        if ($this->tmpSqlTable) {
            $this->getBdohRepo($this->typeOfMeasure)->dropTemporaryTable($this->tmpSqlTable);
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
     * Returns the good 'chronique' repository, depending on $typeOfMeasure.
     */
    public function getChroniqueRepo()
    {
        return $this->getBdohRepo('Chronique' . $this->getChroniqueType());
    }
}
