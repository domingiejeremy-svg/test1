"""Interface en ligne de commande."""

from __future__ import annotations

import argparse
import dataclasses
import json
import logging
import sys
from datetime import date, datetime
from pathlib import Path

from resultats47 import __version__
from resultats47.config import SOURCES_PAR_SPORT, Config, ConfigError, charger
from resultats47.matching import mots_cles
from resultats47.models import Match
from resultats47.report import Bulletin, en_markdown, en_texte
from resultats47.reseau import ErreurSource
from resultats47.sources import SOURCES
from resultats47.weekend import Fenetre, fenetre_personnalisee, weekend_de

CONFIG_PAR_DEFAUT = Path(__file__).resolve().parent.parent / "config.yaml"


def _jour(texte: str) -> date:
    try:
        return datetime.strptime(texte, "%Y-%m-%d").date()
    except ValueError as exc:
        raise argparse.ArgumentTypeError(f"date attendue au format AAAA-MM-JJ, recu '{texte}'") from exc


def construire_parseur() -> argparse.ArgumentParser:
    # Les options communes sont montees sur chaque sous-commande, pour qu'elles
    # fonctionnent aussi bien avant qu'apres le nom de la commande.
    # default=SUPPRESS : sans cela, la sous-commande ecraserait avec sa propre
    # valeur par defaut une option donnee avant elle ("resultats47 -v bulletin").
    communes = argparse.ArgumentParser(add_help=False)
    communes.add_argument("-c", "--config", type=Path, default=argparse.SUPPRESS,
                          help=f"fichier de configuration des clubs (defaut : {CONFIG_PAR_DEFAUT})")
    communes.add_argument("-v", "--verbeux", action="store_true", default=argparse.SUPPRESS,
                          help="journal detaille")

    parseur = argparse.ArgumentParser(
        prog="resultats47",
        parents=[communes],
        description="Compile les resultats du week-end des clubs de Lot-et-Garonne.",
        epilog="Sans sous-commande, 'bulletin' est sous-entendu.",
    )
    parseur.add_argument("--version", action="version", version=f"resultats47 {__version__}")

    sous = parseur.add_subparsers(dest="commande")

    bulletin = sous.add_parser("bulletin", parents=[communes],
                               help="genere le bulletin du week-end (defaut)")
    bulletin.add_argument("--date", type=_jour, metavar="AAAA-MM-JJ",
                          help="date de reference (defaut : aujourd'hui)")
    bulletin.add_argument("--du", type=_jour, metavar="AAAA-MM-JJ", help="debut d'une fenetre sur mesure")
    bulletin.add_argument("--au", type=_jour, metavar="AAAA-MM-JJ", help="fin d'une fenetre sur mesure")
    bulletin.add_argument("--sport", action="append", choices=sorted(SOURCES_PAR_SPORT),
                          help="limiter a un ou plusieurs sports")
    bulletin.add_argument("--format", choices=("texte", "markdown", "json"), default="texte")
    bulletin.add_argument("-o", "--sortie", type=Path, help="ecrire dans un fichier")
    bulletin.add_argument("--strict", action="store_true",
                          help="ecarter les rencontres dont la competition n'est pas identifiable")

    chercher = sous.add_parser("chercher", parents=[communes],
                               help="retrouver la graphie exacte d'un club")
    chercher.add_argument("terme", help="nom approximatif, ex. \"Marmande\"")
    chercher.add_argument("--sport", required=True, choices=sorted(SOURCES_PAR_SPORT))

    diag = sous.add_parser("diagnostiquer", parents=[communes],
                           help="verifier que chaque federation repond")
    diag.add_argument("--source", action="append", choices=sorted(SOURCES),
                      help="limiter a une source")

    return parseur


def _fenetre(args) -> Fenetre:
    if args.du and args.au:
        if args.au < args.du:
            raise SystemExit("erreur : --au est anterieur a --du")
        return fenetre_personnalisee(args.du, args.au)
    if args.du or args.au:
        raise SystemExit("erreur : --du et --au vont par paire")
    return weekend_de(args.date or date.today())


def _competitions(config: Config, sports: list[str] | None):
    if not sports:
        return config.competitions
    return [c for c in config.competitions if c.sport in sports]


