<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * La relecture du contenu (dev/plans/2026-11-19_review.md, « Contenu de la base ») : ce qui est faux sans discussion
 * possible, ou décidé avec Lou, dans les descriptions des observatoires et des sites, les logos, les DOI, les
 * référentiels communs à tous (paramètres, familles, unités permises, qualités, catégories de compte) et quelques
 * titres. Ce qui demande de connaître le terrain part aux gestionnaires, pas ici.
 *
 * Chaque changement vise une ligne par son ancienne valeur, pas par son identifiant : sur la VM, OTHU est fondu dans
 * la base, et un gestionnaire a pu corriger une valeur entre-temps ; une ligne qui n'a plus l'ancienne valeur, ou dont
 * la nouvelle valeur existe déjà là où elle doit être unique, est laissée. Chaque changement fait s'inscrit dans
 * `content_review_log` (table, ligne, colonne, avant, après) : c'est la trace, et le retour arrière la suit, en ne
 * rendant l'ancienne valeur qu'aux lignes qui ont encore la nouvelle.
 *
 * Une description mise en forme ne perd que de la forme : commentaires (le code de Word), blocs `<style>` et `<xml>`,
 * balises `<o:p>`, paragraphes vides, et le lien sans adresse dont le texte est une adresse reçoit cette adresse. Le
 * texte qu'on lit, balises retirées et espaces ramenées à une, doit rester le même : sinon la migration s'arrête.
 */
final class Version20261119120000 extends AbstractMigration
{
    private const LOG = 'content_review_log';

