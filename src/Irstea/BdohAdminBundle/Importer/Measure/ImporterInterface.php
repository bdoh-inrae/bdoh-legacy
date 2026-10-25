<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohAdminBundle\Importer\Measure;

use Doctrine\ORM\EntityManagerInterface;
use Irstea\BdohAdminBundle\Errors\ImportErrors;
use Irstea\BdohDataBundle\Entity\Chronique;
use Irstea\BdohDataBundle\Entity\Repository\ChroniqueContinueRepository;
use Irstea\BdohDataBundle\Entity\Repository\ChroniqueDiscontinueRepository;
use Irstea\BdohDataBundle\Entity\Station;
use Irstea\BdohSecurityBundle\Entity\Utilisateur;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * ImporterInterface is the interface implemented by all "Measure Importer" classes.
 */
interface ImporterInterface
{
    /**
     * @return ImportErrors
     */
    public function getErrors();

    /**
     * @return string
     */
    public function getChroniqueType();

    /**
     * @return int
     */
    public function getExpectedChroniques();

    /**
     * @param $chroniqueId
     *
     * @return array
     */
    public function getValeurTooSmall($chroniqueId);

    /**
     * @param $chroniqueId
     *
     * @return array
     */
    public function getValeurTooLarge($chroniqueId);

    /**
     * Loads metadata from file header.
     */
    public function loadHeader();

    /**
     * Loads file data.
     */
    public function loadData();

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
     */
    public function importData($importTypes, $user, EventDispatcherInterface $dispatcher);

    /**
     * If validation/transformation of given timezone by the validateTimezone() function is OK :
     *      => sets "timezone" attribute with the transformed/validated timezone.
     * Else => sets an error.
     *
     * @param string $timezone
     */
    public function setTimezone($timezone);

    /**
     * Gets the timezone.
     *
     * @return string
     */
    public function getTimezone();

    /**
     * Sets the 'station' for import.
     * If 'station' not found by a code => sets an error.
     *
     * @param Station|string|null $station may be an instance of 'Station', a code of 'station' or null
     */
    public function setStation($station);

    /**
     * Gets the 'station' for import.
     *
     * @return \Irstea\BdohDataBundle\Entity\Station
     */
    public function getStation();

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
    public function setChronique($chronique, $n, $unite = null);

    /**
     * Gets all 'chroniques'.
     *
     * @return array
     */
    public function getChroniques();

    /**
     * Determines if some 'chroniques' have been added.
     *
     * @return bool
     */
    public function hasChroniques();

    /**
     * Determines if the n-th 'chronique' has been selected by user.
     *
     * @param int $n
     *
     * @return bool
     */
    public function isSelectedChronique($n);

    /**
     * Treatments to do when unserializing.
     * NOTE : run manually this function !
     *
     * @param EntityManagerInterface $em
     * @param TranslatorInterface    $translator
     */
    public function wakeup(EntityManagerInterface $em, TranslatorInterface $translator);

    /**
     * Cleans the importer.
     */
    public function clean();

    /**
     * Returns the good 'chronique' repository, depending on $typeOfMeasure.
     *
     * @return ChroniqueContinueRepository|ChroniqueDiscontinueRepository
     */
    public function getChroniqueRepo();

    /**
     * @param bool $propagate
     */
    public function setPropagate($propagate);
}
