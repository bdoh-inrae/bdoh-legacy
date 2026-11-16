<?php
// SPDX-FileCopyrightText: The BDOH authors
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace Irstea\BdohDataBundle\Exporter\Measure\Formatter;

use Irstea\BdohDataBundle\Entity\Unite;
use Irstea\BdohDataBundle\Exporter\Measure\Csv\Tables;

/**
 * Le format CSV (dev/plans/2026-11-13_fair-exports.md) : un fichier par chronique, aux mêmes colonnes pour toutes,
 * station et chronique comprises, si bien que les mettre bout à bout donne le format long. UTF-8, virgule, point
 * décimal, une ligne d'en-tête, dates ISO 8601 avec le décalage du fuseau choisi, `NA` pour une valeur absente. Chaque
 * ligne porte l'unité de sa valeur (Lou, 2026-11-16), celle que donne meta_series.csv. Les colonnes et leur
 * description : Csv\Tables.
 */
class Csv extends AbstractFormatter
{
    /** @var string le décalage du fuseau, « +01:00 » */
    private $offset = '+00:00';

    /** @var string|null */
    private $gapCode;

    /** @var string la langue de l'export, pour le libellé de l'unité, comme dans meta_series.csv */
    private $lang = 'fr';

    /** @var string|null l'unité des valeurs écrites */
    private $unit;

    /** @var string|null la première et la dernière date écrites, pour meta_series.csv */
    private $firstTime;

    /** @var string|null */
    private $lastTime;

    public function supportsRange()
    {
        return true;
    }

    public function setLang(string $lang): void
    {
        $this->lang = $lang;
    }

    protected function writeHeader()
    {
        // Le décalage que prend le SQL des extractions (ChroniqueRepository::getTzCorrectionForSql)
        $this->offset = (new \DateTime('2001-01-01T00:00:00Z'))->setTimezone($this->extractorReport['timezone'])->format('P');
        $gap = $this->jeuToJeu->getDefaultGapQualite();
        $this->gapCode = $gap ? $gap->getCode() : null;
        $this->firstTime = $this->lastTime = null;
        // L'unité des valeurs exportées, celle des cumuls pour un export en cumul (Extractor\Cumulative), dans la langue
        // de l'export : ce qu'écrit Csv\Metadata::writeSeries
        $unite = ($this->extractorReport['unite'] ?? null) instanceof Unite ? $this->extractorReport['unite'] : $this->chronique->getUnite();
        $english = $unite && $this->lang === 'en' ? trim((string) $unite->getLibelleEn()) : '';
        $this->unit = $unite ? ($english !== '' ? $unite->getLibelleEn() : $unite->getLibelle()) : null;
        $this->writeRow(Tables::MEASURE_COLUMNS);
    }

    /**
     * Une lacune garde sa ligne, sans valeur, avec son propre code (« lacune », « invalide »…), que le format BDOH
     * ramène au code de lacune par défaut.
     */
    public function writeMeasure($debut, $fin, $valeur, $qualite, $minimum = null, $maximum = null)
    {
        if ($valeur === null || $this->jeuToJeu->isGapType($qualite)) {
            // Une valeur absente sous un code qui n'est pas une lacune prend le code de lacune par défaut, comme en BDOH
            $code = $this->jeuToJeu->isGapType($qualite) ? ($this->jeuToJeu->codeToCode($qualite) ?? $this->gapCode) : $this->gapCode;
            $this->writeLine($debut, $fin, null, $code, null, null);
            ++$this->gapCount;

            return;
        }

        $this->writeLine($debut, $fin, $valeur, $this->jeuToJeu->codeToCode($qualite), $minimum, $maximum);
        ++$this->measureCount;
    }

    protected function doWriteMeasure($debut, $fin, $valeur, $qualite, $minimum = null, $maximum = null)
    {
        $this->writeLine($debut, $fin, $valeur, $qualite, $minimum, $maximum);
    }

    protected function writeGap($debut, $fin)
    {
        $this->writeLine($debut, $fin, null, $this->gapCode, null, null);
    }

    public function done()
    {
        return array_merge(parent::done(), ['firstTime' => $this->firstTime, 'lastTime' => $this->lastTime]);
    }

    private function writeLine($debut, $fin, $valeur, $qualite, $minimum, $maximum)
    {
        $time = $this->isoDate($debut);
        $this->firstTime ??= $time;
        $this->lastTime = $time;
        $this->writeRow([
            $this->chronique->getStation()->getCode(),
            $this->chronique->getCode(),
            $time,
            $fin === null ? Tables::MISSING : $this->isoDate($fin),
            $valeur ?? Tables::MISSING,
            $this->unit,
            $qualite ?? Tables::MISSING,
            $minimum ?? Tables::MISSING,
            $maximum ?? Tables::MISSING,
        ]);
    }

    /**
     * « 2024-03-01 12:00:00(.mmm) », à l'heure du fuseau choisi, devient « 2024-03-01T12:00:00+01:00 » ; les
     * millièmes ne s'écrivent que s'ils ne sont pas nuls.
     */
    private function isoDate($date)
    {
        if (!preg_match('/^(\d{4}-\d\d-\d\d)[ T](\d\d:\d\d:\d\d)(\.\d+)?/', (string) $date, $m)) {
            return (string) $date;
        }
        $fraction = isset($m[3]) && (int) substr($m[3], 1) !== 0 ? $m[3] : '';

        return $m[1] . 'T' . $m[2] . $fraction . $this->offset;
    }

    private function writeRow(array $values)
    {
        $values = array_map(fn ($value) => $value === null || $value === '' ? Tables::MISSING : $value, $values);
        fputcsv($this->file, $values, ',', '"', '', "\n");
        ++$this->lineCount;
    }
}
