<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohLoggerBundle\Entity;

use Irstea\BdohDataBundle\Entity\ChroniqueCalculee;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * HistoriqueCalculsChroniques.
 */
class HistoriqueCalculsChroniques extends HistoriqueTransformations
{
    const TRANSFO_HEADER = '## ';

    const TRANSFO_SEPARATOR = ' || ';

    const SERIALIZED_DATE_FORMAT = 'Y-m-d H:i:s T';

    /**
     * @param TranslatorInterface $translator
     */
    public function constructDescription(TranslatorInterface $translator)
    {
        $description = $this->getDescription();
        $this->setDescription(is_array($description) ? implode("\n", $description) : $description);
    }

    /**
     * @param ChroniqueCalculee $chronique
     */
    public function serializeDescription(ChroniqueCalculee $chronique)
    {
        /* @var $transformations \Irstea\BdohDataBundle\Entity\Transformation[] */
        $transformations = [$chronique->getPremiereEntree()];
        $hasTwoParents = false;
        if (($secondTransf = $chronique->getSecondeEntree())) {
            $transformations[] = $secondTransf;
            $hasTwoParents = true;
        }

        $descrArray = [
            self::TRANSFO_HEADER . 'Action type: computation',
            self::TRANSFO_HEADER . 'Child time series',
            $chronique->__toString(),
            self::TRANSFO_HEADER . 'Parent time series',
            $transformations[0]->getEntree()->__toString(),
        ];
        if ($hasTwoParents) {
            $descrArray[] = $transformations[1]->getEntree()->__toString();
        }

        $jeuxBaremes = ['Grading scales for 1st parent time series' => $transformations[0]->getJeuBaremeActuel()];
        if ($hasTwoParents) {
            $jeuxBaremes['Grading scales for 2nd parent time series'] = $transformations[1]->getJeuBaremeActuel();
        }
        $tempsPropagations = [];
        /* @var $jeuBareme \Irstea\BdohDataBundle\Entity\JeuBareme */
        foreach ($jeuxBaremes as $headerText => $jeuBareme) {
            $tempsPropagations[] = $jeuBareme->getDelaiPropagation();
            $descrArray[] = self::TRANSFO_HEADER . $headerText;
            $descrArray[] = self::TRANSFO_HEADER . \implode(self::TRANSFO_SEPARATOR, ['Name', 'Creation date, Apply from, Apply to']);
            /* @var $bjb \Irstea\BdohDataBundle\Entity\BaremeJeuBareme */
            foreach ($jeuBareme->getBaremeJeuBaremes() as $bjb) {
                $bareme = $bjb->getBareme();
                $descrArray[] = \implode(
                    self::TRANSFO_SEPARATOR,
                    [
                        $bareme->getNom(),
                        (($dc = $bareme->getDateCreation()) && $bareme->getObservatoire()) ? $dc->format('Y-m-d H:i:s') . ' UTC' : '',
                        ($dv = $bjb->getDebutValidite()) ? $dv->format('Y-m-d H:i:s') . ' UTC' : '',
                        ($fv = $bjb->getFinValidite()) ? $fv->format('Y-m-d H:i:s') . ' UTC' : '',
                    ]
                );
            }
        }

        $descrArray[] = self::TRANSFO_HEADER . 'Coefficient';
        $descrArray[] = ($coeff = $chronique->getFacteurMultiplicatif()) !== null ? $coeff : '';

        $descrArray[] = self::TRANSFO_HEADER . 'Propagation times';
        $descrArray[] = $hasTwoParents ? \implode(self::TRANSFO_SEPARATOR, $tempsPropagations) : '';

        $descrArray[] = self::TRANSFO_HEADER . 'Value limit policies';
        $valueLimitPolicies = [];
        foreach ($jeuxBaremes as $jeuBareme) {
            $transformationPolicy = $jeuBareme->getValueLimitTransformationType();
            $valueLimitPolicies[] = $transformationPolicy ? $transformationPolicy : '';
            $transformationPlaceholder = $jeuBareme->getValueLimitPlaceholder();
            $valueLimitPolicies[] = $transformationPlaceholder !== null ? $transformationPlaceholder : '';
        }
        $descrArray[] = \implode(self::TRANSFO_SEPARATOR, $valueLimitPolicies);

        $this->setDescription(\implode("\n", $descrArray));
    }

