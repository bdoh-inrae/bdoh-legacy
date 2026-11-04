"""Un petit jeu de calcul inventé, aux résultats écrits à la main (compare.py state).

Les vraies données ne passent pas par tous les chemins des fonctions de calcul : aucun jeu de barèmes d'une
chronique visible ne prend la moitié d'une limite, la base réduite n'a aucune mesure pile sur un début de
validité. Ce jeu y passe, et ses résultats ne doivent rien à l'ancien BDOH : chacun se refait à la main en
lisant les fonctions telles qu'elles sont en base (pg_get_functiondef), et le dit en commentaire quand ce n'est
pas immédiat. Pas dans ../bdoh/app/sql/ : les migrations de l'ancien BDOH les ont changées depuis (dev/decisions.md).

Une chronique calculée à deux transformations prête sa structure, sur une copie jetable : elle et ses mères
reçoivent des mesures inventées, ses jeux de barèmes des barèmes inventés. Rien de la base ne s'y lit.
"""

import subprocess

CURVE_NAME = 'courbe inventée'
CURVE = '{{0,0,800},{10,20,800},{20,60,300},{30,100,800},{40,140,700}}'  # entrée, sortie, qualité
PLACEHOLDER = 3

# Un calcul à une transformation : la validité de ses barèmes, et ses mesures
ONE_VALIDITY = [('identite', '2020-01-01', '2020-01-02'), (CURVE_NAME, '2020-01-02', '2020-01-03'),
                ('lacune', '2020-01-03', '2020-01-04'), ('manuel', '2020-01-04', '2020-01-05'),
                ('identite', '2020-01-06', None)]  # du 5 au 6 : aucun barème
ONE_MOTHER = [
    ('2020-01-01 00:00:00', 1.5, 800),  # identité, pile au début de validité
    ('2020-01-01 06:00:00', 4, 600),  # limites
    ('2020-01-01 12:00:00', 6, 700),
    ('2020-01-01 18:00:00', -9999, 100),
    ('2020-01-02 00:00:00', -1, 800),  # courbe, pile à la fin de la validité précédente, sous le minimum
    ('2020-01-02 01:00:00', 0, 800),  # le minimum
    ('2020-01-02 02:00:00', 5, 800),
    ('2020-01-02 03:00:00', 10, 400),  # sur un point de la courbe
    ('2020-01-02 04:00:00', 15, 800),
    ('2020-01-02 05:00:00', 35, 800),
    ('2020-01-02 06:00:00', 40, 800),  # le maximum
    ('2020-01-02 07:00:00', 41, 800),  # au-dessus
    ('2020-01-02 08:00:00', -9999, 100),
    ('2020-01-02 09:00:00', -9999, 200),
    ('2020-01-02 10:00:00', 5, 600),  # limites
    ('2020-01-02 11:00:00', 8, 700),
    ('2020-01-02 12:00:00', 10 / 3, 800),  # un résultat qui demande tous ses chiffres
    ('2020-01-03 00:00:00', 2, 800),  # lacune, pile au début
    ('2020-01-03 12:00:00', 3, 800),
    ('2020-01-04 12:00:00', 3, 800),  # barème manuel : rien
    ('2020-01-05 12:00:00', 3, 800),  # aucun barème : rien
    ('2020-01-06 00:00:00', 9, 800),  # identité, sans fin de validité
    ('2030-01-01 00:00:00', 9.5, 800),
]
# Ce que la chronique a avant le calcul : (date, valeur, qualité, calculée). La purge enlève ce qui est calculé,
# et ce qui tombe sous un barème qui n'est pas manuel.
ONE_DAUGHTER = [
    ('2020-01-01 03:00:00', 44, 800, False),  # identité : enlevée
    ('2020-01-04 13:00:00', 33, 800, False),  # manuel : gardée
    ('2020-01-04 14:00:00', 55, 800, True),  # calculée : enlevée
    ('2020-01-05 13:00:00', 66, 800, False),  # aucun barème : gardée
]
ONE_RESULT = {
    '2020-01-01 00:00:00': (1.5, 800),
    '2020-01-01 18:00:00': (-9999, 100),
    '2020-01-02 00:00:00': (-9999, 100),
    '2020-01-02 01:00:00': (0, 800),
    '2020-01-02 02:00:00': (10, 800),  # (5 * 20) / 10
    '2020-01-02 03:00:00': (20, 400),  # la qualité de la mesure, plus basse que celle de la courbe
    '2020-01-02 04:00:00': (40, 300),  # (5 * 20 + 5 * 60) / 10, la qualité la plus basse de l'intervalle
    '2020-01-02 05:00:00': (120, 500),  # (5 * 100 + 5 * 140) / 10 ; 800 croisé avec une limite donne 500
    '2020-01-02 06:00:00': (140, 500),  # la borne haute est incluse
    '2020-01-02 07:00:00': (-9999, 100),
    '2020-01-02 08:00:00': (-9999, 100),
    '2020-01-02 09:00:00': (-9999, 200),
    '2020-01-02 12:00:00': (10 / 3 * 20 / 10, 800),
    '2020-01-03 00:00:00': (-9999, 100),
    '2020-01-03 12:00:00': (-9999, 100),
    '2020-01-06 00:00:00': (9, 800),
    '2030-01-01 00:00:00': (9.5, 800),
}
# Les limites, selon la politique du jeu de barèmes : 4 en 600 et 6 en 700 sous l'identité, 5 en 600 et 8 en 700
# sous la courbe, où l'entrée adaptée se lit (entrée * 20) / 10. La qualité est 500 : écrite telle quelle sous
# l'identité, et 800 croisé avec une limite sous la courbe.
LIMITS = ('2020-01-01 06:00:00', '2020-01-01 12:00:00', '2020-01-02 10:00:00', '2020-01-02 11:00:00')
AS_TRUE = dict(zip(LIMITS, [(4, 500), (6, 500), (10, 500), (16, 500)]))
ONE_POLICIES = {
    'sans politique': (None, AS_TRUE),
    'vraies valeurs': ('true_value', AS_TRUE),
    'moitié de valeur': ('half_value', dict(zip(LIMITS, [(2, 500), (3, 500), (5, 500), (8, 500)]))),
    # Une limite devient une lacune, et une ligne de la courbe en limite donne le code 200 : une valeur -9999
    'lacunes': ('gap', {**dict(zip(LIMITS, [(-9999, 200)] * 4)), '2020-01-02 05:00:00': (-9999, 200),
                        '2020-01-02 06:00:00': (-9999, 200)}),
    'valeur de remplacement': ('placeholder', dict(zip(LIMITS, [(3, 500), (3, 500), (6, 500), (6, 500)]))),
}

