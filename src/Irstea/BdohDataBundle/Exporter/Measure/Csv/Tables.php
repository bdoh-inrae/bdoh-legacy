<?php
// SPDX-FileCopyrightText: The BDOH authors
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace Irstea\BdohDataBundle\Exporter\Measure\Csv;

/**
 * Les tables d'un export au format CSV et leurs colonnes (dev/plans/2026-11-13_fair-exports.md) : une seule
 * description, d'où viennent l'en-tête des fichiers, la description CSVW (csv-metadata.json) et celle du README.
 * Les noms de colonnes sont en anglais, comme ce qui se tape ; les descriptions, dans la langue de l'export.
 *
 * Chaque colonne : [nom, type CSVW, unité ou null, description en français, description en anglais, titre en
 * français]. Le titre anglais d'une colonne est son nom, celui de l'en-tête, comme le veut CSVW pour les relier ; le
 * titre français ne sert qu'à la description CSVW, qui porte les deux langues (Lou, 2026-11-19).
 */
final class Tables
{
    public const MISSING = 'NA';

    public const MEASURE_COLUMNS = ['station', 'series', 'parameter', 'time', 'time_end', 'value', 'unit', 'quality', 'value_min', 'value_max'];

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
                        'Code of the station (meta_station.csv).', 'station'],
                    ['series', 'string', null,
                        'Code de la chronique, propre à sa station (meta_series.csv).',
                        'Code of the time series, unique within its station (meta_series.csv).', 'chronique'],
                    // Le code du paramètre, pour prendre tous les débits ou toutes les températures de la table longue
                    // sans jointure : le code d'une chronique, choisi par chaque gestionnaire, ne le permet pas
                    // (Lou, 2026-11-19 ; dev/plans/2026-11-16_reopening.md, point 2d)
                    ['parameter', 'string', null,
                        'Code du paramètre mesuré, le même pour toutes les chroniques de ce paramètre, quel que soit leur code (meta_series.csv) ; NA s\'il n\'est pas renseigné.',
                        'Code of the measured parameter, the same for all the series of this parameter, whatever their code (meta_series.csv); NA if not given.', 'paramètre'],
                    ['time', 'dateTime', null,
                        "Date et heure de la valeur, ou début de la période qu'elle couvre quand time_end est donné ; ISO 8601, avec le décalage du fuseau horaire choisi à l'export.",
                        'Date and time of the value, or start of the period it covers when time_end is given; ISO 8601, with the offset of the time zone chosen for the export.', 'date'],
                    ['time_end', 'dateTime', null,
                        'Fin de la période couverte par la valeur (chronique discontinue, moyenne ou cumul à pas de temps fixe) ; NA pour une valeur à un instant.',
                        'End of the period covered by the value (discontinuous series, mean or cumulative over a fixed time step); NA for a value at an instant.', 'date de fin'],
                    ['value', 'double', 'column unit',
                        'Valeur, dans l\'unité que donne la colonne unit ; NA pour une lacune, dont le code de qualité dit la nature.',
                        'Value, in the unit given by the unit column; NA for a gap, whose quality code tells its kind.', 'valeur'],
                    // L'unité, dans chaque fichier de données (Lou, 2026-11-16) : une table de valeurs se lit sans les
                    // métadonnées, et le format long, fichiers mis bout à bout, garde l'unité de chaque ligne
                    ['unit', 'string', null,
                        'Unité de la valeur, de son minimum et de son maximum, la même sur toutes les lignes du fichier et dans meta_series.csv : celle des cumuls pour un export en cumul.',
                        'Unit of the value, of its minimum and of its maximum, the same on every row of the file and in meta_series.csv: that of the totals for a cumulative export.', 'unité'],
                    ['quality', 'string', null,
                        'Code de qualité de la valeur (meta_quality.csv).',
                        'Quality code of the value (meta_quality.csv).', 'qualité'],
                    ['value_min', 'double', 'column unit',
                        'Minimum sur la période, quand BDOH le connaît ; NA sinon.',
                        'Minimum over the period, when BDOH has it; NA otherwise.', 'valeur minimale'],
                    ['value_max', 'double', 'column unit',
                        'Maximum sur la période, quand BDOH le connaît ; NA sinon.',
                        'Maximum over the period, when BDOH has it; NA otherwise.', 'valeur maximale'],
                ],
            ],
            self::SERIES_FILE => [
                'title'   => [
                    'fr' => "Les chroniques de l'export, une ligne par chronique.",
                    'en' => 'The time series of the export, one row per series.',
                ],
                'key'     => ['station', 'series'],
                'columns' => [
                    ['station', 'string', null, 'Code de la station (meta_station.csv).', 'Code of the station (meta_station.csv).', 'station'],
                    ['series', 'string', null, 'Code de la chronique, propre à sa station.', 'Code of the time series, unique within its station.', 'chronique'],
                    ['name', 'string', null, 'Nom de la chronique.', 'Name of the time series.', 'nom'],
                    // Le code du paramètre sous le nom parameter, comme dans les fichiers de valeurs : dans toutes les tables,
                    // une colonne nue porte le code qui sert aux jointures (station, series, parameter), son nom à côté
                    ['parameter', 'string', null, 'Code du paramètre mesuré dans BDOH, comme dans les fichiers de valeurs.', 'Code of the measured parameter in BDOH, as in the value files.', 'paramètre'],
                    ['parameter_name', 'string', null, 'Nom du paramètre mesuré.', 'Name of the measured parameter.', 'nom du paramètre'],
                    ['unit', 'string', null, 'Unité des valeurs exportées : celle des cumuls pour un export en cumul.', 'Unit of the exported values: that of the totals for a cumulative export.', 'unité'],
                    ['kind', 'string', null,
                        'Nature de la chronique : continuous (valeurs à des instants), discontinuous (valeurs sur des périodes), computed (calculée à partir d\'autres chroniques), converted (convertie d\'une autre chronique).',
                        'Kind of series: continuous (values at instants), discontinuous (values over periods), computed (computed from other series), converted (converted from another series).', 'nature'],
                    ['sampling', 'string', null,
                        'Ce que représente une valeur enregistrée d\'une chronique continue : instantaneous (valeur à l\'instant), mean (moyenne sur l\'intervalle), cumulative (cumul sur l\'intervalle) ; NA pour une discontinue.',
                        'What a stored value of a continuous series stands for: instantaneous (value at the instant), mean (mean over the interval), cumulative (total over the interval); NA for a discontinuous one.', 'échantillonnage'],
                    ['direction', 'string', null,
                        'Pour une moyenne ou un cumul enregistré : ahead, l\'intervalle commence à la date de la valeur ; backward, il finit à cette date ; NA sinon. Vaut pour un export à l\'identique.',
                        'For a stored mean or cumulative: ahead, the interval starts at the time of the value; backward, it ends at that time; NA otherwise. Applies to an export as stored.', 'direction'],
                    ['valid_min', 'double', null, 'Seuil de validité bas de la chronique, dans l\'unité où elle est enregistrée ; NA s\'il n\'y en a pas.', 'Lower validity threshold of the series, in the unit it is stored in; NA if none.', 'seuil de validité bas'],
                    ['valid_max', 'double', null, 'Seuil de validité haut de la chronique, dans l\'unité où elle est enregistrée ; NA s\'il n\'y en a pas.', 'Upper validity threshold of the series, in the unit it is stored in; NA if none.', 'seuil de validité haut'],
                    ['producer', 'string', null, 'Producteur de la chronique ; NA s\'il n\'est pas renseigné.', 'Producer of the series; NA if not given.', 'producteur'],
                    ['dataset', 'string', null, 'Jeu de données de la chronique ; NA si elle n\'en a pas.', 'Dataset of the series; NA if none.', 'jeu de données'],
                    ['dataset_doi', 'anyURI', null, 'DOI du jeu de données, à citer ; NA s\'il n\'en a pas.', 'DOI of the dataset, to cite; NA if none.', 'DOI du jeu de données'],
                    ['access', 'string', null,
                        'open : chronique ouverte, sous Licence Ouverte 2.0 ; restricted : chronique en accès restreint, sous les conditions générales de BDOH.',
                        'open: open series, under the Open Licence 2.0; restricted: series with restricted access, under the general terms of use of BDOH.', 'accès'],
                    ['licence', 'string', null,
                        'Licence de la chronique : etalab-2.0, ou le fichier des conditions générales.',
                        'Licence of the series: etalab-2.0, or the file of the general terms of use.', 'licence'],
                    // La date de dernière mise à jour, que la Licence Ouverte demande de mentionner (Lou, 2026-11-19 ;
                    // dev/plans/2026-11-16_reopening.md, point 4) : Exporter\Measure\Updates
                    ['updated', 'date', null,
                        'Date de la dernière mise à jour des valeurs de la chronique dans BDOH, à mentionner avec la source : son dernier calcul pour une chronique calculée ou convertie, son dernier import ou sa dernière suppression de valeurs sinon ; NA si BDOH n\'en garde pas trace (son historique commence en février 2013).',
                        'Date of the last update of the values of the series in BDOH, to mention with the source: its last computation for a computed or converted series, its last import or deletion of values otherwise; NA if BDOH keeps no record of it (its history starts in February 2013).', 'mise à jour'],
                    ['page', 'anyURI', null, 'Page de la chronique dans BDOH.', 'Page of the series in BDOH.', 'page'],
                    ['file', 'string', null, 'Fichier de ses valeurs dans l\'archive ; NA si aucune valeur n\'a été exportée.', 'File of its values in the archive; NA if no value was exported.', 'fichier'],
                    ['n_values', 'integer', null, 'Nombre de valeurs exportées, lacunes non comprises.', 'Number of exported values, gaps excluded.', 'nombre de valeurs'],
                    ['n_gaps', 'integer', null, 'Nombre de lacunes exportées.', 'Number of exported gaps.', 'nombre de lacunes'],
                    ['time_first', 'dateTime', null, 'Colonne time de la première ligne du fichier ; NA s\'il n\'y en a pas.', 'Column time of the first row of the file; NA if none.', 'première date'],
                    ['time_last', 'dateTime', null, 'Colonne time de la dernière ligne du fichier ; NA s\'il n\'y en a pas.', 'Column time of the last row of the file; NA if none.', 'dernière date'],
                ],
            ],
            self::STATION_FILE => [
                'title'   => [
                    'fr' => "Les stations des chroniques de l'export, une ligne par station.",
                    'en' => 'The stations of the exported time series, one row per station.',
                ],
                'key'     => ['station'],
                'columns' => [
                    ['station', 'string', null, 'Code de la station.', 'Code of the station.', 'station'],
                    ['name', 'string', null, 'Nom de la station.', 'Name of the station.', 'nom'],
                    ['latitude', 'double', 'degree', 'Latitude, en degrés décimaux, WGS 84 (EPSG:4326) ; NA si la station n\'est pas placée.', 'Latitude, in decimal degrees, WGS 84 (EPSG:4326); NA if the station has no position.', 'latitude'],
                    ['longitude', 'double', 'degree', 'Longitude, en degrés décimaux, WGS 84 (EPSG:4326) ; NA si la station n\'est pas placée.', 'Longitude, in decimal degrees, WGS 84 (EPSG:4326); NA if the station has no position.', 'longitude'],
                    ['altitude', 'double', 'm', 'Altitude ; NA si elle n\'est pas renseignée.', 'Altitude; NA if not given.', 'altitude'],
                    ['municipality', 'string', null, 'Commune ; NA si elle n\'est pas renseignée.', 'Municipality; NA if not given.', 'commune'],
                    ['insee_code', 'string', null, 'Code INSEE de la commune ; NA s\'il n\'est pas renseigné.', 'INSEE code of the municipality; NA if not given.', 'code INSEE'],
                    ['sites', 'string', null, 'Sites expérimentaux de la station, séparés par « | ».', 'Experimental sites of the station, separated by "|".', 'sites'],
                    ['page', 'anyURI', null, 'Page de la station dans BDOH.', 'Page of the station in BDOH.', 'page'],
                ],
            ],
            self::QUALITY_FILE => [
                'title'   => [
                    'fr' => "Les codes de qualité de l'observatoire, une ligne par code.",
                    'en' => 'The quality codes of the observatory, one row per code.',
                ],
                'key'     => ['quality'],
                'columns' => [
                    ['quality', 'string', null, 'Code de qualité, tel qu\'il apparaît dans la colonne quality des valeurs.', 'Quality code, as it appears in the quality column of the values.', 'qualité'],
                    ['label', 'string', null, 'Signification du code.', 'Meaning of the code.', 'signification'],
                    ['gap', 'boolean', null, 'true : le code marque une lacune, sans valeur ; false sinon.', 'true: the code marks a gap, without value; false otherwise.', 'lacune'],
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
