<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Recale deux compteurs qui n'ont jamais servi : leur table a une ligne d'identifiant 1, et le compteur
 * rendrait 1, déjà pris, si bien qu'ajouter une contrainte de données ou un thème INSPIRE depuis
 * l'administration échouait (dev/differences.md, « Compteurs d'identifiants en retard »). La première
 * migration ne les voyait pas : le contrôle qui l'a guidée comparait le compteur au plus grand
 * identifiant sans savoir s'il avait déjà servi.
 */
final class Version20261026170000 extends AbstractMigration
{
    private const TABLES = ['dataconstraint', 'inspiretheme'];

    public function getDescription(): string
    {
        return "Recale les compteurs des contraintes de données et des thèmes INSPIRE";
    }

    public function up(Schema $schema): void
    {
        foreach (self::TABLES as $table) {
            $this->addSql("SELECT setval('{$table}_id_seq', (SELECT coalesce(max(id), 1) FROM $table))");
        }
    }

    public function down(Schema $schema): void
    {
        // L'état d'avant, celui des bases de production du 21 octobre 2026 : à 1, jamais appelé.
        foreach (self::TABLES as $table) {
            $this->addSql("SELECT setval('{$table}_id_seq', 1, false)");
        }
    }
}
