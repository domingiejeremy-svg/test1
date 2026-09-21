"""Football : API DOFA de la Federation Francaise de Football.

Base : https://api-dofa.prd-aws.fff.fr  (API Platform / JSON-LD, sans cle)
C'est le backend des sites de ligues et districts, dont epreuves.fff.fr.

Chemin le plus court pour nos clubs : resoudre le club par son nom
(/api/clubs?clNom=...), puis demander ses matchs sur la fenetre voulue
(/api/match_entities?clNo=...&ma_dat[after]=...&ma_dat[before]=...).
"""

from __future__ import annotations

import logging

from resultats47.config import Club, Competition
from resultats47.matching import Reconnaisseur, score_similarite
from resultats47.models import Match, RapportSource
from resultats47.reseau import ErreurSource, Session
from resultats47.sources.base import (
    Source,
    en_datetime,
    en_entier,
    nom_equipe,
    premier,
    statut_depuis,
)
from resultats47.weekend import Fenetre

logger = logging.getLogger(__name__)

BASE = "https://api-dofa.prd-aws.fff.fr"
MAX_PAGES = 10


class SourceFFF(Source):
    nom = "fff"
    sport = "football"

    def __init__(self, seuil: float = 0.86, verbeux: bool = False, base: str = BASE):
        super().__init__(seuil, verbeux)
        self.base = base.rstrip("/")
        self.session = Session(entetes={"Accept": "application/ld+json, application/json"})
        self._cache_clubs: dict[str, int | None] = {}

    # -- API ------------------------------------------------------------
    def _membres(self, url: str, params: dict) -> list[dict]:
        """Parcourt une collection hydra paginee."""
        tout: list[dict] = []
        for page in range(1, MAX_PAGES + 1):
            donnees = self.session.json(url, params={**params, "page": page})
            if isinstance(donnees, list):
                tout.extend(d for d in donnees if isinstance(d, dict))
                break
            membres = donnees.get("hydra:member") or donnees.get("member") or []
            tout.extend(m for m in membres if isinstance(m, dict))
            suivante = premier(donnees, "hydra:view.hydra:next", "view.next")
            if not suivante or not membres:
                break
        return tout

    def resoudre_club(self, club: Club) -> int | None:
        """Trouve le cl_no d'un club, epingle dans la config ou cherche par nom."""
        epingle = en_entier(club.ids.get("cl_no"))
        if epingle is not None:
            return epingle
        panne: ErreurSource | None = None
        interrogation_reussie = False
        for alias in club.tous_alias:
            if alias in self._cache_clubs:
                if self._cache_clubs[alias] is not None:
                    return self._cache_clubs[alias]
                interrogation_reussie = True
                continue
            try:
                trouves = self._membres(f"{self.base}/api/clubs", {"clNom": alias})
            except ErreurSource as exc:
                logger.debug("FFF recherche club '%s' : %s", alias, exc)
                panne = exc
                continue
            interrogation_reussie = True
            meilleur, meilleur_score = None, 0.0
            for candidat in trouves:
                libelle = str(premier(candidat, "cl_nom", "nom", "name") or "")
                score = max(score_similarite(a, libelle) for a in club.tous_alias)
                if score > meilleur_score:
                    meilleur, meilleur_score = candidat, score
            if meilleur is not None and meilleur_score >= self.seuil:
                cl_no = en_entier(premier(meilleur, "cl_no", "clNo", "id"))
                self._cache_clubs[alias] = cl_no
                if cl_no is not None:
                    return cl_no
            else:
                self._cache_clubs[alias] = None
        if panne is not None and not interrogation_reussie:
            # Aucune requete n'a abouti : c'est un probleme d'acces, pas un
            # club absent du referentiel. Le distinguer evite de faire courir
            # l'utilisateur apres un mauvais alias.
            raise panne
        return None

    def matchs_du_club(self, cl_no: int, fenetre: Fenetre) -> list[dict]:
        debut, fin = fenetre.iso()
        return self._membres(
            f"{self.base}/api/match_entities",
            {"clNo": cl_no, "ma_dat[after]": debut, "ma_dat[before]": fin},
        )

    # -- normalisation ---------------------------------------------------
    def _vers_match(self, brut: dict, competition: Competition, club: Club) -> Match:
        domicile = nom_equipe(brut, "home", "home_team", "equipe_domicile", "eq_dom")
        exterieur = nom_equipe(brut, "away", "away_team", "equipe_exterieur", "eq_ext")
        score_dom = en_entier(premier(brut, "home_score", "ma_sco_dom", "score_domicile"))
        score_ext = en_entier(premier(brut, "away_score", "ma_sco_ext", "score_exterieur"))
        libelle_compet = str(
            premier(brut, "competition.name", "competition.cp_nom", "competition.libelle") or ""
        )
        return Match(
            sport="football",
            competition=competition.libelle,
            equipe_domicile=domicile,
            equipe_exterieur=exterieur,
            club_suivi=club.libelle,
            poule=str(premier(brut, "poule.name", "poule.po_nom", "poule.nom") or "") or competition.poule,
            journee=str(premier(brut, "poule_journee.pj_no", "ma_jo", "journee") or "") or None,
            date=en_datetime(premier(brut, "ma_dat", "date"), premier(brut, "ma_heure", "heure")),
            score_domicile=score_dom,
            score_exterieur=score_ext,
            statut=statut_depuis(
                score_dom,
                score_ext,
                (premier(brut, "ma_etat", "etat", "status"), premier(brut, "ma_dat", "date"), libelle_compet),
            ),
            source=self.nom,
            url=premier(brut, "url", "@id"),
            brut=brut,
        )

    def _bonne_competition(self, brut: dict, competition: Competition) -> bool:
        """Un club joue dans plusieurs competitions : on garde la bonne."""
        cp_no = en_entier(competition.ids.get("cp_no"))
        if cp_no is not None:
            return en_entier(premier(brut, "competition.cp_no", "competition.id")) == cp_no
        libelle = str(premier(brut, "competition.name", "competition.cp_nom") or "")
        if not libelle:
            return True  # on prefere un match de trop qu'un match manquant
        return score_similarite(competition.libelle, libelle) >= 0.7

    # -- collecte --------------------------------------------------------
    def collecter(self, competitions: list[Competition], fenetre: Fenetre) -> RapportSource:
        rapport = self._rapport()
        for competition in competitions:
            for club in competition.clubs:
                etiquette = f"{competition.titre} / {club.libelle}"
                try:
                    cl_no = self.resoudre_club(club)
                except ErreurSource as exc:
                    rapport.erreurs.append(f"{etiquette} : {exc}")
                    continue
                if cl_no is None:
                    rapport.erreurs.append(
                        f"{etiquette} : club introuvable cote FFF. "
                        f"Epinglez son identifiant (ids.cl_no) dans config.yaml "
                        f"— voir `resultats47 chercher --sport football \"{club.libelle}\"`."
                    )
                    continue
                try:
                    bruts = self.matchs_du_club(cl_no, fenetre)
                except ErreurSource as exc:
                    rapport.erreurs.append(f"{etiquette} : {exc}")
                    continue

                reconnaisseur = Reconnaisseur(club.tous_alias, sport="football", seuil=self.seuil)
                garde = 0
                for brut in bruts:
                    if not self._bonne_competition(brut, competition):
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
