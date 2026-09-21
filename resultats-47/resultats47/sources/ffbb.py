"""Basket : API Directus de la Federation Francaise de Basket-Ball.

Base : https://api.ffbb.app  (c'est le backend de competitions.ffbb.com)
Un jeton porteur public est necessaire ; il est resolu automatiquement
depuis /items/configuration, comme le fait l'application web.

Strategie : une requete Directus par club, filtrant a la fois sur la
fenetre de dates et sur le nom d'equipe. C'est bien plus economique que
de rapatrier toutes les rencontres de France du week-end.
"""

from __future__ import annotations

import json
import logging
import os
import re

from resultats47.config import Club, Competition
from resultats47.matching import Reconnaisseur, mots_cles, score_similarite
from resultats47.models import Match, RapportSource
from resultats47.reseau import ErreurSource, Session
from resultats47.sources.base import (
    Source,
    en_datetime,
    en_entier,
    premier,
    statut_depuis,
)
from resultats47.weekend import Fenetre

logger = logging.getLogger(__name__)

BASE = "https://api.ffbb.app"
ENDPOINT_RENCONTRES = "items/ffbbserver_rencontres"
ENDPOINT_CONFIGURATION = "items/configuration"
ENV_JETON = "API_FFBB_APP_BEARER_TOKEN"
MOTIF_JWT = re.compile(r"^[A-Za-z0-9_-]{8,}\.[A-Za-z0-9_-]{8,}\.[A-Za-z0-9_-]{8,}$")


