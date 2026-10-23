<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Recale sur le plus grand identifiant de leur table cinq compteurs restés à 1 : ajouter une ligne à
 * ces listes depuis l'administration échouait sur un identifiant déjà pris (dev/differences.md,
 * « Compteurs d'identifiants en retard »).
 */
final class Version20261023120000 extends AbstractMigration
{
    private const TABLES = ['criteriaclimat', 'criteriageology', 'milieu', 'topiccategory', 'typefunding'];

    public function getDescription(): string
    {
        return "Recale les compteurs d'identifiants en retard";
    }

    public function up(Schema $schema): void
    {
        foreach (self::TABLES as $table) {
            $this->addSql("SELECT setval('{$table}_id_seq', (SELECT coalesce(max(id), 1) FROM $table))");
        }
    }

    public function down(Schema $schema): void
    {
        // L'état d'avant : chaque compteur à 1, comme dans les bases de production du 21 octobre 2026.
        foreach (self::TABLES as $table) {
            $this->addSql("SELECT setval('{$table}_id_seq', 1)");
        }
    }
}
