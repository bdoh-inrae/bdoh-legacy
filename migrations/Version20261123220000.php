<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Les logos des partenaires qui n'en avaient pas (dev/plans/2026-11-19_review.md, « Logos des partenaires ») : 31 entrées
 * de la production, chacune son fichier, au nom que BDOH lui donnerait (Partenaire::getDocumentFilePrefix). Un fichier
 * n'est jamais partagé : déposer un nouveau logo supprime le fichier en place (dev/decisions.md, « Un fichier déposé
 * n'est jamais partagé »).
 *
 * Les fichiers ne sont pas dans le dépôt : ce sont les marques d'autres organismes, et des données de l'instance. Ils
 * viennent de l'archive bdoh-uploads-partners.tar.gz, avec la source de chacun, que vm-db déballe avant les migrations
 * (dev/deploy/README.md, section 2). Un fichier absent arrête la migration : pas de logo sans fichier.
 *
 * Chaque entrée est visée par son observatoire et son nom, et seulement si elle n'a pas de logo : un gestionnaire a pu en
 * déposer un entre-temps. Chaque logo posé s'inscrit dans `content_review_log`, la trace de la relecture
 * (Version20261119120000) ; le retour arrière n'efface que ceux qui sont encore en place.
 */
final class Version20261123220000 extends AbstractMigration
{
    private const LOG = 'content_review_log';

    /** [observatoire, partenaire, logo] */
    private const LOGOS = [
        ['ARC-ISERE', 'EDF', 'uploads/ARC-ISERE_EDF-logo.png'],
        ['ARC-ISERE', 'INRAE', 'uploads/ARC-ISERE_INRAE-logo.png'],
        ['DRAIX', 'ETNA', 'uploads/DRAIX_ETNA-logo.png'],
        ['DRAIX', 'IGE', 'uploads/DRAIX_IGE-logo.png'],
        ['DRAIX', 'INRAE', 'uploads/DRAIX_INRAE-logo.png'],
        ['DRAIX', 'INSU', 'uploads/DRAIX_INSU-logo.png'],
        ['DRAIX', 'OSUG', 'uploads/DRAIX_OSUG-logo.png'],
        ['DRAIX', 'UGA', 'uploads/DRAIX_UGA-logo.png'],
        ['HAUTE-LOIRE', 'INRAE', 'uploads/HAUTE-LOIRE_INRAE-logo.png'],
        ['LONES-DE-L-AIN', 'INRAE RiverLy', 'uploads/LONES-DE-L-AIN_INRAE-RIVERLY-logo.png'],
        ['OBSERVATOIRE-DES-SEDIMENTS-DU-RHONE', 'ASNR', 'uploads/OBSERVATOIRE-DES-SEDIMENTS-DU-RHONE_ASNR-logo.png'],
        ['OBSERVATOIRE-DES-SEDIMENTS-DU-RHONE', 'CEREGE', 'uploads/OBSERVATOIRE-DES-SEDIMENTS-DU-RHONE_CEREGE-logo.png'],
        ['OBSERVATOIRE-DES-SEDIMENTS-DU-RHONE', 'CNR', 'uploads/OBSERVATOIRE-DES-SEDIMENTS-DU-RHONE_CNR-logo.png'],
        ['OBSERVATOIRE-DES-SEDIMENTS-DU-RHONE', 'EDF', 'uploads/OBSERVATOIRE-DES-SEDIMENTS-DU-RHONE_EDF-logo.png'],
        ['OBSERVATOIRE-DES-SEDIMENTS-DU-RHONE', 'Grand Lyon', 'uploads/OBSERVATOIRE-DES-SEDIMENTS-DU-RHONE_GRAND-LYON-logo.png'],
        ['OBSERVATOIRE-DES-SEDIMENTS-DU-RHONE', 'IFREMER', 'uploads/OBSERVATOIRE-DES-SEDIMENTS-DU-RHONE_IFREMER-logo.png'],
        ['OBSERVATOIRE-DES-SEDIMENTS-DU-RHONE', 'INRAE RiverLy', 'uploads/OBSERVATOIRE-DES-SEDIMENTS-DU-RHONE_INRAE-RIVERLY-logo.png'],
        ['OBSERVATOIRE-DES-SEDIMENTS-DU-RHONE', 'MIO', 'uploads/OBSERVATOIRE-DES-SEDIMENTS-DU-RHONE_MIO-logo.png'],
        ['OHM-CV', 'INSU', 'uploads/OHM-CV_INSU-logo.png'],
        ['ORACLE', 'INRAE', 'uploads/ORACLE_INRAE-logo.png'],
        ['ORACLE', 'MINES Paris', 'uploads/ORACLE_MINES-PARIS-logo.png'],
        ['REAL-COLLOBRIER', 'INRAE PACA - Unité RECOVER', 'uploads/REAL-COLLOBRIER_INRAE-PACA-UNITE-RECOVER-logo.png'],
        ['SITE-ATELIER-ARDIERES-MORCILLE', 'EVS', 'uploads/SITE-ATELIER-ARDIERES-MORCILLE_EVS-logo.png'],
        ['SITE-ATELIER-ARDIERES-MORCILLE', 'INRAE RiverLy', 'uploads/SITE-ATELIER-ARDIERES-MORCILLE_INRAE-RIVERLY-logo.png'],
        ['SITE-ATELIER-ARDIERES-MORCILLE', 'Université de Grenoble', 'uploads/SITE-ATELIER-ARDIERES-MORCILLE_UNIVERSITE-DE-GRENOBLE-logo.png'],
        ['TPG', 'INRAE', 'uploads/TPG_INRAE-logo.png'],
        ['YZERON', 'INRAE UR RiverLy', 'uploads/YZERON_INRAE-UR-RIVERLY-logo.png'],
        ['YZERON', 'Institut de Physique du Globe de Paris (IPGP)', 'uploads/YZERON_INSTITUT-DE-PHYSIQUE-DU-GLOBE-DE-PARIS-IPGP-logo.png'],
        ['YZERON', 'Métropole de Lyon', 'uploads/YZERON_METROPOLE-DE-LYON-logo.png'],
        ['YZERON', 'OTHU', 'uploads/YZERON_OTHU-logo.png'],
        ['YZERON', 'SAGYRC', 'uploads/YZERON_SAGYRC-logo.png'],
    ];

