"""Connecteurs par federation."""

from resultats47.sources.base import Source
from resultats47.sources.ffbb import SourceFFBB
from resultats47.sources.fff import SourceFFF
from resultats47.sources.ffr import SourceFFR

SOURCES: dict[str, type[Source]] = {
    "ffr": SourceFFR,
    "fff": SourceFFF,
    "ffbb": SourceFFBB,
}

__all__ = ["SOURCES", "Source", "SourceFFBB", "SourceFFF", "SourceFFR"]
