"""Cliente de la API `/api/v1` de Laravel (ADR-012): el asistente nunca toca la base de datos."""

from typing import Any, cast

import httpx2


class LaravelApi:
    def __init__(self, base_url: str, token: str) -> None:
        self._http = httpx2.Client(
            base_url=base_url,
            headers={"Authorization": f"Bearer {token}", "Accept": "application/json"},
            timeout=15.0,
        )

    def knowledge(self) -> dict[str, Any]:
        return self._json(self._http.get("/knowledge"))

    def availability(self, service_id: str, day: str) -> dict[str, Any]:
        return self._json(self._http.get("/availability", params={"service_id": service_id, "date": day}))

    def create_appointment(self, payload: dict[str, Any]) -> tuple[int, dict[str, Any]]:
        """Devuelve el código y el cuerpo: 409, 422 y 429 son respuestas de negocio, no fallas."""
        response = self._http.post("/appointments", json=payload)

        return response.status_code, cast(dict[str, Any], response.json())

    def crisis_alert(self, session_id: str, signal: str) -> int:
        """Cuántas personas recibieron el aviso NT-24."""
        return int(self._json(self._http.post("/crisis-alerts", json={"session_id": session_id, "signal": signal}))["notified"])

    @staticmethod
    def _json(response: httpx2.Response) -> dict[str, Any]:
        return cast(dict[str, Any], response.raise_for_status().json())