    public function getDescription(): string
    {
        return 'Les logos des partenaires qui n\'en avaient pas';
    }

    public function up(Schema $schema): void
    {
        foreach (self::LOGOS as [$slug, $nom, $path]) {
            $this->addSql("WITH u AS (UPDATE partenaire t SET pathlogo = :path FROM observatoire o
                                       WHERE o.id = t.observatoire_id AND o.slug = :slug AND t.nom = :nom AND t.pathlogo IS NULL
                                       RETURNING t.id, t.pathlogo)
                           INSERT INTO " . self::LOG . " (table_name, row_id, column_name, old_value, new_value)
                           SELECT 'partenaire', id, 'pathlogo', NULL, pathlogo FROM u",
                ['path' => $path, 'slug' => $slug, 'nom' => $nom]);
        }
    }

    public function down(Schema $schema): void
    {
        $mine = "l.table_name = 'partenaire' AND l.column_name = 'pathlogo' AND l.old_value IS NULL AND l.new_value IN ("
            . implode(', ', array_map(fn (array $logo) => $this->connection->quote($logo[2]), self::LOGOS)) . ')';
        $this->addSql('UPDATE partenaire t SET pathlogo = NULL FROM ' . self::LOG . " l WHERE $mine AND t.id = l.row_id AND t.pathlogo = l.new_value");
        $this->addSql('DELETE FROM ' . self::LOG . " l WHERE $mine");
    }

    /** Après up(), dans sa transaction : chaque logo posé a son fichier, sinon tout est annulé. */
    public function postUp(Schema $schema): void
    {
        $public = \dirname(__DIR__) . '/public/';
        $paths = array_column(self::LOGOS, 2);
        $placed = array_values(array_filter(
            $this->connection->fetchFirstColumn("SELECT pathlogo FROM partenaire WHERE pathlogo IS NOT NULL"),
            fn (string $path) => in_array($path, $paths, true)
        ));
        foreach ($placed as $path) {
            $this->abortIf(!is_file($public . $path), "Logo de partenaire absent : $public$path (bdoh-uploads-partners.tar.gz, dev/deploy/README.md, section 2)");
        }
        $this->write(sprintf('%d logos de partenaires posés.', count($placed)));
    }
}