    /** [table, colonne, avant, après, condition de plus] : un texte changé, partout où il est. */
    private const TEXTS = [
        // Référentiel des paramètres, en français
        ['typeparametre', 'nom', 'Conc. en azote nitrate (N03-)', 'Conc. en azote nitrate (NO3-)'],
        ['typeparametre', 'nom', 'Conc.  en carbone organique total (COT)', 'Conc. en carbone organique total (COT)'],
        ['typeparametre', 'nom', 'Conc. en chlorure Cl  dissous (Cl-)', 'Conc. en chlorure Cl dissous (Cl-)'],
        ['typeparametre', 'nom', 'Conc. manganèse particulaire(Mn)', 'Conc. manganèse particulaire (Mn)'],
        ['typeparametre', 'nom', 'Conc. en diuron  dissous', 'Conc. en diuron dissous'],
        ['typeparametre', 'nom', 'Conc. en flazasulfuron  dissous', 'Conc. en flazasulfuron dissous'],
        ['typeparametre', 'nom', 'Conc. en sulphate (SO4 2-)', 'Conc. en sulfate (SO4 2-)'],
        ['typeparametre', 'nom', 'Conc. en indéno[1,2,3,c-d]pyrène', 'Conc. en indéno[1,2,3-cd]pyrène'],
        ['typeparametre', 'nom', "Flux d'Indéno[1,2,3,c-d]pyrène", "Flux d'indéno[1,2,3-cd]pyrène"],
        ['typeparametre', 'nom', 'Conc. en dibenzo[a;h]anthracène', 'Conc. en dibenzo[a,h]anthracène'],
        ['typeparametre', 'nom', 'Flux de dibenzo[a;h]anthracène', 'Flux de dibenzo[a,h]anthracène'],
        ['typeparametre', 'nom', 'Conc. en 1-(3,4-DICHLOROPHENYL)-3-METHYL UREA dissous', 'Conc. en 1-(3,4-dichlorophényl)-3-méthylurée (DCPMU) dissous'],
        // Le code d'un flux de benzo[a]anthracène, copié de celui du dibenzo[a,h]anthracène (comme CBAA pour sa concentration)
        ['typeparametre', 'code', 'FDBAHA', 'FBAA', "nom = 'Flux de benzo[a]anthracène'"],
        // Référentiel des paramètres, en anglais
        ['typeparametre', 'nomen', 'Conc. of nitrate (N03-)', 'Conc. of nitrate (NO3-)'],
        ['typeparametre', 'nomen', 'Conc. of dissolved  (U)', 'Conc. of dissolved uranium (U)'],
        ['typeparametre', 'nomen', 'Conc. of dissolved (Ca2+)', 'Conc. of dissolved calcium (Ca2+)'],
        ['typeparametre', 'nomen', 'Conc. of ammonium  (NH4+)', 'Conc. of ammonium (NH4+)'],
        ['typeparametre', 'nomen', 'Conc. of total Kjedahl nitrogen NK', 'Conc. of total Kjeldahl nitrogen NK'],
        ['typeparametre', 'nomen', 'Dissolveld inorganic carbon DIC', 'Dissolved inorganic carbon DIC'],
        ['typeparametre', 'nomen', 'Dissolveld organic carbon DOC', 'Dissolved organic carbon DOC'],
        ['typeparametre', 'nomen', 'Conc. of  dissolved chloride (Cl-)', 'Conc. of dissolved chloride (Cl-)'],
        ['typeparametre', 'nomen', 'Conc. of  phosphate (PO4 3-)', 'Conc. of phosphate (PO4 3-)'],
        ['typeparametre', 'nomen', 'Conc. of particulate aluminium(Al)', 'Conc. of particulate aluminium (Al)'],
        ['typeparametre', 'nomen', 'Conc. of particulate Zinc', 'Conc. of particulate zinc (Zn)'],
        ['typeparametre', 'nomen', 'Flux of particulate beryllium', 'Flux of particulate beryllium 7'],
        ['typeparametre', 'nomen', 'Flux of  particulate cobalt 60', 'Flux of particulate cobalt 60'],
        ['typeparametre', 'nomen', 'Flux of  particulate manganese 54', 'Flux of particulate manganese 54'],
        ['typeparametre', 'nomen', 'Conc. of tébuconazole', 'Conc. of dissolved tebuconazole'],
        ['typeparametre', 'nomen', 'Conc. of azoxystrobine', 'Conc. of dissolved azoxystrobin'],
        ['typeparametre', 'nomen', 'Conc. of spiroxamine', 'Conc. of dissolved spiroxamine'],
        ['typeparametre', 'nomen', 'Conc. of dissolved dimethomorphe', 'Conc. of dissolved dimethomorph'],
        ['typeparametre', 'nomen', 'Conc. of pyrène', 'Conc. of pyrene'],
        ['typeparametre', 'nomen', 'Flux of pyrène', 'Flux of pyrene'],
        ['typeparametre', 'nomen', 'Conc. of fluorène', 'Conc. of fluorene'],
        ['typeparametre', 'nomen', 'Conc. of acenaphtene', 'Conc. of acenaphthene'],
        ['typeparametre', 'nomen', 'Conc. of acenaphtylene', 'Conc. of acenaphthylene'],
        ['typeparametre', 'nomen', 'Conc. of naphtalene', 'Conc. of naphthalene'],
        ['typeparametre', 'nomen', 'Flux of Acenaphtene', 'Flux of acenaphthene'],
        ['typeparametre', 'nomen', 'Flux of acenaphtylene', 'Flux of acenaphthylene'],
        ['typeparametre', 'nomen', 'Flux of Anthracene', 'Flux of anthracene'],
        ['typeparametre', 'nomen', 'Flux of naphtalene', 'Flux of naphthalene'],
        ['typeparametre', 'nomen', 'Conc. en indéno[1,2,3,c-d]pyrene', 'Conc. of indeno[1,2,3-cd]pyrene'],
        ['typeparametre', 'nomen', 'Flux of Indéno[1,2,3,c-d]pyrène', 'Flux of indeno[1,2,3-cd]pyrene'],
        ['typeparametre', 'nomen', 'Conc. of dibenzo[a;h]anthracene', 'Conc. of dibenzo[a,h]anthracene'],
        ['typeparametre', 'nomen', 'Flux of dibenzo[a;h]anthracene', 'Flux of dibenzo[a,h]anthracene'],
        ['typeparametre', 'nomen', 'Flux de benzo[b]fluoranthene', 'Flux of benzo[b]fluoranthene'],
        ['typeparametre', 'nomen', 'Flux de phenanthrene', 'Flux of phenanthrene'],
        ['typeparametre', 'nomen', 'Conc. of particulate Cesium 134', 'Conc. of particulate cesium 134'],
        ['typeparametre', 'nomen', 'Flux of particulate césium 134', 'Flux of particulate cesium 134'],
        ['typeparametre', 'nomen', 'Organic Dissolved Carbon', 'Dissolved organic carbon (DOC)'],
        ['typeparametre', 'nomen', 'Conc. of carbon dioxide (CO2)', 'Conc. of dissolved carbon dioxide (CO2)'],
        ['typeparametre', 'nomen', 'Total heterotrophes', 'Total heterotrophs'],
        ['typeparametre', 'nomen', 'Rum-2-bac Bacteroidales  (ruminant fecal matters)', 'Rum-2-bac Bacteroidales (ruminant fecal matters)'],
        ['typeparametre', 'nomen', 'HF183 human-associated bacteroides  (human fecal matters)', 'HF183 human-associated bacteroides (human fecal matters)'],
        ['typeparametre', 'nomen', 'Matrix potential', 'Matric potential'],
        // Familles : les sous-familles de la microbiologie n'avaient qu'un nom anglais
        ['familleparametres', 'nom', 'Bacterial abundance', 'Abondance bactérienne'],
        ['familleparametres', 'nom', 'Bacterial diversity', 'Diversité bactérienne'],
        ['familleparametres', 'nom', 'Bacterial resistance', 'Résistance bactérienne'],
        ['familleparametres', 'nomen', 'Radionucleides and isotopes', 'Radionuclides and isotopes'],
        // Jeu de qualité Biche : deux traductions fausses
        ['qualite', 'libelleen', 'Uncertain', 'Rebuilt', "code = 'r' AND libelle = 'Reconstituée' AND jeu_id IN (SELECT id FROM jeuqualite WHERE nom = 'Biche')"],
        ['qualite', 'libelleen', 'Missing', 'Undetermined', "code = '1' AND libelle = 'Indéterminée' AND jeu_id IN (SELECT id FROM jeuqualite WHERE nom = 'Biche')"],
        ['categorie', 'libelleen', ' Private individual', 'Private individual'],
        // Partenaires : l'unité s'écrit RiverLy
        ['partenaire', 'nom', 'INRAE  RiverLy', 'INRAE RiverLy'],
        ['partenaire', 'nom', 'INRAE UR Riverly', 'INRAE UR RiverLy'],
        ['partenaire', 'nom', 'INRAE Riverly', 'INRAE RiverLy'],
        // Titres de jeux de données sans DOI (ceux qui en ont un suivent leur fiche)
        ['dataset', 'titre', "Suvi d'humidité et de la température du sol à la station de  Lambert", "Suivi d'humidité et de la température du sol à la station de Lambert", 'doi_id IS NULL'],
        ['dataset', 'titre', "Suvi d'humidité et de la température du sol à la station de  Portanière", "Suivi d'humidité et de la température du sol à la station de Portanière", 'doi_id IS NULL'],
        ['dataset', 'titreen', 'Hydrometric monitoring in Valecure catchment', 'Hydrometric monitoring in Valescure catchment', 'doi_id IS NULL'],
        ['observatoire', 'titreen', 'French Guyana', 'French Guiana', "slug = 'GUYANE'"],
        // Logos d'INRAE pris pour logos d'observatoire (Lou, 16 novembre) : INRAE est un partenaire
        ['observatoire', 'pathlogo', 'uploads/ARC-ISERE-logo.jpg', null, "slug = 'ARC-ISERE'"],
        ['observatoire', 'pathlogo', 'uploads/HAUTE-LOIRE-logo.jpg', null, "slug = 'HAUTE-LOIRE'"],
        ['observatoire', 'pathlogo', 'uploads/SITE-ATELIER-ARDIERES-MORCILLE-logo.png', null, "slug = 'SITE-ATELIER-ARDIERES-MORCILLE'"],
    ];