    /**
     * @param string $date
     *
     * @return \DateTime|null
     */
    private function unserializeDate(string $stringDate)
    {
        if (!$stringDate) {
            return null;
        }

        if (\strpos($stringDate, ' UTC') === false) {
            // the old serialize date format is Y-m-d
            $stringDate .= ' 00:00:00 UTC';
        }

        return \DateTime::createFromFormat(
            self::SERIALIZED_DATE_FORMAT,
            $stringDate,
            new \DateTimeZone('UTC')
        );
    }

    /**
     * @return array
     */
    public function unserializeDescription()
    {
        $transfoData = [];
        $descriptionArray = \explode("\n", $this->getDescription());

        $iLine = $this->nextNonHeaderLineIndex($descriptionArray, -1);
        if ($iLine !== false) {
            $transfoData['chronique_fille'] = $descriptionArray[$iLine];
        }

        $iLine = $this->nextNonHeaderLineIndex($descriptionArray, $iLine);
        $hasTwoParents = false;
        if ($iLine !== false) {
            $transfoData['chroniques_meres'] = [$descriptionArray[$iLine]];
            if (!$this->isHeaderLine($descriptionArray[$iLine + 1])) {
                ++$iLine;
                $transfoData['chroniques_meres'][] = [$descriptionArray[$iLine]];
                $hasTwoParents = true;
            }
        }

        for ($iParent = 0; $iParent < ($hasTwoParents ? 2 : 1); ++$iParent) {
            $iLine = $this->nextNonHeaderLineIndex($descriptionArray, $iLine);
            $baremes = [];
            while (isset($descriptionArray[$iLine]) && !$this->isHeaderLine($descriptionArray[$iLine])) {
                $bareme = \explode(self::TRANSFO_SEPARATOR, $descriptionArray[$iLine]);
                $baremes[] = [
                    'nom'           => $bareme[0],
                    'date_creation' => $this->unserializeDate($bareme[1]),
                    'date_debut'    => $this->unserializeDate($bareme[2]),
                    'date_fin'      => $this->unserializeDate($bareme[3]),
                ];
                ++$iLine;
            }
            $transfoData['baremes'][] = $baremes;
            --$iLine;
        }

        $iLine = $this->nextNonHeaderLineIndex($descriptionArray, $iLine);
        if ($iLine !== false) {
            $coeff = $descriptionArray[$iLine];
            $transfoData['facteur_multiplicatif'] = $hasTwoParents && \is_numeric($coeff) ? (float) $coeff : null;
        }

        $iLine = $this->nextNonHeaderLineIndex($descriptionArray, $iLine);
        if ($iLine !== false) {
            $times = \explode(self::TRANSFO_SEPARATOR, $descriptionArray[$iLine]);
            $transfoData['temps_propagation'] = $hasTwoParents ? [
                \is_numeric($times[0]) ? (float) $times[0] : null,
                \is_numeric($times[1]) ? (float) $times[1] : null,
            ] : [null, null];
        }

        if ($iLine !== false) {
            $iLine = $this->nextNonHeaderLineIndex($descriptionArray, $iLine);
            $policies = \explode(self::TRANSFO_SEPARATOR, $descriptionArray[$iLine]);
            $transfoData['transformations_limites'] = [
                $policies[0] ? $policies[0] : null,
                \is_numeric($policies[1]) ? $policies[1] : null,
            ];
            if ($hasTwoParents) {
                $transfoData['transformations_limites'][] = $policies[2] ? $policies[2] : null;
                $transfoData['transformations_limites'][] = \is_numeric($policies[3]) ? $policies[3] : null;
            }
        }

        return $transfoData;
    }

    /**
     * @param $line
     *
     * @return bool
     */
    private function isHeaderLine($line)
    {
        return \strpos(\trim($line), self::TRANSFO_HEADER) === 0;
    }

    /**
     * @param $descriptionArray
     * @param $iLine
     *
     * @return bool
     */
    private function nextNonHeaderLineIndex($descriptionArray, $iLine)
    {
        if ($iLine === false) {
            return false;
        }
        ++$iLine;
        while (isset($descriptionArray[$iLine]) && $this->isHeaderLine($descriptionArray[$iLine])) {
            ++$iLine;
        }

        return isset($descriptionArray[$iLine]) ? $iLine : false;
    }
}
