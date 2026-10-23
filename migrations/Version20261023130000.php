<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Depuis PostgreSQL 14, EXTRACT rend un numeric et non plus un double precision : les fonctions de
 * calcul de BDOH qui l'emploient échouent (« structure of query does not match function result
 * type ») ou calculent en numeric. Chaque EXTRACT(EPOCH FROM …) de ces fonctions reçoit donc la
 * conversion ::double precision, qui lui rend le type et la valeur qu'il avait sous PostgreSQL 11 ;
 * le reste de leur définition est inchangé (dev/differences.md, « EXTRACT sous PostgreSQL 14 et
 * plus »). La conversion n'ajoute pas d'apostrophe : elle vaut aussi dans le SQL dynamique, écrit
 * entre apostrophes, que certaines fonctions exécutent.
 *
 * La réécriture part de la définition présente dans la base, qui diffère entre la production et
 * OTHU pour trois de ces fonctions. Le retour arrière retire la conversion et rend la définition
 * d'origine à l'identique.
 */
final class Version20261023130000 extends AbstractMigration
{
    private const CAST = '::double precision';

    /** EXTRACT(EPOCH FROM, aussi écrit EXTRACT('epoch' FROM, apostrophes doublées dans une chaîne. */
    private const EXTRACT = "/extract\\s*\\(\\s*'*epoch'*\\s+from\\b/i";

    public function getDescription(): string
    {
        return 'Rend à EXTRACT(EPOCH …) le type double precision dans les fonctions de calcul';
    }

    public function up(Schema $schema): void
    {
        foreach ($this->definitions() as $definition) {
            $this->addSql($this->rewrite($definition, true));
        }
    }

    public function down(Schema $schema): void
    {
        foreach ($this->definitions() as $definition) {
            $this->addSql($this->rewrite($definition, false));
        }
    }

    /**
     * Les définitions des fonctions du schéma bdoh qui emploient EXTRACT(EPOCH …).
     *
     * @return string[]
     */
    private function definitions(): array
    {
        return $this->connection->fetchFirstColumn(<<<'SQL'
            SELECT pg_get_functiondef(p.oid) FROM pg_proc p JOIN pg_namespace n ON n.oid = p.pronamespace
             WHERE n.nspname = 'bdoh' AND p.prokind = 'f' AND p.prosrc ~* 'extract\s*\(\s*''*epoch''*\s+from'
             ORDER BY p.oid
            SQL);
    }

    /**
     * Ajoute (ou retire) la conversion après la parenthèse qui ferme chaque EXTRACT(EPOCH FROM ….
     */
    private function rewrite(string $definition, bool $cast): string
    {
        preg_match_all(self::EXTRACT, $definition, $matches, PREG_OFFSET_CAPTURE);
        // En partant de la fin, les positions des occurrences précédentes ne bougent pas.
        foreach (array_reverse($matches[0]) as [$text, $offset]) {
            $depth = 0;
            for ($i = $offset + strpos($text, '('); $i < strlen($definition); ++$i) {
                $depth += ['(' => 1, ')' => -1][$definition[$i]] ?? 0;
                if ($depth === 0) {
                    break;
                }
            }
            $after = $i + 1;
            $hasCast = substr_compare($definition, self::CAST, $after, strlen(self::CAST), true) === 0;
            if ($cast && !$hasCast) {
                $definition = substr_replace($definition, self::CAST, $after, 0);
            } elseif (!$cast && $hasCast) {
                $definition = substr_replace($definition, '', $after, strlen(self::CAST));
            }
        }

        return $definition;
    }
}
