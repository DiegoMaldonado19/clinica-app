"""RN-20: clasificador determinista que corre antes que el modelo.

Está calibrado para escalar de más: un falso positivo cuesta una llamada a la
línea de crisis; un falso negativo puede costar una vida (HU-26).
"""

import re
import unicodedata

# El orden importa: una frase que encaja en varias categorías se informa con la primera.
PATTERNS: dict[str, tuple[str, ...]] = {
    "autolesion": (
        r"suicid",
        r"\bmatarme\b",
        r"quitarme la vida",
        r"acabar con mi vida",
        r"terminar con (todo|mi vida)",
        r"\bquiero morir",
        r"\bmorirme\b",
        r"no quiero (vivir|seguir viviendo|despertar)",
        r"hacerme dano",
        r"\bme hago dano\b",
        r"lastimarme",
        r"\bme lastimo\b",
        r"\bcortarme\b",
        r"\bme corto\b",
        r"autolesion",
        r"sobredosis",
        r"tomar(me)? todas (las|mis) pastillas",
        r"\b(colgarme|ahorcarme)\b",
        r"\b(tirarme|me voy a tirar|aventarme|lanzarme) (de|del|al|a)\b",
    ),
    "desesperanza": (
        r"no vale la pena (vivir|seguir)",
        r"no tiene sentido (vivir|seguir)",
        r"ya no (puedo|aguanto) mas",
        r"(seria|estarian) mejor (si no estuviera|sin mi)",
        r"nadie me (extranaria|echaria de menos)",
        r"no hay salida",
        r"sin (ninguna )?esperanza",
        r"despedirme de (todos|todo el mundo)",
        r"(quisiera|quiero) desaparecer",
        r"desaparecer para siempre",
        r"ya no quiero (seguir|estar aqui)",
    ),
    "riesgo_terceros": (
        r"\bmatar(lo|la|los|las)?\b",
        r"hacerle dano a",
        r"lastimar a",
        r"\bme (pega|golpea|amenaza)\b",
        r"amenaza de muerte",
        r"(abuso|violencia) (sexual|domestica|intrafamiliar)",
        r"\bme (violo|abuso)\b",
    ),
    "peligro": (
        r"estoy en peligro",
        r"es una emergencia",
        r"\bauxilio\b",
        r"ayudenme",
    ),
}

_COMPILED = {category: re.compile("|".join(patterns)) for category, patterns in PATTERNS.items()}


def normalize(text: str) -> str:
    """Minúsculas, sin tildes ni eñes y con un solo espacio: «Ya no puedo más» -> «ya no puedo mas»."""
    decomposed = unicodedata.normalize("NFKD", text.lower())

    return " ".join("".join(char for char in decomposed if not unicodedata.combining(char)).split())


def classify(text: str) -> str | None:
    """Categoría de la señal de crisis, o None si no hay ninguna."""
    normalized = normalize(text)

    return next((category for category, pattern in _COMPILED.items() if pattern.search(normalized)), None)
