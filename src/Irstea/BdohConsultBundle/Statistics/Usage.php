<?php
// SPDX-FileCopyrightText: The BDOH authors
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace Irstea\BdohConsultBundle\Statistics;

use Doctrine\DBAL\Connection;
use Irstea\BdohDataBundle\Entity\Chronique;
use Irstea\BdohDataBundle\Entity\Observatoire;

/**
 * Les chiffres de l'usage de BDOH (Lou, 2026-11-12 et 16 ; dev/plans/2026-11-16_reopening.md, point 6), la contrepartie
 * de l'ouverture des données : montrer qu'elles servent. Les téléchargements et la vie d'un observatoire se tirent de
 * l'historique, qui commence en février 2013 ; les consultations, du compteur (series_views).
 *
 * L'historique a deux écritures : avant le 20 novembre 2018, l'action en clair (« Création », « Create »), puis une clé
 * de traduction (historique.actions.ChroniqueCreate). La sorte de l'entrée (dtype) ne change pas. Ses descriptions ne
 * servent pas : elles changent d'une écriture à l'autre. Un téléchargement est l'export fait d'une chronique : une
 * ligne donneesExport, qu'un export de plusieurs chroniques écrit pour chacune.
 */
final class Usage
{
    /** Les actions d'une création, dans les deux écritures de l'historique */
    private const CREATED = "action IN ('Création', 'Create') OR action LIKE 'historique.actions.%Create'";

    /** Les colonnes du tableau par année, dans l'ordre, et la façon de les compter */
    public const YEAR_COLUMNS = [
        'accounts_created'  => "count(*) FILTER (WHERE dtype = 'administrationUtilisateur' AND (" . self::CREATED . '))',
        'stations_created'  => "count(*) FILTER (WHERE dtype = 'administrationStation' AND (" . self::CREATED . '))',
        'series_created'    => "count(*) FILTER (WHERE dtype = 'administrationChronique' AND (" . self::CREATED . '))',
        'imports'           => "count(*) FILTER (WHERE dtype = 'donneesImport')",
        'imported_values'   => "coalesce(sum(nombremesures) FILTER (WHERE dtype = 'donneesImport'), 0)",
        'checkpoint_imports' => "count(*) FILTER (WHERE dtype = 'donneesPointControle' AND action IN ('import', 'historique.actions.PointControleImport'))",
        'computations'      => "count(*) FILTER (WHERE dtype IN ('calculsChroniques', 'conversionsChroniques'))",
        'downloads'         => "count(*) FILTER (WHERE dtype = 'donneesExport')",
        'downloaded_values' => "coalesce(sum(nombremesures) FILTER (WHERE dtype = 'donneesExport'), 0)",
        // Les comptes qui ont exporté ; depuis l'ouverture, un export sans compte n'a pas d'auteur : la colonne suivante
        // compte ces exports, une demande étant toutes les lignes d'un même moment
        'downloading_accounts' => "count(DISTINCT auteur_id) FILTER (WHERE dtype = 'donneesExport')",
        'anonymous_requests'   => "count(DISTINCT date) FILTER (WHERE dtype = 'donneesExport' AND auteur_id IS NULL)",
    ];

    public function __construct(private Connection $connection)
    {
    }

    /**
     * Ce que montre la page d'une chronique : ses consultations depuis le début du compteur, ses téléchargements depuis
     * le début de l'historique.
     *
     * @return array{views: int, viewsSince: ?string, downloads: int, downloadsSince: ?string}
     */
    public function forChronique(Chronique $chronique): array
    {
        $row = $this->connection->fetchAssociative(
            "SELECT (SELECT coalesce(sum(views), 0) FROM series_views WHERE chronique_id = :id) AS views,
                    (SELECT min(day) FROM series_views) AS views_since,
                    (SELECT count(*) FROM historique WHERE dtype = 'donneesExport' AND chronique_id = :id) AS downloads,
                    (SELECT to_char(min(date), 'YYYY') FROM historique) AS downloads_since",
            ['id' => $chronique->getId()]
        );

        return [
            'views'          => (int) $row['views'],
            'viewsSince'     => $row['views_since'],
            'downloads'      => (int) $row['downloads'],
            'downloadsSince' => $row['downloads_since'],
        ];
    }

