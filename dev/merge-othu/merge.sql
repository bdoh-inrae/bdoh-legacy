-- Fusion d'OTHU dans la base de production : OTHU devient un observatoire de BDOH, avec toutes ses données,
-- sans rien changer à celles des autres. Pourquoi, et les règles de rapprochement des listes communes :
-- dev/plans/2026-11-13_othu-merge.md.
--
-- Avant : la base d'OTHU chargée dans le schéma othu de la base cible, par make merge-othu (développement) ou
-- make vm-merge-othu (VM). Le script lit othu.*, écrit dans bdoh.*, en une seule transaction : une erreur, et
-- rien n'a changé. La correspondance des identifiants reste dans le schéma fusion_othu, que lit unmerge.sql.
-- Il n'écrit que des comptes et des libellés de listes communes, aucune donnée personnelle.
\set ON_ERROR_STOP on
SET client_min_messages = notice;
SET search_path = public;
BEGIN;

-- --- Gardes ------------------------------------------------------------------------------------------------

DO $$
DECLARE
    unknown text;
    differing text;
BEGIN
    IF to_regnamespace('othu') IS NULL THEN
        RAISE EXCEPTION 'Pas de schéma othu : la base d''OTHU n''est pas chargée';
    END IF;
    IF to_regnamespace('fusion_othu') IS NOT NULL THEN
        RAISE EXCEPTION 'Le schéma fusion_othu existe : la fusion a déjà eu lieu';
    END IF;
    IF to_regclass('bdoh.favorite') IS NULL OR to_regclass('bdoh.station_point_rebuilt') IS NULL THEN
        RAISE EXCEPTION 'La base cible n''a pas les migrations du nouveau BDOH';
    END IF;
    IF (SELECT count(*) FROM othu.observatoire) <> 1 THEN
        RAISE EXCEPTION 'La base d''OTHU doit avoir un seul observatoire';
    END IF;
    IF EXISTS (SELECT 1 FROM bdoh.observatoire p JOIN othu.observatoire o
                   ON p.nom = o.nom OR p.slug = o.slug OR p.theiacode = o.theiacode) THEN
        RAISE EXCEPTION 'Un observatoire de la base cible a le nom, l''adresse ou le code Theia d''OTHU';
    END IF;
    -- Chaque table d'OTHU est reprise, vide, ou laissée en connaissance de cause (plus bas)
    SELECT string_agg(table_name, ', ') INTO unknown FROM information_schema.tables
     WHERE table_schema = 'othu' AND table_type = 'BASE TABLE' AND table_name NOT IN (
        'categorie', 'commune', 'criteriaclimat', 'criteriageology', 'dataconstraint', 'inspiretheme', 'topiccategory',
        'typefunding', 'typetravaux', 'objectifrecherche', 'milieu', 'echantillonnage', 'pasechantillonnage',
        'optionechantillonnagesortie', 'familleparametres', 'typeparametre', 'unite', 'parametres_unites',
        'jeuqualite', 'qualite', 'qualitejeuqualite', 'utilisateur', 'observatoire', 'partenaire', 'personnetheia',
        'doi', 'observatoires_data_manager', 'siteexperimental', 'station', 'stations_sites', 'bassin', 'courseau',
        'dataset', 'criteria_climat_dataset', 'criteria_geology_dataset', 'data_collector_dataset',
        'data_manager_dataset', 'principal_investigator_dataset', 'project_member_dataset', 'topic_category_dataset',
        'bareme', 'chronique', 'echantillonnagesortielicite_chronique', 'optionechantillonnagesortie_chronique',
        'mesure', 'plage', 'pointcontrole', 'tauxremplissage', 'besoin', 'besoins_parametres', 'besoins_stations',
        'role', 'historique', 'favorite', 'station_point_rebuilt',
        -- Vides dans OTHU, vérifié plus bas : la chaîne de calcul et deux listes que la fusion ne rapproche pas
        'transformation', 'jeubareme', 'baremejeubareme', 'conversion', 'jeuconversion', 'interp_qualite',
        'theiacategories',
        -- Laissées : l'historique des tâches, et les migrations de l'ancien BDOH et du nouveau
        'jms_jobs', 'jms_job_dependencies', 'jms_job_related_entities', 'jms_job_statistics', 'jms_cron_jobs',
        'migration_versions', 'doctrine_migration_versions');
    IF unknown IS NOT NULL THEN
        RAISE EXCEPTION 'Tables d''OTHU que la fusion ne connaît pas : %', unknown;
    END IF;
    -- Mêmes colonnes, de mêmes types, des deux côtés
    SELECT string_agg(DISTINCT table_name, ', ') INTO differing FROM (
        (SELECT table_name, column_name, udt_name FROM information_schema.columns WHERE table_schema = 'othu'
         EXCEPT SELECT table_name, column_name, udt_name FROM information_schema.columns WHERE table_schema = 'bdoh')
        UNION ALL
        (SELECT table_name, column_name, udt_name FROM information_schema.columns WHERE table_schema = 'bdoh'
            AND table_name IN (SELECT table_name FROM information_schema.tables WHERE table_schema = 'othu')
         EXCEPT SELECT table_name, column_name, udt_name FROM information_schema.columns WHERE table_schema = 'othu')
    ) d WHERE table_name NOT IN ('migration_versions', 'doctrine_migration_versions');
    IF differing IS NOT NULL THEN
        RAISE EXCEPTION 'Colonnes différentes entre OTHU et la base cible : %', differing;
    END IF;
    IF EXISTS (SELECT 1 FROM othu.transformation) OR EXISTS (SELECT 1 FROM othu.conversion)
       OR EXISTS (SELECT 1 FROM othu.jeubareme) OR EXISTS (SELECT 1 FROM othu.baremejeubareme)
       OR EXISTS (SELECT 1 FROM othu.jeuconversion) OR EXISTS (SELECT 1 FROM othu.interp_qualite)
       OR EXISTS (SELECT 1 FROM othu.theiacategories) THEN
        RAISE EXCEPTION 'OTHU a des transformations, des conversions ou des catégories Theia : la fusion ne les reprend pas';
    END IF;
    IF EXISTS (SELECT lower(trim(email)) FROM othu.utilisateur GROUP BY 1 HAVING count(*) > 1) THEN
        RAISE EXCEPTION 'Deux comptes d''OTHU ont la même adresse, à la casse près : à corriger dans OTHU d''abord';
    END IF;
    -- Sa contrainte d'accès est rapprochée de celle des jeux sans chronique en accès restreint (plus bas)
    IF EXISTS (SELECT 1 FROM othu.chronique WHERE NOT estvisible) THEN
        RAISE EXCEPTION 'OTHU a des chroniques cachées : le rapprochement de sa contrainte d''accès est à revoir';
    END IF;