class SourceFFBB(Source):
    nom = "ffbb"
    sport = "basket"

    def __init__(self, seuil: float = 0.86, verbeux: bool = False, base: str = BASE):
        super().__init__(seuil, verbeux)
        self.base = base.rstrip("/")
        self.session = Session()
        self._jeton: str | None = None
        self._echec_jeton: ErreurSource | None = None

    # -- jeton -----------------------------------------------------------
    def jeton(self) -> str:
        """Jeton porteur : variable d'environnement, client officiel, puis /items/configuration."""
        if self._jeton:
            return self._jeton
        if self._echec_jeton is not None:
            raise self._echec_jeton

        depuis_env = os.getenv(ENV_JETON)
        if depuis_env:
            self._jeton = depuis_env
            return self._jeton

        try:
            from ffbb_api_client_v2.facade.token_manager import TokenManager

            self._jeton = TokenManager.get_tokens().api_token
            return self._jeton
        except Exception as exc:  # paquet absent ou API modifiee
            logger.debug("FFBB : TokenManager indisponible (%s), repli sur /items/configuration", exc)

        try:
            donnees = self.session.json(f"{self.base}/{ENDPOINT_CONFIGURATION}")
        except ErreurSource as exc:
            self._echec_jeton = exc
            raise
        trouve = _cherche_jwt(donnees)
        if not trouve:
            self._echec_jeton = ErreurSource(
                "Jeton FFBB introuvable. Installez ffbb-api-client-v2 "
                f"ou renseignez la variable d'environnement {ENV_JETON}."
            )
            raise self._echec_jeton
        self._jeton = trouve
        return self._jeton

    def _entetes(self) -> dict[str, str]:
        return {"Authorization": f"Bearer {self.jeton()}"}

    # -- API -------------------------------------------------------------
    def rencontres_du_club(self, club: Club, fenetre: Fenetre) -> list[dict]:
        """Rencontres d'un club sur la fenetre, via filtre Directus."""
        debut, fin = fenetre.iso()
        termes = _termes_de_recherche(club)
        if not termes:
            return []
        conditions = []
        for terme in termes:
            conditions.append({"nomEquipe1": {"_icontains": terme}})
            conditions.append({"nomEquipe2": {"_icontains": terme}})
        filtre = {
            "_and": [
                {"date_rencontre": {"_between": [debut, fin]}},
                {"_or": conditions},
            ]
        }
        donnees = self.session.json(
            f"{self.base}/{ENDPOINT_RENCONTRES}",
            params={
                "filter": json.dumps(filtre, separators=(",", ":")),
                "fields[]": "*.*",
                "sort[]": "date_rencontre",
                "limit": 100,
            },
            headers=self._entetes(),
        )
        membres = donnees.get("data") if isinstance(donnees, dict) else donnees
        return [m for m in (membres or []) if isinstance(m, dict)]

    # -- normalisation ---------------------------------------------------
    def _vers_match(self, brut: dict, competition: Competition, club: Club) -> Match:
        score_dom = en_entier(premier(brut, "resultatEquipe1", "scoreEquipe1"))
        score_ext = en_entier(premier(brut, "resultatEquipe2", "scoreEquipe2"))
        return Match(
            sport="basket",
            competition=competition.libelle,
            equipe_domicile=str(premier(brut, "nomEquipe1", "equipe1.nom") or "").strip(),
            equipe_exterieur=str(premier(brut, "nomEquipe2", "equipe2.nom") or "").strip(),
            club_suivi=club.libelle,
            poule=str(premier(brut, "idPoule.nom", "idPoule.libelle") or "") or competition.poule,
            journee=str(premier(brut, "numeroJournee") or "") or None,
            date=en_datetime(
                premier(brut, "date_rencontre", "date"), premier(brut, "horaire")
            ),
            score_domicile=score_dom,
            score_exterieur=score_ext,
            statut=statut_depuis(
                score_dom,
                score_ext,
                (
                    premier(brut, "etat", "status"),
                    "reporte" if premier(brut, "remise") else "",
                    "forfait" if premier(brut, "forfaitEquipe1", "forfaitEquipe2") else "",
                    premier(brut, "date_rencontre", "date"),
                ),
            ),
            source=self.nom,
            url=premier(brut, "url_competition"),
            brut=brut,
        )

    def _libelle_competition(self, brut: dict) -> str:
        return str(
            premier(
                brut,
                "competitionId.nom",
                "competitionId.libelle",
                "competitionId.code",
                "idPoule.nom",
            )
            or ""
        )

    def _bonne_competition(self, brut: dict, competition: Competition, strict: bool) -> bool:
        for cle, champs in (
            ("poule_id", ("idPoule.id", "idPoule")),
            ("competition_id", ("competitionId.id", "competitionId")),
        ):
            attendu = competition.ids.get(cle)
            if attendu is not None:
                return str(premier(brut, *champs) or "") == str(attendu)
        libelle = self._libelle_competition(brut)
        if not libelle:
            # Competition indeterminable : on garde par defaut (mieux vaut un
            # match de trop qu'un resultat manquant a l'antenne), sauf en strict.
            return not strict
        return score_similarite(competition.libelle, libelle) >= 0.7

    # -- collecte --------------------------------------------------------
    def collecter(
        self, competitions: list[Competition], fenetre: Fenetre, strict: bool = False
    ) -> RapportSource:
        rapport = self._rapport()
        for competition in competitions:
            for club in competition.clubs:
                etiquette = f"{competition.titre} / {club.libelle}"
                try:
                    bruts = self.rencontres_du_club(club, fenetre)
                except ErreurSource as exc:
                    rapport.erreurs.append(f"{etiquette} : {exc}")
                    continue

                reconnaisseur = Reconnaisseur(club.tous_alias, sport="basket", seuil=self.seuil)
                garde = 0
                for brut in bruts:
                    if not self._bonne_competition(brut, competition, strict):
                        continue
                    match = self._vers_match(brut, competition, club)
                    if not fenetre.contient(match.date):
                        continue
                    if not reconnaisseur.reconnait_match(match.equipe_domicile, match.equipe_exterieur):
                        continue
                    rapport.matchs.append(match)
                    garde += 1
                if garde == 0:
                    rapport.equipes_sans_match.append(etiquette)
        return rapport


def _termes_de_recherche(club: Club) -> list[str]:
    """Mots distinctifs a envoyer au filtre _icontains de Directus."""
    termes: list[str] = []
    for alias in club.tous_alias:
        candidats = sorted(mots_cles(alias), key=len, reverse=True)
        for mot in candidats[:2]:
            if len(mot) >= 4 and mot not in termes:
                termes.append(mot)
    return termes[:4]


def _cherche_jwt(donnee, profondeur: int = 0) -> str | None:
    """Repere un jeton JWT dans une reponse JSON de forme inconnue."""
    if profondeur > 6:
        return None
    if isinstance(donnee, str):
        return donnee if MOTIF_JWT.match(donnee) else None
    if isinstance(donnee, dict):
        # On privilegie les cles qui parlent de jeton d'API.
        cles = sorted(donnee, key=lambda k: ("token" not in str(k).lower(), str(k)))
        for cle in cles:
            trouve = _cherche_jwt(donnee[cle], profondeur + 1)
            if trouve:
                return trouve
    elif isinstance(donnee, list):
        for element in donnee:
            trouve = _cherche_jwt(element, profondeur + 1)
            if trouve:
                return trouve
    return None
