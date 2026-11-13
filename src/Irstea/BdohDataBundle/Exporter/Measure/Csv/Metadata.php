<?php
// SPDX-FileCopyrightText: The BDOH authors
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace Irstea\BdohDataBundle\Exporter\Measure\Csv;

use Doctrine\DBAL\Connection;
use Irstea\BdohDataBundle\Entity\Chronique;
use Irstea\BdohDataBundle\Entity\ChroniqueContinue;
use Irstea\BdohDataBundle\Entity\JeuQualite;
use Irstea\BdohDataBundle\Entity\Station;
use Irstea\BdohDataBundle\Entity\Unite;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Les métadonnées d'un export au format CSV (dev/plans/2026-11-13_fair-exports.md) : les chroniques, leurs stations
 * et les codes de qualité, chacun dans son CSV, et leur description CSVW. Les colonnes : Tables.
 */
final class Metadata
{
    /**
     * @param \Closure(string): bool $isGap ce que le formateur tient pour une lacune (JeuToJeu::isGapType), pour que
     *                                    meta_quality.csv dise la même chose que les NA des fichiers
     */
    public function __construct(
        private Connection $connection,
        private UrlGeneratorInterface $router,
        private string $lang,
        private string $termsFile,
        private \Closure $isGap
    ) {
    }

    /**
     * Une ligne par chronique.
     *
     * @param Chronique[]         $chroniques
     * @param array<string, array> $reports    ce qu'a dit l'extraction de chacune, par identifiant
     */
    public function writeSeries(array $chroniques, array $reports, $output): void
    {
        $this->writeRow($output, Tables::names(Tables::SERIES_FILE));
        foreach ($chroniques as $chronique) {
            /** @var array<string, mixed> $report */
            $report = $reports[$chronique->getId()] ?? [];
            $station = $chronique->getStation();
            $observatoire = $chronique->getObservatoire();
            $continue = $chronique instanceof ChroniqueContinue ? $chronique : null;
            // L'unité des valeurs exportées : celle des cumuls pour un export en cumul (Extractor\Cumulative)
            $unite = ($report['unite'] ?? null) instanceof Unite ? $report['unite'] : $chronique->getUnite();
            $dataset = $chronique->getDataSet();
            $doi = $dataset?->getDoi()?->getIdentifiant();
            $this->writeRow($output, [
                $station->getCode(),
                $chronique->getCode(),
                $this->label($chronique->getLibelle(), $chronique->getLibelleEn()),
                $chronique->getParametre() ? $this->label($chronique->getParametre()->getNom(), $chronique->getParametre()->getNomEn()) : null,
                $chronique->getParametre()?->getCode(),
                $unite ? $this->label($unite->getLibelle(), $unite->getLibelleEn()) : null,
                $this->kind($chronique),
                $continue?->getEchantillonnage()?->getCode(),
                $continue?->getDirectionMesure() ?: null,
                $chronique->getMinimumValide(),
                $chronique->getMaximumValide(),
                $chronique->getProducteur()?->getNom(),
                $dataset ? $this->label($dataset->getTitre(), $dataset->getTitreEn()) : null,
                $doi ? 'https://doi.org/' . preg_replace('#^(?:https?://(?:dx\.)?doi\.org/|doi:)#i', '', $doi) : null,
                $chronique->getEstVisible() ? 'open' : 'restricted',
                $chronique->getEstVisible() ? 'etalab-2.0' : $this->termsFile,
                $this->router->generate('bdoh_consult_chronique', [
                    '_observatoire' => $observatoire->getSlug(), 'station' => $station->getCode(), 'chronique' => $chronique->getCode(),
                ], UrlGeneratorInterface::ABSOLUTE_URL),
                $report['filename'] ?? null,
                $report['count']['measures'] ?? 0,
                $report['count']['gaps'] ?? 0,
                $report['firstTime'] ?? null,
                $report['lastTime'] ?? null,
            ]);
        }
    }

    /**
     * Une ligne par station, la position en WGS 84 tirée du point (les colonnes latitude et longitude de la station
     * portent du Lambert 93).
     *
     * @param Chronique[] $chroniques
     */
    public function writeStations(array $chroniques, $output): void
    {
        /** @var Station[] $stations */
        $stations = [];
        foreach ($chroniques as $chronique) {
            $stations[$chronique->getStation()->getId()] = $chronique->getStation();
        }
        $positions = [];
        if ($stations) {
            $rows = $this->connection->fetchAllAssociative(
                'SELECT id, ST_Y(p) AS lat, ST_X(p) AS lon FROM (SELECT id, ST_Transform(point, 4326) AS p FROM station '
                . 'WHERE id IN (?) AND point IS NOT NULL) s',
                [array_keys($stations)],
                [\Doctrine\DBAL\ArrayParameterType::INTEGER]
            );
            foreach ($rows as $row) {
                $positions[$row['id']] = [round((float) $row['lat'], 6), round((float) $row['lon'], 6)];
            }
        }
        $observatoire = reset($chroniques)->getObservatoire();

        $this->writeRow($output, Tables::names(Tables::STATION_FILE));
        foreach ($stations as $id => $station) {
            $sites = array_map(fn ($site) => $site->getNom(), iterator_to_array($station->getSites()));
            sort($sites);
            $this->writeRow($output, [
                $station->getCode(),
                $station->getNom(),
                $positions[$id][0] ?? null,
                $positions[$id][1] ?? null,
                $station->getAltitude(),
                $station->getCommune()?->getNom(),
                $station->getCommune()?->getCodeInsee(),
                implode(' | ', $sites),
                $this->router->generate('bdoh_consult_station', [
                    '_observatoire' => $observatoire->getSlug(), 'station' => $station->getCode(),
                ], UrlGeneratorInterface::ABSOLUTE_URL),
            ]);
        }
    }