END $$;

-- --- Ce que garde la fusion, pour son retour arrière ----------------------------------------------------------

CREATE SCHEMA fusion_othu;
COMMENT ON SCHEMA fusion_othu IS 'Fusion d''OTHU (dev/merge-othu/merge.sql) : correspondance des identifiants, lue par unmerge.sql';
-- Ancien identifiant d'une ligne d'OTHU, et celui de la base fusionnée ; added : ligne ajoutée, et non rapprochée
-- d'une ligne qui existait (listes communes, comptes)
CREATE TABLE fusion_othu.id_map (tab text, old_id bigint, new_id bigint NOT NULL, added boolean NOT NULL,
                                 PRIMARY KEY (tab, old_id));
-- Les identifiants réservés pour les lignes ajoutées, table par table
CREATE TABLE fusion_othu.ranges (tab text, first_id bigint, last_id bigint);
-- Les lignes ajoutées aux tables de liaison, qui n'ont pas d'identifiant
CREATE TABLE fusion_othu.links (tab text, line jsonb);
-- Les compteurs d'identifiants d'avant la fusion
CREATE TABLE fusion_othu.sequences (seq text PRIMARY KEY, last_value bigint, is_called boolean);
CREATE TABLE fusion_othu.info (key text PRIMARY KEY, value text);
INSERT INTO fusion_othu.info VALUES ('date', now()::text);

DO $$
DECLARE s record; v record;
BEGIN
    FOR s IN SELECT sequencename FROM pg_sequences WHERE schemaname = 'bdoh' LOOP
        EXECUTE format('SELECT last_value, is_called FROM bdoh.%I', s.sequencename) INTO v;
        INSERT INTO fusion_othu.sequences VALUES (s.sequencename, v.last_value, v.is_called);
    END LOOP;
END $$;

-- --- Outils ---------------------------------------------------------------------------------------------------

