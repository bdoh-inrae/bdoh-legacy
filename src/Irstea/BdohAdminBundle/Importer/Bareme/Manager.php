<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohAdminBundle\Importer\Bareme;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NoResultException;
use Irstea\BdohAdminBundle\Errors\ImportErrors;
use Irstea\BdohAdminBundle\Importer\Measure\Exception\StopImportException;
use Irstea\BdohBundle\DataTransformer\FromDateTimeMsTransformer;
use Irstea\BdohDataBundle\Entity\Bareme;
use Irstea\BdohDataBundle\Entity\Qualite;
use RuntimeException;
use Symfony\Contracts\Translation\TranslatorInterface;

class Manager
{
    const GAP_VALUE = '-9999';
    const TMP_DIR = '/tmp/';
    const MAX_ERRORS_IN_LOAD_DATA = 10;
    const COMMENT = '#';
    const BAREME_HEADER_SIZE = 3;

    /***************************************************************************
     * Some dependencies of this class, and their getter
     **************************************************************************/

    protected $em;

    protected $translator;

    protected $delimiter = ';';

    /**
     * Errors manager.
     *
     * @var ImportErrors
     */
    protected $errors;

    public function getErrors()
    {
        return $this->errors;
    }

    protected $nom = '';

    protected $dateCreation = null;

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
     * 'Station' for import.
     *
     * @var Irstea\BdohDataBundle\Entity\Observatoire
     */
    protected $observatoire = null;

    protected $uniteEntreeId = null;

    protected $uniteSortieId = null;

    private $commentaire = '';

    private $baremeGrid = [];

    protected $baremeRepo = null;

    protected $uniteRepo = null;

    protected $qualiteMap = [];

    private $xPrevious = -1e35;

    /**
     * @param EntityManagerInterface                                     $em
     * @param TranslatorInterface $translator
     * @param string                                            $file       Path of a readable file
     * @param ImportErrors                                      $errors
     * @param mixed                                             $isExport
     *
     * @throws RuntimeException if $file isn't a real file
     */
    public function __construct(EntityManagerInterface $em, TranslatorInterface $translator, $file, ?ImportErrors $errors = null)
    {
        $this->em = $em;
        $this->translator = $translator;
        $this->filePath = $file;
        $this->errors = (null === $errors) ? new ImportErrors() : $errors;

        $this->baremeRepo = $this->getBdohRepo('Bareme');
        $this->uniteRepo = $this->getBdohRepo('Unite');

        $qualites = $this->getBdohRepo('Observatoire')->getCurrentObservatoire()->getJeu()->getQualites();
        foreach ($qualites as $q) {
            $this->qualiteMap[$q->getCode()] = $q->getOrdre();
        }

        /* $file is really a file  ? */
        if (!is_file($file)) {
            throw new RuntimeException(
                $this->translator->trans(
                    'Errors.notFile(%file%)',
                    ['%file%' => $file]
                )
            );
        }
    }

    // Sets metadata of import by reading the file header.
    protected function setMetadataFromHeader()
    {
        try {
            // Three-line header, but only first and second lines are checked
            for ($i = 0; $i < 2; ++$i) {
                $data[$i] = explode(';', fgets($this->file));
            }
        } catch (\Exception $e) {
            $this->errors->addError($e->getMessage());
        }

        if ($this->errors->isDirty()) {
            throw new StopImportException();
        }

        // First and second line should have the same columns number
        if (count($data[0]) !== count($data[1])) {
            $this->errors->badHeaderFirstTwoLines();
            throw new StopImportException();
        }

        // First line should be : Nom ; Unite entree ; Unite sortie ; Commentaire
        if (count($data[0]) !== 4 ||
            'nom' !== trim(strtolower($data[0][0])) ||
            'unite entree' !== trim(strtolower($data[0][1])) ||
            'unite sortie' !== trim(strtolower($data[0][2])) ||
            'commentaire' !== trim(strtolower($data[0][3]))) {
            $this->errors->badHeaderSyntax();
            throw new StopImportException();
        }

        // Second line should be the values for : scale name ; input unit ; output unit ; comment
        $this->nom = trim($data[1][0]);

        $data[1][1] = trim($data[1][1]);
        if (($unite = $this->uniteRepo->findOneByLibelle($data[1][1]))) {
            $this->uniteEntreeId = $unite;
        } else {
            $this->errors->badUniteEntreeForBareme($data[1][1]);
        }

        $data[1][2] = trim($data[1][2]);
        if (($unite = $this->uniteRepo->findOneByLibelle($data[1][2]))) {
            $this->uniteSortieId = $unite;
        } else {
            $this->errors->badUniteSortieForBareme($data[1][2]);
        }

        $this->commentaire = trim($data[1][3]);

        // If bad station, retrieving of 'bareme' data will fail => so stops
        if ($this->errors->isDirty()) {
            throw new StopImportException();
        }
    }

