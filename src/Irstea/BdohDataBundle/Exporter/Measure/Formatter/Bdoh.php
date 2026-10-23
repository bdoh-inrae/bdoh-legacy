<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohDataBundle\Exporter\Measure\Formatter;

use Irstea\BdohBundle\DataTransformer\FromDateTimeMsTransformer;
use Irstea\BdohDataBundle\Util\JeuToJeu;

/**
 * Class Bdoh.
 */
class Bdoh extends AbstractFormatter
{
    const GAP_VALUE = -9999;

    /**
     * @var string
     */
    private $gapCode;

    /**
     * {@inheritdoc}
     */
    public function supportsRange()
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function setJeuToJeu(JeuToJeu $jeuToJeu)
    {
        parent::setJeuToJeu($jeuToJeu);
        $this->gapCode = $this->jeuToJeu->getDefaultGapQualite() ?
            $this->jeuToJeu->getDefaultGapQualite()->getCode() : '';
    }

    protected function writeHeader()
    {
        $this->writeCsv('Station', 'Fuseau', 'Chronique', 'Unite');

        /** @var \DateTimeZone $timeZone */
        $timeZone = $this->extractorReport['timezone'];

        $this->writeCsv(
            $this->chronique->getStation()->getCode(),
            sprintf('UTC%+03d', $timeZone->getOffset(new \DateTime('00:00:00Z')) / 3600),
            $this->chronique->getCode(),
            $this->extractorReport['unite']
        );

        if ($this->extractorReport['isRange']) {
            $this->writeCsv('Debut', 'Fin', 'Valeur', 'Qualite', 'Min', 'Max');
        } else {
            $this->writeCsv('DateHeure', 'Valeur', 'Qualite', 'Min', 'Max');
        }
    }

    /**
     * @param string      $debut
     * @param string|null $fin
     * @param float       $valeur
     * @param string      $qualite
     * @param null        $minimum
     * @param null        $maximum
     *
     * @SuppressWarnings(PHPMD.StaticAccess)
     */
    protected function doWriteMeasure($debut, $fin, $valeur, $qualite, $minimum = null, $maximum = null)
    {
        $line = [FromDateTimeMsTransformer::toFrenchDateTimeMs($debut)];
        if ($fin !== null) {
            $line[] = FromDateTimeMsTransformer::toFrenchDateTimeMs($fin);
        }
        $line[] = $valeur;
        $line[] = $qualite;
        $line[] = $minimum !== null ? $minimum : '';
        $line[] = $maximum !== null ? $maximum : '';

        $this->writeCsv(...$line);
    }

    /**
     * @param string      $debut
     * @param string|null $fin
     */
    protected function writeGap($debut, $fin)
    {
        $this->doWriteMeasure($debut, $fin, self::GAP_VALUE, $this->gapCode);
    }
}
