"""Socle commun aux connecteurs federaux.

Les API federales ne sont pas documentees publiquement et leurs champs
changent sans preavis. Les connecteurs lisent donc les enregistrements de
facon tolerante : on essaie plusieurs noms de champs plausibles et on
signale clairement ce qu'on n'a pas su lire, plutot que de planter ou,
pire, d'inventer un score.
"""

from __future__ import annotations

import re
from abc import ABC, abstractmethod
from datetime import date, datetime
from typing import Any, Iterable

from resultats47.config import Competition
from resultats47.models import A_VENIR, INCONNU, JOUE, Match, RapportSource
from resultats47.weekend import Fenetre

_FORMATS_DATE = (
    "%Y-%m-%dT%H:%M:%S%z",
    "%Y-%m-%dT%H:%M:%S.%f%z",
    "%Y-%m-%dT%H:%M:%S",
    "%Y-%m-%d %H:%M:%S",
    "%Y-%m-%d %H:%M",
    "%Y-%m-%d",
    "%d/%m/%Y %H:%M",
    "%d/%m/%Y",
)


def chemin(donnee: Any, parcours: str) -> Any:
    """Lit 'a.b.c' dans des dictionnaires imbriques, None si absent."""
    courant = donnee
    for cle in parcours.split("."):
        if isinstance(courant, dict):
            courant = courant.get(cle)
        else:
            return None
        if courant is None:
            return None
    return courant


def premier(donnee: Any, *parcours: str) -> Any:
    """Premiere valeur non vide parmi plusieurs chemins candidats."""
    for p in parcours:
        valeur = chemin(donnee, p)
        if valeur not in (None, "", [], {}):
            return valeur
    return None


def en_entier(valeur: Any) -> int | None:
    """Entier tolerant : None, '', '-' et les textes non numeriques -> None."""
    if valeur is None or isinstance(valeur, bool):
        return None
    if isinstance(valeur, int):
        return valeur
    if isinstance(valeur, float):
        return int(valeur)
    texte = str(valeur).strip()
    if not texte:
        return None
    trouve = re.fullmatch(r"-?\d+", texte)
    return int(trouve.group()) if trouve else None


def en_datetime(valeur: Any, heure: Any = None) -> datetime | None:
    """Parse les formats de date rencontres chez les federations."""
    if valeur is None:
        return None
    if isinstance(valeur, datetime):
        return valeur.replace(tzinfo=None)
    if isinstance(valeur, date):
        base = datetime.combine(valeur, datetime.min.time())
        return _ajoute_heure(base, heure)

    texte = str(valeur).strip()
    if not texte:
        return None
    if texte.endswith("Z"):
        texte = texte[:-1] + "+0000"
    texte = re.sub(r"([+-]\d{2}):(\d{2})$", r"\1\2", texte)

    for fmt in _FORMATS_DATE:
        try:
            resultat = datetime.strptime(texte, fmt).replace(tzinfo=None)
        except ValueError:
            continue
        return _ajoute_heure(resultat, heure) if fmt == "%Y-%m-%d" or fmt == "%d/%m/%Y" else resultat
    try:
        return datetime.fromisoformat(texte).replace(tzinfo=None)
    except ValueError:
        return None


def _ajoute_heure(base: datetime, heure: Any) -> datetime:
    if not heure:
        return base
    trouve = re.match(r"(\d{1,2})[h:](\d{2})", str(heure).strip())
    if not trouve:
        return base
    return base.replace(hour=int(trouve.group(1)) % 24, minute=int(trouve.group(2)) % 60)


def nom_equipe(donnee: Any, *parcours: str) -> str:
    """Extrait un libelle d'equipe, que la source donne un texte ou un objet."""
    valeur = premier(donnee, *parcours)
    if valeur is None:
        return ""
    if isinstance(valeur, str):
        return valeur.strip()
    if isinstance(valeur, dict):
        pour_objet = premier(
            valeur,
            "short_name", "shortName", "name", "nom", "libelle",
            "club.nom", "club.cl_nom", "cl_nom", "organisme.nom", "nomEquipe",
        )
        return str(pour_objet).strip() if pour_objet else ""
    return str(valeur).strip()


def statut_depuis(score_dom: int | None, score_ext: int | None, indices: Iterable[Any] = ()) -> str:
    """Deduit le statut d'une rencontre."""
    texte = " ".join(str(i) for i in indices if i not in (None, "")).lower()
    if any(mot in texte for mot in ("report", "remise", "annul")):
        return "reporte"
    if "forfait" in texte:
        return "forfait"
    if score_dom is not None and score_ext is not None:
        return JOUE
    if texte:
        return A_VENIR
    return INCONNU


class Source(ABC):
    """Contrat commun a tous les connecteurs federaux."""

    nom: str = "source"
    sport: str = ""

    def __init__(self, seuil: float = 0.86, verbeux: bool = False):
        self.seuil = seuil
        self.verbeux = verbeux

    @abstractmethod
    def collecter(self, competitions: list[Competition], fenetre: Fenetre) -> RapportSource:
        """Ramene les matchs des clubs suivis sur la fenetre donnee."""

    def _rapport(self) -> RapportSource:
        return RapportSource(source=self.nom)