# -- commandes -----------------------------------------------------------
def commande_bulletin(args, config: Config) -> int:
    fenetre = _fenetre(args)
    retenues = _competitions(config, args.sport)
    bulletin = Bulletin(fenetre=fenetre, config=config)

    for nom_source, classe in SOURCES.items():
        competitions = [c for c in retenues if c.actif and c.source == nom_source]
        if not competitions:
            continue
        source = classe(seuil=config.seuil_correspondance, verbeux=args.verbeux)
        try:
            if nom_source == "ffbb":
                rapport = source.collecter(competitions, fenetre, strict=args.strict)
            else:
                rapport = source.collecter(competitions, fenetre)
        except ErreurSource as exc:
            rapport = source._rapport()
            rapport.erreurs.append(str(exc))
        except Exception as exc:  # une federation cassee ne doit pas tout arreter
            logging.getLogger(__name__).debug("source %s", nom_source, exc_info=True)
            rapport = source._rapport()
            rapport.erreurs.append(f"erreur inattendue : {exc}")
        bulletin.rapports.append(rapport)

    if args.format == "json":
        sortie = json.dumps(_en_dict(bulletin), ensure_ascii=False, indent=2, default=str)
    elif args.format == "markdown":
        sortie = en_markdown(bulletin)
    else:
        sortie = en_texte(bulletin)

    if args.sortie:
        args.sortie.parent.mkdir(parents=True, exist_ok=True)
        args.sortie.write_text(sortie + "\n", encoding="utf-8")
        print(f"Bulletin ecrit dans {args.sortie}", file=sys.stderr)
    else:
        print(sortie)

    if bulletin.erreurs and not bulletin.matchs:
        return 1
    return 0


def _en_dict(bulletin: Bulletin) -> dict:
    def match_en_dict(m: Match) -> dict:
        donnees = dataclasses.asdict(m)
        donnees.pop("brut", None)
        donnees["score"] = m.score
        return donnees

    debut, fin = bulletin.fenetre.iso()
    return {
        "fenetre": {"debut": debut, "fin": fin, "libelle": bulletin.fenetre.libelle},
        "matchs": [match_en_dict(m) for m in bulletin.matchs],
        "sans_match": sorted(set(bulletin.sans_match)),
        "erreurs": bulletin.erreurs,
    }


def commande_chercher(args, config: Config) -> int:
    sport = args.sport
    nom_source = SOURCES_PAR_SPORT[sport]
    source = SOURCES[nom_source](seuil=config.seuil_correspondance, verbeux=args.verbeux)
    print(f"Recherche de « {args.terme} » cote {nom_source.upper()}...\n")

    try:
        if nom_source == "fff":
            trouves = source._membres(f"{source.base}/api/clubs", {"clNom": args.terme})
            if not trouves:
                print("Aucun club. Essayez un mot plus court ou une autre graphie.")
                return 1
            for club in trouves[:25]:
                cl_no = club.get("cl_no") or club.get("clNo") or club.get("id")
                print(f"  cl_no={str(cl_no):<8} {club.get('cl_nom') or club.get('nom')}")
            print("\nEpinglez l'identifiant dans config.yaml :\n"
                  "    - nom: Marmande\n      ids: {cl_no: 12345}")
            return 0

        if nom_source == "ffbb":
            libelles = _libelles_ffbb(source, args.terme)
            if not libelles:
                print("Aucune equipe. Elargissez la periode ou changez de terme.")
                return 1
            for libelle in libelles:
                print(f"  {libelle}")
            print("\nReprenez la graphie exacte dans les 'alias' du club.")
            return 0

        libelles = _libelles_ffr(source, config, args.terme)
        if not libelles:
            print("Aucune equipe trouvee dans les competitions rugby configurees.")
            return 1
        for competition, equipe in libelles:
            print(f"  [{competition}] {equipe}")
        print("\nReprenez la graphie exacte dans les 'alias' du club.")
        return 0
    except ErreurSource as exc:
        print(f"Echec : {exc}", file=sys.stderr)
        return 1


def _libelles_ffbb(source, terme: str) -> list[str]:
    """Libelles d'equipes FFBB contenant le terme, sur la saison en cours."""
    import json as _json

    aujourdhui = date.today()
    debut = date(aujourdhui.year - (0 if aujourdhui.month >= 8 else 1), 8, 1)
    filtre = {
        "_and": [
            {"date_rencontre": {"_between": [debut.isoformat(), aujourdhui.isoformat()]}},
            {"_or": [
                {"nomEquipe1": {"_icontains": terme}},
                {"nomEquipe2": {"_icontains": terme}},
            ]},
        ]
    }
    donnees = source.session.json(
        f"{source.base}/items/ffbbserver_rencontres",
        params={
            "filter": _json.dumps(filtre, separators=(",", ":")),
            "fields[]": ["nomEquipe1", "nomEquipe2"],
            "limit": 200,
        },
        headers=source._entetes(),
    )
    membres = donnees.get("data") if isinstance(donnees, dict) else donnees
    motif = {m for m in mots_cles(terme)}
    libelles = set()
    for ligne in membres or []:
        for cle in ("nomEquipe1", "nomEquipe2"):
            valeur = (ligne or {}).get(cle) or ""
            if valeur and motif & mots_cles(valeur):
                libelles.add(valeur.strip())
    return sorted(libelles)