    /**
     * La vie d'un observatoire, ou de tout BDOH, par année : une ligne par année, de la première de l'historique à
     * l'année en cours, les consultations comprises.
     *
     * @return list<array<string, int>>
     */
    public function byYear(?Observatoire $observatoire): array
    {
        $where = $observatoire ? 'WHERE observatoire_id = :obs' : '';
        $columns = implode(', ', array_map(fn ($sql, $name) => "$sql AS $name", self::YEAR_COLUMNS, array_keys(self::YEAR_COLUMNS)));
        $rows = $this->connection->fetchAllAssociativeIndexed(
            "SELECT extract(year FROM date)::int AS year, $columns FROM historique $where GROUP BY 1 ORDER BY 1",
            $observatoire ? ['obs' => $observatoire->getId()] : []
        );
        $views = $this->connection->fetchAllKeyValue(
            'SELECT extract(year FROM v.day)::int, sum(v.views) FROM series_views v ' . $this->ofObservatoire($observatoire, 'v.chronique_id') . ' GROUP BY 1',
            $observatoire ? ['obs' => $observatoire->getId()] : []
        );
        $first = min([...array_keys($rows), ...array_keys($views), (int) date('Y')]);
        $years = [];
        for ($year = $first; $year <= (int) date('Y'); ++$year) {
            $years[] = ['year' => $year]
                + array_map('intval', $rows[$year] ?? array_fill_keys(array_keys(self::YEAR_COLUMNS), 0))
                + ['views' => (int) ($views[$year] ?? 0)];
        }

        return $years;
    }

    /**
     * Par chronique de l'observatoire, ou de tout BDOH, et par année : les valeurs ajoutées par import, les
     * téléchargements et les consultations. Une ligne par chronique et par année où il s'est passé quelque chose. Les
     * chroniques supprimées depuis n'y sont plus ; leurs lignes de l'historique comptent dans le tableau par année.
     *
     * @return list<array<string, mixed>>
     */
    public function bySeriesAndYear(?Observatoire $observatoire): array
    {
        $parameters = $observatoire ? ['obs' => $observatoire->getId()] : [];

        return $this->connection->fetchAllAssociative(
            "WITH h AS (
                 SELECT chronique_id, extract(year FROM date)::int AS year,
                        coalesce(sum(nombremesures) FILTER (WHERE dtype = 'donneesImport'), 0) AS imported_values,
                        count(*) FILTER (WHERE dtype = 'donneesExport') AS downloads
                   FROM historique WHERE chronique_id IS NOT NULL AND dtype IN ('donneesImport', 'donneesExport')
                  GROUP BY 1, 2
             ), v AS (
                 SELECT chronique_id, extract(year FROM day)::int AS year, sum(views) AS views FROM series_views GROUP BY 1, 2
             )
             SELECT o.nom AS observatory, st.code AS station, c.code AS series, coalesce(h.year, v.year) AS year,
                    coalesce(h.imported_values, 0) AS imported_values, coalesce(h.downloads, 0) AS downloads,
                    coalesce(v.views, 0) AS views
               FROM h FULL JOIN v ON v.chronique_id = h.chronique_id AND v.year = h.year
               JOIN chronique c ON c.id = coalesce(h.chronique_id, v.chronique_id)
               JOIN station st ON st.id = c.station_id
               JOIN observatoire o ON o.id = (SELECT min(si.observatoire_id) FROM stations_sites ss
                                               JOIN siteexperimental si ON si.id = ss.site_id WHERE ss.station_id = st.id)
              " . ($observatoire ? 'WHERE o.id = :obs' : '') . '
              ORDER BY o.nom, st.code, c.code, 4',
            $parameters
        );
    }

    /** La condition qui garde les lignes des chroniques d'un observatoire, par leur station et ses sites */
    private function ofObservatoire(?Observatoire $observatoire, string $column): string
    {
        return $observatoire ? "WHERE $column IN (SELECT c.id FROM chronique c JOIN stations_sites ss ON ss.station_id = c.station_id
                                                  JOIN siteexperimental si ON si.id = ss.site_id WHERE si.observatoire_id = :obs)" : '';
    }
}
