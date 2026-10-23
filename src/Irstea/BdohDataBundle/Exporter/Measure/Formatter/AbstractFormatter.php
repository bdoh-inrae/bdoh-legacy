<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohDataBundle\Exporter\Measure\Formatter;

use Irstea\BdohDataBundle\Entity\Chronique;
use Irstea\BdohDataBundle\Exporter\Measure\FormatterInterface;
use Irstea\BdohDataBundle\Util\JeuToJeu;

abstract class AbstractFormatter implements FormatterInterface
{
    /**
     * @var
     */
    private $file;

    /**
     * @var JeuToJeu
     */
    protected $jeuToJeu;

    /**
     * @var Chronique
     */
    protected $chronique;

    /**
     * @var array
     */
    protected $extractorReport;

    /**
     * @var int
     */
    protected $lineCount;

    /**
     * @var int
     */
    protected $measureCount;

    /**
     * @var int
     */
    protected $gapCount;

    /**
     * {@inheritdoc}
     */
    public function start(Chronique $chronique, $resource, array $extractorReport)
    {
        $this->chronique = $chronique;
        $this->file = $resource;
        $this->extractorReport = $extractorReport;
        $this->lineCount = 0;
        $this->gapCount = 0;
        $this->measureCount = 0;

        $this->writeHeader();
    }

    protected function writeHeader()
    {
    }

    /**
     * {@inheritdoc}
     */
    public function writeMeasure($debut, $fin, $valeur, $qualite, $minimum = null, $maximum = null)
    {
        if ($valeur === null || $this->jeuToJeu->isGapType($qualite)) {
            $this->writeGap($debut, $fin);
            ++$this->gapCount;

            return;
        }

        $qualite = $this->jeuToJeu->codeToCode($qualite);
        $this->doWriteMeasure($debut, $fin, $valeur, $qualite, $minimum, $maximum);
        ++$this->measureCount;
    }

    /**
     * @param string      $debut
     * @param string|null $fin
     * @param float       $valeur
     * @param string      $qualite
     * @param float null  $minimum
     * @param float       $maximum
     */
    abstract protected function doWriteMeasure($debut, $fin, $valeur, $qualite, $minimum = null, $maximum = null);

    /**
     * @param string      $debut
     * @param string|null $fin
     */
    abstract protected function writeGap($debut, $fin);

    /**
     * {@inheritdoc}
     */
    public function done()
    {
        $this->writeFooter();

        return [
            'count' => [
                'lines'    => $this->lineCount,
                'gaps'     => $this->gapCount,
                'measures' => $this->measureCount,
            ],
        ];
    }

    protected function writeFooter()
    {
    }

    /**
     * {@inheritdoc}
     */
    public function setJeuToJeu(JeuToJeu $jeuToJeu)
    {
        $this->jeuToJeu = $jeuToJeu;
    }

    /**
     * {@inheritdoc}
     */
    public function resolveJeuQualite($default)
    {
        return $default;
    }

    /**
     * @param string[] $values
     *
     * @return self
     */
    protected function writeCsv(...$values)
    {
        fwrite($this->file, implode(';', $values) . "\n");
        ++$this->lineCount;

        return $this;
    }
}
