<?php
// SPDX-FileCopyrightText: The BDOH authors
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace Irstea\BdohDataBundle\Exporter\Measure\Csv;

/**
 * Les tables d'un export au format CSV et leurs colonnes (dev/plans/2026-11-13_fair-exports.md) : une seule
 * description, d'où viennent l'en-tête des fichiers, la description CSVW (csv-metadata.json) et celle du README.
 * Les noms de colonnes sont en anglais, comme ce qui se tape ; les descriptions, dans la langue de l'export.
 *
 * Chaque colonne : [nom, type CSVW, unité ou null, description en français, description en anglais].
 */
final class Tables
{
    public const MISSING = 'NA';

    public const MEASURE_COLUMNS = ['station', 'series', 'time', 'time_end', 'value', 'quality', 'value_min', 'value_max'];

    public const SERIES_FILE = 'meta_series.csv';
    public const STATION_FILE = 'meta_station.csv';
    public const QUALITY_FILE = 'meta_quality.csv';
    public const CSVW_FILE = 'csv-metadata.json';

    /**
     * @return array<string, array{title: array{fr: string, en: string}, columns: list<array>, key: list<string>}>
     *                                                                                                                  les tables, par nom de fichier ; « {station}_{series}.csv » pour les mesures
     */
    public static function all(): array
    {
        return [
            '{station}_{series}.csv' => [
                'title'   => [
                    'fr' => "Les valeurs d'une chronique, une ligne par valeur. Toutes ont les mêmes colonnes : mises bout à bout, elles forment une seule table (format long).",
                    'en' => 'The values of one time series, one row per value. All have the same columns: put end to end, they make a single table (long format).',
                ],
                // Sans clé : rien dans la base n'interdit deux valeurs à la même date d'une chronique discontinue
                'key'     => [],
                'columns' => [
                    ['station', 'string', null,
                        'Code de la station (meta_station.csv).',
                        'Code of the station (meta_station.csv).'],
                    ['series', 'string', null,
                        'Code de la chronique, propre à sa station (meta_series.csv).',
                        'Code of the time series, unique within its station (meta_series.csv).'],
                    ['time', 'dateTime', null,
                        "Date et heure de la valeur, ou début de la période qu'elle couvre quand time_end est donné ; ISO 8601, avec le décalage du fuseau horaire choisi à l'export.",
                        'Date and time of the value, or start of the period it covers when time_end is given; ISO 8601, with the offset of the time zone chosen for the export.'],
                    ['time_end', 'dateTime', null,
                        'Fin de la période couverte par la valeur (chronique discontinue, moyenne ou cumul à pas de temps fixe) ; NA pour une valeur à un instant.',
                        'End of the period covered by the value (discontinuous series, mean or cumulative over a fixed time step); NA for a value at an instant.'],
                    ['value', 'double', 'unit (meta_series.csv)',
                        'Valeur, dans l\'unité de la chronique ; NA pour une lacune, dont le code de qualité dit la nature.',
                        'Value, in the unit of the series; NA for a gap, whose quality code tells its kind.'],
                    ['quality', 'string', null,
                        'Code de qualité de la valeur (meta_quality.csv).',
                        'Quality code of the value (meta_quality.csv).'],
                    ['value_min', 'double', 'unit (meta_series.csv)',
                        'Minimum sur la période, quand BDOH le connaît ; NA sinon.',
                        'Minimum over the period, when BDOH has it; NA otherwise.'],
                    ['value_max', 'double', 'unit (meta_series.csv)',
                        'Maximum sur la période, quand BDOH le connaît ; NA sinon.',
                        'Maximum over the period, when BDOH has it; NA otherwise.'],
                ],
            ],
            self::SERIES_FILE => [
                'title'   => [
                    'fr' => "Les chroniques de l'export, une ligne par chronique.",
                    'en' => 'The time series of the export, one row per series.',
                ],
                'key'     => ['station', 'series'],
                'columns' => [
                    ['station', 'string', null, 'Code de la station (meta_station.csv).', 'Code of the station (meta_station.csv).'],
                    ['series', 'string', null, 'Code de la chronique, propre à sa station.', 'Code of the time series, unique within its station.'],
                    ['name', 'string', null, 'Nom de la chronique.', 'Name of the time series.'],
                    ['parameter', 'string', null, 'Paramètre mesuré.', 'Measured parameter.'],
                    ['parameter_code', 'string', null, 'Code du paramètre dans BDOH.', 'Code of the parameter in BDOH.'],
                    ['unit', 'string', null, 'Unité des valeurs exportées : celle des cumuls pour un export en cumul.', 'Unit of the exported values: that of the totals for a cumulative export.'],
                    ['kind', 'string', null,
                        'Nature de la chronique : continuous (valeurs à des instants), discontinuous (valeurs sur des périodes), computed (calculée à partir d\'autres chroniques), converted (convertie d\'une autre chronique).',
                        'Kind of series: continuous (values at instants), discontinuous (values over periods), computed (computed from other series), converted (converted from another series).'],
                    ['sampling', 'string', null,
                        'Ce que représente une valeur enregistrée d\'une chronique continue : instantaneous (valeur à l\'instant), mean (moyenne sur l\'intervalle), cumulative (cumul sur l\'intervalle) ; NA pour une discontinue.',
                        'What a stored value of a continuous series stands for: instantaneous (value at the instant), mean (mean over the interval), cumulative (total over the interval); NA for a discontinuous one.'],
                    ['direction', 'string', null,
                        'Pour une moyenne ou un cumul enregistré : ahead, l\'intervalle commence à la date de la valeur ; backward, il finit à cette date ; NA sinon. Vaut pour un export à l\'identique.',
                        'For a stored mean or cumulative: ahead, the interval starts at the time of the value; backward, it ends at that time; NA otherwise. Applies to an export as stored.'],
                    ['valid_min', 'double', null, 'Seuil de validité bas de la chronique, dans l\'unité où elle est enregistrée ; NA s\'il n\'y en a pas.', 'Lower validity threshold of the series, in the unit it is stored in; NA if none.'],
                    ['valid_max', 'double', null, 'Seuil de validité haut de la chronique, dans l\'unité où elle est enregistrée ; NA s\'il n\'y en a pas.', 'Upper validity threshold of the series, in the unit it is stored in; NA if none.'],
                    ['producer', 'string', null, 'Producteur de la chronique ; NA s\'il n\'est pas renseigné.', 'Producer of the series; NA if not given.'],
                    ['dataset', 'string', null, 'Jeu de données de la chronique ; NA si elle n\'en a pas.', 'Dataset of the series; NA if none.'],
                    ['dataset_doi', 'anyURI', null, 'DOI du jeu de données, à citer ; NA s\'il n\'en a pas.', 'DOI of the dataset, to cite; NA if none.'],
                    ['access', 'string', null,
                        'open : chronique ouverte, sous Licence Ouverte 2.0 ; restricted : chronique en accès restreint, sous les conditions générales de BDOH.',
                        'open: open series, under the Open Licence 2.0; restricted: series with restricted access, under the general terms of use of BDOH.'],
                    ['licence', 'string', null,
                        'Licence de la chronique : etalab-2.0, ou le fichier des conditions générales.',
                        'Licence of the series: etalab-2.0, or the file of the general terms of use.'],
                    ['page', 'anyURI', null, 'Page de la chronique dans BDOH.', 'Page of the series in BDOH.'],
                    ['file', 'string', null, 'Fichier de ses valeurs dans l\'archive ; NA si aucune valeur n\'a été exportée.', 'File of its values in the archive; NA if no value was exported.'],
                    ['n_values', 'integer', null, 'Nombre de valeurs exportées, lacunes non comprises.', 'Number of exported values, gaps excluded.'],
                    ['n_gaps', 'integer', null, 'Nombre de lacunes exportées.', 'Number of exported gaps.'],
                    ['time_first', 'dateTime', null, 'Colonne time de la première ligne du fichier ; NA s\'il n\'y en a pas.', 'Column time of the first row of the file; NA if none.'],
                    ['time_last', 'dateTime', null, 'Colonne time de la dernière ligne du fichier ; NA s\'il n\'y en a pas.', 'Column time of the last row of the file; NA if none.'],
                ],
            ],
            self::STATION_FILE => [
                'title'   => [
                    'fr' => "Les stations des chroniques de l'export, une ligne par station.",
                    'en' => 'The stations of the exported time series, one row per station.',
                ],
                'key'     => ['station'],
                'columns' => [
                    ['station', 'string', null, 'Code de la station.', 'Code of the station.'],
                    ['name', 'string', null, 'Nom de la station.', 'Name of the station.'],
                    ['latitude', 'double', 'degree', 'Latitude, en degrés décimaux, WGS 84 (EPSG:4326) ; NA si la station n\'est pas placée.', 'Latitude, in decimal degrees, WGS 84 (EPSG:4326); NA if the station has no position.'],
                    ['longitude', 'double', 'degree', 'Longitude, en degrés décimaux, WGS 84 (EPSG:4326) ; NA si la station n\'est pas placée.', 'Longitude, in decimal degrees, WGS 84 (EPSG:4326); NA if the station has no position.'],
                    ['altitude', 'double', 'm', 'Altitude ; NA si elle n\'est pas renseignée.', 'Altitude; NA if not given.'],
                    ['municipality', 'string', null, 'Commune ; NA si elle n\'est pas renseignée.', 'Municipality; NA if not given.'],
                    ['insee_code', 'string', null, 'Code INSEE de la commune ; NA s\'il n\'est pas renseigné.', 'INSEE code of the municipality; NA if not given.'],
                    ['sites', 'string', null, 'Sites expérimentaux de la station, séparés par « | ».', 'Experimental sites of the station, separated by "|".'],
                    ['page', 'anyURI', null, 'Page de la station dans BDOH.', 'Page of the station in BDOH.'],
                ],
            ],
            self::QUALITY_FILE => [
                'title'   => [
                    'fr' => "Les codes de qualité de l'observatoire, une ligne par code.",
                    'en' => 'The quality codes of the observatory, one row per code.',
                ],
                'key'     => ['quality'],
                'columns' => [
                    ['quality', 'string', null, 'Code de qualité, tel qu\'il apparaît dans la colonne quality des valeurs.', 'Quality code, as it appears in the quality column of the values.'],
                    ['label', 'string', null, 'Signification du code.', 'Meaning of the code.'],
                    ['gap', 'boolean', null, 'true : le code marque une lacune, sans valeur ; false sinon.', 'true: the code marks a gap, without value; false otherwise.'],
                ],
            ],
        ];
    }

    /** Les noms des colonnes d'une table. */
    public static function names(string $table): array
    {
        return array_map(fn (array $column) => $column[0], self::all()[$table]['columns']);
    }
}
