"""Calcul de la fenetre 'week-end'."""

from __future__ import annotations

from dataclasses import dataclass
from datetime import date, datetime, time, timedelta

VENDREDI = 4  # date.weekday(): lundi=0 ... dimanche=6


@dataclass(frozen=True)
class Fenetre:
    """Intervalle [debut, fin] inclusif couvrant un week-end."""

    debut: datetime
    fin: datetime

    def contient(self, moment: datetime | date | None) -> bool:
        if moment is None:
            return False
        if isinstance(moment, datetime):
            instant = moment.replace(tzinfo=None)
        else:
            instant = datetime.combine(moment, time.min)
        return self.debut <= instant <= self.fin

    @property
    def libelle(self) -> str:
        return f"week-end du {self.debut:%d/%m/%Y} au {self.fin:%d/%m/%Y}"

    def iso(self) -> tuple[str, str]:
        return self.debut.date().isoformat(), self.fin.date().isoformat()


def weekend_de(reference: date) -> Fenetre:
    """Week-end (vendredi -> dimanche) rattache a la date de reference.

    Du vendredi au dimanche, c'est le week-end en cours : on couvre les
    matchs du soir meme. Du lundi au jeudi -- le cas de l'emission du lundi --
    c'est le week-end qui vient de s'ecouler.
    """
    jour = reference.weekday()
    recul = jour - VENDREDI if jour >= VENDREDI else jour + 3
    vendredi = reference - timedelta(days=recul)
    dimanche = vendredi + timedelta(days=2)
    return Fenetre(
        debut=datetime.combine(vendredi, time.min),
        fin=datetime.combine(dimanche, time.max),
    )


def fenetre_personnalisee(debut: date, fin: date) -> Fenetre:
    return Fenetre(datetime.combine(debut, time.min), datetime.combine(fin, time.max))
