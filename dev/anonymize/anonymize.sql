-- Anonymise une base BDOH restaurée depuis un dump de production.
--
-- Chaque colonne de texte de la base est classée dans anon.rules :
--   keep      gardée telle quelle : données publiques (lieux, paramètres, organismes) ou techniques ;
--   scramble  texte libre : lettres remplacées, chiffres, ponctuation et balises HTML gardés ;
--   replace   remplacée par l'expression SQL donnée, évaluée ligne à ligne ;
--   clear     vidée (NULL) ;
--   truncate  table vidée entièrement.
-- Une colonne de texte absente de la liste arrête tout, pour qu'aucune ne passe par oubli.
-- Les colonnes qui ne sont pas du texte (nombres, dates, géométries) sont gardées : les mesures
-- restent intactes.
--
-- Lancé par anonymize.sh, en une seule transaction :
--   psql -v ON_ERROR_STOP=1 --single-transaction -f anonymize.sql
-- anon.report() donne ensuite les comptes du rapport ; le schéma anon est retiré avant le dump.

SET client_min_messages = warning;

CREATE SCHEMA anon;

CREATE TABLE anon.rules (
    schema_name text NOT NULL,
    table_name  text NOT NULL,  -- '*' : toutes les tables du schéma
    column_name text NOT NULL,  -- '*' : toutes les colonnes de la table
    rule        text NOT NULL CHECK (rule IN ('keep', 'scramble', 'replace', 'clear', 'truncate')),
    expression  text CHECK ((rule = 'replace') = (expression IS NOT NULL))
);

