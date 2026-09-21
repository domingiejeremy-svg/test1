"""Modele commun a toutes les federations."""

from __future__ import annotations

from dataclasses import dataclass, field
from datetime import datetime
from typing import Any

JOUE = "joue"
A_VENIR = "a_venir"
REPORTE = "reporte"
FORFAIT = "forfait"
INCONNU = "inconnu"


@dataclass
class Match:
    """Une rencontre, normalisee quelle que soit la source."""

    sport: str                      # "rugby" | "football" | "basket"
    competition: str                # libelle affiche, ex. "Nationale 2"
    equipe_domicile: str
    equipe_exterieur: str
    club_suivi: str = ""            # libelle du club suivi (depuis la config)
    poule: str | None = None
    journee: str | None = None
    date: datetime | None = None
    score_domicile: int | None = None
    score_exterieur: int | None = None
    statut: str = INCONNU
    source: str = ""
    url: str | None = None
    brut: dict[str, Any] = field(default_factory=dict, repr=False)

    # -- etat -----------------------------------------------------------
    @property
    def est_joue(self) -> bool:
        return self.score_domicile is not None and self.score_exterieur is not None

    @property
    def score(self) -> str:
        if not self.est_joue:
            return {
                A_VENIR: "a jouer",
                REPORTE: "reporte",
                FORFAIT: "forfait",
            }.get(self.statut, "score non disponible")
        return f"{self.score_domicile} - {self.score_exterieur}"

    # -- point de vue du club suivi -------------------------------------
    def joue_a_domicile(self, alias_ok) -> bool | None:
        """True si le club suivi recoit, False s'il se deplace, None si indetermine."""
        if alias_ok(self.equipe_domicile):
            return True
        if alias_ok(self.equipe_exterieur):
            return False
        return None

    def issue(self, a_domicile: bool | None) -> str:
        """'V', 'D', 'N' du point de vue du club suivi, '' si inconnu."""
        if not self.est_joue or a_domicile is None:
            return ""
        pour = self.score_domicile if a_domicile else self.score_exterieur
        contre = self.score_exterieur if a_domicile else self.score_domicile
        if pour > contre:
            return "V"
        if pour < contre:
            return "D"
        return "N"

    @property
    def cle(self) -> tuple:
        """Cle de dedoublonnage (deux clubs suivis peuvent s'affronter)."""
        jour = self.date.date().isoformat() if self.date else ""
        return (
            self.sport,
            self.competition,
            jour,
            self.equipe_domicile.casefold().strip(),
            self.equipe_exterieur.casefold().strip(),
        )


@dataclass
class RapportSource:
    """Ce qu'une source a reussi (ou non) a ramener, pour le diagnostic."""

    source: str
    matchs: list[Match] = field(default_factory=list)
    equipes_sans_match: list[str] = field(default_factory=list)
    erreurs: list[str] = field(default_factory=list)

    @property
    def ok(self) -> bool:
        return not self.erreurs
