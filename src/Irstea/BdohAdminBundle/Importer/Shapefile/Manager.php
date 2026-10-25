<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohAdminBundle\Importer\Shapefile;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Irstea\BdohAdminBundle\Errors\ImportErrors;
use Irstea\BdohDataBundle\Entity\Repository\BassinRepository;
use Irstea\BdohDataBundle\Entity\Repository\CoursEauRepository;
use Irstea\BdohDataBundle\Entity\Repository\StationRepository;
use Irstea\BdohDataBundle\Entity\Station;
use Symfony\Component\Process\Process;
use Symfony\Contracts\Translation\TranslatorInterface;

class Manager
{
    const TMP_TABLE = 'temp.shape';

    protected $em;

    protected $translator;

    protected $tmpTable;

    protected $targettedTable;

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

    public function getTmpTable()
    {
        return $this->tmpTable;
    }

    public function setTmpTable($tmpTable)
    {
        $this->tmpTable = $tmpTable;
    }

    public function getTargettedTable()
    {
        return $this->targettedTable;
    }

    public function setTargettedTable($targettedTable)
    {
        $this->targettedTable = $targettedTable;
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
            'tmpTable',
            'targettedTable',
        ];
    }

    public function __construct(EntityManagerInterface $em, TranslatorInterface $translator, $file, $table, ?ImportErrors $errors = null)
    {
        $this->em = $em;
        $this->translator = $translator;
        $this->filePath = $file;
        $this->errors = (null === $errors) ? new ImportErrors() : $errors;
        $this->targettedTable = $table;
        $this->tmpTable = static::TMP_TABLE . rand();
    }

    /**
     * Charge le shapefile dans la table temporaire : shp2pgsql écrit le SQL, que psql exécute. Comme
     * l'ancien, la sortie de shp2pgsql va dans <fichier>_Success.log, ses erreurs dans
     * <fichier>_Errors.log, que le contrôleur lit, et celle de psql dans <fichier>_Psql.log. Les
     * commandes partent sans shell, arguments en liste, et le mot de passe par l'environnement.
     */
    public function loadShapefile()
    {
        $shp2pgsql = new Process(['shp2pgsql', '-s', (string) Station::SRID, '-ciD', $this->filePath, $this->tmpTable]);
        $shp2pgsql->setTimeout(null);
        $shp2pgsql->run();
        file_put_contents($this->filePath . '_Success.log', $shp2pgsql->getOutput());
        file_put_contents($this->filePath . '_Errors.log', $shp2pgsql->getErrorOutput());

        $params = $this->em->getConnection()->getParams();
        $psql = new Process(
            ['psql', '-h', $params['host'], '-p', (string) ($params['port'] ?? 5432), '-d', $params['dbname'],
             '-U', $params['user'], '-f', $this->filePath . '_Success.log'],
            null,
            ['PGPASSWORD' => $params['password'] ?? '']
        );
        $psql->setTimeout(null);
        $psql->run();
        file_put_contents($this->filePath . '_Psql.log', $psql->getOutput() . $psql->getErrorOutput());
    }

    final public function importData($importType, $user)
    {
        try {
            // Can't continue if there are errors
            if ($this->errors->isDirty()) {
                throw new \RuntimeException(
                    $this->translator->trans(
                        'shape.import.thereAreErrors.cantContinue'
                    )
                );
            }
            /** @var BassinRepository|StationRepository|CoursEauRepository $shapeRepo */
            $shapeRepo = $this->getBdohRepo(ucfirst($this->targettedTable));
            if ('keepExisting' === $importType) {
                return $shapeRepo->shapeKeepExistingImport($this->tmpTable, $user);
            } elseif ('overwriteExisting' === $importType) {
                return $shapeRepo->shapeOverwriteExistingImport($this->tmpTable, $user);
            }

            return [0, 0];
        } catch (\Exception $e) {
            $this->errors->addError($e->getMessage());
        }
    }

    /**
     * Cleans the importer.
     */
    final public function clean()
    {
        if ($this->filePath) {
            array_map('unlink', glob($this->filePath . '*'));
            $this->filePath = null;
        }

        // Deletes temporary SQL table
        if ($this->tmpTable) {
            /** @var \Irstea\BdohDataBundle\Entity\Repository\EntityRepository $shapeRepo */
            $shapeRepo = $this->getBdohRepo(ucfirst($this->targettedTable));
            $shapeRepo->dropTemporaryShapeTable($this->tmpTable);
        }
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
    }

    /**
     * Shortcut to return a Doctrine repository.
     *
     * @param string $entity
     *
     * @return EntityRepository
     */
    protected function getRepository($entity)
    {
        return $this->em->getRepository($entity);
    }

    /**
     *  Shortcut to return a Doctrine repository of a BDOH entity.
     *
     * @param string $bdohEntity
     *
     * @return EntityRepository
     */
    protected function getBdohRepo($bdohEntity)
    {
        return $this->getRepository('Irstea\\BdohDataBundle\\Entity\\' . $bdohEntity);
    }
}