INSERT INTO anon.rules VALUES
    -- Comptes. Tous reçoivent le mot de passe de test « bdoh-test » (bcrypt, coût 10).
    ('bdoh', 'utilisateur',          'email',                 'replace',  $$'utilisateur-' || id || '@example.invalid'$$),
    ('bdoh', 'utilisateur',          'login',                 'replace',  $$'utilisateur-' || id$$),
    ('bdoh', 'utilisateur',          'password',              'replace',  $$'$2y$10$SPSIur1.1EEIXN50SjkVtutk7fK187XCASae5HOVBaAfiQEJbVdHq'$$),
    ('bdoh', 'utilisateur',          'salt',                  'replace',  $$''$$),
    ('bdoh', 'utilisateur',          'prenom',                'replace',  $$'Test'$$),
    ('bdoh', 'utilisateur',          'nom',                   'replace',  $$'Utilisateur ' || id$$),
    ('bdoh', 'utilisateur',          'organisme',             'replace',  $$'Organisme'$$),
    ('bdoh', 'utilisateur',          'telephone',             'clear',    NULL),
    ('bdoh', 'utilisateur',          'adresse',               'clear',    NULL),
    ('bdoh', 'utilisateur',          'confirmationtoken',     'clear',    NULL),
    ('bdoh', 'utilisateur',          'roleadmin',             'keep',     NULL),
    ('bdoh', 'role',                 'valeur',                'keep',     NULL),
    ('bdoh', 'role',                 'dtype',                 'keep',     NULL),

    -- Contacts transmis à Theia/OZCAR, et ce que les observatoires y ont de personnel ou de secret.
    ('bdoh', 'personnetheia',        'nom',                   'replace',  $$'Personne ' || id$$),
    ('bdoh', 'personnetheia',        'prenom',                'replace',  $$'Test'$$),
    ('bdoh', 'personnetheia',        'email',                 'replace',  $$'personne-' || id || '@example.invalid'$$),
    ('bdoh', 'observatoire',         'email',                 'replace',  $$'observatoire-' || id || '@example.invalid'$$),
    ('bdoh', 'observatoire',         'theiapassword',         'clear',    NULL),

    -- Demandes d'accès et historique des actions : propres aux utilisateurs.
    ('bdoh', 'besoin',               'duree',                 'scramble', NULL),
    ('bdoh', 'besoin',               'details',               'scramble', NULL),
    ('bdoh', 'historique',           'description',           'scramble', NULL),
    ('bdoh', 'historique',           'name',                  'scramble', NULL),
    ('bdoh', 'historique',           'lien',                  'scramble', NULL),
    ('bdoh', 'historique',           'dtype',                 'keep',     NULL),
    ('bdoh', 'historique',           'action',                'keep',     NULL),
    ('bdoh', 'historique',           'formatexport',          'keep',     NULL),
    ('bdoh', 'historique',           'formatimport',          'keep',     NULL),
    ('bdoh', 'historique',           'fuseau',                'keep',     NULL),
    ('bdoh', 'historique',           'inputunit',             'keep',     NULL),
    ('bdoh', 'historique',           'outputunit',            'keep',     NULL),
    ('bdoh', 'historique',           'typeexport',            'keep',     NULL),
    ('bdoh', 'historique',           'timestep',              'keep',     NULL),

    -- File de tâches : arguments, sorties et traces peuvent citer des personnes. Inutile aux tests.
    ('bdoh', 'jms_jobs',             '*',                     'truncate', NULL),
    ('bdoh', 'jms_job_dependencies', '*',                     'truncate', NULL),
    ('bdoh', 'jms_job_related_entities', '*',                 'truncate', NULL),
    ('bdoh', 'jms_job_statistics',   '*',                     'truncate', NULL),
    ('bdoh', 'jms_cron_jobs',        'command',               'keep',     NULL),

    -- Textes libres des fiches : ils peuvent nommer des personnes.
    ('bdoh', 'observatoire',         'description',           'scramble', NULL),
    ('bdoh', 'observatoire',         'descriptionen',         'scramble', NULL),
    ('bdoh', 'siteexperimental',     'description',           'scramble', NULL),
    ('bdoh', 'siteexperimental',     'descriptionen',         'scramble', NULL),
    ('bdoh', 'station',              'commentaire',           'scramble', NULL),
    ('bdoh', 'station',              'commentaireen',         'scramble', NULL),
    ('bdoh', 'chronique',            'description',           'scramble', NULL),
    ('bdoh', 'chronique',            'genealogie',            'scramble', NULL),
    ('bdoh', 'chronique',            'genealogieen',          'scramble', NULL),
    ('bdoh', 'dataset',              'description',           'scramble', NULL),
    ('bdoh', 'dataset',              'descriptionen',         'scramble', NULL),
    ('bdoh', 'dataset',              'genealogie',            'scramble', NULL),
    ('bdoh', 'dataset',              'genealogieen',          'scramble', NULL),
    ('bdoh', 'doi',                  'description',           'scramble', NULL),
    ('bdoh', 'doi',                  'descriptionen',         'scramble', NULL),
    ('bdoh', 'bareme',               'commentaire',           'scramble', NULL),

    -- Noms et codes publics : observatoires, lieux, paramètres, organismes, référentiels.
    ('bdoh', 'observatoire',         'nom',                   'keep',     NULL),
    ('bdoh', 'observatoire',         'slug',                  'keep',     NULL),
    ('bdoh', 'observatoire',         'titre',                 'keep',     NULL),
    ('bdoh', 'observatoire',         'titreen',               'keep',     NULL),
    ('bdoh', 'observatoire',         'lien',                  'keep',     NULL),
    ('bdoh', 'observatoire',         'theiacode',             'keep',     NULL),
    ('bdoh', 'observatoire',         'conditionsutilisation', 'keep',     NULL),  -- chemin de fichier
    ('bdoh', 'observatoire',         'couleurprimaire',       'keep',     NULL),
    ('bdoh', 'observatoire',         'couleursecondaire',     'keep',     NULL),
    ('bdoh', 'observatoire',         'pathphotoprincipale',   'keep',     NULL),
    ('bdoh', 'observatoire',         'pathphotosecondaire',   'keep',     NULL),
    ('bdoh', 'observatoire',         'pathlogo',              'keep',     NULL),
    ('bdoh', 'siteexperimental',     'nom',                   'keep',     NULL),
    ('bdoh', 'siteexperimental',     'slug',                  'keep',     NULL),
    ('bdoh', 'station',              'nom',                   'keep',     NULL),
    ('bdoh', 'station',              'code',                  'keep',     NULL),
    ('bdoh', 'station',              'codealternatif',        'keep',     NULL),
    ('bdoh', 'chronique',            'code',                  'keep',     NULL),
    ('bdoh', 'chronique',            'libelle',               'keep',     NULL),
    ('bdoh', 'chronique',            'libelleen',             'keep',     NULL),
    ('bdoh', 'chronique',            'dtype',                 'keep',     NULL),
    ('bdoh', 'chronique',            'directionmesure',       'keep',     NULL),
    ('bdoh', 'dataset',              'titre',                 'keep',     NULL),
    ('bdoh', 'dataset',              'titreen',               'keep',     NULL),
    ('bdoh', 'dataset',              'uuid',                  'keep',     NULL),
    ('bdoh', 'doi',                  'identifiant',           'keep',     NULL),
    ('bdoh', 'bareme',               'nom',                   'keep',     NULL),
    ('bdoh', 'bassin',               'nom',                   'keep',     NULL),
    ('bdoh', 'courseau',             'nom',                   'keep',     NULL),
    ('bdoh', 'courseau',             'codehydro',             'keep',     NULL),
    ('bdoh', 'commune',              'nom',                   'keep',     NULL),
    ('bdoh', 'commune',              'codepostal',            'keep',     NULL),
    ('bdoh', 'commune',              'codeinsee',             'keep',     NULL),
    ('bdoh', 'partenaire',           'nom',                   'keep',     NULL),
    ('bdoh', 'partenaire',           'lien',                  'keep',     NULL),
    ('bdoh', 'partenaire',           'pathlogo',              'keep',     NULL),
    ('bdoh', 'partenaire',           'iso',                   'keep',     NULL),
    ('bdoh', 'partenaire',           'scanr',                 'keep',     NULL),
    ('bdoh', 'categorie',            'libelle',               'keep',     NULL),
    ('bdoh', 'categorie',            'libelleen',             'keep',     NULL),
    ('bdoh', 'criteriaclimat',       'nom',                   'keep',     NULL),
    ('bdoh', 'criteriageology',      'nom',                   'keep',     NULL),
    ('bdoh', 'dataconstraint',       'nom',                   'keep',     NULL),
    ('bdoh', 'echantillonnage',      'nom',                   'keep',     NULL),
    ('bdoh', 'echantillonnage',      'nomen',                 'keep',     NULL),
    ('bdoh', 'echantillonnage',      'code',                  'keep',     NULL),
    ('bdoh', 'familleparametres',    'nom',                   'keep',     NULL),
    ('bdoh', 'familleparametres',    'nomen',                 'keep',     NULL),
    ('bdoh', 'familleparametres',    'prefix',                'keep',     NULL),
    ('bdoh', 'inspiretheme',         'nom',                   'keep',     NULL),
    ('bdoh', 'jeubareme',            'valuelimittransformationtype', 'keep', NULL),
    ('bdoh', 'jeuqualite',           'nom',                   'keep',     NULL),
    ('bdoh', 'jeuqualite',           'checkpointstyle',       'keep',     NULL),
    ('bdoh', 'jeuqualite',           'discontinuousstyle',    'keep',     NULL),
    ('bdoh', 'migration_versions',   'version',               'keep',     NULL),
    ('bdoh', 'milieu',               'nom',                   'keep',     NULL),
    ('bdoh', 'objectifrecherche',    'libelle',               'keep',     NULL),
    ('bdoh', 'objectifrecherche',    'libelleen',             'keep',     NULL),
    ('bdoh', 'pasechantillonnage',   'unite',                 'keep',     NULL),
    ('bdoh', 'pasechantillonnage',   'libelle',               'keep',     NULL),
    ('bdoh', 'pasechantillonnage',   'libelleen',             'keep',     NULL),
    ('bdoh', 'qualite',              'code',                  'keep',     NULL),
    ('bdoh', 'qualite',              'libelle',               'keep',     NULL),
    ('bdoh', 'qualite',              'libelleen',             'keep',     NULL),
    ('bdoh', 'qualite',              'type',                  'keep',     NULL),
    ('bdoh', 'qualite',              'style',                 'keep',     NULL),
    ('bdoh', 'theiacategories',      'nom',                   'keep',     NULL),
    ('bdoh', 'theiacategories',      'uriozcartheia',         'keep',     NULL),
    ('bdoh', 'topiccategory',        'nom',                   'keep',     NULL),
    ('bdoh', 'typefunding',          'nom',                   'keep',     NULL),
    ('bdoh', 'typeparametre',        'nom',                   'keep',     NULL),
    ('bdoh', 'typeparametre',        'nomen',                 'keep',     NULL),
    ('bdoh', 'typeparametre',        'code',                  'keep',     NULL),
    ('bdoh', 'typetravaux',          'libelle',               'keep',     NULL),
    ('bdoh', 'typetravaux',          'libelleen',             'keep',     NULL),
    ('bdoh', 'unite',                'libelle',               'keep',     NULL),
    ('bdoh', 'unite',                'libelleen',             'keep',     NULL),

    -- Tables de PostGIS.
    ('public',   'spatial_ref_sys',  '*',                     'keep',     NULL),
    ('topology', '*',                '*',                     'keep',     NULL);

