"""Chargement et validation de config.yaml."""

from __future__ import annotations

from dataclasses import dataclass, field
from pathlib import Path
from typing import Any

import yaml

SPORTS_CONNUS = {"rugby", "football", "basket"}
SOURCES_PAR_SPORT = {"rugby": "ffr", "football": "fff", "basket": "ffbb"}


class ConfigError(ValueError):
    """Configuration invalide."""


@dataclass
class Club:
    """Un club suivi dans une competition."""

    libelle: str                       # nom a l'antenne, ex. "Marmande"
    alias: list[str] = field(default_factory=list)
    ids: dict[str, Any] = field(default_factory=dict)  # identifiants federaux epingles

    @property
    def tous_alias(self) -> list[str]:
        return [self.libelle, *self.alias]


@dataclass
class Competition:
    """Une competition suivie (Nationale 2, Fedérale 3 poule 11, NM3...)."""

    sport: str
    libelle: str
    clubs: list[Club]
    poule: str | None = None
    slug: str | None = None            # FFR : segment d'URL monclubhouse
    ids: dict[str, Any] = field(default_factory=dict)  # cp_no FFF, id poule FFBB...
    actif: bool = True

    @property
    def source(self) -> str:
        return SOURCES_PAR_SPORT[self.sport]

    @property
    def titre(self) -> str:
        return f"{self.libelle} - poule {self.poule}" if self.poule else self.libelle


@dataclass
class Config:
    competitions: list[Competition]
    seuil_correspondance: float = 0.86
    fuseau: str = "Europe/Paris"

    def par_source(self, source: str) -> list[Competition]:
        return [c for c in self.competitions if c.actif and c.source == source]

    @property
    def sources_actives(self) -> list[str]:
        vues: list[str] = []
        for c in self.competitions:
            if c.actif and c.source not in vues:
                vues.append(c.source)
        return vues


def _club_depuis(donnee: Any, ou: str) -> Club:
    if isinstance(donnee, str):
        return Club(libelle=donnee)
    if not isinstance(donnee, dict):
        raise ConfigError(f"{ou} : un club doit etre un texte ou un dictionnaire")
    libelle = donnee.get("nom") or donnee.get("libelle")
    if not libelle:
        raise ConfigError(f"{ou} : club sans cle 'nom'")
    alias = donnee.get("alias") or []
    if isinstance(alias, str):
        alias = [alias]
    if not isinstance(alias, list):
        raise ConfigError(f"{ou} / {libelle} : 'alias' doit etre une liste")
    ids = donnee.get("ids") or {}
    if not isinstance(ids, dict):
        raise ConfigError(f"{ou} / {libelle} : 'ids' doit etre un dictionnaire")
    return Club(libelle=str(libelle), alias=[str(a) for a in alias], ids=ids)


def charger(chemin: str | Path) -> Config:
    chemin = Path(chemin)
    if not chemin.exists():
        raise ConfigError(f"Fichier de configuration introuvable : {chemin}")
    brut = yaml.safe_load(chemin.read_text(encoding="utf-8")) or {}
    if not isinstance(brut, dict):
        raise ConfigError(f"{chemin} : le document doit etre un dictionnaire YAML")

    entrees = brut.get("competitions")
    if not isinstance(entrees, list) or not entrees:
        raise ConfigError(f"{chemin} : cle 'competitions' absente ou vide")

    competitions: list[Competition] = []
    for i, entree in enumerate(entrees):
        ou = f"competitions[{i}]"
        if not isinstance(entree, dict):
            raise ConfigError(f"{ou} : doit etre un dictionnaire")
        sport = str(entree.get("sport", "")).strip().lower()
        if sport not in SPORTS_CONNUS:
            raise ConfigError(
                f"{ou} : sport '{sport}' inconnu (attendu : {', '.join(sorted(SPORTS_CONNUS))})"
            )
        libelle = entree.get("libelle") or entree.get("nom")
        if not libelle:
            raise ConfigError(f"{ou} : cle 'libelle' absente")
        clubs_bruts = entree.get("clubs") or []
        if not isinstance(clubs_bruts, list) or not clubs_bruts:
            raise ConfigError(f"{ou} ({libelle}) : aucun club suivi")
        poule = entree.get("poule")
        competitions.append(
            Competition(
                sport=sport,
                libelle=str(libelle),
                clubs=[_club_depuis(c, f"{ou} ({libelle})") for c in clubs_bruts],
                poule=str(poule) if poule is not None else None,
                slug=entree.get("slug"),
                ids=entree.get("ids") or {},
                actif=bool(entree.get("actif", True)),
            )
        )

    options = brut.get("options") or {}
    return Config(
        competitions=competitions,
        seuil_correspondance=float(options.get("seuil_correspondance", 0.86)),
        fuseau=str(options.get("fuseau", "Europe/Paris")),
    )
