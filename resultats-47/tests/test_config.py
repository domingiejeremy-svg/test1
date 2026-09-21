import tempfile
import unittest
from pathlib import Path

from resultats47.config import ConfigError, charger


def ecrire(contenu: str) -> Path:
    fichier = Path(tempfile.mkdtemp()) / "config.yaml"
    fichier.write_text(contenu, encoding="utf-8")
    return fichier


class TestConfig(unittest.TestCase):
    def test_config_du_depot(self):
        config = charger("config.yaml")
        self.assertEqual(sorted(config.sources_actives), ["ffbb", "fff", "ffr"])
        self.assertTrue(all(c.clubs for c in config.competitions))

    def test_chaque_sport_pointe_vers_sa_federation(self):
        config = charger("config.yaml")
        attendu = {"rugby": "ffr", "football": "fff", "basket": "ffbb"}
        for competition in config.competitions:
            self.assertEqual(competition.source, attendu[competition.sport])

    def test_un_club_peut_s_ecrire_simplement(self):
        config = charger(ecrire(
            "competitions:\n"
            "  - sport: rugby\n    libelle: Federale 3\n    clubs: [Nerac]\n"
        ))
        club = config.competitions[0].clubs[0]
        self.assertEqual(club.libelle, "Nerac")
        self.assertEqual(club.tous_alias, ["Nerac"])

    def test_alias_et_identifiants(self):
        config = charger(ecrire(
            "competitions:\n"
            "  - sport: football\n    libelle: Regional 1\n    clubs:\n"
            "      - nom: Marmande\n        alias: [US Marmande]\n        ids: {cl_no: 1234}\n"
        ))
        club = config.competitions[0].clubs[0]
        self.assertEqual(club.tous_alias, ["Marmande", "US Marmande"])
        self.assertEqual(club.ids["cl_no"], 1234)

    def test_fichier_absent(self):
        with self.assertRaises(ConfigError):
            charger("/chemin/qui/n/existe/pas.yaml")

    def test_sport_inconnu(self):
        with self.assertRaises(ConfigError) as ctx:
            charger(ecrire("competitions:\n  - sport: petanque\n    libelle: X\n    clubs: [Y]\n"))
        self.assertIn("petanque", str(ctx.exception))

    def test_competition_sans_club(self):
        with self.assertRaises(ConfigError):
            charger(ecrire("competitions:\n  - sport: rugby\n    libelle: Federale 3\n    clubs: []\n"))

    def test_competition_desactivee_est_ignoree(self):
        config = charger(ecrire(
            "competitions:\n"
            "  - sport: rugby\n    libelle: Federale 3\n    actif: false\n    clubs: [Nerac]\n"
        ))
        self.assertEqual(config.par_source("ffr"), [])


if __name__ == "__main__":
    unittest.main()