    /** Les colonnes uniques : une valeur nouvelle qui y existe déjà laisse la ligne telle quelle. */
    private const UNIQUE = ['typeparametre.nom', 'typeparametre.nomen', 'familleparametres.nom', 'familleparametres.nomen',
                            'categorie.libelleen', 'partenaire.nom'];

    /** Les DOI principaux (Lou, 16 novembre), déjà dans la liste de l'observatoire. */
    private const MAIN_DOIS = ['LONES-DE-L-AIN' => '10.17180/OBS.LONES-AIN', 'ORACLE' => '10.17180/obs.oracle',
                               'REAL-COLLOBRIER' => '10.17180/obs.real'];

    /** Les unités permises d'un paramètre, la liste du formulaire d'une chronique : [paramètre, unité, à retirer ?]. */
    private const UNITS = [
        ['Chaleur latente', 'Unité pH', true],
        ['Flux de chaleur dans le sol', 'ppmv', true],
        ['Flux de chaleur dans le sol', 'W/m²', false],
        ['Conc. dioxyde de carbone atmosphérique (CO2)', 'ppmv', false],
    ];

    public function getDescription(): string
    {
        return 'La relecture du contenu : descriptions mises en forme, logos, DOI, référentiels et titres';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE ' . self::LOG . ' (id serial PRIMARY KEY, table_name text NOT NULL, row_id integer NOT NULL,
                        column_name text NOT NULL, old_value text, new_value text, changed_at timestamptz NOT NULL DEFAULT now())');
        $this->addSql('COMMENT ON TABLE ' . self::LOG . " IS 'Relecture du contenu (2026-11-19) : ce que la migration Version20261119120000 a changé, pour la trace et le retour arrière'");

        foreach ($this->descriptions() as [$table, $id, $column, $old, $new]) {
            $this->change($table, $column, $old, $new, 'id = ' . (int) $id);
        }
        foreach (self::TEXTS as $text) {
            [$table, $column, $old, $new] = $text;
            $this->change($table, $column, $old, $new, $text[4] ?? null);
        }
        // Les DOI enregistrés avec leur résolveur : l'identifiant seul, comme les autres
        $this->addSql("WITH c AS (SELECT id, identifiant AS old FROM doi WHERE identifiant ~* '^https?://(dx\\.)?doi\\.org/10\\.'),
                            u AS (UPDATE doi t SET identifiant = regexp_replace(t.identifiant, '^https?://(dx\\.)?doi\\.org/', '', 'i')
                                    FROM c WHERE t.id = c.id RETURNING t.id, c.old, t.identifiant AS new)
                       INSERT INTO " . self::LOG . " (table_name, row_id, column_name, old_value, new_value)
                       SELECT 'doi', id, 'identifiant', old, new FROM u");
        // Les espaces doublées dans les libellés des chroniques
        foreach (['libelle', 'libelleen'] as $column) {
            $this->addSql("WITH c AS (SELECT id, $column AS old FROM chronique WHERE $column ~ '\\S\\s{2,}\\S'),
                                u AS (UPDATE chronique t SET $column = regexp_replace(t.$column, '(\\S)\\s{2,}(?=\\S)', '\\1 ', 'g')
                                        FROM c WHERE t.id = c.id RETURNING t.id, c.old, t.$column AS new)
                           INSERT INTO " . self::LOG . " (table_name, row_id, column_name, old_value, new_value)
                           SELECT 'chronique', id, '$column', old, new FROM u");
        }
        foreach (self::MAIN_DOIS as $slug => $doi) {
            $this->addSql("WITH c AS (SELECT o.id, d.id AS doi FROM observatoire o JOIN doi d ON d.observatoire_id = o.id
                                        WHERE o.slug = :slug AND o.doiprincipal_id IS NULL AND lower(d.identifiant) = lower(CAST(:doi AS text))),
                                u AS (UPDATE observatoire t SET doiprincipal_id = c.doi FROM c WHERE t.id = c.id RETURNING t.id, t.doiprincipal_id)
                           INSERT INTO " . self::LOG . " (table_name, row_id, column_name, old_value, new_value)
                           SELECT 'observatoire', id, 'doiprincipal_id', NULL, doiprincipal_id::text FROM u", ['slug' => $slug, 'doi' => $doi]);
        }
        foreach (self::UNITS as [$parametre, $unite, $remove]) {
            $pair = 'SELECT p.id AS parametre_id, u.id AS unite_id FROM typeparametre p, unite u WHERE p.nom = :parametre AND u.libelle = :unite';
            $this->addSql($remove
                ? "WITH d AS (DELETE FROM parametres_unites t USING ($pair) c
                               WHERE t.parametre_id = c.parametre_id AND t.unite_id = c.unite_id RETURNING t.parametre_id, t.unite_id)
                   INSERT INTO " . self::LOG . " (table_name, row_id, column_name, old_value, new_value)
                   SELECT 'parametres_unites', parametre_id, 'unite_id', unite_id::text, NULL FROM d"
                : "WITH i AS (INSERT INTO parametres_unites (parametre_id, unite_id) SELECT * FROM ($pair) c
                               WHERE NOT EXISTS (SELECT 1 FROM parametres_unites t WHERE t.parametre_id = c.parametre_id AND t.unite_id = c.unite_id)
                               RETURNING parametre_id, unite_id)
                   INSERT INTO " . self::LOG . " (table_name, row_id, column_name, old_value, new_value)
                   SELECT 'parametres_unites', parametre_id, 'unite_id', NULL, unite_id::text FROM i",
                ['parametre' => $parametre, 'unite' => $unite]);
        }
    }

