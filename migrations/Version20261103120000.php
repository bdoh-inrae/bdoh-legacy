<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Construit le point des stations qui ont des coordonnées sans point : l'ancien ne le calculait qu'à la
 * modification d'une station, jamais à sa création (dev/differences.md, « Point d'une station créée »).
 * Le calcul est celui de StationRepository::updatePosition. La table garde les stations touchées, pour le
 * retour arrière ; elle se supprime une fois la bascule éprouvée (dev/plans/2026-10-26_deployment.md).
 */
final class Version20261103120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return "Construit le point des stations qui ont des coordonnées sans point";
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE station_point_rebuilt AS SELECT id FROM station
                        WHERE point IS NULL AND latitude IS NOT NULL AND longitude IS NOT NULL');
        $this->addSql('UPDATE station SET point = ST_SetSRID(ST_MakePoint(latitude, longitude), 2154)
                        WHERE id IN (SELECT id FROM station_point_rebuilt)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('UPDATE station SET point = NULL WHERE id IN (SELECT id FROM station_point_rebuilt)');
        $this->addSql('DROP TABLE station_point_rebuilt');
    }
}
