<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohDataBundle\Exporter\Measure;

use Irstea\BdohDataBundle\Entity\Chronique;
use Irstea\BdohDataBundle\Util\JeuToJeu;

/**
 * Interface FormatterInterface.
 */
interface FormatterInterface
{
    /**
     * @param Chronique $chronique
     * @param resource  $resource
     * @param array     $extractorReport
     */
    public function start(Chronique $chronique, $resource, array $extractorReport);

    /**
     * @param string      $debut
     * @param string|null $fin
     * @param float       $valeur
     * @param string      $qualite
     * @param float null  $minimum
     * @param float       $maximum
     */
    public function writeMeasure($debut, $fin, $valeur, $qualite, $minimum = null, $maximum = null);

    /**
     * @return array
     */
    public function done();

    /**
     * @param mixed $codeJeu
     *
     * @return string
     */
    public function resolveJeuQualite($codeJeu);

    /**
     * @param JeuToJeu $jeuToJeu
     */
    public function setJeuToJeu(JeuToJeu $jeuToJeu);

    /**
     * @return bool
     */
    public function supportsRange();
}
