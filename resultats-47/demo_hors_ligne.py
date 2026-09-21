"""Apercu du bulletin sans appeler les federations.

Utile pour regler la mise en forme, ou pour verifier l'installation :
    python demo_hors_ligne.py
"""

from __future__ import annotations

from datetime import date, datetime

from resultats47.config import charger
from resultats47.models import A_VENIR, JOUE, REPORTE, Match, RapportSource
from resultats47.report import Bulletin, en_texte
from resultats47.weekend import weekend_de

D = datetime


def m(sport, competition, dom, ext, sd, se, club, quand, statut=JOUE, poule=None, journee=None):
    return Match(
        sport=sport, competition=competition, equipe_domicile=dom, equipe_exterieur=ext,
        club_suivi=club, score_domicile=sd, score_exterieur=se, date=quand,
        statut=statut, poule=poule, journee=journee, source="demo",
    )


EXEMPLES = [
    m("rugby", "Nationale 2", "US MARMANDE", "FC FLEURANCE", 24, 17, "Marmande", D(2026, 9, 20, 15, 0), journee="3"),
    m("rugby", "Nationale 2", "US VALENCE D'AGEN", "STADE BAGNERAIS", 12, 28, "Valence d'Agen", D(2026, 9, 20, 15, 0), journee="3"),
    m("rugby", "Federale 2", "US CASTELJALOUX", "US TOURNON", None, None, "Casteljaloux", D(2026, 9, 20, 15, 0), REPORTE, poule="7"),
    m("rugby", "Federale 3", "US NERAC", "STADE VILLENEUVOIS", 19, 19, "Nerac / Villeneuve-sur-Lot", D(2026, 9, 20, 15, 0), poule="11", journee="2"),
    m("football", "Regional 1", "MARMANDE 47", "EBBE 47", 1, 3, "Marmande / EBBE 47", D(2026, 9, 19, 19, 30), journee="5"),
    m("football", "Regional 2", "SU AGEN", "AS PONT-DU-CASSE", 2, 2, "SU Agen / Pont-du-Casse", D(2026, 9, 20, 15, 0)),
    m("basket", "Nationale Masculine 3", "VILLENEUVE SUR LOT", "US SEYCHES", 78, 65, "Villeneuve-sur-Lot / Seyches", D(2026, 9, 19, 20, 0), journee="2"),
    m("basket", "Pre-Nationale Masculine", "AGEN BC", "COLAYRAC ST CIRQ", None, None, "Agen Basket Club / Colayrac", D(2026, 9, 20, 20, 30), A_VENIR),
]


def principal() -> None:
    config = charger("config.yaml")
    bulletin = Bulletin(
        fenetre=weekend_de(date(2026, 9, 21)),
        config=config,
        rapports=[
            RapportSource(
                source="demo",
                matchs=EXEMPLES,
                equipes_sans_match=["Federale 1 - poule 4 / 4 Cantons"],
                erreurs=[],
            )
        ],
    )
    print(en_texte(bulletin))


if __name__ == "__main__":
    principal()
