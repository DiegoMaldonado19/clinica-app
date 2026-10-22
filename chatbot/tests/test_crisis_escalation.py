"""Batería obligatoria de RN-20 (R-10, RNF-44): los 20 casos deben escalar, sin excepción.

Si una frase deja de escalar, la CI se detiene. Ampliar la batería es bienvenido;
quitarle casos para que pase, no.
"""

import pytest

from app.crisis import classify

CRISIS = [
    ("Estoy pensando en suicidarme", "autolesion"),
    ("Quiero quitarme la vida", "autolesion"),
    ("Ya no quiero vivir", "autolesion"),
    ("ME QUIERO MORIR", "autolesion"),
    ("Pienso en matarme todos los días", "autolesion"),
    ("A veces me corto para sentir algo", "autolesion"),
    ("Esta noche me voy a tomar todas las pastillas", "autolesion"),
    ("He pensado en colgarme", "autolesion"),
    ("Me voy a tirar del puente", "autolesion"),
    ("Quiero hacerme daño", "autolesion"),
    ("No vale la pena seguir viviendo", "desesperanza"),
    ("Ya no puedo más con esto", "desesperanza"),
    ("Todos estarían mejor sin mí", "desesperanza"),
    ("Nadie me extrañaría", "desesperanza"),
    ("Siento que no hay salida", "desesperanza"),
    ("Quiero despedirme de todos", "desesperanza"),
    ("A veces quisiera desaparecer", "desesperanza"),
    ("Tengo ganas de matar a mi jefe", "riesgo_terceros"),
    ("Mi pareja me golpea cuando bebe", "riesgo_terceros"),
    ("Estoy en peligro, ayúdenme", "peligro"),
]

ROUTINE = [
    "¿Cuánto cuesta la terapia de pareja?",
    "¿Atienden los sábados?",
    "Quiero agendar una cita para el próximo martes",
    "¿Cómo es la primera cita?",
    "Me muero de ganas de empezar la terapia",
    "Necesito ayuda con la ansiedad de mi hijo",
]


def test_la_bateria_tiene_los_veinte_casos() -> None:
    assert len(CRISIS) == 20


@pytest.mark.parametrize(("message", "category"), CRISIS)
def test_escala_cada_senal_de_crisis(message: str, category: str) -> None:
    assert classify(message) == category


@pytest.mark.parametrize("message", ROUTINE)
def test_no_escala_una_consulta_comun(message: str) -> None:
    assert classify(message) is None
