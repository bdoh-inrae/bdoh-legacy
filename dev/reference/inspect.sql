-- Contrôles d'intégrité d'une base BDOH. Ne sort que des comptes, aucune valeur : il peut tourner
-- sur une base non anonymisée. Sert à inspecter la base de référence, et à comparer deux bases
-- (avant et après un changement de version de PostgreSQL, ou la bascule) : les comptes doivent
-- rester égaux. Lecture seule, hors deux tables et une fonction temporaires.
--
--   make inspect [BASE=bdoh_othu]
--
-- Les longues requêtes parcourent une fois chacune les tables de mesures et de plages.

SET client_min_messages = warning;

-- Observatoire de chaque chronique, par sa station et le site de la station.
CREATE TEMP TABLE chronique_observatoire AS
SELECT DISTINCT ON (c.id) c.id AS chronique_id, s.observatoire_id
  FROM bdoh.chronique c
  JOIN bdoh.stations_sites ss ON ss.station_id = c.station_id
  JOIN bdoh.siteexperimental s ON s.id = ss.site_id
 ORDER BY c.id, s.observatoire_id;

-- Une passe sur les mesures, agrégée par chronique.
CREATE TEMP TABLE mesures_par_chronique AS
SELECT m.chronique_id,
       count(*) AS n,
       min(m.date) AS premiere,
       max(m.date) AS derniere,
       count(*) FILTER (WHERE m.qualite_id IS NULL) AS sans_qualite,
       count(*) FILTER (WHERE q.ordre = 100 AND m.valeur IS NOT NULL) AS lacune_avec_valeur,
       count(*) FILTER (WHERE q.ordre NOT IN (100, 200) AND m.valeur IS NULL) AS valide_sans_valeur,
       count(*) FILTER (WHERE m.minimum > m.maximum) AS min_sup_max,
       count(*) FILTER (WHERE m.valeur < m.minimum OR m.valeur > m.maximum) AS hors_min_max,
       count(*) FILTER (WHERE m.date > now()) AS futur,
       count(*) FILTER (WHERE m.date < '1900-01-01') AS avant_1900,
       count(*) FILTER (WHERE m.date <> date_trunc('second', m.date)) AS millisecondes,
       count(*) FILTER (WHERE m.estcalculee) AS calculees,
       count(*) FILTER (WHERE q.jeu_id IS DISTINCT FROM o.jeu_id) AS qualite_hors_jeu,
       count(*) FILTER (WHERE q.ordre NOT IN (100, 200)
                          AND (m.valeur < c.minimumvalide OR m.valeur > c.maximumvalide)) AS valide_hors_seuils
  FROM bdoh.mesure m
  JOIN bdoh.chronique c ON c.id = m.chronique_id
  LEFT JOIN bdoh.qualite q ON q.id = m.qualite_id
  LEFT JOIN chronique_observatoire co ON co.chronique_id = m.chronique_id
  LEFT JOIN bdoh.observatoire o ON o.id = co.observatoire_id
 GROUP BY m.chronique_id;

CREATE FUNCTION pg_temp.sequences_en_retard() RETURNS bigint
LANGUAGE plpgsql AS $$
DECLARE
    r record;
    late bigint := 0;
    max_id bigint;
    next_id bigint;
BEGIN
    FOR r IN SELECT c.relname AS seq, t.relname AS tbl
               FROM pg_class c
               JOIN pg_namespace n ON n.oid = c.relnamespace AND n.nspname = 'bdoh'
               JOIN pg_class t ON t.relnamespace = n.oid AND t.relkind = 'r'
                              AND c.relname = t.relname || '_id_seq'
              WHERE c.relkind = 'S' LOOP
        EXECUTE format('SELECT max(id) FROM bdoh.%I', r.tbl) INTO max_id;
        -- La prochaine valeur : last_value si le compteur n'a jamais servi, sinon la suivante
        EXECUTE format('SELECT CASE WHEN is_called THEN last_value + 1 ELSE last_value END FROM bdoh.%I', r.seq)
            INTO next_id;
        IF max_id >= next_id THEN
            late := late + 1;
        END IF;
    END LOOP;
    RETURN late;
END $$;

SELECT 'mesures', sum(n) FROM mesures_par_chronique
UNION ALL SELECT 'mesures sans qualité', sum(sans_qualite) FROM mesures_par_chronique
UNION ALL SELECT 'mesures en lacune avec une valeur', sum(lacune_avec_valeur) FROM mesures_par_chronique
UNION ALL SELECT 'mesures valides sans valeur', sum(valide_sans_valeur) FROM mesures_par_chronique
UNION ALL SELECT 'mesures dont le minimum dépasse le maximum', sum(min_sup_max) FROM mesures_par_chronique
UNION ALL SELECT 'mesures hors de leur minimum et maximum', sum(hors_min_max) FROM mesures_par_chronique
UNION ALL SELECT 'mesures datées dans le futur', sum(futur) FROM mesures_par_chronique
UNION ALL SELECT 'mesures datées avant 1900', sum(avant_1900) FROM mesures_par_chronique
UNION ALL SELECT 'mesures avec millisecondes', sum(millisecondes) FROM mesures_par_chronique
UNION ALL SELECT 'mesures marquées calculées', sum(calculees) FROM mesures_par_chronique
UNION ALL SELECT 'mesures dont la qualité n''est pas du jeu de l''observatoire', sum(qualite_hors_jeu) FROM mesures_par_chronique
UNION ALL SELECT 'mesures valides hors des seuils de validité', sum(valide_hors_seuils) FROM mesures_par_chronique
UNION ALL SELECT 'chroniques ' || dtype, count(*) FROM bdoh.chronique GROUP BY dtype
UNION ALL SELECT 'chroniques non visibles', count(*) FROM bdoh.chronique WHERE NOT estvisible
UNION ALL SELECT 'chroniques sans station', count(*) FROM bdoh.chronique WHERE station_id IS NULL
UNION ALL SELECT 'chroniques sans observatoire', count(*) FROM bdoh.chronique c
           WHERE NOT EXISTS (SELECT 1 FROM chronique_observatoire co WHERE co.chronique_id = c.id)
