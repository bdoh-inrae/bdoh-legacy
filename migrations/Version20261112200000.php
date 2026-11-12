<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Les favoris d'un compte : une chronique par ligne, et les étiquettes que le compte y pose, une liste de noms
 * (dev/plans/2026-11-12_favorites.md). Un favori part avec son compte ou sa chronique. `created` n'est pas lu par
 * l'application : il date le favori, pour compter un jour ce qui sert (ROADMAP.md, « Suivi de l'usage »).
 */
final class Version20261112200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return "Crée la table des favoris d'un compte";
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE SEQUENCE favorite_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql("CREATE TABLE favorite (
            id integer NOT NULL DEFAULT nextval('favorite_id_seq'),
            utilisateur_id integer NOT NULL,
            chronique_id integer NOT NULL,
            labels jsonb NOT NULL DEFAULT '[]',
            created timestamp(0) without time zone NOT NULL DEFAULT (now() AT TIME ZONE 'UTC'),
            CONSTRAINT favorite_pkey PRIMARY KEY (id),
            CONSTRAINT favorite_utilisateur_chronique_key UNIQUE (utilisateur_id, chronique_id),
            CONSTRAINT favorite_utilisateur_id_fkey FOREIGN KEY (utilisateur_id)
                REFERENCES utilisateur (id) ON DELETE CASCADE,
            CONSTRAINT favorite_chronique_id_fkey FOREIGN KEY (chronique_id)
                REFERENCES chronique (id) ON DELETE CASCADE,
            CONSTRAINT favorite_labels_is_array CHECK (jsonb_typeof(labels) = 'array')
        )");
        $this->addSql('ALTER SEQUENCE favorite_id_seq OWNED BY favorite.id');
        // La suppression d'une chronique cherche ses favoris
        $this->addSql('CREATE INDEX favorite_chronique_id_idx ON favorite (chronique_id)');
    }

    public function down(Schema $schema): void
    {
        // La séquence, attachée à la colonne, part avec la table
        $this->addSql('DROP TABLE favorite');
    }
}
