"""Rugby : Mon Club House, le site competitions de la FFR.

https://monclubhouse.ffr.fr/competitions/<slug>/calendrier-resultats

Contrairement a la FFF et a la FFBB, la FFR ne documente aucune API
publique et n'en expose pas d'evidente. On lit donc les donnees JSON que
le front React embarque dans la page (__NEXT_DATA__, flux Next.js,
Nuxt ou JSON-LD), ce qui reste bien plus stable que de gratter le HTML.

Si la FFR change de structure, `resultats47 diagnostiquer --source ffr`
affiche ce qui a ete trouve dans la page, et la cle `ids.api_url` d'une
competition permet de pointer directement un endpoint JSON.
"""

from __future__ import annotations

import json
import logging
import re
from typing import Any, Iterator

from resultats47.config import Competition
from resultats47.matching import Reconnaisseur
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

BASE = "https://monclubhouse.ffr.fr"
GABARIT_PAGE = "{base}/competitions/{slug}/calendrier-resultats"

# Slugs par defaut des competitions nationales amateurs.
SLUGS = {
    "nationale": "nationale",
    "nationale 2": "nationale-2",
    "federale 1": "federale-1",
    "federale 2": "federale-2",
    "federale 3": "federale-3",
}

CLES_DOMICILE = ("home", "domicile", "equipe1", "equipeDomicile", "team1", "localTeam", "receveur")
CLES_EXTERIEUR = ("away", "exterieur", "equipe2", "equipeExterieur", "team2", "visitorTeam", "visiteur")
CLES_SCORE_DOM = (
    "homeScore", "home_score", "scoreDomicile", "score1", "pointsEquipe1",
    "scoreEquipe1", "resultatEquipe1",
)
CLES_SCORE_EXT = (
    "awayScore", "away_score", "scoreExterieur", "score2", "pointsEquipe2",
    "scoreEquipe2", "resultatEquipe2",
)

_NEXT_DATA = re.compile(
    r'<script[^>]+id="__NEXT_DATA__"[^>]*>(.*?)</script>', re.DOTALL
)
_NUXT = re.compile(r"window\.__NUXT__\s*=\s*(\{.*?\});?\s*</script>", re.DOTALL)
_JSON_LD = re.compile(
    r'<script[^>]+type="application/ld\+json"[^>]*>(.*?)</script>', re.DOTALL
)
_FLUX_NEXT = re.compile(r'self\.__next_f\.push\(\[\d+,\s*"(.*?)"\]\)', re.DOTALL)


