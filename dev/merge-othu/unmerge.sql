-- Retour arrière de la fusion d'OTHU (merge.sql) : retire ce que la fusion a ajouté, d'après le schéma
-- fusion_othu, et remet les compteurs d'identifiants. Seulement tant que personne n'a écrit sur OTHU depuis la
-- fusion (un export n'y écrit rien), et que rien dans la production ne cite ce qu'elle a ajouté : sinon il s'arrête
-- en disant quoi, sans rien changer, et le retour se fait par la sauvegarde d'avant la fusion. Une seule transaction.
\set ON_ERROR_STOP on
SET client_min_messages = notice;
SET search_path = public;
BEGIN;

DO $$
DECLARE obs bigint;
BEGIN
    IF to_regnamespace('fusion_othu') IS NULL THEN
        RAISE EXCEPTION 'Pas de schéma fusion_othu : pas de fusion à défaire';
    END IF;
    SELECT new_id INTO obs FROM fusion_othu.id_map WHERE tab = 'observatoire';
    -- Un import ou un changement dans l'administration d'OTHU laisse une entrée d'historique ; un export aussi, mais
    -- il n'écrit rien dans ses données : son entrée part avec OTHU
    IF EXISTS (SELECT 1 FROM bdoh.historique h WHERE h.observatoire_id = obs AND h.dtype <> 'donneesExport' AND NOT EXISTS (
                   SELECT 1 FROM fusion_othu.ranges r WHERE r.tab = 'historique' AND h.id BETWEEN r.first_id AND r.last_id)) THEN
        RAISE EXCEPTION 'Des écritures ont eu lieu sur OTHU depuis la fusion : revenir par la sauvegarde d''avant la fusion';
    END IF;
    DELETE FROM bdoh.historique h WHERE h.observatoire_id = obs AND h.dtype = 'donneesExport' AND NOT EXISTS (
        SELECT 1 FROM fusion_othu.ranges r WHERE r.tab = 'historique' AND h.id BETWEEN r.first_id AND r.last_id);
END $$;

-- Rien de ce que la fusion n'a pas ajouté ne doit citer ce qu'elle a ajouté : une telle ligne partirait en cascade, ou
-- perdrait sa référence (un rôle donné depuis à un ancien compte d'OTHU, une demande qui cite un paramètre ajouté, un
-- favori). Toutes les clés étrangères vers une table où la fusion a ajouté des lignes sont parcourues.
DO $$
DECLARE r record; n bigint; added text; found text[] := '{}';
BEGIN
    FOR r IN SELECT c.conrelid::regclass AS child, k.relname AS child_name, a.attname AS col, p.relname AS parent
               FROM pg_constraint c
               JOIN pg_class k ON k.oid = c.conrelid
               JOIN pg_attribute a ON a.attrelid = c.conrelid AND a.attnum = c.conkey[1]
               JOIN pg_class p ON p.oid = c.confrelid
              WHERE c.contype = 'f' AND c.connamespace = 'bdoh'::regnamespace
                AND p.relname IN (SELECT tab FROM fusion_othu.ranges) LOOP
        -- Une ligne ajoutée par la fusion, elle, peut citer une autre ligne ajoutée
        IF EXISTS (SELECT 1 FROM fusion_othu.ranges WHERE tab = r.child_name) THEN
            added := format('EXISTS (SELECT 1 FROM fusion_othu.ranges a WHERE a.tab = %L AND x.id BETWEEN a.first_id AND a.last_id)', r.child_name);
        ELSE
            added := format('to_jsonb(x) IN (SELECT line FROM fusion_othu.links WHERE tab = %L)', r.child_name);
        END IF;
        EXECUTE format('SELECT count(*) FROM %s x WHERE NOT (%s) AND EXISTS (SELECT 1 FROM fusion_othu.ranges g
                         WHERE g.tab = %L AND x.%I BETWEEN g.first_id AND g.last_id)', r.child, added, r.parent, r.col) INTO n;
        IF n > 0 THEN
            found := found || format('%s.%s (%s)', r.child_name, r.col, n);
        END IF;
    END LOOP;
    IF cardinality(found) > 0 THEN
        RAISE EXCEPTION 'Des lignes écrites depuis la fusion citent ce qu''elle a ajouté : % ; revenir par la sauvegarde d''avant la fusion',
            array_to_string(found, ', ');
    END IF;
END $$;

DO $$
DECLARE t text; n bigint; total bigint;
BEGIN
    -- Les lignes ajoutées aux tables de liaison
    FOR t IN SELECT DISTINCT tab FROM fusion_othu.links ORDER BY 1 LOOP
        EXECUTE format('DELETE FROM bdoh.%1$I x WHERE to_jsonb(x) IN (SELECT line FROM fusion_othu.links WHERE tab = %1$L)', t);
        GET DIAGNOSTICS n = ROW_COUNT;
        RAISE NOTICE '% : % ligne(s) retirée(s)', t, n;
    END LOOP;
    DELETE FROM bdoh.station_point_rebuilt x USING fusion_othu.ranges r
     WHERE r.tab = 'station' AND x.id BETWEEN r.first_id AND r.last_id;
    UPDATE bdoh.observatoire x SET projectleader_id = NULL, doiprincipal_id = NULL FROM fusion_othu.ranges r
     WHERE r.tab = 'observatoire' AND x.id BETWEEN r.first_id AND r.last_id;
    -- Les lignes ajoutées, chaque table avant celles qu'elle cite. Une famille de paramètres peut citer une
    -- autre famille ajoutée : celles qu'aucune autre ne cite d'abord, jusqu'à la dernière.
    FOREACH t IN ARRAY ARRAY['historique', 'role', 'favorite', 'besoin', 'tauxremplissage', 'pointcontrole', 'plage',
                             'mesure', 'chronique', 'bareme', 'dataset', 'courseau', 'bassin', 'station',
                             'siteexperimental', 'doi', 'personnetheia', 'partenaire', 'observatoire', 'utilisateur',
                             'commune', 'unite', 'typeparametre', 'familleparametres', 'qualitejeuqualite'] LOOP
        total := 0;
        LOOP
            EXECUTE format('DELETE FROM bdoh.%1$I x USING fusion_othu.ranges r
                             WHERE r.tab = %1$L AND x.id BETWEEN r.first_id AND r.last_id
                               AND (%1$L <> ''familleparametres'' OR NOT EXISTS (
                                    SELECT 1 FROM bdoh.familleparametres c WHERE c.familleparente_id = x.id))', t);
            GET DIAGNOSTICS n = ROW_COUNT;
            total := total + n;
            EXIT WHEN n = 0;
        END LOOP;
        IF total > 0 THEN RAISE NOTICE '% : % ligne(s) retirée(s)', t, total; END IF;
    END LOOP;
    -- Rien d'ajouté ne doit rester
    FOR t IN SELECT DISTINCT tab FROM fusion_othu.ranges LOOP
        EXECUTE format('SELECT count(*) FROM bdoh.%1$I x JOIN fusion_othu.ranges r
                         ON r.tab = %1$L AND x.id BETWEEN r.first_id AND r.last_id', t) INTO n;
        IF n > 0 THEN RAISE EXCEPTION '% : % ligne(s) ajoutée(s) par la fusion restent', t, n; END IF;
    END LOOP;
END $$;

-- Les compteurs, à leur valeur d'avant la fusion, ou au plus grand identifiant si d'autres lignes ont été écrites
-- depuis dans la même table
DO $$
DECLARE s record; m bigint;
BEGIN
    FOR s IN SELECT * FROM fusion_othu.sequences WHERE regexp_replace(seq, '_id_seq$', '') IN (SELECT tab FROM fusion_othu.ranges) LOOP
        EXECUTE format('SELECT max(id) FROM bdoh.%I', regexp_replace(s.seq, '_id_seq$', '')) INTO m;
        IF m IS NOT NULL AND m > s.last_value THEN
            PERFORM setval(format('bdoh.%I', s.seq), m, true);
        ELSE
            PERFORM setval(format('bdoh.%I', s.seq), s.last_value, s.is_called);
        END IF;
    END LOOP;
END $$;

SET client_min_messages = warning;
DROP SCHEMA fusion_othu CASCADE;
COMMIT;
ANALYZE;