    // Reads file data.
    public function read()
    {
        try {
            //We skip the header
            $numLine = self::BAREME_HEADER_SIZE + 1;
            $numDataLines = 0;
            $numCols = 5;
            $minNumCols = 3;

            if (!$this->file = fopen($this->filePath, 'r')) {
                throw new RuntimeException(
                    $this->translator->trans(
                        'Error.file.cantRead(%file%)',
                        ['%file%' => $this->filePath]
                    )
                );
            }

            for ($i = 0; $i < self::BAREME_HEADER_SIZE; ++$i) {
                fgets($this->file);
            }

            while (($line = fgets($this->file))) {
                // If line begins with self::COMMENT => it's a comment
                if ($line[0] !== self::COMMENT && $numLine > self::BAREME_HEADER_SIZE) {
                    // If line contains self::COMMENT => rest of line is a comment
                    $splitLine = strtok($line, self::COMMENT);
                    $data = explode(';', $splitLine);

                    $found = count($data);
                    if ($found > $numCols) {
                        $this->errors->badLineSize($numLine, $found, "$numCols");
                        throw new StopImportException();
                    }
                    if ($found < $minNumCols) {
                        $this->errors->badMinLineSize($numLine, $found, "$minNumCols");
                        throw new StopImportException();
                    }

                    $this->treatLine($data, $numLine);
                    ++$numDataLines;
                }
                ++$numLine;
            }

            // File must contain at least 2 data lines
            if ($numDataLines < 2) {
                $this->errors->notEnoughData(2);
                throw new StopImportException();
            }

            return $numDataLines;
        } catch (\Exception $e) {
            if (!($e instanceof StopImportException)) {
                $this->errors->addError($e->getMessage());
            }
            $this->clean();
        }
    }

    protected function treatLine($line, $numLine)
    {
        /**
         * One line should be : x ; y ; quality ; min (opt.) ; max (opt.).
         */
        $nData = sizeof($line);
        $x = trim($line[0]);
        $y = trim($line[1]);
        $qualityCode = trim($line[2]);
        $min = ($nData > 3) ? trim($line[3]) : '';
        $max = ($nData > 4) ? trim($line[4]) : '';

        $this->processBaremeLine($x, $y, $qualityCode, $min, $max, $numLine);
    }

