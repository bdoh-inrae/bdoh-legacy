"""Le tri des contacts, sur des lignes inventées : Claude ne lit pas ce qu'il produit sur le dump brut."""

import unittest

import contacts as c

DUMP = r"""--
-- Data for Name: observatoire; Type: TABLE DATA
--
COPY bdoh.observatoire (id, slug, email, projectleader_id) FROM stdin;
1	ALPHA	Contact@inrae.fr	10
2	BETA	\N	\N
\.
COPY bdoh.personnetheia (id, nom, prenom, email) FROM stdin;
10	Dupont	Ana	ana@univ.example
11	Roy	Max	max@irstea.fr
12	Duo	Ali	ali@x.example; Bea@Y.example
13	Rien	Sans	voir le site web
\.
COPY bdoh.observatoires_data_manager (observatoire_id, personnetheia_id) FROM stdin;
1	11
2	12
2	13
\.
COPY bdoh.siteexperimental (id, observatoire_id) FROM stdin;
5	2
\.
COPY bdoh.utilisateur (id, email, prenom, nom, finacces) FROM stdin;
20	contact@inrae.fr	Léa	Fort	\N
21	vieux@inrae.fr	Tom	Vieux	2020-01-01 00:00:00
22	site@labo.inrae.fr	Zoé	Site	2999-01-01 00:00:00
23	simple@inrae.fr	Eva	Simple	\N
\.
COPY bdoh.role (id, dtype, valeur, utilisateur_id, observatoire_id, site_id) FROM stdin;
30	observatoire	gestionnaire	20	1	\N
31	observatoire	gestionnaire	21	1	\N
32	site	gestionnaire	22	\N	5
33	observatoire	utilisateur	23	1	\N
\.
"""


class Contacts(unittest.TestCase):
    def setUp(self):
        self.found = c.contacts(c.parse_copy(DUMP.splitlines(True)), '2026-11-05')

    def test_a_person_is_listed_once_with_all_its_titles(self):
        name, titles = self.found['ALPHA']['contact@inrae.fr']
        self.assertEqual(name, 'Léa Fort')
        self.assertEqual(titles, ['contact de l’observatoire', 'gestionnaire'])

    def test_who_is_listed(self):
        self.assertEqual(sorted(self.found['ALPHA']), ['ana@univ.example', 'contact@inrae.fr', 'max@irstea.fr'])
        self.assertEqual(sorted(self.found['BETA']), ['ali@x.example', 'bea@y.example', 'site@labo.inrae.fr'])

    def test_a_field_with_two_addresses_gives_both_and_text_gives_none(self):
        self.assertEqual(self.found['BETA']['bea@y.example'], ['Ali Duo', ['gestionnaire de données (Theia)']])
        self.assertFalse(any(' ' in email for people in self.found.values() for email in people))

    def test_an_expired_account_and_a_mere_user_are_left_out(self):
        self.assertNotIn('vieux@inrae.fr', self.found['ALPHA'])
        self.assertNotIn('simple@inrae.fr', self.found['ALPHA'])

    def test_inrae_or_not(self):
        for email in ('a@inrae.fr', 'a@labo.inrae.fr', 'a@irstea.fr', 'a@inra.fr', 'A@INRAE.FR'):
            self.assertTrue(c.INRAE.search(email), email)
        for email in ('a@univ.example', 'a@inrae.fr.example', 'inrae.fr@gmail.com', 'a@notinrae.fr'):
            self.assertFalse(c.INRAE.search(email), email)

    def test_allowed_recipients_need_no_backslash(self):
        self.assertEqual(c.allowed_recipients(['a.b+c@inrae.fr', 'd@x.fr']), '(?i)a[.]b[+]c@inrae[.]fr,(?i)d@x[.]fr')
        self.assertEqual(c.allowed_recipients(['a_b-c@x.fr']), '(?i)a[_]b[-]c@x[.]fr')

    def test_old_domains_are_flagged(self):
        self.assertTrue(c.OLD_DOMAIN.search('a@irstea.fr'))
        self.assertTrue(c.OLD_DOMAIN.search('a@lyon.cemagref.fr'))
        self.assertTrue(c.INRAE.search('a@cemagref.fr'))
        self.assertFalse(c.OLD_DOMAIN.search('a@inrae.fr'))


if __name__ == '__main__':
    unittest.main()