class SourceFFR(Source):
    nom = "ffr"
    sport = "rugby"

    def __init__(self, seuil: float = 0.86, verbeux: bool = False, base: str = BASE):
        super().__init__(seuil, verbeux)
        self.base = base.rstrip("/")
        self.session = Session(entetes={"Accept": "text/html,application/json"})

    # -- recuperation ----------------------------------------------------
    def url_competition(self, competition: Competition) -> str:
        api = competition.ids.get("api_url")
        if api:
            return str(api)
        slug = competition.slug or SLUGS.get(competition.libelle.strip().lower())
        if not slug:
            raise ErreurSource(
                f"Aucun slug connu pour '{competition.libelle}'. "
                f"Ajoutez 'slug:' (ex. federale-3) a cette competition dans config.yaml."
            )
        return GABARIT_PAGE.format(base=self.base, slug=slug)

    def charger(self, competition: Competition) -> list[dict]:
        """Ramene les objets 'rencontre' trouves pour une competition."""
        url = self.url_competition(competition)
        reponse = self.session.get(url)
        type_contenu = reponse.headers.get("Content-Type", "")
        if "json" in type_contenu:
            return list(_objets_de_match(reponse.json()))
        return list(self.extraire(reponse.text))

    def extraire(self, html: str) -> Iterator[dict]:
        """Objets de match trouves dans les JSON embarques d'une page."""
        vus: set[int] = set()
        for donnees in _json_embarques(html):
            for objet in _objets_de_match(donnees):
                if id(objet) not in vus:
                    vus.add(id(objet))
                    yield objet

    def inventaire(self, html: str) -> dict[str, int]:
        """Diagnostic : ce que la page contient comme JSON exploitable."""
        return {
            "__NEXT_DATA__": len(_NEXT_DATA.findall(html)),
            "flux_next": len(_FLUX_NEXT.findall(html)),
            "__NUXT__": len(_NUXT.findall(html)),
            "json_ld": len(_JSON_LD.findall(html)),
            "objets_de_match": len(list(self.extraire(html))),
        }

    # -- normalisation ---------------------------------------------------
    def _vers_match(self, brut: dict, competition: Competition, club_suivi: str) -> Match:
        score_dom = en_entier(premier(brut, *CLES_SCORE_DOM))
        score_ext = en_entier(premier(brut, *CLES_SCORE_EXT))
        if score_dom is None:
            score_dom = en_entier(premier(brut, *(f"{c}.score" for c in CLES_DOMICILE)))
        if score_ext is None:
            score_ext = en_entier(premier(brut, *(f"{c}.score" for c in CLES_EXTERIEUR)))
        return Match(
            sport="rugby",
            competition=competition.libelle,
            equipe_domicile=nom_equipe(brut, *CLES_DOMICILE),
            equipe_exterieur=nom_equipe(brut, *CLES_EXTERIEUR),
            club_suivi=club_suivi,
            poule=str(premier(brut, "poule", "pool.name", "poule.nom", "groupe") or "")
            or competition.poule,
            journee=str(premier(brut, "journee", "round", "day", "numeroJournee") or "") or None,
            date=en_datetime(
                premier(brut, "date", "dateRencontre", "kickOff", "startDate", "datetime"),
                premier(brut, "heure", "time"),
            ),
            score_domicile=score_dom,
            score_exterieur=score_ext,
            statut=statut_depuis(
                score_dom,
                score_ext,
                (premier(brut, "statut", "status", "state"), premier(brut, "date", "startDate")),
            ),
            source=self.nom,
            url=premier(brut, "url", "link"),
            brut=brut,
        )

    # -- collecte --------------------------------------------------------
    def collecter(self, competitions: list[Competition], fenetre: Fenetre) -> RapportSource:
        rapport = self._rapport()
        for competition in competitions:
            try:
                bruts = self.charger(competition)
            except ErreurSource as exc:
                rapport.erreurs.append(f"{competition.titre} : {exc}")
                continue
            if not bruts:
                rapport.erreurs.append(
                    f"{competition.titre} : aucune rencontre lisible sur "
                    f"{self.url_competition(competition)}. La structure de la page a "
                    f"probablement change — lancez `resultats47 diagnostiquer --source ffr`."
                )
                continue

            candidats = [self._vers_match(b, competition, "") for b in bruts]
            dans_fenetre = [m for m in candidats if fenetre.contient(m.date)]
            for club in competition.clubs:
                reconnaisseur = Reconnaisseur(club.tous_alias, sport="rugby", seuil=self.seuil)
                garde = 0
                for match in dans_fenetre:
                    if not reconnaisseur.reconnait_match(match.equipe_domicile, match.equipe_exterieur):
                        continue
                    match.club_suivi = club.libelle
                    rapport.matchs.append(match)
                    garde += 1
                if garde == 0:
                    rapport.equipes_sans_match.append(f"{competition.titre} / {club.libelle}")
        return rapport


# -- extraction generique ------------------------------------------------
def _json_embarques(html: str) -> Iterator[Any]:
    """Tous les blocs JSON exploitables d'une page React/Next/Nuxt."""
    for motif in (_NEXT_DATA, _NUXT, _JSON_LD):
        for bloc in motif.findall(html):
            try:
                yield json.loads(bloc.strip())
            except (ValueError, TypeError):
                continue
    # Next.js App Router : le payload arrive par morceaux echappes.
    morceaux = _FLUX_NEXT.findall(html)
    if morceaux:
        try:
            assemble = "".join(json.loads(f'"{m}"') for m in morceaux)
        except ValueError:
            assemble = ""
        for objet in _json_dans_texte(assemble):
            yield objet


def _json_dans_texte(texte: str) -> Iterator[Any]:
    """Repere des objets JSON equilibres dans un texte quelconque."""
    decodeur = json.JSONDecoder()
    position = 0
    trouves = 0
    while position < len(texte) and trouves < 200:
        debut = texte.find("{", position)
        if debut == -1:
            return
        try:
            objet, fin = decodeur.raw_decode(texte, debut)
        except ValueError:
            position = debut + 1
            continue
        trouves += 1
        position = fin
        yield objet


def _objets_de_match(donnee: Any, profondeur: int = 0) -> Iterator[dict]:
    """Parcourt une structure JSON et rend les objets qui ressemblent a un match."""
    if profondeur > 12:
        return
    if isinstance(donnee, dict):
        if _ressemble_a_un_match(donnee):
            yield donnee
        for valeur in donnee.values():
            yield from _objets_de_match(valeur, profondeur + 1)
    elif isinstance(donnee, list):
        for element in donnee:
            yield from _objets_de_match(element, profondeur + 1)


def _ressemble_a_un_match(donnee: dict) -> bool:
    """Un objet avec une equipe a domicile ET une equipe a l'exterieur."""
    a_domicile = any(donnee.get(c) not in (None, "", [], {}) for c in CLES_DOMICILE)
    a_exterieur = any(donnee.get(c) not in (None, "", [], {}) for c in CLES_EXTERIEUR)
    if not (a_domicile and a_exterieur):
        return False
    return bool(nom_equipe(donnee, *CLES_DOMICILE) and nom_equipe(donnee, *CLES_EXTERIEUR))
