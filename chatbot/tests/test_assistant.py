"""El asistente con un cliente de modelo falso: ninguna prueba llama a la API real de Anthropic."""

from datetime import date
from typing import Any

import pytest
from anthropic.types import CitationCharLocation, Message, TextBlock, ToolUseBlock, Usage
from fastapi import HTTPException

from app.assistant import MODEL, Assistant, BookingAction, Turn
from app.main import require_token

KNOWLEDGE: dict[str, Any] = {
    "clinic": {
        "name": "Clínica",
        "address": "Zona 10",
        "phone": "2222-0000",
        "email": "contacto@clinica.test",
        "crisis_line": "1-800-000-0000",
    },
    "services": [{"id": "svc-pareja", "name": "Terapia de pareja", "duration_minutes": 60, "price": "Q 500"}],
    "hours": [{"day": "lunes", "from": "08:00", "to": "12:00"}],
    "policies": {
        "cancellation": [{"when": "24 horas o más de anticipación", "fee": "Sin cargo"}],
        "payment_deadline_hours": 3,
        "approval_sla_hours": 24,
    },
    "faqs": [{"question": "¿Cómo es la primera cita?", "answer": "Una conversación para conocerte."}],
}
SLOT = {"starts_at": "2026-10-27T15:00:00+00:00", "time": "09:00"}


class FakeApi:
    def __init__(self) -> None:
        self.alerts: list[tuple[str, str]] = []

    def knowledge(self) -> dict[str, Any]:
        return KNOWLEDGE

    def availability(self, service_id: str, day: str) -> dict[str, Any]:
        return {"date": day, "slots": [SLOT], "notice": None}

    def crisis_alert(self, session_id: str, signal: str) -> int:
        self.alerts.append((session_id, signal))
        return 1


class FakeModel:
    """Devuelve respuestas guionadas y guarda cada petición."""

    def __init__(self, *responses: Message) -> None:
        self.responses = list(responses)
        self.requests: list[dict[str, Any]] = []

    def __call__(self, **params: Any) -> Message:
        self.requests.append(params)
        return self.responses.pop(0)


def message(*content: TextBlock | ToolUseBlock, stop: str = "end_turn") -> Message:
    return Message(
        id="msg_1",
        type="message",
        role="assistant",
        model=MODEL,
        content=list(content),
        stop_reason=stop,  # type: ignore[arg-type]
        stop_sequence=None,
        usage=Usage(input_tokens=1, output_tokens=1),
    )


def tool(name: str, **data: object) -> Message:
    return message(ToolUseBlock(type="tool_use", id="toolu_1", name=name, input=data), stop="tool_use")


def asks(text: str) -> list[Turn]:
    return [{"role": "user", "content": text}]


def test_escala_la_crisis_antes_de_consultar_al_modelo() -> None:
    model, api = FakeModel(), FakeApi()

    reply = Assistant(model, api).answer("s1", asks("Ya no quiero vivir"))

    assert reply.escalated and reply.closed
    assert "línea de crisis" in reply.reply and reply.reply.endswith("Ya avisamos a la psicóloga.")
    assert api.alerts == [("s1", "autolesion")]
    assert model.requests == []


def test_responde_con_su_fuente_y_cachea_la_base_de_conocimiento() -> None:
    cited = TextBlock(
        type="text",
        text="La terapia de pareja cuesta Q 500.",
        citations=[
            CitationCharLocation(
                type="char_location",
                cited_text="Terapia de pareja: Q 500",
                document_index=0,
                document_title="Servicios y tarifas",
                start_char_index=0,
                end_char_index=24,
            )
        ],
    )
    model = FakeModel(message(cited))

    reply = Assistant(model, FakeApi(), today=lambda: date(2026, 10, 20)).answer("s1", asks("¿Cuánto cuesta la terapia de pareja?"))

    request = model.requests[0]
    documents = [block for block in request["messages"][0]["content"] if block["type"] == "document"]
    assert reply.reply == "La terapia de pareja cuesta Q 500." and reply.sources == ["Servicios y tarifas"]
    assert request["model"] == "claude-haiku-5-5" and request["output_config"] == {"effort": "low"}
    assert all(document["citations"] == {"enabled": True} for document in documents)
    assert ["cache_control" in document for document in documents] == [False, False, False, True]
    assert request["system"].endswith("Hoy es martes 2026-10-20.")


def test_no_propone_un_horario_que_la_api_no_ofrece() -> None:
    model = FakeModel(
        tool("create_appointment", service="Terapia de pareja", date="2026-10-27", starts_at="2026-10-27T22:00:00+00:00"),
        message(TextBlock(type="text", text="Ese horario no está libre; tengo las 09:00.")),
    )

    reply = Assistant(model, FakeApi()).answer("s1", asks("Quiero el martes a las 4 de la tarde"))

    result = model.requests[1]["messages"][-1]["content"][0]
    assert reply.action is None
    assert result["is_error"] is True and "09:00" in result["content"]


def test_propone_el_formulario_para_un_horario_que_la_api_ofrece() -> None:
    model = FakeModel(
        tool("create_appointment", service="Terapia de pareja", date="2026-10-27", starts_at=SLOT["starts_at"]),
        message(TextBlock(type="text", text="Completa el formulario para confirmar.")),
    )

    reply = Assistant(model, FakeApi()).answer("s1", asks("Quiero el martes a las 9"))

    assert reply.action == BookingAction(
        service_id="svc-pareja", service="Terapia de pareja", starts_at=SLOT["starts_at"], date="2026-10-27", time="09:00"
    )
    assert reply.reply == "Completa el formulario para confirmar."


def test_el_modelo_tambien_escala_y_la_conversacion_no_sigue() -> None:
    model, api = FakeModel(tool("escalate_crisis")), FakeApi()

    reply = Assistant(model, api).answer("s1", asks("Siento que todo me pesa demasiado"))

    assert reply.escalated and reply.closed
    assert api.alerts == [("s1", "asistente")] and len(model.requests) == 1


def test_cierra_al_llegar_al_limite_de_turnos() -> None:
    history: list[Turn] = [{"role": "user" if index % 2 == 0 else "assistant", "content": "Hola"} for index in range(41)]
    model = FakeModel()

    reply = Assistant(model, FakeApi()).answer("s1", history)

    assert reply.closed and model.requests == []


def test_ofrece_el_contacto_si_el_modelo_se_niega() -> None:
    reply = Assistant(FakeModel(message(stop="refusal")), FakeApi()).answer("s1", asks("Algo fuera de lugar"))

    assert "2222-0000" in reply.reply


def test_el_servicio_solo_atiende_con_el_token_de_laravel(monkeypatch: pytest.MonkeyPatch) -> None:
    monkeypatch.setenv("CHATBOT_API_TOKEN", "secreto")

    require_token("Bearer secreto")

    with pytest.raises(HTTPException):
        require_token("Bearer otro")
