import unittest
from datetime import date, datetime

from resultats47.weekend import fenetre_personnalisee, weekend_de


class TestFenetreWeekend(unittest.TestCase):
    def test_lundi_regarde_le_weekend_ecoule(self):
        f = weekend_de(date(2026, 9, 21))  # lundi
        self.assertEqual(f.debut.date(), date(2026, 9, 18))
        self.assertEqual(f.fin.date(), date(2026, 9, 20))

    def test_du_vendredi_au_dimanche_le_weekend_est_celui_en_cours(self):
        for jour in (18, 19, 20):
            f = weekend_de(date(2026, 9, jour))
            self.assertEqual(f.debut.date(), date(2026, 9, 18), f"le {jour}")
            self.assertEqual(f.fin.date(), date(2026, 9, 20), f"le {jour}")

    def test_jeudi_regarde_encore_le_weekend_precedent(self):
        f = weekend_de(date(2026, 9, 24))  # jeudi
        self.assertEqual(f.debut.date(), date(2026, 9, 18))

    def test_la_fenetre_couvre_toute_la_journee_de_dimanche(self):
        f = weekend_de(date(2026, 9, 21))
        self.assertTrue(f.contient(datetime(2026, 9, 20, 23, 30)))
        self.assertTrue(f.contient(datetime(2026, 9, 18, 0, 5)))
        self.assertFalse(f.contient(datetime(2026, 9, 21, 0, 1)))
        self.assertFalse(f.contient(datetime(2026, 9, 17, 20, 0)))

    def test_contient_accepte_une_date_simple_et_ignore_none(self):
        f = weekend_de(date(2026, 9, 21))
        self.assertTrue(f.contient(date(2026, 9, 19)))
        self.assertFalse(f.contient(None))

    def test_fenetre_personnalisee(self):
        f = fenetre_personnalisee(date(2026, 9, 1), date(2026, 9, 3))
        self.assertTrue(f.contient(datetime(2026, 9, 3, 22, 0)))
        self.assertEqual(f.iso(), ("2026-09-01", "2026-09-03"))


if __name__ == "__main__":
    unittest.main()