    /**
     * Loads metadata from file header.
     */
    final public function loadHeader()
    {
        try {
            // Can't continue if there are errors
            if ($this->errors->isDirty()) {
                throw new RuntimeException(
                    $this->translator->trans(
                        'controle.import.thereAreErrors.cantContinue'
                    )
                );
            }

            // Opens the file
            if (!$this->file = fopen($this->filePath, 'r')) {
                throw new RuntimeException(
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
            if (!($e instanceof StopImportException)) {
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
                throw new RuntimeException(
                    $this->translator->trans(
                        'controle.import.thereAreErrors.cantContinue'
                    )
                );
            }

            $bareme = new Bareme();
            $bareme->setNom($this->nom);
            $bareme->setUniteEntree($this->uniteRepo->findOneById($this->uniteEntreeId));
            $bareme->setUniteSortie($this->uniteRepo->findOneById($this->uniteSortieId));
            $bareme->setValeurs($this->baremeGrid);
            $bareme->setCommentaire($this->commentaire);
            $bareme->setObservatoire($this->getBdohRepo('Observatoire')->getCurrentObservatoire());
            $bareme->setDateCreation(new \DateTime('now', new \DateTimeZone('UTC')));

            $this->em->persist($bareme);
            $this->em->flush();

            $values = $bareme->getValeurs();

            return [
                'nom'           => $this->nom,
                'uniteEntree'   => $bareme->getUniteEntree()->getLibelle(),
                'uniteEntreeId' => $bareme->getUniteEntree()->getId(),
                'uniteSortie'   => $bareme->getUniteSortie()->getLibelle(),
                'uniteSortieId' => $bareme->getUniteSortie()->getId(),
                'nValues'       => sizeof($values),
                'xMin'          => $values[0][0],
                'xMax'          => $values[sizeof($values) - 1][0],
                'commentaire'   => $this->commentaire,
                'id'            => $bareme->getId(),
                'dateCreation'  => $bareme->getDateCreation(),
            ];
        } catch (\Exception $e) {
            if (false === ($e instanceof StopImportException)) {
                $this->errors->addError($e->getMessage());
            }
            $this->clean();
        }
    }

    /**
     * Generic actions for processBareme():
     *  Checks that 'x', 'y', 'minimum and 'maximum' are numeric and that 'quality code' is valid.
     *
     * @param mixed $x
     * @param mixed $y
     * @param mixed $qualityCode
     * @param mixed $minimum
     * @param mixed $maximum
     * @param mixed $numLine
     */
    private function processBaremeLine($x, $y, $qualityCode, $minimum, $maximum, $numLine)
    {
        //Checks that 'x', 'y', 'minimum' and 'maximum' are numeric
        //and that 'x' > value on previous line
        if ($x === null || !is_numeric($x)) {
            $this->errors->variableNotNumeric($numLine, 'x', $x);
        }
        if ($y === null || !is_numeric($y)) {
            $this->errors->variableNotNumeric($numLine, 'y', $y);
        }
        if ($this->errors->isClean()) {
            $xDouble = doubleval($x);
            if ($xDouble <= $this->xPrevious) {
                $this->errors->notStrictlyAscending($numLine, $x, $this->xPrevious);
            }
            $this->xPrevious = $xDouble;
        }
        if ($minimum && !is_numeric($minimum)) {
            $this->errors->variableNotNumeric($numLine, 'minimum', $minimum);
        }
        if ($maximum && !is_numeric($maximum)) {
            $this->errors->variableNotNumeric($numLine, 'maximum', $maximum);
        }

        // Try to convert quality code to quality order
        if ($qualityCode === null || !array_key_exists($qualityCode, $this->qualiteMap) || $qualityCode === 'gap') {
            $this->errors->badCodeQualite($numLine, $qualityCode);
        } elseif (\in_array($this->qualiteMap[$qualityCode], Qualite::$ordresLimites)) {
            $this->errors->valueLimitInBareme($numLine, $qualityCode);
        }

        // If too many errors => stops the controles import process !
        if ($this->errors->count > static::MAX_ERRORS_IN_LOAD_DATA) {
            $this->errors->tooManyErrors();
            throw new StopImportException();
        }

        // Fill the bareme grid
        if ($this->errors->isClean()) {
            $this->baremeGrid[] = [$x, $y, strval($this->qualiteMap[$qualityCode]), $minimum, $maximum];
        }
    }

    /**
     * Treatments to do when serializing.
     */
    public function __sleep()
    {
        /* Attributes to serialize */
        return [
            'errors',
            'filePath',
            'nom',
            'uniteEntreeId',
            'uniteSortieId',
            'commentaire',
            'baremeGrid',
        ];
    }

    /**
     * Treatments to do when unserializing.
     * NOTE : run manually this function !
     *
     * @param EntityManagerInterface                                     $em
     * @param TranslatorInterface $translator
     */
    public function wakeup(EntityManagerInterface $em, TranslatorInterface $translator)
    {
        $this->em = $em;
        $this->translator = $translator;
        $this->uniteRepo = $this->getBdohRepo('Unite');
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

        if ($this->filePath) {
            unlink($this->filePath);
            $this->filePath = null;
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
}
