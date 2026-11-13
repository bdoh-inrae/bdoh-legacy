-- Empreinte de toutes les tables d'une base BDOH, pour l'aller et retour de la fusion d'OTHU
-- (dev/plans/2026-11-13_othu-merge.md) : par table, le nombre de lignes et la somme d'une empreinte de chaque
-- ligne, puis les compteurs d'identifiants. Sur une base fusionnée (schéma fusion_othu), les lignes ajoutées par
-- la fusion sont laissées de côté : ce qui reste doit être la base d'avant, compteurs à part. Ne sort aucune
-- valeur. Une dizaine de minutes sur la base complète.
SET client_min_messages = warning;

CREATE FUNCTION pg_temp.checksum() RETURNS TABLE (tab text, n bigint, h numeric) LANGUAGE plpgsql AS $$
DECLARE t record; merged boolean := to_regnamespace('fusion_othu') IS NOT NULL; cond text;
BEGIN
    FOR t IN SELECT c.relname, EXISTS (SELECT 1 FROM pg_attribute a WHERE a.attrelid = c.oid AND a.attname = 'id'
                                                                    AND NOT a.attisdropped) AS has_id
               FROM pg_class c WHERE c.relnamespace = 'bdoh'::regnamespace AND c.relkind = 'r' ORDER BY 1 LOOP
        cond := 'true';
        IF merged AND t.has_id THEN
            -- station_point_rebuilt porte des identifiants de stations
            cond := format('NOT EXISTS (SELECT 1 FROM fusion_othu.ranges r WHERE r.tab = %L
                                           AND x.id BETWEEN r.first_id AND r.last_id)',
                           CASE t.relname WHEN 'station_point_rebuilt' THEN 'station' ELSE t.relname END);
        ELSIF merged THEN
            cond := format('NOT EXISTS (SELECT 1 FROM fusion_othu.links l WHERE l.tab = %L AND l.line = to_jsonb(x))',
                           t.relname);
        END IF;
        RETURN QUERY EXECUTE format('SELECT %L::text, count(*),
                                            coalesce(sum((''x'' || substr(md5(x::text), 1, 15))::bit(60)::bigint), 0)::numeric
                                       FROM bdoh.%I x WHERE %s', t.relname, t.relname, cond);
    END LOOP;
END $$;

SELECT * FROM pg_temp.checksum();
SELECT 'compteur ' || sequencename, last_value FROM pg_sequences WHERE schemaname = 'bdoh' ORDER BY 1;