def _libelles_ffr(source, config: Config, terme: str) -> list[tuple[str, str]]:
    """Libelles d'equipes rugby trouves sur les pages de competition configurees."""
    motif = mots_cles(terme)
    resultats: set[tuple[str, str]] = set()
    for competition in config.par_source("ffr"):
        try:
            bruts = source.charger(competition)
        except ErreurSource:
            continue
        for brut in bruts:
            match = source._vers_match(brut, competition, "")
            for equipe in (match.equipe_domicile, match.equipe_exterieur):
                if equipe and motif & mots_cles(equipe):
                    resultats.add((competition.libelle, equipe.strip()))
    return sorted(resultats)


def commande_diagnostiquer(args, config: Config) -> int:
    fenetre = weekend_de(date.today())
    voulues = args.source or config.sources_actives
    print(f"Diagnostic sur le {fenetre.libelle}\n")
    souci = 0

    for nom_source in voulues:
        competitions = config.par_source(nom_source)
        if not competitions:
            print(f"[{nom_source}] aucune competition configuree — ignore")
            continue
        source = SOURCES[nom_source](seuil=config.seuil_correspondance, verbeux=args.verbeux)
        print(f"[{nom_source}] {len(competitions)} competition(s)")
        try:
            if nom_source == "ffr":
                competition = competitions[0]
                url = source.url_competition(competition)
                reponse = source.session.get(url)
                inventaire = source.inventaire(reponse.text)
                print(f"      page      : {url} ({reponse.status_code}, {len(reponse.text)} octets)")
                for cle, valeur in inventaire.items():
                    print(f"      {cle:<16}: {valeur}")
                if not inventaire["objets_de_match"]:
                    souci += 1
                    print("      -> aucune rencontre lisible : structure de page a recalibrer")
            elif nom_source == "fff":
                club = competitions[0].clubs[0]
                cl_no = source.resoudre_club(club)
                print(f"      base      : {source.base}")
                print(f"      club test : {club.libelle} -> cl_no={cl_no}")
                if cl_no is None:
                    souci += 1
                    print("      -> club non resolu : ajustez les alias ou epinglez ids.cl_no")
                else:
                    bruts = source.matchs_du_club(cl_no, fenetre)
                    print(f"      matchs    : {len(bruts)} sur la fenetre")
            else:
                jeton = source.jeton()
                club = competitions[0].clubs[0]
                bruts = source.rencontres_du_club(club, fenetre)
                print(f"      base      : {source.base}")
                print(f"      jeton     : obtenu ({len(jeton)} caracteres)")
                print(f"      club test : {club.libelle} -> {len(bruts)} rencontre(s)")
        except ErreurSource as exc:
            souci += 1
            print(f"      ECHEC : {exc}")
        except Exception as exc:
            souci += 1
            logging.getLogger(__name__).debug("diagnostic %s", nom_source, exc_info=True)
            print(f"      ECHEC inattendu : {exc}")
        print()

    print("Diagnostic termine." if not souci else f"{souci} source(s) a recalibrer.")
    return 0 if not souci else 1


COMMANDES = ("bulletin", "chercher", "diagnostiquer")


def _avec_commande_par_defaut(argv: list[str]) -> list[str]:
    """'resultats47 --sport rugby' doit marcher comme 'resultats47 bulletin ...'."""
    if any(a in ("-h", "--help", "--version") for a in argv):
        return argv
    if any(a in COMMANDES for a in argv):
        return argv
    return ["bulletin", *argv]


def analyser(argv: list[str] | None = None) -> argparse.Namespace:
    """Analyse la ligne de commande, options communes comprises."""
    parseur = construire_parseur()
    brut = list(argv if argv is not None else sys.argv[1:])
    args = parseur.parse_args(_avec_commande_par_defaut(brut))
    # Les options communes sont en SUPPRESS pour survivre a la sous-commande :
    # on pose ici leur valeur par defaut si elles n'ont pas ete donnees.
    if not hasattr(args, "config"):
        args.config = CONFIG_PAR_DEFAUT
    if not hasattr(args, "verbeux"):
        args.verbeux = False
    return args


def main(argv: list[str] | None = None) -> int:
    args = analyser(argv)
    logging.basicConfig(
        level=logging.DEBUG if args.verbeux else logging.WARNING,
        format="%(levelname)s %(name)s: %(message)s",
    )

    try:
        config = charger(args.config)
    except ConfigError as exc:
        print(f"Configuration invalide : {exc}", file=sys.stderr)
        return 2

    commande = args.commande or "bulletin"
    if commande == "bulletin":
        return commande_bulletin(args, config)
    if commande == "chercher":
        return commande_chercher(args, config)
    return commande_diagnostiquer(args, config)
