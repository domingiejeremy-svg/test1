"""Session HTTP commune : en-tetes, temporisation, reessais."""

from __future__ import annotations

import logging
import time
from typing import Any

import requests

logger = logging.getLogger(__name__)

NAVIGATEUR = (
    "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 "
    "(KHTML, like Gecko) Chrome/125.0 Safari/537.36"
)


class ErreurSource(RuntimeError):
    """Une source n'a pas pu etre interrogee."""


class Session:
    """Petit wrapper requests : reessais, delai poli, messages clairs."""

    def __init__(
        self,
        timeout: float = 20.0,
        essais: int = 3,
        delai: float = 0.4,
        entetes: dict[str, str] | None = None,
    ):
        self.timeout = timeout
        self.essais = max(1, essais)
        self.delai = delai
        self._session = requests.Session()
        self._session.headers.update(
            {
                "User-Agent": NAVIGATEUR,
                "Accept": "application/json, text/html;q=0.9, */*;q=0.8",
                "Accept-Language": "fr-FR,fr;q=0.9",
                **(entetes or {}),
            }
        )

    def get(self, url: str, **kwargs) -> requests.Response:
        derniere: Exception | None = None
        for tentative in range(1, self.essais + 1):
            try:
                reponse = self._session.get(url, timeout=self.timeout, **kwargs)
            except requests.RequestException as exc:
                derniere = exc
                logger.debug("GET %s : echec reseau (%s/%s) %s", url, tentative, self.essais, exc)
            else:
                if reponse.status_code < 400:
                    time.sleep(self.delai)
                    return reponse
                # 4xx hors 429 : inutile d'insister.
                if 400 <= reponse.status_code < 500 and reponse.status_code != 429:
                    raise ErreurSource(
                        f"{url} a repondu {reponse.status_code} "
                        f"{reponse.reason}. Verifiez les identifiants epingles dans config.yaml."
                    )
                derniere = ErreurSource(f"{url} a repondu {reponse.status_code}")
                logger.debug("GET %s : %s (%s/%s)", url, reponse.status_code, tentative, self.essais)
            if tentative < self.essais:
                time.sleep(self.delai * 2**tentative)
        raise ErreurSource(f"{url} injoignable apres {self.essais} tentatives : {derniere}")

    def json(self, url: str, **kwargs) -> Any:
        reponse = self.get(url, **kwargs)
        try:
            return reponse.json()
        except ValueError as exc:
            extrait = reponse.text[:200].replace("\n", " ")
            raise ErreurSource(f"{url} n'a pas renvoye du JSON ({exc}). Debut : {extrait}") from exc