-- Réserve n identifiants à la suite du compteur de la table (même si d'autres en prennent pendant la fusion), et
-- rend le premier
CREATE FUNCTION pg_temp.reserve(t text, n bigint) RETURNS bigint LANGUAGE plpgsql AS $$
DECLARE first bigint; seq text := format('bdoh.%I', t || '_id_seq');
BEGIN
    IF n = 0 THEN RETURN NULL; END IF;
    first := nextval(seq);
    PERFORM setval(seq, first + n - 1);
    INSERT INTO fusion_othu.ranges VALUES (t, first, first + n - 1);
    RETURN first;
END $$;

-- Un nouvel identifiant pour chaque ligne d'OTHU que rien n'a rapprochée, dans l'ordre des anciens
CREATE FUNCTION pg_temp.assign(t text) RETURNS void LANGUAGE plpgsql AS $$
DECLARE n bigint; first bigint;
BEGIN
    EXECUTE format('SELECT count(*) FROM othu.%1$I o WHERE NOT EXISTS
                      (SELECT 1 FROM fusion_othu.id_map k WHERE k.tab = %1$L AND k.old_id = o.id)', t) INTO n;
    first := pg_temp.reserve(t, n);
    EXECUTE format('INSERT INTO fusion_othu.id_map
                    SELECT %1$L, o.id, %2$s + row_number() OVER (ORDER BY o.id) - 1, true FROM othu.%1$I o
                     WHERE NOT EXISTS (SELECT 1 FROM fusion_othu.id_map k WHERE k.tab = %1$L AND k.old_id = o.id)',
                   t, coalesce(first, 0));
END $$;

-- Le nouvel identifiant d'une ligne d'OTHU ; une ligne sans correspondance arrête la fusion
CREATE FUNCTION pg_temp.m(t text, old bigint) RETURNS bigint LANGUAGE plpgsql STABLE AS $$
DECLARE r bigint;
BEGIN
    IF old IS NULL THEN RETURN NULL; END IF;
    SELECT new_id INTO r FROM fusion_othu.id_map WHERE tab = t AND old_id = old;
    IF r IS NULL THEN RAISE EXCEPTION 'Sans correspondance : %.id = %', t, old; END IF;
    RETURN r;
END $$;

-- Recopie les lignes ajoutées d'une table d'OTHU : son identifiant et chaque clé étrangère passent par la
-- correspondance, les colonnes de nulls valent NULL. Une table de liaison, sans identifiant, reçoit les lignes
-- qu'elle n'a pas déjà. Rend le nombre de lignes écrites.
CREATE FUNCTION pg_temp.add_rows(t text, nulls text[] DEFAULT '{}') RETURNS bigint LANGUAGE plpgsql AS $$
DECLARE cols text; exprs text; has_id boolean; n bigint; expected bigint; left_out bigint;
BEGIN
    SELECT string_agg(quote_ident(a.attname), ', ' ORDER BY a.attnum),
           string_agg(CASE WHEN a.attname = ANY (nulls) THEN 'NULL'
                           WHEN a.attname = 'id' THEN format('pg_temp.m(%L, o.id)', t)
                           WHEN f.ref IS NOT NULL THEN format('pg_temp.m(%L, o.%I)', f.ref, a.attname)
                           ELSE 'o.' || quote_ident(a.attname) END, ', ' ORDER BY a.attnum),
           bool_or(a.attname = 'id')
      INTO cols, exprs, has_id
      FROM pg_attribute a
      LEFT JOIN (SELECT c.conkey[1] AS attnum, r.relname AS ref FROM pg_constraint c JOIN pg_class r ON r.oid = c.confrelid
                  WHERE c.conrelid = format('bdoh.%I', t)::regclass AND c.contype = 'f') f ON f.attnum = a.attnum
     WHERE a.attrelid = format('bdoh.%I', t)::regclass AND a.attnum > 0 AND NOT a.attisdropped;
    IF has_id THEN
        EXECUTE format('INSERT INTO bdoh.%1$I (%2$s) SELECT %3$s FROM othu.%1$I o
                          JOIN fusion_othu.id_map k ON k.tab = %1$L AND k.old_id = o.id AND k.added', t, cols, exprs);
        GET DIAGNOSTICS n = ROW_COUNT;
        SELECT count(*) INTO expected FROM fusion_othu.id_map WHERE tab = t AND added;
        IF n <> expected THEN RAISE EXCEPTION '% : % lignes écrites, % attendues', t, n, expected; END IF;
    ELSE
        EXECUTE format('WITH added AS (INSERT INTO bdoh.%1$I (%2$s) SELECT %3$s FROM othu.%1$I o
                                       EXCEPT SELECT %2$s FROM bdoh.%1$I RETURNING *)
                        INSERT INTO fusion_othu.links SELECT %1$L, to_jsonb(added) FROM added', t, cols, exprs);
        GET DIAGNOSTICS n = ROW_COUNT;
        EXECUTE format('SELECT count(*) FROM (SELECT %3$s FROM othu.%1$I o EXCEPT SELECT %2$s FROM bdoh.%1$I) x',
                       t, cols, exprs) INTO left_out;
        IF left_out > 0 THEN RAISE EXCEPTION '% : % lignes d''OTHU manquent', t, left_out; END IF;
    END IF;
    RETURN n;
END $$;

-- Rapproche les lignes d'une liste d'OTHU de celles de la base cible par une condition sur o (OTHU) et p (cible),
-- celles qui n'ont pas encore de correspondance ; la plus ancienne des lignes qui conviennent
CREATE FUNCTION pg_temp.match(t text, cond text) RETURNS void LANGUAGE plpgsql AS $$
BEGIN
    EXECUTE format('INSERT INTO fusion_othu.id_map
                    SELECT %1$L, o.id, min(p.id), false FROM othu.%1$I o JOIN bdoh.%1$I p ON %2$s
                     WHERE NOT EXISTS (SELECT 1 FROM fusion_othu.id_map k WHERE k.tab = %1$L AND k.old_id = o.id)
                     GROUP BY o.id', t, cond);
END $$;

-- Arrête la fusion si une ligne de la liste n'a pas de pareil
CREATE FUNCTION pg_temp.all_matched(t text) RETURNS void LANGUAGE plpgsql AS $$
DECLARE n bigint;
BEGIN
    EXECUTE format('SELECT count(*) FROM othu.%1$I o WHERE NOT EXISTS
                      (SELECT 1 FROM fusion_othu.id_map k WHERE k.tab = %1$L AND k.old_id = o.id)', t) INTO n;
    IF n > 0 THEN RAISE EXCEPTION 'Liste % : % ligne(s) d''OTHU sans pareil dans la base cible', t, n; END IF;
END $$;

-- Ajoute les lignes d'OTHU que rien n'a rapprochées, sous de nouveaux identifiants
CREATE FUNCTION pg_temp.take(t text, nulls text[] DEFAULT '{}') RETURNS void LANGUAGE plpgsql AS $$
BEGIN
    PERFORM pg_temp.assign(t);
    PERFORM pg_temp.add_rows(t, nulls);
END $$;

-- --- Listes communes ------------------------------------------------------------------------------------------

DO $$
DECLARE t text;
BEGIN
    -- Identiques à celles de la production, hors identifiant : une ligne sans pareil arrête la fusion
    FOREACH t IN ARRAY ARRAY['categorie', 'criteriaclimat', 'criteriageology', 'dataconstraint', 'inspiretheme',
                             'topiccategory', 'typefunding', 'typetravaux', 'objectifrecherche', 'milieu',
                             'echantillonnage', 'pasechantillonnage'] LOOP
        PERFORM pg_temp.match(t, $c$to_jsonb(p) - 'id' = to_jsonb(o) - 'id'$c$);
        -- La contrainte d'accès des jeux d'OTHU, sous le nom que la migration Version20261113120000 a changé dans
        -- la cible : OTHU n'a pas de chronique en accès restreint
        IF t = 'dataconstraint' THEN
            PERFORM pg_temp.match(t, $c$o.nom = 'Conditions Generales Utilisation (voir pdf)'
                                        AND p.nom = 'Licence Ouverte Etalab 2.0 (etalab-2.0)'$c$);
        END IF;
        PERFORM pg_temp.all_matched(t);
    END LOOP;
    PERFORM pg_temp.match('optionechantillonnagesortie',
        $c$p.echantillonnageentree_id = pg_temp.m('echantillonnage', o.echantillonnageentree_id)
           AND p.pasechantillonnage_id = pg_temp.m('pasechantillonnage', o.pasechantillonnage_id)$c$);
    PERFORM pg_temp.all_matched('optionechantillonnagesortie');

    -- Qualités : les jeux par leur nom, chaque qualité par son code dans son jeu ; leurs traductions, ajoutées
    -- s'il en manque
    PERFORM pg_temp.match('jeuqualite', 'p.nom = o.nom');
    PERFORM pg_temp.all_matched('jeuqualite');
    PERFORM pg_temp.match('qualite', $c$p.jeu_id = pg_temp.m('jeuqualite', o.jeu_id) AND p.code = o.code$c$);
    PERFORM pg_temp.all_matched('qualite');
    PERFORM pg_temp.match('qualitejeuqualite', $c$p.jeu_id = pg_temp.m('jeuqualite', o.jeu_id)
        AND p.qualite_id = pg_temp.m('qualite', o.qualite_id) AND p.traduction_id = pg_temp.m('qualite', o.traduction_id)$c$);
    PERFORM pg_temp.take('qualitejeuqualite');

    -- Familles de paramètres, puis types de paramètres : par nom, puis par nom anglais ; les autres sont ajoutés
    PERFORM pg_temp.match('familleparametres', 'p.nom = o.nom');
    PERFORM pg_temp.match('familleparametres', 'p.nomen = o.nomen');
    PERFORM pg_temp.take('familleparametres');
    PERFORM pg_temp.match('typeparametre', 'p.nom = o.nom');
    PERFORM pg_temp.match('typeparametre', 'p.nomen = o.nomen');
    PERFORM pg_temp.take('typeparametre');

    -- Unités : par libellé, par libellé anglais, puis trois écritures d'une même unité ; les autres sont ajoutées
    PERFORM pg_temp.match('unite', 'p.libelle = o.libelle');
    PERFORM pg_temp.match('unite', 'p.libelleen = o.libelleen');
    PERFORM pg_temp.match('unite', $c$(o.libelle, p.libelle) IN (('m³', 'm3'), ('cm3/cm3', 'm3.m-3'), ('mm/jour', 'mm/j'))$c$);
    PERFORM pg_temp.take('unite');
    PERFORM pg_temp.add_rows('parametres_unites');

    -- Communes : par code postal et code INSEE, puis par code postal et nom (la Villeurbanne d'OTHU a un code
    -- INSEE qui n'existe pas) ; les autres sont ajoutées
    PERFORM pg_temp.match('commune', 'p.codepostal = o.codepostal AND p.codeinsee = o.codeinsee');
    PERFORM pg_temp.match('commune', 'p.codepostal = o.codepostal AND p.nom = o.nom');
    PERFORM pg_temp.take('commune');

    -- Comptes : un compte d'OTHU dont l'adresse existe déjà est rapproché de ce compte, qui reste tel quel. Les
    -- autres sont ajoutés, sans rôle d'administration : un administrateur d'OTHU devient gestionnaire de
    -- l'observatoire (plus bas).
    PERFORM pg_temp.match('utilisateur', $c$lower(trim(p.email)) = lower(trim(o.email)) AND trim(o.email) <> ''$c$);
    PERFORM pg_temp.take('utilisateur', ARRAY['roleadmin']);

    -- L'observatoire : son responsable et son DOI principal viennent de tables qui dépendent de lui, posés après elles.
    -- Son mot de passe Theia, chiffré par la clé de l'ancien serveur d'OTHU, ne se déchiffrerait pas : à ressaisir.
    PERFORM pg_temp.take('observatoire', ARRAY['projectleader_id', 'doiprincipal_id', 'theiapassword']);
    PERFORM pg_temp.take('partenaire');
    PERFORM pg_temp.take('personnetheia');
    PERFORM pg_temp.take('doi');
    UPDATE bdoh.observatoire p
       SET projectleader_id = pg_temp.m('personnetheia', o.projectleader_id),
           doiprincipal_id = pg_temp.m('doi', o.doiprincipal_id)
      FROM othu.observatoire o WHERE p.id = pg_temp.m('observatoire', o.id);
    PERFORM pg_temp.add_rows('observatoires_data_manager');

    -- Ce qui lui appartient, chaque table après celles qu'elle cite
    PERFORM pg_temp.take('siteexperimental');
    PERFORM pg_temp.take('station');
    PERFORM pg_temp.add_rows('stations_sites');
    PERFORM pg_temp.take('bassin');
    PERFORM pg_temp.take('courseau');
    PERFORM pg_temp.take('dataset');
    FOREACH t IN ARRAY ARRAY['criteria_climat_dataset', 'criteria_geology_dataset', 'data_collector_dataset',
                             'data_manager_dataset', 'principal_investigator_dataset', 'project_member_dataset',
                             'topic_category_dataset'] LOOP
        PERFORM pg_temp.add_rows(t);
    END LOOP;
    PERFORM pg_temp.take('bareme');
    PERFORM pg_temp.take('chronique');
    PERFORM pg_temp.add_rows('echantillonnagesortielicite_chronique');
    PERFORM pg_temp.add_rows('optionechantillonnagesortie_chronique');
END $$;

-- Les mesures, d'un bloc : leurs identifiants gardent leurs écarts, à la suite de ceux de la production
DO $$
DECLARE lo bigint; hi bigint; first bigint; n bigint;
BEGIN
    SELECT min(id), max(id) INTO lo, hi FROM othu.mesure;
    IF lo IS NULL THEN RETURN; END IF;
    first := pg_temp.reserve('mesure', hi - lo + 1);
    INSERT INTO bdoh.mesure (id, qualite_id, chronique_id, date, valeur, minimum, maximum, estcalculee)
    SELECT first + (m.id - lo), q.new_id, c.new_id, m.date, m.valeur, m.minimum, m.maximum, m.estcalculee
      FROM othu.mesure m
      JOIN fusion_othu.id_map c ON c.tab = 'chronique' AND c.old_id = m.chronique_id
      LEFT JOIN fusion_othu.id_map q ON q.tab = 'qualite' AND q.old_id = m.qualite_id;
    GET DIAGNOSTICS n = ROW_COUNT;
    IF n <> (SELECT count(*) FROM othu.mesure) THEN RAISE EXCEPTION 'mesure : % lignes écrites', n; END IF;
END $$;

DO $$
BEGIN
    PERFORM pg_temp.take('plage');
    PERFORM pg_temp.take('pointcontrole');
    PERFORM pg_temp.take('tauxremplissage');
    PERFORM pg_temp.take('besoin');
    PERFORM pg_temp.add_rows('besoins_parametres');
    PERFORM pg_temp.add_rows('besoins_stations');
    PERFORM pg_temp.take('role');
    PERFORM pg_temp.take('historique');
END $$;

-- Les administrateurs d'OTHU, gestionnaires de son observatoire s'ils ne l'étaient pas déjà
DO $$
DECLARE n bigint; first bigint;
BEGIN
    CREATE TEMP TABLE admins AS
    SELECT DISTINCT pg_temp.m('utilisateur', u.id) AS utilisateur_id, pg_temp.m('observatoire', o.id) AS observatoire_id
      FROM othu.utilisateur u, othu.observatoire o
     WHERE u.roleadmin IS NOT NULL
       AND NOT EXISTS (SELECT 1 FROM othu.role r WHERE r.utilisateur_id = u.id AND r.observatoire_id = o.id
                          AND r.valeur = 'gestionnaire' AND r.dtype = 'observatoire');
    SELECT count(*) INTO n FROM admins;
    first := pg_temp.reserve('role', n);
    INSERT INTO bdoh.role (id, observatoire_id, utilisateur_id, site_id, chronique_id, valeur, dtype)
    SELECT first + row_number() OVER (ORDER BY utilisateur_id) - 1, observatoire_id, utilisateur_id, NULL, NULL,
           'gestionnaire', 'observatoire'
      FROM admins;
END $$;

-- Les favoris, si la base d'OTHU a déjà les migrations du nouveau BDOH (développement)
DO $$
BEGIN
    IF to_regclass('othu.favorite') IS NOT NULL THEN
        PERFORM pg_temp.take('favorite');
    END IF;
END $$;

-- Les points des stations qui ont des coordonnées sans point, comme la migration Version20261103120000, notés
-- pour son retour arrière ; et ceux qu'elle a déjà reconstruits dans OTHU (développement)
DO $$
BEGIN
    INSERT INTO bdoh.station_point_rebuilt
    SELECT s.id FROM bdoh.station s JOIN fusion_othu.id_map k ON k.tab = 'station' AND k.new_id = s.id
     WHERE s.point IS NULL AND s.latitude IS NOT NULL AND s.longitude IS NOT NULL;
    UPDATE bdoh.station SET point = ST_SetSRID(ST_MakePoint(latitude, longitude), 2154)
     WHERE point IS NULL AND id IN (SELECT new_id FROM fusion_othu.id_map WHERE tab = 'station')
       AND id IN (SELECT id FROM bdoh.station_point_rebuilt);
    IF to_regclass('othu.station_point_rebuilt') IS NOT NULL THEN
        INSERT INTO bdoh.station_point_rebuilt SELECT pg_temp.m('station', id) FROM othu.station_point_rebuilt;
    END IF;
END $$;

-- --- Vérifications --------------------------------------------------------------------------------------------

-- L'empreinte de chaque chronique d'OTHU, des deux côtés, les qualités par leur correspondance : celle de
-- dev/reference/fingerprint.sql
DO $$
DECLARE d bigint;
BEGIN
    CREATE TEMP TABLE chroniques AS SELECT old_id, new_id FROM fusion_othu.id_map WHERE tab = 'chronique';
    CREATE TEMP TABLE qualites AS SELECT old_id, new_id FROM fusion_othu.id_map WHERE tab = 'qualite';

    WITH src AS (SELECT c.new_id AS chronique_id, count(*) AS n,
                        sum(('x' || substr(md5(format('%s;%s;%s;%s;%s;%s', m.date, encode(float8send(m.valeur), 'hex'),
                            encode(float8send(m.minimum), 'hex'), encode(float8send(m.maximum), 'hex'), q.new_id,
                            m.estcalculee)), 1, 15))::bit(60)::bigint) AS h
                   FROM othu.mesure m JOIN chroniques c ON c.old_id = m.chronique_id
                   LEFT JOIN qualites q ON q.old_id = m.qualite_id GROUP BY 1),
         dst AS (SELECT m.chronique_id, count(*) AS n,
                        sum(('x' || substr(md5(format('%s;%s;%s;%s;%s;%s', m.date, encode(float8send(m.valeur), 'hex'),
                            encode(float8send(m.minimum), 'hex'), encode(float8send(m.maximum), 'hex'), m.qualite_id,
                            m.estcalculee)), 1, 15))::bit(60)::bigint) AS h
                   FROM bdoh.mesure m WHERE m.chronique_id IN (SELECT new_id FROM chroniques) GROUP BY 1)
    SELECT count(*) INTO d FROM src FULL JOIN dst USING (chronique_id) WHERE src.n IS DISTINCT FROM dst.n OR src.h IS DISTINCT FROM dst.h;
    IF d > 0 THEN RAISE EXCEPTION 'Empreinte des mesures : % chronique(s) diffèrent', d; END IF;

    WITH src AS (SELECT c.new_id AS chronique_id, count(*) AS n,
                        sum(('x' || substr(md5(format('%s;%s;%s;%s;%s;%s', m.debut, m.fin, encode(float8send(m.valeur), 'hex'),
                            encode(float8send(m.minimum), 'hex'), encode(float8send(m.maximum), 'hex'), q.new_id)), 1, 15))::bit(60)::bigint) AS h
                   FROM othu.plage m JOIN chroniques c ON c.old_id = m.chronique_id
                   LEFT JOIN qualites q ON q.old_id = m.qualite_id GROUP BY 1),
         dst AS (SELECT m.chronique_id, count(*) AS n,
                        sum(('x' || substr(md5(format('%s;%s;%s;%s;%s;%s', m.debut, m.fin, encode(float8send(m.valeur), 'hex'),
                            encode(float8send(m.minimum), 'hex'), encode(float8send(m.maximum), 'hex'), m.qualite_id)), 1, 15))::bit(60)::bigint) AS h
                   FROM bdoh.plage m WHERE m.chronique_id IN (SELECT new_id FROM chroniques) GROUP BY 1)
    SELECT count(*) INTO d FROM src FULL JOIN dst USING (chronique_id) WHERE src.n IS DISTINCT FROM dst.n OR src.h IS DISTINCT FROM dst.h;
    IF d > 0 THEN RAISE EXCEPTION 'Empreinte des plages : % chronique(s) diffèrent', d; END IF;

    WITH src AS (SELECT c.new_id AS chronique_id, count(*) AS n,
                        sum(('x' || substr(md5(format('%s;%s;%s;%s;%s', m.date, encode(float8send(m.valeur), 'hex'),
                            encode(float8send(m.minimum), 'hex'), encode(float8send(m.maximum), 'hex'), q.new_id)), 1, 15))::bit(60)::bigint) AS h
                   FROM othu.pointcontrole m JOIN chroniques c ON c.old_id = m.chronique_id
                   LEFT JOIN qualites q ON q.old_id = m.qualite_id GROUP BY 1),
         dst AS (SELECT m.chronique_id, count(*) AS n,
                        sum(('x' || substr(md5(format('%s;%s;%s;%s;%s', m.date, encode(float8send(m.valeur), 'hex'),
                            encode(float8send(m.minimum), 'hex'), encode(float8send(m.maximum), 'hex'), m.qualite_id)), 1, 15))::bit(60)::bigint) AS h
                   FROM bdoh.pointcontrole m WHERE m.chronique_id IN (SELECT new_id FROM chroniques) GROUP BY 1)
    SELECT count(*) INTO d FROM src FULL JOIN dst USING (chronique_id) WHERE src.n IS DISTINCT FROM dst.n OR src.h IS DISTINCT FROM dst.h;
    IF d > 0 THEN RAISE EXCEPTION 'Empreinte des points de contrôle : % chronique(s) diffèrent', d; END IF;

    WITH src AS (SELECT c.new_id AS chronique_id, count(*) AS n,
                        sum(('x' || substr(md5(format('%s;%s;%s;%s', m.debut, m.fin, encode(float8send(m.taux), 'hex'),
                            encode(float8send(m.poids), 'hex'))), 1, 15))::bit(60)::bigint) AS h
                   FROM othu.tauxremplissage m JOIN chroniques c ON c.old_id = m.chronique_id GROUP BY 1),
         dst AS (SELECT m.chronique_id, count(*) AS n,
                        sum(('x' || substr(md5(format('%s;%s;%s;%s', m.debut, m.fin, encode(float8send(m.taux), 'hex'),
                            encode(float8send(m.poids), 'hex'))), 1, 15))::bit(60)::bigint) AS h
                   FROM bdoh.tauxremplissage m WHERE m.chronique_id IN (SELECT new_id FROM chroniques) GROUP BY 1)
    SELECT count(*) INTO d FROM src FULL JOIN dst USING (chronique_id) WHERE src.n IS DISTINCT FROM dst.n OR src.h IS DISTINCT FROM dst.h;
    IF d > 0 THEN RAISE EXCEPTION 'Empreinte des taux de remplissage : % chronique(s) diffèrent', d; END IF;

    -- Chaque chronique d'OTHU est rattachée à l'observatoire OTHU, par sa station et ses sites
    SELECT count(*) INTO d FROM chroniques c WHERE NOT EXISTS (
        SELECT 1 FROM bdoh.chronique ch JOIN bdoh.stations_sites ss ON ss.station_id = ch.station_id
          JOIN bdoh.siteexperimental s ON s.id = ss.site_id
         WHERE ch.id = c.new_id AND s.observatoire_id = (SELECT new_id FROM fusion_othu.id_map WHERE tab = 'observatoire'));
    IF d > 0 THEN RAISE EXCEPTION '% chronique(s) d''OTHU hors de son observatoire', d; END IF;
END $$;

-- --- Ce qu'a fait la fusion -------------------------------------------------------------------------------------

\echo
\echo 'Lignes d''OTHU, par table : ajoutées, ou rapprochées d''une ligne existante'
SELECT tab AS "table", count(*) FILTER (WHERE added) AS "ajoutées", count(*) FILTER (WHERE NOT added) AS "rapprochées"
  FROM fusion_othu.id_map GROUP BY tab ORDER BY tab;
SELECT 'mesure' AS "table", count(*) AS "ajoutées" FROM othu.mesure
UNION ALL SELECT tab, count(*) FROM fusion_othu.links GROUP BY tab ORDER BY 1;
\echo 'Listes communes : les lignes ajoutées'
SELECT 'unité' AS liste, o.libelle AS "libellé" FROM othu.unite o JOIN fusion_othu.id_map k ON k.tab = 'unite' AND k.old_id = o.id AND k.added
UNION ALL SELECT 'type de paramètre', o.nom FROM othu.typeparametre o JOIN fusion_othu.id_map k ON k.tab = 'typeparametre' AND k.old_id = o.id AND k.added
UNION ALL SELECT 'famille de paramètres', o.nom FROM othu.familleparametres o JOIN fusion_othu.id_map k ON k.tab = 'familleparametres' AND k.old_id = o.id AND k.added
UNION ALL SELECT 'commune', o.nom FROM othu.commune o JOIN fusion_othu.id_map k ON k.tab = 'commune' AND k.old_id = o.id AND k.added
ORDER BY 1, 2;
\echo 'Listes communes : rapprochées d''une ligne d''un autre libellé'
SELECT 'unité' AS liste, o.libelle AS "OTHU", p.libelle AS "BDOH" FROM othu.unite o JOIN fusion_othu.id_map k ON k.tab = 'unite' AND k.old_id = o.id AND NOT k.added JOIN bdoh.unite p ON p.id = k.new_id WHERE p.libelle <> o.libelle
UNION ALL SELECT 'type de paramètre', o.nom, p.nom FROM othu.typeparametre o JOIN fusion_othu.id_map k ON k.tab = 'typeparametre' AND k.old_id = o.id AND NOT k.added JOIN bdoh.typeparametre p ON p.id = k.new_id WHERE p.nom <> o.nom
UNION ALL SELECT 'commune', o.nom, p.nom FROM othu.commune o JOIN fusion_othu.id_map k ON k.tab = 'commune' AND k.old_id = o.id AND NOT k.added JOIN bdoh.commune p ON p.id = k.new_id WHERE p.nom <> o.nom OR p.codeinsee <> o.codeinsee
ORDER BY 1, 2;
\echo 'Comptes'
SELECT count(*) FILTER (WHERE k.added) AS "ajoutés", count(*) FILTER (WHERE NOT k.added) AS "rapprochés d'un compte existant",
       count(*) FILTER (WHERE u.roleadmin IS NOT NULL) AS "administrateurs d'OTHU, devenus gestionnaires de l'observatoire"
  FROM othu.utilisateur u JOIN fusion_othu.id_map k ON k.tab = 'utilisateur' AND k.old_id = u.id;
\echo 'Tâches d''OTHU, non reprises'
SELECT state AS "état", count(*) FROM othu.jms_jobs GROUP BY 1 ORDER BY 1;
SELECT count(*) AS "mot de passe Theia d'OTHU, à ressaisir dans sa fiche" FROM othu.observatoire
 WHERE theiapassword IS NOT NULL AND theiapassword <> '';

-- Dans la transaction : une fusion validée ne laisse pas OTHU à part dans la base
SET client_min_messages = warning;
DROP SCHEMA othu CASCADE;
COMMIT;
ANALYZE;
