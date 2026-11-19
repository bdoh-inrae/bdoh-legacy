<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Le compteur des consultations de chaque chronique (dev/plans/2026-11-16_reopening.md, point 6 ; dev/differences.md,
 * « Statistiques d'usage ») : une ligne par chronique et par jour, le nombre de visiteurs qui ont ouvert sa page, chacun
 * compté une fois par heure, robots écartés (Statistics\ViewCounter). Aucune donnée personnelle : seulement des comptes.
 * Une table à part, que l'ancien n'a pas ; effacée avec sa chronique.
 */
final class Version20261120000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Le compteur des consultations de chaque chronique, par jour';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE series_views (
                           chronique_id INTEGER NOT NULL REFERENCES chronique (id) ON DELETE CASCADE,
                           day DATE NOT NULL,
                           views INTEGER NOT NULL DEFAULT 0,
                           PRIMARY KEY (chronique_id, day)
                       )');
        // Pour la première date du compteur, que la page d'une chronique dit
        $this->addSql('CREATE INDEX series_views_day ON series_views (day)');
        $this->addSql("COMMENT ON TABLE series_views IS 'Consultations de la page de chaque chronique, par jour : un visiteur compté une fois par heure, robots écartés (règles de COUNTER)'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE series_views');
    }
}
