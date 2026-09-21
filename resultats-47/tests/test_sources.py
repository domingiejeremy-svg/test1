import unittest
from datetime import date, datetime

from resultats47.config import Club, Competition
from resultats47.models import FORFAIT, JOUE, REPORTE
from resultats47.reseau import ErreurSource
from resultats47.sources.base import en_datetime, en_entier, nom_equipe, premier, statut_depuis
from resultats47.sources.ffbb import SourceFFBB, _cherche_jwt, _termes_de_recherche
from resultats47.sources.ffr import SourceFFR
from resultats47.sources.fff import SourceFFF
from resultats47.weekend import weekend_de


class TestLectureTolerante(unittest.TestCase):
    def test_dates_des_trois_federations(self):
        self.assertEqual(en_datetime("2026-09-20T15:00:00+02:00"), datetime(2026, 9, 20, 15, 0))
        self.assertEqual(en_datetime("2026-09-20T13:00:00Z"), datetime(2026, 9, 20, 13, 0))
        self.assertEqual(en_datetime("2026-09-20", "15h30"), datetime(2026, 9, 20, 15, 30))
        self.assertEqual(en_datetime("20/09/2026", "20:00"), datetime(2026, 9, 20, 20, 0))
        self.assertIsNone(en_datetime(""))
        self.assertIsNone(en_datetime("bientot"))

    def test_scores_absents_ne_deviennent_pas_zero(self):
        # Le piege a eviter : annoncer 0-0 pour un match non joue.
        self.assertIsNone(en_entier(""))
        self.assertIsNone(en_entier("-"))
        self.assertIsNone(en_entier(None))
        self.assertEqual(en_entier(0), 0)
        self.assertEqual(en_entier("24"), 24)

    def test_nom_equipe_texte_ou_objet(self):
        self.assertEqual(nom_equipe({"home": "US MARMANDE"}, "home"), "US MARMANDE")
        self.assertEqual(nom_equipe({"home": {"short_name": "MARMANDE"}}, "home"), "MARMANDE")
        self.assertEqual(nom_equipe({"a": {"club": {"cl_nom": "SU AGEN"}}}, "a"), "SU AGEN")
        self.assertEqual(nom_equipe({}, "home"), "")

    def test_premier_chemin_non_vide(self):
        self.assertEqual(premier({"x": {"y": 3}}, "absent", "x.y"), 3)
        self.assertIsNone(premier({"x": ""}, "x"))

    def test_statuts(self):
        self.assertEqual(statut_depuis(24, 17, ()), JOUE)
        self.assertEqual(statut_depuis(None, None, ("match reporte",)), REPORTE)
        self.assertEqual(statut_depuis(None, None, ("forfait equipe 2",)), FORFAIT)
        self.assertEqual(statut_depuis(None, None, ("2026-09-20",)), "a_venir")


class TestFFF(unittest.TestCase):
    def setUp(self):
        self.source = SourceFFF()
        self.competition = Competition(
            sport="football", libelle="Regional 1",
            clubs=[Club("Marmande", ["US Marmande"])],
        )

    def test_normalise_un_enregistrement_dofa(self):
        brut = {
            "ma_no": 42,
            "ma_dat": "2026-09-20",
            "ma_heure": "18:00",
            "home": {"short_name": "MARMANDE 47"},
            "away": {"short_name": "SU AGEN"},
            "home_score": 2,
            "away_score": 1,
            "competition": {"cp_no": 777, "name": "REGIONAL 1"},
            "poule": {"name": "Poule A"},
        }
        m = self.source._vers_match(brut, self.competition, self.competition.clubs[0])
        self.assertEqual((m.equipe_domicile, m.equipe_exterieur), ("MARMANDE 47", "SU AGEN"))
        self.assertEqual(m.score, "2 - 1")
        self.assertEqual(m.date, datetime(2026, 9, 20, 18, 0))
        self.assertEqual(m.statut, JOUE)
        self.assertEqual(m.sport, "football")

    def test_match_sans_score_n_invente_rien(self):
        brut = {"ma_dat": "2026-09-20", "home": "MARMANDE", "away": "AGEN",
                "home_score": None, "away_score": None}
        m = self.source._vers_match(brut, self.competition, self.competition.clubs[0])
        self.assertIsNone(m.score_domicile)
        self.assertFalse(m.est_joue)
        self.assertEqual(m.score, "a jouer")

    def test_filtre_de_competition_par_identifiant_epingle(self):
        self.competition.ids = {"cp_no": 777}
        self.assertTrue(self.source._bonne_competition({"competition": {"cp_no": 777}}, self.competition))
        self.assertFalse(self.source._bonne_competition({"competition": {"cp_no": 999}}, self.competition))

    def test_une_panne_reseau_ne_se_deguise_pas_en_club_introuvable(self):
        def tombe_en_panne(url, params):
            raise ErreurSource("api injoignable")

        self.source._membres = tombe_en_panne
        with self.assertRaises(ErreurSource):
            self.source.resoudre_club(Club("Marmande"))

    def test_un_club_vraiment_absent_renvoie_none(self):
        self.source._membres = lambda url, params: []
        self.assertIsNone(self.source.resoudre_club(Club("Club Inexistant")))

    def test_sans_identifiant_le_filtre_retombe_sur_le_libelle(self):
        self.assertTrue(self.source._bonne_competition(
            {"competition": {"name": "REGIONAL 1 - POULE A"}}, self.competition))
        self.assertFalse(self.source._bonne_competition(
            {"competition": {"name": "COUPE DE FRANCE U15"}}, self.competition))


