"""Mise en forme du bulletin du week-end."""

from __future__ import annotations

from dataclasses import dataclass, field
from datetime import datetime

from resultats47.config import Config
from resultats47.matching import Reconnaisseur
from resultats47.models import FORFAIT, JOUE, REPORTE, Match, RapportSource
from resultats47.weekend import Fenetre

EMOJI_SPORT = {"rugby": "\U0001f3c9", "football": "⚽", "basket": "\U0001f3c0"}
EMOJI_ISSUE = {"V": "✅", "D": "❌", "N": "➰"}
JOURS = ("lundi", "mardi", "mercredi", "jeudi", "vendredi", "samedi", "dimanche")
ORDRE_SPORTS = ("rugby", "football", "basket")


@dataclass
class Bulletin:
    """Tout ce qu'il faut pour rendre le bulletin."""

    fenetre: Fenetre
    config: Config
    rapports: list[RapportSource] = field(default_factory=list)

    @property
    def matchs(self) -> list[Match]:
        """Matchs dedoublonnes (deux clubs suivis peuvent s'affronter)."""
        vus: dict[tuple, Match] = {}
        for rapport in self.rapports:
            for match in rapport.matchs:
                existant = vus.get(match.cle)
                if existant is None:
                    vus[match.cle] = match
                elif existant.club_suivi != match.club_suivi:
                    existant.club_suivi = f"{existant.club_suivi} / {match.club_suivi}"
        return list(vus.values())

    @property
    def erreurs(self) -> list[str]:
        """Erreurs dedoublonnees : une panne de source ne s'affiche qu'une fois."""
        vues: list[str] = []
        for rapport in self.rapports:
            for erreur in rapport.erreurs:
                ligne = f"[{rapport.source}] {erreur}"
                if ligne not in vues:
                    vues.append(ligne)
        return vues

    @property
    def sans_match(self) -> list[str]:
        return [e for r in self.rapports for e in r.equipes_sans_match]

    @property
    def joues(self) -> int:
        return sum(1 for m in self.matchs if m.est_joue)


def _cle_tri(config: Config):
    ordre_compet = {c.libelle: i for i, c in enumerate(config.competitions)}

    def cle(match: Match):
        return (
            ORDRE_SPORTS.index(match.sport) if match.sport in ORDRE_SPORTS else 9,
            ordre_compet.get(match.competition, 99),
            match.date or datetime.max,
            match.equipe_domicile,
        )

    return cle


def _quand(match: Match) -> str:
    if not match.date:
        return ""
    jour = JOURS[match.date.weekday()]
    heure = f" {match.date:%H}h{match.date:%M}" if (match.date.hour or match.date.minute) else ""
    return f"{jour} {match.date:%d/%m}{heure}"


def _cotes_suivies(match: Match, config: Config) -> list[tuple[str, bool]]:
    """Clubs suivis impliques : (nom a l'antenne, joue a domicile)."""
    cotes: list[tuple[str, bool]] = []
    for competition in config.competitions:
        if competition.libelle != match.competition or competition.sport != match.sport:
            continue
        for club in competition.clubs:
            reconnaisseur = Reconnaisseur(
                club.tous_alias, sport=match.sport, seuil=config.seuil_correspondance
            )
            if reconnaisseur(match.equipe_domicile):
                cotes.append((club.libelle, True))
            elif reconnaisseur(match.equipe_exterieur):
                cotes.append((club.libelle, False))
    return cotes


def _ligne(match: Match, config: Config) -> str:
    """Une ligne de resultat, nommant toujours le club qui gagne ou qui perd."""
    cotes = _cotes_suivies(match, config)
    corps = f"{match.equipe_domicile} {match.score} {match.equipe_exterieur}"
    details: list[str] = []
    marque = ""

    if len({a_domicile for _, a_domicile in cotes}) == 2:
        # Deux clubs suivis s'affrontent : pas de "victoire" sans sujet.
        noms = " / ".join(nom for nom, _ in cotes)
        details.append(f"duel entre clubs suivis ({noms})")
    elif cotes:
        nom, a_domicile = cotes[0]
        issue = match.issue(a_domicile)
        marque = EMOJI_ISSUE.get(issue, "")
        if issue == "V":
            details.append(f"victoire de {nom} {'a domicile' if a_domicile else 'a l exterieur'}")
        elif issue == "D":
            details.append(f"defaite de {nom} {'a domicile' if a_domicile else 'a l exterieur'}")
        elif issue == "N":
            details.append(f"match nul pour {nom}")

    if not any(d.startswith(("victoire", "defaite", "match nul")) for d in details):
        if match.statut == REPORTE:
            details.append("rencontre reportee")
        elif match.statut == FORFAIT:
            details.append("forfait")
        elif not match.est_joue:
            details.append("score non publie")

    if match.journee:
        details.append(f"J{match.journee}")
    quand = _quand(match)
    if quand:
        details.append(quand)

    suffixe = f" *({' — '.join(details)})*" if details else ""
    prefixe = f"{marque} " if marque else ""
    return f"- {prefixe}**{corps}**{suffixe}"


def en_markdown(bulletin: Bulletin) -> str:
    config = bulletin.config
    matchs = sorted(bulletin.matchs, key=_cle_tri(config))

    lignes = [
        f"# Resultats sportifs — {bulletin.fenetre.libelle}",
        "",
        f"*{len(matchs)} rencontre(s) suivie(s), dont {bulletin.joues} avec un score publie.*",
        "",
    ]

    sport_courant = competition_courante = None
    for match in matchs:
        if match.sport != sport_courant:
            sport_courant, competition_courante = match.sport, None
            emoji = EMOJI_SPORT.get(match.sport, "")
            lignes += ["", f"## {emoji} {match.sport.capitalize()}", ""]
        titre = f"{match.competition} — poule {match.poule}" if match.poule else match.competition
        if titre != competition_courante:
            if competition_courante is not None:
                lignes.append("")
            competition_courante = titre
            lignes += [f"### {titre}", ""]
        lignes.append(_ligne(match, config))

    if bulletin.sans_match:
        lignes += ["", "## Sans rencontre trouvee", ""]
        lignes += [f"- {e}" for e in sorted(set(bulletin.sans_match))]
        lignes += [
            "",
            "*Exempt, match non programme, ou libelle du club a ajuster dans `config.yaml`.*",
        ]

    if bulletin.erreurs:
        lignes += ["", "## Sources en echec", ""]
        lignes += [f"- {e}" for e in bulletin.erreurs]

    lignes += [
        "",
        "---",
        "",
        "*Bulletin genere automatiquement. Les scores restent a verifier "
        "avant diffusion : les federations publient parfois tardivement.*",
    ]
    return "\n".join(lignes).strip() + "\n"


def en_texte(bulletin: Bulletin) -> str:
    """Version console, sans balisage markdown."""
    brut = en_markdown(bulletin)
    sortie = []
    for ligne in brut.splitlines():
        ligne = ligne.replace("**", "").replace("*", "")
        if ligne.startswith("### "):
            ligne = f"  {ligne[4:]}"
        elif ligne.startswith("## "):
            ligne = f"\n{ligne[3:].upper()}"
        elif ligne.startswith("# "):
            ligne = ligne[2:].upper()
        sortie.append(ligne)
    return "\n".join(sortie)