# Un calcul à deux transformations : (mère principale, seconde mère, ce que la chronique a avant le calcul, délais,
# facteur, résultat). Il s'arrête quand l'une des mères est finie, et commence quand les deux ont commencé.
TWO_VALIDITY = [('identite', '2021-01-01', None)]
TWOS = {
    'deux transformations, délais et facteur': (
        [('2021-01-01 00:00:00', 10, 800), ('2021-01-01 01:00:00', 20, 400), ('2021-01-01 02:00:00', 30, 800),
         ('2021-01-01 03:00:00', -9999, 100), ('2021-01-01 04:00:00', 50, 800)],
        [('2021-01-01 00:10:00', 0.5, 800), ('2021-01-01 00:20:00', 1, 800), ('2021-01-01 02:20:00', 3, 800),
         ('2021-01-01 02:50:00', 5, 600), ('2021-01-01 05:00:00', 7, 800)],
        [('2020-06-01 00:00:00', 77, 800, False),  # hors de toute validité : gardée
         ('2021-01-01 00:15:00', 88, 800, False)],  # sous l'identité : enlevée
        30, 10, 2,
        [('2020-06-01 00:00:00', 77, 800, False),
         # À 00:20, la seconde seule, décalée : rien, la principale n'a pas commencé
         ('2021-01-01 00:30:00', 10 * 1 * 2, 800, True),  # les deux à la même date
         ('2021-01-01 01:30:00', 20 * 2 * 2, 400, True),  # la seconde interpolée entre 1 et 3 ; qualité la plus basse
         ('2021-01-01 02:30:00', 30 * 3 * 2, 800, True),
         ('2021-01-01 03:00:00', -9999, 100, True),  # la principale interpolée vers une lacune
         ('2021-01-01 03:30:00', -9999, 100, True),  # une lacune de la principale
         # La seconde interpolée entre 5 en limite (03:00) et 7 en 800 (05:10), à 5400 s sur 7800, ce qui donne
         # la qualité 500. À 05:10 : rien, la principale est finie.
         ('2021-01-01 04:30:00', 50 * ((2400 * 5 + 5400 * 7) / 7800) * 2, 500, True)]),
    'deux transformations, la principale d\'abord': (
        [('2021-01-01 00:00:00', 1, 800), ('2021-01-01 01:00:00', 2, 800), ('2021-01-01 02:00:00', 7, 200),
         ('2021-01-01 03:00:00', 3, 300), ('2021-01-01 04:00:00', 4, 800)],
        [('2021-01-01 00:30:00', 10, 800), ('2021-01-01 01:30:00', 20, 800), ('2021-01-01 02:30:00', 30, 800),
         ('2021-01-01 03:30:00', 40, 800), ('2021-01-01 04:30:00', 50, 800)],
        [],
        0, 0, 3,
        # À 00:00, la principale seule : rien, la seconde n'a pas commencé
        [('2021-01-01 00:30:00', 1.5 * 10 * 3, 800, True),
         ('2021-01-01 01:00:00', 2 * 15 * 3, 800, True),
         ('2021-01-01 01:30:00', -9999, 200, True),  # interpolée vers une qualité 200, sa valeur est perdue
         ('2021-01-01 02:00:00', -9999, 200, True),  # le seuil : 200 ne donne pas de valeur
         ('2021-01-01 02:30:00', -9999, 200, True),  # interpolée depuis une qualité 200
         ('2021-01-01 03:00:00', 3 * 35 * 3, 300, True),  # 300 en donne une
         ('2021-01-01 03:30:00', 3.5 * 40 * 3, 300, True),
         ('2021-01-01 04:00:00', 4 * 45 * 3, 800, True)]),
}