-- Les colonnes de texte de la base, hors catalogues.
CREATE VIEW anon.text_columns AS
SELECT c.table_schema AS schema_name, c.table_name, c.column_name
  FROM information_schema.columns c
  JOIN information_schema.tables t ON t.table_schema = c.table_schema AND t.table_name = c.table_name
 WHERE t.table_type = 'BASE TABLE'
   AND c.table_schema NOT IN ('pg_catalog', 'information_schema', 'anon')
   AND c.udt_name IN ('text', 'varchar', 'bpchar', 'name', 'json', 'jsonb', 'xml', 'bytea',
                      '_text', '_varchar', '_bpchar', '_json', '_jsonb');

-- Arrêt si une colonne de texte n'est pas classée, ou si une règle vise une colonne absente.
DO $$
DECLARE
    unclassified text;
    unknown text;
BEGIN
    SELECT string_agg(format('%s.%s.%s', schema_name, table_name, column_name), ', '
                      ORDER BY schema_name, table_name, column_name)
      INTO unclassified
      FROM anon.text_columns c
     WHERE NOT EXISTS (SELECT 1 FROM anon.rules r
                        WHERE r.schema_name = c.schema_name
                          AND r.table_name IN (c.table_name, '*')
                          AND r.column_name IN (c.column_name, '*'));
    IF unclassified IS NOT NULL THEN
        RAISE EXCEPTION 'Colonnes de texte non classées : %', unclassified;
    END IF;

    SELECT string_agg(format('%s.%s.%s', schema_name, table_name, column_name), ', ')
      INTO unknown
      FROM anon.rules r
     WHERE r.column_name <> '*'
       AND NOT EXISTS (SELECT 1 FROM information_schema.columns c
                        WHERE c.table_schema = r.schema_name
                          AND c.table_name = r.table_name
                          AND c.column_name = r.column_name);
    IF unknown IS NOT NULL THEN
        RAISE EXCEPTION 'Règles sur des colonnes absentes de la base : %', unknown;
    END IF;
