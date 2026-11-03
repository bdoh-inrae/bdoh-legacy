-- Empreinte au bit près d'une base BDOH, table par table et chronique par chronique : le nombre de
-- lignes et la somme d'une empreinte de chaque ligne. Ne sort aucune valeur : il peut tourner sur une
-- base non anonymisée. Sert à comparer deux bases (avant et après une restauration, une bascule), là où
-- les comptes d'inspect.sql ne voient pas une valeur changée. Quelques minutes pour toute la production.
--
--   make fingerprint BASE=bdoh SERVER=new > empreinte.txt
--   dev/compare/compare.py fingerprints empreinte-a.txt empreinte-b.txt
--
-- Les nombres passent en binaire (float8send) : le texte d'un float8 change entre PostgreSQL 11 et 12.
-- format garde sa place à une valeur NULL, là où concat_ws la saute. La somme ne dépend pas de l'ordre
-- des lignes, et ne demande pas de les trier. compare.py lit ce fichier pour l'état d'une base.

SET datestyle = 'ISO';

SELECT 'mesure', chronique_id, count(*), sum(('x' || substr(md5(format('%s;%s;%s;%s;%s;%s', date,
           encode(float8send(valeur), 'hex'), encode(float8send(minimum), 'hex'),
           encode(float8send(maximum), 'hex'), qualite_id, estcalculee)), 1, 15))::bit(60)::bigint)
  FROM bdoh.mesure GROUP BY chronique_id ORDER BY 2;

SELECT 'plage', chronique_id, count(*), sum(('x' || substr(md5(format('%s;%s;%s;%s;%s;%s', debut, fin,
           encode(float8send(valeur), 'hex'), encode(float8send(minimum), 'hex'),
           encode(float8send(maximum), 'hex'), qualite_id)), 1, 15))::bit(60)::bigint)
  FROM bdoh.plage GROUP BY chronique_id ORDER BY 2;

SELECT 'pointcontrole', chronique_id, count(*), sum(('x' || substr(md5(format('%s;%s;%s;%s;%s', date,
           encode(float8send(valeur), 'hex'), encode(float8send(minimum), 'hex'),
           encode(float8send(maximum), 'hex'), qualite_id)), 1, 15))::bit(60)::bigint)
  FROM bdoh.pointcontrole GROUP BY chronique_id ORDER BY 2;

SELECT 'tauxremplissage', chronique_id, count(*), sum(('x' || substr(md5(format('%s;%s;%s;%s', debut, fin,
           encode(float8send(taux), 'hex'), encode(float8send(poids), 'hex'))), 1, 15))::bit(60)::bigint)
  FROM bdoh.tauxremplissage GROUP BY chronique_id ORDER BY 2;
