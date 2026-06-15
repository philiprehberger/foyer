"""HMAC'd HTTP client for the Laravel internal API.

All requests carry ``X-Foyer-Internal-Sig: hmac_sha256(secret, body)``,
the secret pulled from ``FOYER_INTERNAL_SECRET`` (raw bytes; never
base64-decoded). For GETs the body is the empty string. The Laravel side
binds the ``/_internal/*`` routes to ``127.0.0.1`` and rejects mismatched
signatures with ``403`` — verified by the matching middleware on that
side.

The signature comparison on both sides uses :func:`hmac.compare_digest`
for constant-time equality.
"""
from __future__ import annotations

import hmac
import json
import os
from dataclasses import dataclass
from hashlib import sha256
from typing import Any

import httpx

from contracts import EscalatePayload, TurnContext, TurnResultPayload

SECRET_ENV = "FOYER_INTERNAL_SECRET"
BASE_URL_ENV = "FOYER_LARAVEL_INTERNAL_BASE_URL"
DEFAULT_BASE_URL = "http://127.0.0.1:8000"

SIG_HEADER = "X-Foyer-Internal-Sig"


def compute_signature(secret: str, body: bytes) -> str:
    """Return the lowercase hex HMAC-SHA256 of ``body`` under ``secret``.

    The header value Laravel expects is the bare hex digest — no
    ``sha256=`` prefix, matching the existing internal-API middleware.
    """
    return hmac.new(
        secret.encode("utf-8"), body, sha256
    ).hexdigest()


def verify_signature(secret: str, body: bytes, provided: str) -> bool:
    """Constant-time signature check.

    Exposed so request-handling code on this side (e.g. webhook receivers
    from Laravel into the worker) can validate signatures with the same
    primitive as the producer.
    """
    expected = compute_signature(secret, body)
    return hmac.compare_digest(expected, provided)


class InternalAPIError(RuntimeError):
    """Raised when the Laravel internal API returns non-2xx."""

    def __init__(self, status_code: int, body: str) -> None:
        super().__init__(f"internal API error {status_code}: {body[:500]}")
        self.status_code = status_code
        self.body = body


@dataclass(frozen=True)
class InternalAPIConfig:
    """Settings the client needs. Built once at module import in app.py."""

    base_url: str
    secret: str
    timeout_seconds: float = 8.0

    @classmethod
    def from_env(cls) -> InternalAPIConfig:
        secret = os.environ.get(SECRET_ENV, "")
        if not secret:
            # Local dev: degrade to a placeholder so the worker still boots
            # for unit tests that don't actually hit the internal API.
            secret = "local-dev-foyer-internal-secret"
        base_url = os.environ.get(BASE_URL_ENV, DEFAULT_BASE_URL).rstrip("/")
        return cls(base_url=base_url, secret=secret)


class InternalAPIClient:
    """Async HMAC'd client for the Laravel internal API.

    The three endpoints — ``GET turn-context``, ``POST turn-result``,
    ``POST escalate`` — are the only ones the worker needs. New
    endpoints get their own method on this class so signing logic stays
    centralised.
    """

    def __init__(
        self,
        config: InternalAPIConfig | None = None,
        client: httpx.AsyncClient | None = None,
    ) -> None:
        self._config = config or InternalAPIConfig.from_env()
        # The injected client form is for tests; production code lets the
        # client construct its own httpx pool and close it on shutdown.
        self._client = client
        self._owns_client = client is None

    async def __aenter__(self) -> InternalAPIClient:
        if self._client is None:
            self._client = httpx.AsyncClient(
                base_url=self._config.base_url,
                timeout=self._config.timeout_seconds,
            )
        return self

    async def __aexit__(self, exc_type, exc, tb) -> None:
        if self._owns_client and self._client is not None:
            await self._client.aclose()
            self._client = None

    @property
    def _http(self) -> httpx.AsyncClient:
        if self._client is None:
            self._client = httpx.AsyncClient(
                base_url=self._config.base_url,
                timeout=self._config.timeout_seconds,
            )
        return self._client

    def _sign(self, body: bytes) -> dict[str, str]:
        return {
            SIG_HEADER: compute_signature(self._config.secret, body),
            "Content-Type": "application/json",
        }

    async def fetch_turn_context(self, conversation_id: str) -> TurnContext:
        """``GET /_internal/conversations/{id}/turn-context``.

        Empty body — sign the empty string and forward.
        """
        path = f"/_internal/conversations/{conversation_id}/turn-context"
        headers = self._sign(b"")
        # GETs in Laravel's middleware sign the empty body; drop Content-Type.
        headers.pop("Content-Type", None)
        resp = await self._http.get(path, headers=headers)
        if resp.status_code >= 400:
            raise InternalAPIError(resp.status_code, resp.text)
        return TurnContext.model_validate(resp.json())

    async def post_turn_result(
        self, conversation_id: str, payload: TurnResultPayload
    ) -> None:
        """``POST /_internal/conversations/{id}/turn-result``."""
        path = f"/_internal/conversations/{conversation_id}/turn-result"
        body = self._encode(payload.model_dump(mode="json"))
        headers = self._sign(body)
        resp = await self._http.post(path, content=body, headers=headers)
        if resp.status_code >= 400:
            raise InternalAPIError(resp.status_code, resp.text)

    async def post_escalate(
        self, conversation_id: str, payload: EscalatePayload
    ) -> None:
        """``POST /_internal/conversations/{id}/escalate``."""
        path = f"/_internal/conversations/{conversation_id}/escalate"
        body = self._encode(payload.model_dump(mode="json"))
        headers = self._sign(body)
        resp = await self._http.post(path, content=body, headers=headers)
        if resp.status_code >= 400:
            raise InternalAPIError(resp.status_code, resp.text)

    @staticmethod
    def _encode(payload: dict[str, Any]) -> bytes:
        """Canonical JSON encoding for sign-on-write.

        ``separators=(",", ":")`` keeps the byte stream stable across
        platforms; both sides use the same compact form so the HMAC
        matches without any normalisation step.
        """
        return json.dumps(payload, separators=(",", ":"), sort_keys=False).encode(
            "utf-8"
        )