END $$;

-- Brouille un texte libre en gardant sa forme : longueur, casse, chiffres, ponctuation, balises
-- HTML et entités. Les valeurs des attributs HTML (liens, courriels) sont effacées, les numéros
-- de téléphone mis à zéro, les courriels remplacés.
CREATE FUNCTION anon.scramble(value text) RETURNS text
LANGUAGE plpgsql IMMUTABLE AS $$
DECLARE
    letters constant text := 'abcdefghijklmnopqrstuvwxyz' || 'ABCDEFGHIJKLMNOPQRSTUVWXYZ'
                          || 'àáâãäåæçèéêëìíîïñòóôõöøùúûüýÿœß'
                          || 'ÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÑÒÓÔÕÖØÙÚÛÜÝŸŒ';
    masks constant text := repeat('x', 26) || repeat('X', 26) || repeat('é', 31) || repeat('É', 30);
    piece text;
    result text := '';
BEGIN
    IF length(letters) <> length(masks) THEN
        RAISE EXCEPTION 'anon.scramble : table de remplacement incohérente';
    END IF;
    IF value IS NULL THEN
        RETURN NULL;
    END IF;
    value := regexp_replace(value, '(?<![0-9])(\+33\s?|0)[1-9]([\s.-]?[0-9]{2}){4}(?![0-9])',
                            '00 00 00 00 00', 'g');
    FOR piece IN SELECT (regexp_matches(value, '(<[^>]*>|&#?[A-Za-z0-9]+;|[^<&]+|[<&])', 'g'))[1] LOOP
        IF piece ~ '^<.+>$' THEN
            result := result || regexp_replace(piece, '=\s*("[^"]*"|''[^'']*''|[^\s>]+)', '=""', 'g');
        ELSIF piece ~ '^&#?[A-Za-z0-9]+;$' THEN
            result := result || piece;
        ELSE
            result := result || translate(piece, letters, masks);
        END IF;
    END LOOP;
    RETURN regexp_replace(result, '[^\s<>@,;:()"'']+@[^\s<>@,;:()"'']+', 'masque@example.invalid', 'g');
END $$;

