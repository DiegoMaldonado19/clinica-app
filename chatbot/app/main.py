"""Servicio del asistente (F3-06). Interno a la red de compose: solo Laravel le habla, con token.

FastAPI publica su contrato OpenAPI en /openapi.json y /docs.
"""

import hmac
import logging
import os
import traceback
from functools import cache
from typing import Annotated, Any, Literal, cast

import anthropic
from anthropic.types import Message
from fastapi import Depends, FastAPI, Header, HTTPException, Request
from fastapi.responses import JSONResponse
from pydantic import BaseModel, Field

from app.assistant import Assistant, Reply, Turn
from app.laravel import LaravelApi

log = logging.getLogger("chatbot")

app = FastAPI(
    title="Asistente de la clínica",
    version="1.0.0",
    summary="Conversación, agendamiento y escalamiento de crisis para el widget de la landing.",
)


class ChatMessage(BaseModel):
    role: Literal["user", "assistant"]
    content: str = Field(min_length=1, max_length=2000)


class ChatRequest(BaseModel):
    session_id: str = Field(min_length=1, max_length=64)
    messages: list[ChatMessage] = Field(min_length=1, max_length=60)


class BookingRequest(BaseModel):
    service_id: str
    starts_at: str
    name: str = Field(max_length=150)
    email: str = Field(max_length=190)
    phone: str = Field(max_length=30)
    nit: str = Field(max_length=20)
    accepted: bool = Field(description="Aceptó la política de cancelación y el tratamiento de sus datos.")
    client_ip: str | None = None


class BookingResponse(BaseModel):
    ok: bool
    reply: str


def require_token(authorization: Annotated[str, Header()] = "") -> None:
    expected = os.environ.get("CHATBOT_API_TOKEN", "")

    # Sin token configurado no entra nadie.
    if not expected or not hmac.compare_digest(authorization.encode(), f"Bearer {expected}".encode()):
        raise HTTPException(status_code=401, detail="No autorizado.")


@cache
def laravel() -> LaravelApi:
    return LaravelApi(os.environ["LARAVEL_API_URL"], os.environ.get("CHATBOT_API_TOKEN", ""))


@cache
def model() -> anthropic.Anthropic:
    return anthropic.Anthropic(timeout=30.0)


def create_message(**params: Any) -> Message:
    # El cliente se crea en la primera consulta: la rama de crisis no lo necesita.
    return cast(Message, model().messages.create(**params))


@app.post("/chat", response_model=Reply, dependencies=[Depends(require_token)], summary="Responde un mensaje del widget")
def chat(request: ChatRequest) -> Reply:
    if request.messages[-1].role != "user":
        raise HTTPException(status_code=422, detail="El último mensaje debe ser de la persona.")

    history: list[Turn] = [{"role": message.role, "content": message.content} for message in request.messages]

    return Assistant(create_message, laravel()).answer(request.session_id, history)


@app.post(
    "/bookings",
    response_model=BookingResponse,
    dependencies=[Depends(require_token)],
    summary="Registra por la API de Laravel la cita que la persona confirmó en el formulario",
)
def book(request: BookingRequest) -> BookingResponse:
    payload = request.model_dump(exclude={"client_ip"}) | {"ip_address": request.client_ip}
    status, body = laravel().create_appointment(payload)

    if status == 201:
        return BookingResponse(ok=True, reply=str(body["message"]))

    if status in (409, 422, 429):
        return BookingResponse(ok=False, reply=str(body.get("message", "No pudimos registrar la solicitud.")))

    raise RuntimeError(f"La API de la clínica respondió {status} al agendar.")


@app.get("/health", summary="Estado del servicio")
def health() -> dict[str, str]:
    return {"status": "ok"}


@app.exception_handler(Exception)
async def unexpected(request: Request, error: Exception) -> JSONResponse:
    frame = traceback.extract_tb(error.__traceback__)[-1]
    log.error("Error del asistente: %s (%s:%s)", error, frame.filename, frame.lineno, exc_info=error)

    return JSONResponse({"detail": "El asistente no está disponible."}, status_code=503)