def line(date, value, order, computed):
    return f"{date}\t{float(value)!r}\t{order}\t{'t' if computed else 'f'}"


def one_rows(limits):
    rows = {**{d: (*r, True) for d, r in {**ONE_RESULT, **limits}.items()},
            **{d: (v, o, c) for d, v, o, c in ONE_DAUGHTER if not c and d[:10] in ('2020-01-04', '2020-01-05')}}
    return [line(d, *rows[d]) for d in sorted(rows)]


STEPS = [*((f'une transformation, limites : {name}', 'one', policy, one_rows(limits))
           for name, (policy, limits) in ONE_POLICIES.items()),
         *((name, 'two', name, [line(*r) for r in two[-1]]) for name, two in TWOS.items())]


def choose(query):
    """La chronique qui prête sa structure : (fille, seconde transformation, mère principale, seconde mère,
    jeu de barèmes principal, second jeu). Elle est visible, comme ses mères, et ses qualités sont celles du jeu VALIDE."""
    rows = query("""
        SELECT c.id, t2.id, t1.entree_id, t2.entree_id, t1.jeubaremeactuel_id, t2.jeubaremeactuel_id
          FROM chronique c
          JOIN transformation t1 ON t1.id = c.premiereentree_id
          JOIN transformation t2 ON t2.id = c.secondeentree_id
          JOIN chronique m1 ON m1.id = t1.entree_id
          JOIN chronique m2 ON m2.id = t2.entree_id
         WHERE c.dtype = 'calculee' AND m1.dtype <> 'discontinue' AND m2.dtype <> 'discontinue'
           AND c.estvisible AND m1.estvisible AND m2.estvisible AND m1.id <> m2.id
           AND t1.jeubaremeactuel_id <> t2.jeubaremeactuel_id
           AND bdoh_jeu_qualite_id(c.id) = (SELECT id FROM jeuqualite WHERE nom = 'VALIDE')
         ORDER BY c.id LIMIT 1""")
    if not rows:
        raise RuntimeError('Aucune chronique calculée ne peut prêter sa structure au jeu inventé.')
    return rows[0]


def quality(order):
    return f"(SELECT q.id FROM qualite q JOIN jeuqualite j ON j.id = q.jeu_id WHERE j.nom = 'VALIDE' AND q.ordre = {order} AND q.code <> 'gap')"


def measures(chronique, rows):
    if not rows:
        return ''
    values = ', '.join(f"(nextval('mesure_id_seq'), {chronique}, {quality(o)}, '{d}', {float(v)!r}, {str(c).lower()})"
                       for d, v, o, c in rows)
    return f'INSERT INTO mesure (id, chronique_id, qualite_id, date, valeur, estcalculee) VALUES {values};\n'


def validity(jeu, periods):
    values = ', '.join(f"(nextval('baremejeubareme_id_seq'), (SELECT id FROM bareme WHERE nom = '{name}'), '{begin}',"
                       f" {f'{chr(39)}{end}{chr(39)}' if end else 'NULL'}, {jeu})" for name, begin, end in periods)
    return (f'DELETE FROM baremejeubareme WHERE jeubareme_id = {jeu};\n'
            f'INSERT INTO baremejeubareme (id, bareme_id, debutvalidite, finvalidite, jeubareme_id) VALUES {values};\n')


