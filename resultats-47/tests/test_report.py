import unittest
from datetime import date, datetime

from resultats47.config import charger
from resultats47.models import REPORTE, Match, RapportSource
from resultats47.report import Bulletin, en_markdown, en_texte
from resultats47.weekend import weekend_de

CONFIG = "config.yaml"


def match(**kw):
    base = dict(
        sport="rugby", competition="Nationale 2",
        equipe_domicile="US MARMANDE", equipe_exterieur="STADE BAGNERAIS",
        club_suivi="Marmande", date=datetime(2026, 9, 20, 15, 0),
        score_domicile=24, score_exterieur=17, statut="joue", source="ffr",
    )
    base.update(kw)
    return Match(**base)


class TestBulletin(unittest.TestCase):
    def setUp(self):
        self.config = charger(CONFIG)
        self.fenetre = weekend_de(date(2026, 9, 21))

    def bulletin(self, matchs, **kw):
        rapport = RapportSource(source="ffr", matchs=matchs, **kw)
        return Bulletin(fenetre=self.fenetre, config=self.config, rapports=[rapport])

    def test_victoire_nomme_toujours_le_club(self):
        texte = en_markdown(self.bulletin([match()]))
        self.assertIn("US MARMANDE 24 - 17 STADE BAGNERAIS", texte)
        self.assertIn("victoire de Marmande a domicile", texte)
        self.assertIn("✅", texte)

    def test_defaite_nomme_toujours_le_club(self):
        m = match(equipe_domicile="STADE BAGNERAIS", equipe_exterieur="US MARMANDE",
                  score_domicile=30, score_exterieur=10)
        texte = en_markdown(self.bulletin([m]))
        self.assertIn("defaite de Marmande a l exterieur", texte)
        self.assertIn("❌", texte)

    def test_match_nul(self):
        texte = en_markdown(self.bulletin([match(score_domicile=17, score_exterieur=17)]))
        self.assertIn("match nul pour Marmande", texte)

    def test_duel_entre_deux_clubs_suivis_n_annonce_pas_de_vainqueur_ambigu(self):
        # Marmande et Fleurance sont tous deux suivis en Nationale 2 : annoncer
        # "victoire a domicile" sans nommer le club induirait l'antenne en erreur.
        m = match(equipe_exterieur="FC FLEURANCE", club_suivi="Marmande / Fleurance")
        texte = en_markdown(self.bulletin([m]))
        self.assertIn("duel entre clubs suivis", texte)
        self.assertIn("Marmande", texte)
        self.assertIn("Fleurance", texte)
        self.assertNotIn("victoire", texte)
        self.assertNotIn("✅", texte)

    def test_rencontre_reportee_n_affiche_pas_de_score(self):
        m = match(score_domicile=None, score_exterieur=None, statut=REPORTE)
        texte = en_markdown(self.bulletin([m]))
        self.assertIn("reporte", texte)
        self.assertNotIn(" 0 - 0 ", texte)

    def test_les_clubs_sans_match_sont_signales(self):
        b = self.bulletin([], equipes_sans_match=["Federale 2 - poule 7 / Casteljaloux"])
        texte = en_markdown(b)
        self.assertIn("Sans rencontre trouvee", texte)
        self.assertIn("Casteljaloux", texte)

    def test_une_meme_panne_ne_s_affiche_qu_une_fois(self):
        # Une source injoignable echoue pour chacun de ses clubs : inutile de
        # repeter vingt fois la meme ligne dans le bulletin.
        b = self.bulletin([], erreurs=["api injoignable"] * 6)
        self.assertEqual(len(b.erreurs), 1)

    def test_les_erreurs_de_source_remontent(self):
        b = self.bulletin([], erreurs=["page illisible"])
        self.assertIn("Sources en echec", en_markdown(b))
        self.assertIn("page illisible", en_markdown(b))

    def test_un_match_entre_deux_clubs_suivis_n_apparait_qu_une_fois(self):
        commun = dict(competition="Federale 3", equipe_domicile="US NERAC",
                      equipe_exterieur="STADE VILLENEUVOIS", score_domicile=12, score_exterieur=20)
        b = self.bulletin([match(club_suivi="Nerac", **commun),
                           match(club_suivi="Villeneuve-sur-Lot", **commun)])
        self.assertEqual(len(b.matchs), 1)
        self.assertEqual(en_markdown(b).count("US NERAC 12 - 20 STADE VILLENEUVOIS"), 1)

    def test_comptage_des_rencontres_jouees(self):
        b = self.bulletin([
            match(),
            match(equipe_domicile="US NERAC", equipe_exterieur="SU AGEN", club_suivi="Nerac",
                  score_domicile=None, score_exterieur=None, statut="a_venir"),
        ])
        self.assertEqual(len(b.matchs), 2)
        self.assertEqual(b.joues, 1)

    def test_groupement_par_sport_dans_l_ordre_de_l_emission(self):
        b = self.bulletin([
            match(sport="basket", competition="Nationale Masculine 3",
                  equipe_domicile="VILLENEUVE", equipe_exterieur="SEYCHES",
                  club_suivi="Villeneuve-sur-Lot", score_domicile=78, score_exterieur=65),
            match(),
            match(sport="football", competition="Regional 1", club_suivi="Marmande",
                  equipe_domicile="MARMANDE 47", equipe_exterieur="SU AGEN",
                  score_domicile=2, score_exterieur=1),
        ])
        texte = en_markdown(b)
        self.assertLess(texte.index("Rugby"), texte.index("Football"))
        self.assertLess(texte.index("Football"), texte.index("Basket"))

    def test_rendu_texte_sans_balisage(self):
        texte = en_texte(self.bulletin([match()]))
        self.assertNotIn("**", texte)
        self.assertNotIn("###", texte)
        self.assertIn("US MARMANDE 24 - 17 STADE BAGNERAIS", texte)


if __name__ == "__main__":
    unittest.main()
