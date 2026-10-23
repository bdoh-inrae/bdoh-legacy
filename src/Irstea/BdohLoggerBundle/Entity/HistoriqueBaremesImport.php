<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohLoggerBundle\Entity;

use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * HistoriqueBaremesImport.
 */
class HistoriqueBaremesImport extends HistoriqueBaremes
{
    /**
     * @var \DateTime
     */
    private $dateCreation;

    /**
     * @var int
     */
    private $nValues;

    /**
     * Set dateCreation.
     *
     * @param \DateTime $dateCreation
     *
     * @return HistoriqueBaremesImport
     */
    public function setDateCreation($dateCreation)
    {
        $this->dateCreation = $dateCreation;

        return $this;
    }

    /**
     * Get dateCreation.
     *
     * @return \DateTime
     */
    public function getDateCreation()
    {
        return $this->dateCreation;
    }

    /**
     * Set nValues.
     *
     * @param int $nValues
     *
     * @return HistoriqueBaremesImport
     */
    public function setNValues($nValues)
    {
        $this->nValues = $nValues;

        return $this;
    }

    /**
     * Get nValues.
     *
     * @return int
     */
    public function getNValues()
    {
        return $this->nValues;
    }

    /**
     * @param TranslatorInterface $translator
     */
    public function constructDescription(TranslatorInterface $translator)
    {
        $this->setDescription(
            $this->actionLine($translator) .
            $this->nameLine($translator) .
            $translator->trans('bareme.dateCreation') . ' ' .
            $this->getDateCreation()->format($translator->trans('transformation.bareme.dateBareme')) . ' ' .
            $translator->trans('UTC') . "\n" .
            $this->unitsLine($translator) .
            $this->getNValues() . ' ' . $translator->trans('bareme.values') . "\n" .
            $this->minMaxLine($translator)
        );
    }
}
