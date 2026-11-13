<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Les mêmes conditions d'utilisation pour tous les observatoires (dev/differences.md, « Conditions d'utilisation
 * communes ») : ORACLE perd le fichier qui remplaçait les conditions de BDOH, une copie des anciennes conditions de
 * 2014 qui imposaient à toutes ses données un compte et un usage non commercial ; la contrainte d'accès des jeux de
 * données, affichée sur leur page et envoyée à Theia, dit la licence, avec une seconde entrée pour les jeux qui ont
 * des chroniques en accès restreint. Le champ n'est plus dans l'administration depuis 2021 : seul SQL le change.
 */
final class Version20261113120000 extends AbstractMigration
{
    private const ORACLE_FILE = 'uploads/documents/ORACLE-ConditionsGeneralesUtilisation.pdf';
    private const OLD = 'Conditions Generales Utilisation (voir pdf)';
    private const OPEN = 'Licence Ouverte Etalab 2.0 (etalab-2.0)';
    private const MIXED = "Licence Ouverte Etalab 2.0 (etalab-2.0), sauf les chroniques en accès restreint : conditions générales d'utilisation de BDOH";

    public function getDescription(): string
    {
        return "Les mêmes conditions d'utilisation pour tous les observatoires, et la licence dans la contrainte des jeux de données";
    }

    public function up(Schema $schema): void
    {
        $this->addSql("UPDATE observatoire SET conditionsutilisation = NULL
                        WHERE slug = 'ORACLE' AND conditionsutilisation = :file", ['file' => self::ORACLE_FILE]);
        $this->addSql('UPDATE dataconstraint SET nom = :open WHERE id = 1 AND nom = :old',
                      ['open' => self::OPEN, 'old' => self::OLD]);
        $this->addSql("INSERT INTO dataconstraint (id, nom) VALUES (nextval('dataconstraint_id_seq'), :mixed)",
                      ['mixed' => self::MIXED]);
        $this->addSql('UPDATE dataset SET dataconstraint_id = (SELECT id FROM dataconstraint WHERE nom = :mixed)
                        WHERE dataconstraint_id = 1
                          AND EXISTS (SELECT 1 FROM chronique c WHERE c.dataset_id = dataset.id AND NOT c.estvisible)',
                      ['mixed' => self::MIXED]);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('UPDATE dataset SET dataconstraint_id = 1
                        WHERE dataconstraint_id = (SELECT id FROM dataconstraint WHERE nom = :mixed)',
                      ['mixed' => self::MIXED]);
        $this->addSql('DELETE FROM dataconstraint WHERE nom = :mixed', ['mixed' => self::MIXED]);
        $this->addSql('UPDATE dataconstraint SET nom = :old WHERE id = 1 AND nom = :open',
                      ['open' => self::OPEN, 'old' => self::OLD]);
        $this->addSql("UPDATE observatoire SET conditionsutilisation = :file
                        WHERE slug = 'ORACLE' AND conditionsutilisation IS NULL", ['file' => self::ORACLE_FILE]);
    }
}