class TestFFBB(unittest.TestCase):
    def setUp(self):
        self.source = SourceFFBB()
        self.competition = Competition(
            sport="basket", libelle="Nationale Masculine 3",
            clubs=[Club("Villeneuve-sur-Lot", ["Villeneuve"])],
        )

    def test_normalise_une_rencontre_directus(self):
        brut = {
            "date_rencontre": "2026-09-19",
            "horaire": "20h00",
            "nomEquipe1": "VILLENEUVE SUR LOT",
            "nomEquipe2": "SEYCHES",
            "resultatEquipe1": 78,
            "resultatEquipe2": 65,
            "numeroJournee": 2,
            "competitionId": {"nom": "NATIONALE MASCULINE 3"},
        }
        m = self.source._vers_match(brut, self.competition, self.competition.clubs[0])
        self.assertEqual(m.score, "78 - 65")
        self.assertEqual(m.date, datetime(2026, 9, 19, 20, 0))
        self.assertEqual(m.journee, "2")
        self.assertEqual(m.statut, JOUE)

    def test_rencontre_remise(self):
        brut = {"date_rencontre": "2026-09-19", "nomEquipe1": "A", "nomEquipe2": "B", "remise": True}
        m = self.source._vers_match(brut, self.competition, self.competition.clubs[0])
        self.assertEqual(m.statut, REPORTE)

    def test_termes_de_recherche_distinctifs(self):
        termes = _termes_de_recherche(Club("Layrac-Astaffort", ["ALA Basket"]))
        self.assertIn("astaffort", termes)
        self.assertTrue(all(len(t) >= 4 for t in termes))

    def test_repere_le_jeton_dans_une_configuration_de_forme_inconnue(self):
        charge = {"data": {"divers": 1, "apiBearerToken": "aaaaaaaa.bbbbbbbb.cccccccc"}}
        self.assertEqual(_cherche_jwt(charge), "aaaaaaaa.bbbbbbbb.cccccccc")
        self.assertIsNone(_cherche_jwt({"data": {"nom": "sans jeton"}}))

    def test_competition_indeterminable_gardee_sauf_en_strict(self):
        brut = {"nomEquipe1": "A", "nomEquipe2": "B"}
        self.assertTrue(self.source._bonne_competition(brut, self.competition, strict=False))
        self.assertFalse(self.source._bonne_competition(brut, self.competition, strict=True))


PAGE_NEXT = '''<html><body>
<script id="__NEXT_DATA__" type="application/json">
{"props":{"pageProps":{"calendrier":{"rencontres":[
  {"date":"2026-09-20T15:00:00+02:00","journee":3,
   "home":{"name":"US MARMANDE"},"away":{"name":"FC FLEURANCE"},
   "homeScore":24,"awayScore":17},
  {"date":"2026-09-20T15:00:00+02:00",
   "home":{"name":"US NERAC"},"away":{"name":"SU AGEN"},
   "homeScore":null,"awayScore":null,"statut":"reporte"},
  {"date":"2026-09-13T15:00:00+02:00",
   "home":{"name":"US MARMANDE"},"away":{"name":"US NERAC"},
   "homeScore":10,"awayScore":30}
]}}}}
</script></body></html>'''


class TestFFR(unittest.TestCase):
    def setUp(self):
        self.source = SourceFFR()
        self.competition = Competition(
            sport="rugby", libelle="Nationale 2", slug="nationale-2",
            clubs=[Club("Marmande", ["US Marmande"]), Club("Nerac", ["US Nerac"])],
        )

    def test_inventaire_de_page(self):
        inventaire = self.source.inventaire(PAGE_NEXT)
        self.assertEqual(inventaire["__NEXT_DATA__"], 1)
        self.assertEqual(inventaire["objets_de_match"], 3)

    def test_extraction_et_normalisation(self):
        objets = list(self.source.extraire(PAGE_NEXT))
        m = self.source._vers_match(objets[0], self.competition, "Marmande")
        self.assertEqual(m.equipe_domicile, "US MARMANDE")
        self.assertEqual(m.score, "24 - 17")
        self.assertEqual(m.journee, "3")

    def test_url_depuis_le_slug_puis_depuis_le_libelle(self):
        self.assertTrue(self.source.url_competition(self.competition).endswith("/nationale-2/calendrier-resultats"))
        sans_slug = Competition(sport="rugby", libelle="Federale 3", clubs=[Club("X")])
        self.assertIn("federale-3", self.source.url_competition(sans_slug))

    def test_collecte_filtre_sur_la_fenetre_et_les_clubs(self):
        self.source.charger = lambda competition: list(self.source.extraire(PAGE_NEXT))
        rapport = self.source.collecter([self.competition], weekend_de(date(2026, 9, 21)))
        # Le match du 13/09 est hors fenetre ; Fleurance n'est pas suivi ici.
        libelles = {(m.equipe_domicile, m.equipe_exterieur) for m in rapport.matchs}
        self.assertEqual(libelles, {("US MARMANDE", "FC FLEURANCE"), ("US NERAC", "SU AGEN")})
        self.assertEqual(rapport.erreurs, [])


if __name__ == "__main__":
    unittest.main()