    public function down(Schema $schema): void
    {
        // Les unités permises : rendre celles qu'on a retirées, retirer celles qu'on a ajoutées
        $this->addSql("INSERT INTO parametres_unites (parametre_id, unite_id)
                       SELECT row_id, old_value::integer FROM " . self::LOG . " l
                        WHERE table_name = 'parametres_unites' AND new_value IS NULL
                          AND NOT EXISTS (SELECT 1 FROM parametres_unites t WHERE t.parametre_id = l.row_id AND t.unite_id = l.old_value::integer)");
        $this->addSql("DELETE FROM parametres_unites t USING " . self::LOG . " l
                        WHERE l.table_name = 'parametres_unites' AND l.old_value IS NULL
                          AND t.parametre_id = l.row_id AND t.unite_id = l.new_value::integer");
        // Les valeurs, dans l'ordre inverse, et seulement là où la nouvelle valeur est encore en place
        foreach (['observatoire', 'siteexperimental', 'typeparametre', 'familleparametres', 'qualite', 'categorie',
                  'partenaire', 'dataset', 'doi', 'chronique'] as $table) {
            foreach ($this->columns($table) as $column) {
                $cast = $column === 'doiprincipal_id' ? '::integer' : '';
                $this->addSql("UPDATE $table t SET $column = l.old_value$cast FROM (
                                   SELECT DISTINCT ON (row_id) row_id, old_value, new_value FROM " . self::LOG . "
                                    WHERE table_name = '$table' AND column_name = '$column' ORDER BY row_id, id) l
                                WHERE t.id = l.row_id AND t.$column::text IS NOT DISTINCT FROM l.new_value");
            }
        }
        $this->addSql('DROP TABLE ' . self::LOG);
    }

    /** Les colonnes que up() peut changer dans une table. */
    private function columns(string $table): array
    {
        $columns = [];
        foreach (self::TEXTS as [$t, $c]) {
            if ($t === $table) {
                $columns[$c] = true;
            }
        }
        $more = ['observatoire' => ['description', 'descriptionen', 'doiprincipal_id'],
                 'siteexperimental' => ['description', 'descriptionen'], 'doi' => ['identifiant'],
                 'chronique' => ['libelle', 'libelleen']];
        foreach ($more[$table] ?? [] as $c) {
            $columns[$c] = true;
        }

        return array_keys($columns);
    }

    /** Un changement d'une colonne, inscrit au journal, là où la ligne a encore l'ancienne valeur. */
    private function change(string $table, string $column, ?string $old, ?string $new, ?string $where): void
    {
        $conditions = ["$column IS NOT DISTINCT FROM :old"];
        if ($where !== null) {
            $conditions[] = $where;
        }
        if ($new !== null && in_array("$table.$column", self::UNIQUE, true)) {
            $conditions[] = "NOT EXISTS (SELECT 1 FROM $table x WHERE x.$column = :new"
                . ($table === 'partenaire' ? " AND x.observatoire_id IS NOT DISTINCT FROM $table.observatoire_id" : '') . ')';
        }
        $this->addSql("WITH c AS (SELECT id FROM $table WHERE " . implode(' AND ', $conditions) . "),
                            u AS (UPDATE $table t SET $column = :new FROM c WHERE t.id = c.id RETURNING t.id)
                       INSERT INTO " . self::LOG . " (table_name, row_id, column_name, old_value, new_value)
                       SELECT '$table', id, '$column', CAST(:old AS text), CAST(:new AS text) FROM u", ['old' => $old, 'new' => $new]);
    }

    /**
     * Les descriptions mises en forme qui changent : [table, id, colonne, avant, après]. Lues avant tout changement,
     * nettoyées ici ; une description dont le texte lu changerait arrête la migration.
     */
    private function descriptions(): array
    {
        $changes = [];
        foreach (['observatoire', 'siteexperimental'] as $table) {
            $rows = $this->connection->fetchAllAssociative("SELECT id, description, descriptionen FROM $table ORDER BY id");
            foreach ($rows as $row) {
                foreach (['description', 'descriptionen'] as $column) {
                    $old = $row[$column];
                    if ($old === null || $old === '') {
                        continue;
                    }
                    $new = self::clean($old);
                    if ($new === $old) {
                        continue;
                    }
                    $this->abortIf(self::readable($new) !== self::readable($old),
                        "$table {$row['id']}, $column : le nettoyage changerait le texte lu ; rien n'est fait");
                    $changes[] = [$table, $row['id'], $column, $old, $new];
                }
            }
        }

        return $changes;
    }

    /** La forme seulement : ce que Word et les copier-coller ont laissé, et les paragraphes vides. */
    public static function clean(string $html): string
    {
        $html = preg_replace('/<!--.*?-->/s', '', $html);
        $html = preg_replace('#<(style|xml)\b[^>]*>.*?</\1\s*>#si', '', $html);
        $html = preg_replace('#</?o:p\b[^>]*>#i', '', $html);
        // Un lien sans adresse, dont le texte est une adresse
        $html = preg_replace_callback('#<a(\s[^>]*)?\shref=(["\'])\2([^>]*)>\s*(https?://[^<\s]+)\s*</a>#i',
            fn ($m) => '<a' . $m[1] . ' href="' . htmlspecialchars(html_entity_decode($m[4]), ENT_QUOTES) . '"' . $m[3] . '>' . $m[4] . '</a>', $html);
        // Les paragraphes vides : rien que des espaces, des sauts de ligne et des balises de mise en forme vides
        $empty = '#<p\b[^>]*>(?:\s|&nbsp;|\x{a0}|<br\s*/?>|<(strong|em|b|i|span|u)\b[^>]*>(?:\s|&nbsp;|\x{a0})*</\1>)*</p>\s*#iu';
        do {
            $html = preg_replace($empty, '', $html, -1, $count);
        } while ($count > 0);

        return trim($html);
    }

    /** Le texte qu'on lit : sans commentaires, styles ni balises, entités décodées, espaces ramenées à une. */
    public static function readable(string $html): string
    {
        $text = preg_replace(['/<!--.*?-->/s', '#<(style|xml)\b[^>]*>.*?</\1\s*>#si'], '', $html);
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace('/[\s\x{a0}]+/u', ' ', $text));
    }
}
