-- Donne au rôle bdoh, sans superuser, ce que BDOH écrit dans sa base : ses schémas, leurs tables, séquences
-- et fonctions. La restauration a tout laissé à postgres ; PostGIS, dans le schéma public, lui reste.
-- Sert au serveur du nouveau BDOH, où l'on éprouve ce rôle (dev/audits/2026-11-03_validation-plan.md, constat 5).
ALTER SCHEMA bdoh OWNER TO bdoh;
ALTER SCHEMA temp OWNER TO bdoh;

DO $$
DECLARE
    r record;
BEGIN
    -- Une séquence qui appartient à une colonne suit sa table
    FOR r IN SELECT format('%I.%I', n.nspname, c.relname) AS name, c.relkind
               FROM pg_class c JOIN pg_namespace n ON n.oid = c.relnamespace
              WHERE n.nspname IN ('bdoh', 'temp') AND c.relkind IN ('r', 'p', 'v', 'm', 'S')
                AND NOT EXISTS (SELECT 1 FROM pg_depend d WHERE d.classid = 'pg_class'::regclass AND d.objid = c.oid
                                   AND d.deptype IN ('a', 'i') AND c.relkind = 'S') LOOP
        EXECUTE format('ALTER %s %s OWNER TO bdoh',
                       CASE r.relkind WHEN 'S' THEN 'SEQUENCE' WHEN 'v' THEN 'VIEW' WHEN 'm' THEN 'MATERIALIZED VIEW' ELSE 'TABLE' END, r.name);
    END LOOP;
    FOR r IN SELECT p.oid::regprocedure AS name FROM pg_proc p JOIN pg_namespace n ON n.oid = p.pronamespace
              WHERE n.nspname IN ('bdoh', 'temp') LOOP
        EXECUTE format('ALTER ROUTINE %s OWNER TO bdoh', r.name);
    END LOOP;
END $$;
