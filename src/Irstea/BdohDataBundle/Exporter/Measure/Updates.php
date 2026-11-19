<?php
// SPDX-FileCopyrightText: The BDOH authors
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace Irstea\BdohDataBundle\Exporter\Measure;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Irstea\BdohDataBundle\Entity\Chronique;

/**
 * La date de dernière mise à jour des valeurs de chaque chronique d'un export, que la Licence Ouverte demande de
 * mentionner avec la source (Lou, 2026-11-19 ; dev/plans/2026-11-16_reopening.md, point 4). Une chronique calculée ou
 * convertie la porte (miseajour, qu'écrivent ComputeChroniqueCommand et ConvertChroniqueCommand) ; pour les autres,
 * c'est la date de leur dernier import ou de leur dernière suppression de valeurs dans l'historique. L'historique
 * commence en février 2013 : sans trace, la date reste inconnue, jamais inventée.
 *
 * Au jour près : l'ancien écrivait l'historique à l'heure de Paris, le nouveau l'écrit en UTC.
 */
class Updates
{
    public function __construct(private Connection $connection)
    {
    }

    /**
     * @param Chronique[] $chroniques
     *
     * @return array<int, string|null> la date (AAAA-MM-JJ), par identifiant de chronique
     */
    public function dates(array $chroniques): array
    {
        $ids = array_map(fn (Chronique $chronique) => $chronique->getId(), $chroniques);
        if (!$ids) {
            return [];
        }
        $rows = $this->connection->fetchAllKeyValue(
            'SELECT c.id, to_char(coalesce(c.miseajour, h.last), \'YYYY-MM-DD\') FROM chronique c
               LEFT JOIN (SELECT chronique_id, max(date) AS last FROM historique
                           WHERE dtype IN (\'donneesImport\', \'donneesSuppression\') AND chronique_id IN (?)
                           GROUP BY chronique_id) h ON h.chronique_id = c.id
              WHERE c.id IN (?)',
            [$ids, $ids],
            [ArrayParameterType::INTEGER, ArrayParameterType::INTEGER]
        );

        return array_replace(array_fill_keys($ids, null), $rows);
    }
}