UNION ALL SELECT 'chroniques à mesures sans mesure', count(*) FROM bdoh.chronique c
           WHERE c.dtype IN ('continue', 'calculee', 'convertie')
             AND NOT EXISTS (SELECT 1 FROM mesures_par_chronique a WHERE a.chronique_id = c.id)
UNION ALL SELECT 'mesures rattachées à une chronique sans mesures', coalesce(sum(a.n), 0)
            FROM mesures_par_chronique a JOIN bdoh.chronique c ON c.id = a.chronique_id
           WHERE c.dtype NOT IN ('continue', 'calculee', 'convertie')
UNION ALL SELECT 'chroniques dont le nombre de mesures noté diffère', count(*)
            FROM bdoh.chronique c JOIN mesures_par_chronique a ON a.chronique_id = c.id
           WHERE c.nbmesures IS DISTINCT FROM a.n
UNION ALL SELECT 'chroniques dont les dates de mesures notées diffèrent', count(*)
            FROM bdoh.chronique c JOIN mesures_par_chronique a ON a.chronique_id = c.id
           WHERE c.datedebutmesures IS DISTINCT FROM a.premiere OR c.datefinmesures IS DISTINCT FROM a.derniere
UNION ALL SELECT 'chroniques à seuil de validité de plus de 3 décimales', count(*) FROM bdoh.chronique
           WHERE minimumvalide::numeric <> round(minimumvalide::numeric, 3)
              OR maximumvalide::numeric <> round(maximumvalide::numeric, 3)
UNION ALL SELECT 'chroniques calculées sans première entrée', count(*) FROM bdoh.chronique
           WHERE dtype = 'calculee' AND premiereentree_id IS NULL
UNION ALL SELECT 'transformations dont l''entrée n''existe pas', count(*) FROM bdoh.transformation t
           WHERE NOT EXISTS (SELECT 1 FROM bdoh.chronique c WHERE c.id = t.entree_id)
UNION ALL SELECT 'transformations sans jeu de barèmes', count(*) FROM bdoh.transformation WHERE jeubaremeactuel_id IS NULL
UNION ALL SELECT 'chroniques converties sans conversion', count(*) FROM bdoh.chronique
           WHERE dtype = 'convertie' AND conversion_id IS NULL
UNION ALL SELECT 'plages', count(*) FROM bdoh.plage
UNION ALL SELECT 'plages dont la fin précède le début', count(*) FROM bdoh.plage WHERE fin < debut
UNION ALL SELECT 'plages qui chevauchent la précédente', count(*) FROM (
            SELECT debut < lag(fin) OVER (PARTITION BY chronique_id ORDER BY debut, fin) AS chevauche
              FROM bdoh.plage) p WHERE chevauche
UNION ALL SELECT 'plages rattachées à une chronique non discontinue', count(*) FROM bdoh.plage p
            JOIN bdoh.chronique c ON c.id = p.chronique_id WHERE c.dtype <> 'discontinue'
UNION ALL SELECT 'points de contrôle', count(*) FROM bdoh.pointcontrole
UNION ALL SELECT 'points de contrôle en double', count(*) - count(DISTINCT (chronique_id, date)) FROM bdoh.pointcontrole
UNION ALL SELECT 'taux de remplissage', count(*) FROM bdoh.tauxremplissage
UNION ALL SELECT 'taux de remplissage hors de 0 à 1', count(*) FROM bdoh.tauxremplissage WHERE taux < 0 OR taux > 1
UNION ALL SELECT 'taux de remplissage de chroniques sans mesure', count(*) FROM bdoh.tauxremplissage t
           WHERE NOT EXISTS (SELECT 1 FROM mesures_par_chronique a WHERE a.chronique_id = t.chronique_id)
UNION ALL SELECT 'séquences en retard sur leur table', pg_temp.sequences_en_retard()
UNION ALL SELECT 'colonnes raster', count(*) FROM information_schema.columns WHERE udt_name = 'raster'
UNION ALL SELECT 'tables laissées par des imports (schéma temp)', count(*) FROM information_schema.tables WHERE table_schema = 'temp'
UNION ALL SELECT 'noms à l''encodage suspect (Ã, �)', (
            SELECT count(*) FROM bdoh.station WHERE nom ~ '[Ã�]')
          + (SELECT count(*) FROM bdoh.chronique WHERE libelle ~ '[Ã�]' OR libelleen ~ '[Ã�]')
          + (SELECT count(*) FROM bdoh.observatoire WHERE nom ~ '[Ã�]' OR titre ~ '[Ã�]')
          + (SELECT count(*) FROM bdoh.siteexperimental WHERE nom ~ '[Ã�]')
          + (SELECT count(*) FROM bdoh.partenaire WHERE nom ~ '[Ã�]')
UNION ALL SELECT 'comptes', count(*) FROM bdoh.utilisateur
UNION ALL SELECT 'comptes ' || coalesce(roleadmin, 'sans rôle d''administration'), count(*) FROM bdoh.utilisateur GROUP BY roleadmin
UNION ALL SELECT 'comptes dont l''accès a pris fin', count(*) FROM bdoh.utilisateur WHERE finacces < now()
UNION ALL SELECT 'droits ' || dtype || coalesce(' ' || valeur, ''), count(*) FROM bdoh.role GROUP BY dtype, valeur;
