"""Reconnaissance des clubs suivis dans les libelles des federations.

Les federations ecrivent "US MARMANDE 1", "MARMANDE US", "U.S. MARMANDAISE"...
On normalise puis on compare sur les mots significatifs.
"""

from __future__ import annotations

import re
import unicodedata
from difflib import SequenceMatcher

# Mots qui n'identifient pas un club : on les retire avant comparaison.
BRUIT = {
    "us", "usa", "as", "asa", "rc", "sc", "fc", "ac", "cs", "ca", "sa", "ua", "so",
    "ol", "es", "el", "su", "sua", "ct", "uas", "asc", "usc", "rcs",
    "club", "sportif", "sportive", "sport", "sports", "union", "entente", "stade",
    "athletic", "athletique", "olympique", "racing", "association", "amicale",
    "avenir", "etoile", "jeunesse", "elan", "foyer", "cercle", "groupe", "groupement",
    "rugby", "football", "foot", "basket", "basketball", "ball", "xv", "quinze",
    "seniors", "senior", "equipe", "masculin", "masculine", "feminin", "feminine",
    "a", "b", "1", "2", "3", "de", "du", "des", "la", "le", "les", "et", "en", "sur",
}

_SEPARATEURS = re.compile(r"[^a-z0-9]+")

# Mots de sport non ambigus. Ils servent d'arbitre quand deux clubs d'une meme
# ville existent dans plusieurs disciplines (SU Agen rugby / Agen Basket Club).
# Les abreviations (fc, bc, ac...) sont volontairement exclues : trop ambigues.
MOTS_SPORT = {
    "rugby": {"rugby", "ovalie"},
    "football": {"football", "foot"},
    "basket": {"basket", "basketball"},
}


def normaliser(texte: str) -> str:
    """Minuscule, sans accent, sans ponctuation."""
    if not texte:
        return ""
    sans_accent = unicodedata.normalize("NFKD", texte)
    sans_accent = "".join(c for c in sans_accent if not unicodedata.combining(c))
    return _SEPARATEURS.sub(" ", sans_accent.lower()).strip()


def mots_cles(texte: str) -> set[str]:
    """Mots significatifs d'un libelle de club."""
    mots = {m for m in normaliser(texte).split() if m and m not in BRUIT}
    return mots or set(normaliser(texte).split())


def conflit_de_sport(libelle: str, sport: str | None) -> bool:
    """True si le libelle annonce explicitement une autre discipline."""
    if not sport:
        return False
    mots = set(normaliser(libelle).split())
    for discipline, marqueurs in MOTS_SPORT.items():
        if discipline != sport and mots & marqueurs:
            return True
    return False


def score_similarite(alias: str, libelle: str) -> float:
    """Score 0..1 entre un alias de la config et un libelle de federation."""
    a_norm, l_norm = normaliser(alias), normaliser(libelle)
    if not a_norm or not l_norm:
        return 0.0
    if a_norm == l_norm:
        return 1.0

    a_mots, l_mots = mots_cles(alias), mots_cles(libelle)
    if a_mots and a_mots <= l_mots:
        # Tous les mots significatifs de l'alias sont dans le libelle.
        return 0.97
    if l_mots and l_mots < a_mots:
        # Le libelle est plus pauvre que l'alias : "US LAYRAC" n'est pas
        # "Layrac-Astaffort". On refuse, quitte a ce que l'utilisateur ajoute
        # l'alias court explicitement dans sa config.
        return 0.78

    communs = a_mots & l_mots
    if communs:
        # Un mot rare partage ("casteljaloux") vaut mieux qu'un mot court.
        poids = max(len(m) for m in communs)
        if poids >= 6:
            return 0.9
        if poids >= 4:
            return 0.8

    return SequenceMatcher(None, a_norm, l_norm).ratio()


class Reconnaisseur:
    """Teste si un libelle de federation designe un club suivi donne."""

    def __init__(self, alias: list[str], sport: str | None = None, seuil: float = 0.86):
        self.alias = [a for a in alias if a and a.strip()]
        self.sport = sport
        self.seuil = seuil

    def score(self, libelle: str) -> float:
        if conflit_de_sport(libelle, self.sport):
            return 0.0
        return max((score_similarite(a, libelle) for a in self.alias), default=0.0)

    def __call__(self, libelle: str) -> bool:
        return self.score(libelle) >= self.seuil

    def reconnait_match(self, domicile: str, exterieur: str) -> bool:
        return self(domicile) or self(exterieur)