DO $$
BEGIN
    ASSERT anon.scramble('<p class="intro">Jean Dupont, 06 12 34 56 78, jean.dupont@inrae.fr, '
                         || '&eacute;t&eacute; 2015 <a href="mailto:j@d.fr">ici</a></p>')
         = '<p class="">Xxxx Xxxxxx, 00 00 00 00 00, masque@example.invalid, '
           || '&eacute;x&eacute; 2015 <a href="">xxx</a></p>',
         'anon.scramble ne rend pas ce qui est attendu';
END $$;

-- Application des règles : tables vidées, puis remplacements, puis textes brouillés.
DO $$
DECLARE
    r record;
    tables text;
BEGIN
    SELECT string_agg(format('%I.%I', schema_name, table_name), ', ')
      INTO tables
      FROM anon.rules WHERE rule = 'truncate';
    IF tables IS NOT NULL THEN
        EXECUTE 'TRUNCATE ' || tables;
    END IF;

    FOR r IN SELECT * FROM anon.rules WHERE rule IN ('replace', 'clear', 'scramble') LOOP
        EXECUTE format('UPDATE %I.%I SET %I = %s WHERE %I IS NOT NULL',
                       r.schema_name, r.table_name, r.column_name,
                       CASE r.rule
                           WHEN 'replace' THEN r.expression
                           WHEN 'clear' THEN 'NULL'
                           ELSE format('anon.scramble(%I)', r.column_name)
                       END,
                       r.column_name);
    END LOOP;
END $$;

-- Rapport : uniquement des comptes. Les lignes « fuite » et « contrôle » doivent valoir 0.
CREATE FUNCTION anon.report() RETURNS TABLE (item text, n bigint)
LANGUAGE plpgsql AS $$
DECLARE
    c record;
    email constant text := '[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}';
    fake_email constant text := '[A-Za-z0-9._%+-]+@example\.invalid';
    phone constant text := '(?<![0-9])(\+33\s?|0)[1-9]([\s.-]?[0-9]{2}){4}(?![0-9])';
BEGIN
    FOR c IN SELECT table_name FROM information_schema.tables
              WHERE table_schema = 'bdoh' AND table_type = 'BASE TABLE' ORDER BY table_name LOOP
        item := 'lignes ' || c.table_name;
        EXECUTE format('SELECT count(*) FROM bdoh.%I', c.table_name) INTO n;
        RETURN NEXT;
    END LOOP;

    -- Courriels hors example.invalid et numéros de téléphone, dans toute colonne de texte restante,
    -- hors tables gardées en bloc (celles de PostGIS, dont les chaînes de projection ressemblent à
    -- des numéros).
    FOR c IN SELECT t.* FROM anon.text_columns t
               JOIN information_schema.columns i
                 ON i.table_schema = t.schema_name AND i.table_name = t.table_name
                AND i.column_name = t.column_name
              WHERE i.udt_name <> 'bytea'
                AND NOT EXISTS (SELECT 1 FROM anon.rules r
                                 WHERE r.schema_name = t.schema_name
                                   AND r.table_name IN (t.table_name, '*')
                                   AND r.column_name = '*' AND r.rule = 'keep')
              ORDER BY 1, 2, 3 LOOP
        EXECUTE format($f$SELECT count(*) FROM %I.%I
                          WHERE regexp_replace(%I::text, %L, '', 'g') ~ %L OR %I::text ~ %L$f$,
                       c.schema_name, c.table_name, c.column_name, fake_email, email,
                       c.column_name, phone)
           INTO n;
        IF n > 0 THEN
            item := format('fuite %s.%s.%s', c.schema_name, c.table_name, c.column_name);
            RETURN NEXT;
        END IF;
    END LOOP;

    item := 'contrôle comptes sans le mot de passe de test';
    SELECT count(*) INTO n FROM bdoh.utilisateur
     WHERE password <> '$2y$10$SPSIur1.1EEIXN50SjkVtutk7fK187XCASae5HOVBaAfiQEJbVdHq';
    RETURN NEXT;
    item := 'contrôle mots de passe Theia restants';
    SELECT count(*) INTO n FROM bdoh.observatoire WHERE theiapassword IS NOT NULL;
    RETURN NEXT;
    item := 'contrôle jetons de confirmation restants';
    SELECT count(*) INTO n FROM bdoh.utilisateur WHERE confirmationtoken IS NOT NULL;
    RETURN NEXT;
END $$;
