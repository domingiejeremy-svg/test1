import unittest

from resultats47.matching import Reconnaisseur, mots_cles, normaliser


class TestNormalisation(unittest.TestCase):
    def test_accents_ponctuation_et_casse(self):
        self.assertEqual(normaliser("U.S. Nérac"), "u s nerac")
        self.assertEqual(normaliser("Pont-du-Casse"), "pont du casse")

    def test_les_mots_de_bruit_disparaissent(self):
        self.assertEqual(mots_cles("US Casteljaloux 1"), {"casteljaloux"})

    def test_un_libelle_entierement_bruite_garde_ses_mots(self):
        self.assertTrue(mots_cles("US Rugby"))


class TestReconnaisseur(unittest.TestCase):
    def test_retrouve_les_graphies_federales(self):
        r = Reconnaisseur(["Marmande", "US Marmande"], sport="rugby")
        self.assertTrue(r("US MARMANDAISE"))
        self.assertTrue(r("U.S. MARMANDE 1"))

    def test_ne_confond_pas_deux_clubs_de_la_meme_ville(self):
        r = Reconnaisseur(["Marmande"], sport="rugby")
        self.assertFalse(r("SU AGEN"))

    def test_le_sport_arbitre_entre_clubs_homonymes(self):
        basket = Reconnaisseur(["Agen Basket Club", "Agen BC"], sport="basket")
        self.assertTrue(basket("AGEN BC"))
        self.assertFalse(basket("SU AGEN RUGBY"))

    def test_un_libelle_plus_pauvre_que_l_alias_est_refuse(self):
        # "US LAYRAC" (rugby) ne doit pas passer pour "Layrac-Astaffort" (basket).
        r = Reconnaisseur(["Layrac-Astaffort"], sport="basket")
        self.assertFalse(r("US LAYRAC"))

    def test_mais_l_alias_court_explicite_fonctionne(self):
        r = Reconnaisseur(["Layrac-Astaffort", "Layrac"], sport="basket")
        self.assertTrue(r("LAYRAC ASTAFFORT BASKET"))

    def test_reconnait_match_regarde_les_deux_equipes(self):
        r = Reconnaisseur(["Nerac"], sport="rugby")
        self.assertTrue(r.reconnait_match("US NERAC", "SU AGEN"))
        self.assertTrue(r.reconnait_match("SU AGEN", "US NERAC"))
        self.assertFalse(r.reconnait_match("SU AGEN", "US MARMANDE"))


if __name__ == "__main__":
    unittest.main()
