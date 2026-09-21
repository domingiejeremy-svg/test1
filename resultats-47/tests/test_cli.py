import unittest

from resultats47.cli import CONFIG_PAR_DEFAUT, _avec_commande_par_defaut, analyser, construire_parseur


class TestCommandeParDefaut(unittest.TestCase):
    def test_sans_argument(self):
        self.assertEqual(_avec_commande_par_defaut([]), ["bulletin"])

    def test_options_de_bulletin_sans_nommer_la_commande(self):
        self.assertEqual(
            _avec_commande_par_defaut(["--sport", "rugby"]),
            ["bulletin", "--sport", "rugby"],
        )

    def test_une_commande_explicite_est_respectee(self):
        for argv in (["diagnostiquer"], ["chercher", "--sport", "football", "Marmande"]):
            self.assertEqual(_avec_commande_par_defaut(argv), argv)

    def test_aide_et_version_ne_sont_pas_detournees(self):
        for argv in (["--help"], ["-h"], ["--version"]):
            self.assertEqual(_avec_commande_par_defaut(argv), argv)


class TestParseur(unittest.TestCase):
    def setUp(self):
        self.parseur = construire_parseur()

    def test_options_communes_disponibles_avant_et_apres_la_commande(self):
        self.assertTrue(analyser(["-v", "bulletin"]).verbeux)
        self.assertTrue(analyser(["bulletin", "-v"]).verbeux)
        self.assertTrue(analyser(["-v"]).verbeux)
        self.assertTrue(analyser(["diagnostiquer", "-v"]).verbeux)

    def test_valeurs_par_defaut_des_options_communes(self):
        args = analyser(["bulletin"])
        self.assertFalse(args.verbeux)
        self.assertEqual(args.config, CONFIG_PAR_DEFAUT)

    def test_config_personnalisee_avant_ou_apres_la_commande(self):
        from pathlib import Path
        self.assertEqual(analyser(["-c", "autre.yaml", "bulletin"]).config, Path("autre.yaml"))
        self.assertEqual(analyser(["bulletin", "-c", "autre.yaml"]).config, Path("autre.yaml"))

    def test_date_mal_formee_est_refusee(self):
        with self.assertRaises(SystemExit):
            self.parseur.parse_args(["bulletin", "--date", "21-09-2026"])

    def test_sport_inconnu_est_refuse(self):
        with self.assertRaises(SystemExit):
            self.parseur.parse_args(["bulletin", "--sport", "petanque"])


if __name__ == "__main__":
    unittest.main()
