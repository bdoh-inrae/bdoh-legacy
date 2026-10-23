<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohLoggerBundle\Entity;

use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * HistoriqueBaremes.
 */
class HistoriqueBaremes extends HistoriqueTransformations
{
    /**
     * @var string
     */
    private $name;

    /**
     * @var string
     */
    private $inputUnit;

    /**
     * @var string
     */
    private $outputUnit;

    /**
     * @var float
     */
    private $minAbscissa;

    /**
     * @var float
     */
    private $maxAbscissa;

    /**
     * Set name.
     *
     * @param string $name
     *
     * @return HistoriqueBaremes
     */
    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Get name.
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Set inputUnit.
     *
     * @param string $inputUnit
     *
     * @return HistoriqueBaremes
     */
    public function setInputUnit($inputUnit)
    {
        $this->inputUnit = $inputUnit;

        return $this;
    }

    /**
     * Get inputUnit.
     *
     * @return string
     */
    public function getInputUnit()
    {
        return $this->inputUnit;
    }

    /**
     * Set outputUnit.
     *
     * @param string $outputUnit
     *
     * @return HistoriqueBaremes
     */
    public function setOutputUnit($outputUnit)
    {
        $this->outputUnit = $outputUnit;

        return $this;
    }

    /**
     * Get outputUnit.
     *
     * @return string
     */
    public function getOutputUnit()
    {
        return $this->outputUnit;
    }

    /**
     * Set minAbscissa.
     *
     * @param float $minAbscissa
     *
     * @return HistoriqueBaremes
     */
    public function setMinAbscissa($minAbscissa)
    {
        $this->minAbscissa = $minAbscissa;

        return $this;
    }

    /**
     * Get minAbscissa.
     *
     * @return float
     */
    public function getMinAbscissa()
    {
        return $this->minAbscissa;
    }

    /**
     * Set maxAbscissa.
     *
     * @param float $maxAbscissa
     *
     * @return HistoriqueBaremes
     */
    public function setMaxAbscissa($maxAbscissa)
    {
        $this->maxAbscissa = $maxAbscissa;

        return $this;
    }

    /**
     * Get maxAbscissa.
     *
     * @return float
     */
    public function getMaxAbscissa()
    {
        return $this->maxAbscissa;
    }

    /**
     * @param TranslatorInterface $translator
     *
     * @return string
     */
    protected function nameLine(TranslatorInterface $translator)
    {
        return $translator->trans('historique.bareme') . $translator->trans('deux_points') . ' ' .
            $this->getName() . "\n";
    }

    /**
     * @param TranslatorInterface $translator
     *
     * @return string
     */
    protected function unitsLine(TranslatorInterface $translator)
    {
        return $translator->trans(
            'bareme.units(%inputUnit%, %outputUnit%)',
            [
                    '%inputUnit%'  => $this->getInputUnit(),
                    '%outputUnit%' => $this->getOutputUnit(),
                ]
        ) . "\n";
    }

    /**
     * @param TranslatorInterface $translator
     *
     * @return string
     */
    protected function minMaxLine(TranslatorInterface $translator)
    {
        return $translator->trans(
            'bareme.xMinMax(%xMin%, %xMax%)',
            [
                    '%xMin%' => $this->getMinAbscissa(),
                    '%xMax%' => $this->getMaxAbscissa(),
                ]
        ) . "\n";
    }
}