def fixture(ids, kind, policy):
    """Le SQL qui met la copie en place pour une étape, tout remis à neuf : la politique de limites d'un calcul à
    une transformation, ou le nom d'un calcul à deux."""
    daughter, second, mother1, mother2, jeu1, jeu2 = ids
    calculated = lambda rows: [(*r, False) for r in rows]
    sql = (f"INSERT INTO bareme (id, nom, valeurs, datecreation) SELECT nextval('bareme_id_seq'), '{CURVE_NAME}', '{CURVE}', '2020-01-01'"
           f" WHERE NOT EXISTS (SELECT 1 FROM bareme WHERE nom = '{CURVE_NAME}');\n"
           f'DELETE FROM mesure WHERE chronique_id IN ({daughter}, {mother1}, {mother2});\n')
    if kind == 'one':
        limit = f"'chronique.lq_ld.options.{policy}'" if policy else 'NULL'
        return (sql + validity(jeu1, ONE_VALIDITY) + measures(mother1, calculated(ONE_MOTHER)) + measures(daughter, ONE_DAUGHTER)
                + f'UPDATE chronique SET secondeentree_id = NULL WHERE id = {daughter};\n'
                + f'UPDATE jeubareme SET valuelimittransformationtype = {limit}, valuelimitplaceholder = {PLACEHOLDER},'
                  f' delaipropagation = NULL WHERE id = {jeu1};\n')
    main, other, before, delay1, delay2, factor, _ = TWOS[policy]
    delays = 'UPDATE jeubareme SET valuelimittransformationtype = NULL, valuelimitplaceholder = NULL, delaipropagation = {} WHERE id = {};\n'
    return (sql + validity(jeu1, TWO_VALIDITY) + validity(jeu2, TWO_VALIDITY)
            + measures(mother1, calculated(main)) + measures(mother2, calculated(other)) + measures(daughter, before)
            + f'UPDATE chronique SET secondeentree_id = {second}, facteurmultiplicatif = {factor} WHERE id = {daughter};\n'
            + delays.format(delay1, jeu1) + delays.format(delay2, jeu2))


ROWS = """SELECT to_char(m.date, 'YYYY-MM-DD HH24:MI:SS'), m.valeur, q.ordre, m.estcalculee
            FROM mesure m JOIN qualite q ON q.id = m.qualite_id WHERE m.chronique_id = {} ORDER BY m.date"""


def psql(server, base, sql):
    done = subprocess.run(['docker', 'exec', '-i', server, 'psql', '-U', 'bdoh', '-d', base, '-q', '-At', '-F', '\t',
                           '-v', 'ON_ERROR_STOP=1'], input=sql, capture_output=True, text=True)
    if done.returncode:
        raise RuntimeError(f"Jeu inventé, psql sur {base} : {(done.stderr.splitlines() or ['sans message'])[0]}")
    return [text.split('\t') for text in done.stdout.splitlines()]


def as_line(row):
    """Une mesure lue, dans la forme des résultats attendus : le float de Python écrit tous ses chiffres."""
    return f'{row[0]}\t{float(row[1])!r}\t{row[2]}\t{row[3]}'


def metadata_line(rows):
    """Ce que le calcul écrit sur la chronique, tiré de ses mesures."""
    dates = [text.split('\t')[0] for text in rows]
    return f'{len(rows)} mesures, du {dates[0]} au {dates[-1]}'


def expected():
    return {name: ['code 0', metadata_line(rows), *rows] for name, _, _, rows in STEPS}


def play(console, server, base, ids, user):
    """Chaque étape : la copie mise en place, le calcul par la commande de l'instance, ce qu'il a écrit."""
    results = {}
    for name, kind, policy, _ in STEPS:
        psql(server, base, fixture(ids, kind, policy))
        code = subprocess.run([*console, 'bdoh:compute:chronique', ids[0], user, '--env=prod'], capture_output=True).returncode
        rows = [as_line(r) for r in psql(server, base, ROWS.format(ids[0]))]
        count, begin, end = psql(server, base, f"""
            SELECT nbmesures, to_char(datedebutmesures, 'YYYY-MM-DD HH24:MI:SS'), to_char(datefinmesures, 'YYYY-MM-DD HH24:MI:SS')
              FROM chronique WHERE id = {ids[0]}""")[0]
        results[name] = [f'code {code}', f'{count} mesures, du {begin} au {end}', *rows]
    return results


def under_postgresql_11(server, base, ids):
    """Les mêmes étapes par les seules fonctions SQL, chacune dans une transaction annulée : PostgreSQL 11 n'a
    pas d'instance qu'on puisse brancher sur une copie à bon compte. Le nombre de chiffres se règle après le
    calcul : la courbe passe ses résultats par du texte, qui en dépend."""
    results = {}
    for name, kind, policy, _ in STEPS:
        rows = psql(server, base, f"""BEGIN;
            {fixture(ids, kind, policy)}
            SELECT bdoh_compute_chronique({ids[0]});
            SET extra_float_digits = 3;
            {ROWS.format(ids[0])};
            ROLLBACK;""")
        results[name] = [f'code {rows[0][0]}', *(as_line(r) for r in rows[1:])]
    return results
