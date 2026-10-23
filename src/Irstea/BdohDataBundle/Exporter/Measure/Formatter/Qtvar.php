<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohDataBundle\Exporter\Measure\Formatter;

use Irstea\BdohDataBundle\Entity\Chronique;
use Irstea\BdohDataBundle\IrsteaBdohDataBundle;

class Qtvar extends AbstractFormatter
{
    const ALT_CODE_KEY = 'hydro2';

    /**
     * @var bool
     */
    private $wasGap;

    /**
     * @var string
     */
    private $codeStation;

    /**
     * {@inheritdoc}
     */
    public function resolveJeuQualite($default)
    {
        return 'Hydro2';
    }

    /**
     * {@inheritdoc}
     */
    public function start(Chronique $chronique, $resource, array $extractorReport)
    {
        $this->codeStation = $chronique->getStation()->getOneCodeAlternatif(self::ALT_CODE_KEY);
        if (!$this->codeStation) {
            $this->codeStation = $chronique->getStation()->getCode();
            //throw new \RuntimeException(sprintf("Missing %s code for %s", self::ALT_CODE_KEY, $station));
        }
        $this->wasGap = false;

        parent::start($chronique, $resource, $extractorReport);
    }

    /**
     * {@ionh.
     */
    protected function writeHeader()
    {
        $unite = IrsteaBdohDataBundle::slugify($this->extractorReport['unite']);

        /*$beginEnd = $this->getBdohRepo('Chronique')->getFirstLastDates($this->chronique);
        $beginDateAndTime = self::getHydro2DateTime($beginEnd[0]);
        $endDateAndTime = self::getHydro2DateTime($beginEnd[1]);*/
        $beginEnd = [
            $this->extractorReport['first'],
            $this->extractorReport['last'],
        ];

        $this
            ->writeCsv(
                'DEC',
                '. 6 13'
            )
            ->writeCsv(
                'DEB',
                'EXP-HYDRO',
                'EXPORT DE DONNEES A PARTIR DE BDOH',
                date('Ymd'),
                'IRSTEA',
                '1997-1',
                '',
                '',
                ''
            )
            ->writeCsv(
                '950',
                $this->codeStation,
                IrsteaBdohDataBundle::slugify($this->chronique->getStation()->getNom()),
                $this->chronique->getStation()->getAltitude(),
                null, // surface BV
                null, // surface,
                null, // num producteur,
                ''
            )
            ->writeCsv(
                '919',
                '1',
                null, // unknown date
                $this->codeStation,
                $this->codeStation,
                'QTVAR',
                $this->formatDateTime($beginEnd[0]),
                $this->formatDateTime($beginEnd[1]),
                '5',
                $unite,
                ''
            );
    }

    /**
     * {@inheritdoc}
     */
    protected function doWriteMeasure($debut, $fin, $valeur, $qualite, $minimum = null, $maximum = null)
    {
        $this->writeCsv(
            '920',
            '1',
            $this->codeStation,
            $this->formatDateTime($debut),
            $valeur,
            $qualite,
            $this->wasGap ? '1' : '2',
            // Comment column ; present in example file but not in specification...
            ''
        );
        $this->wasGap = false;
    }

    /**
     * {@inheritdoc}
     */
    protected function writeGap($debut, $fin)
    {
        $this->wasGap = true;
    }

    /**
     * {@inheritdoc}
     */
    public function supportsRange()
    {
        return false;
    }

    /**
     * {@inheritdoc}
     */
    protected function writeFooter()
    {
        $this->writeCsv('FIN', 'EXP-HYDRO', $this->measureCount, '');
    }

    /**
     * Format d'entrée : YYYY-MM-AA hh:mm:ss
     * Format de sortie : YYYYMMAA;hh:mm.
     *
     * @param string $datetime
     *
     * @return string
     */
    protected function formatDateTime($datetime)
    {
        return substr($datetime, 0, 4)
            . substr($datetime, 5, 2)
            . substr($datetime, 8, 2)
            . ';'
            . substr($datetime, 11, 5);
    }
}