    /** Une ligne par code de qualité du jeu de l'observatoire. */
    public function writeQualities(JeuQualite $jeu, $output): void
    {
        $qualites = iterator_to_array($jeu->getQualites());
        usort($qualites, fn ($a, $b) => [$a->getOrdre(), $a->getCode()] <=> [$b->getOrdre(), $b->getCode()]);

        $this->writeRow($output, Tables::names(Tables::QUALITY_FILE));
        foreach ($qualites as $qualite) {
            $this->writeRow($output, [
                $qualite->getCode(),
                $this->label($qualite->getLibelle(), $qualite->getLibelleEn()),
                ($this->isGap)($qualite->getCode()) ? 'true' : 'false',
            ]);
        }
    }

    /**
     * La description CSVW des tables de l'archive (https://www.w3.org/TR/tabular-metadata/) : les fichiers de
     * mesures partagent un schéma, et leurs colonnes station et series renvoient aux métadonnées.
     *
     * Chaque table porte sa licence : la Licence Ouverte pour les valeurs d'une chronique ouverte et pour les
     * métadonnées, les conditions générales pour celles d'une chronique cachée ; le groupe, seulement si elle est la même
     * pour toutes.
     *
     * @param array<string, bool> $measureFiles les fichiers de mesures de l'archive, et si leur chronique est ouverte
     */
    public function csvw(array $measureFiles, string $title, string $openUrl): string
    {
        $lang = $this->lang === 'en' ? 'en' : 'fr';
        $schema = function (string $table) use ($lang) {
            $definition = Tables::all()[$table];
            $columns = [];
            foreach ($definition['columns'] as [$name, $datatype, $unit, $fr, $en]) {
                $column = [
                    'name'      => $name,
                    'titles'    => $name,
                    'dc:description' => $lang === 'en' ? $en : $fr,
                    // Une date ISO 8601 avec son décalage est la forme lexicale de xsd:dateTime : pas de motif à donner
                    'datatype'  => $datatype,
                    'null'      => Tables::MISSING,
                ];
                if ($unit !== null) {
                    $column['dc:description'] .= ' (' . ($lang === 'en' ? 'unit: ' : 'unité : ') . $unit . ')';
                }
                $columns[] = $column;
            }

            return array_filter(['columns' => $columns, 'primaryKey' => $definition['key'] ?: null]);
        };

        $measureSchema = $schema('{station}_{series}.csv');
        $measureSchema['foreignKeys'] = [
            ['columnReference' => ['station', 'series'], 'reference' => ['resource' => Tables::SERIES_FILE, 'columnReference' => ['station', 'series']]],
            ['columnReference' => 'quality', 'reference' => ['resource' => Tables::QUALITY_FILE, 'columnReference' => 'quality']],
        ];
        $seriesSchema = $schema(Tables::SERIES_FILE);
        $seriesSchema['foreignKeys'] = [
            ['columnReference' => 'station', 'reference' => ['resource' => Tables::STATION_FILE, 'columnReference' => 'station']],
        ];

        $tables = [];
        foreach ($measureFiles as $file => $open) {
            $tables[] = [
                'url'            => $file,
                'dc:description' => Tables::all()['{station}_{series}.csv']['title'][$lang],
                'dc:license'     => ['@id' => $open ? $openUrl : $this->termsFile],
                'tableSchema'    => $measureSchema,
            ];
        }
        foreach ([Tables::SERIES_FILE => $seriesSchema, Tables::STATION_FILE => $schema(Tables::STATION_FILE), Tables::QUALITY_FILE => $schema(Tables::QUALITY_FILE)] as $file => $tableSchema) {
            $tables[] = [
                'url'            => $file,
                'dc:description' => Tables::all()[$file]['title'][$lang],
                'dc:license'     => ['@id' => $openUrl],
                'tableSchema'    => $tableSchema,
            ];
        }
        $licences = array_unique(array_map(fn (array $table) => $table['dc:license']['@id'], $tables));

        return json_encode(array_filter([
            '@context'     => ['http://www.w3.org/ns/csvw', ['@language' => $lang]],
            'dc:title'     => $title,
            'dc:license'   => count($licences) === 1 ? ['@id' => reset($licences)] : null,
            'dc:publisher' => 'INRAE, BDOH',
            'dialect'      => ['delimiter' => ',', 'quoteChar' => '"', 'doubleQuote' => true, 'header' => true, 'encoding' => 'utf-8'],
            'tables'       => $tables,
        ]), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n";
    }

    private function kind(Chronique $chronique): string
    {
        return match (true) {
            $chronique->isCalculee()   => 'computed',
            $chronique->isConvertie()  => 'converted',
            $chronique->isContinue()   => 'continuous',
            default                    => 'discontinuous',
        };
    }

    /** Le libellé dans la langue de l'export, le français à défaut de l'anglais. */
    private function label(?string $fr, ?string $en): ?string
    {
        return $this->lang === 'en' && $en !== null && trim($en) !== '' ? $en : $fr;
    }

    private function writeRow($output, array $values): void
    {
        $values = array_map(fn ($value) => $value === null || $value === '' ? Tables::MISSING : $value, $values);
        fputcsv($output, $values, ',', '"', '', "\n");
    }
}
