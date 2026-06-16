"""HMAC signing + verification tests for the internal-API boundary.

Covers:

- :func:`internal_api.compute_signature` deterministic + matches a hand
  computed reference HMAC.
- :func:`internal_api.verify_signature` is true on match, false on
  mismatch, and uses constant-time comparison (the test is a smoke check
  — we just verify it's the right primitive).
- The FastAPI ``/run-turn`` + ``/reload-config`` endpoints return ``403``
  when ``X-Foyer-Internal-Sig`` is absent or wrong.
- A correctly-signed request reaches the handler.
"""
from __future__ import annotations

import hashlib
import hmac as _hmac
import json
import os

import pytest
from fastapi.testclient import TestClient

# Ensure the worker boots against a known secret before importing app.
os.environ["FOYER_INTERNAL_SECRET"] = "test-secret-do-not-use-in-prod"
os.environ["FOYER_LLM_PROVIDER"] = "mock"

from app import app  # noqa: E402
from internal_api import (  # noqa: E402
    SIG_HEADER,
    compute_signature,
    verify_signature,
)


SECRET = "test-secret-do-not-use-in-prod"


def test_compute_signature_matches_reference_hmac() -> None:
    body = b'{"hello":"world"}'
    expected = _hmac.new(SECRET.encode("utf-8"), body, hashlib.sha256).hexdigest()
    assert compute_signature(SECRET, body) == expected


def test_compute_signature_is_deterministic() -> None:
    body = b"some bytes here"
    assert compute_signature(SECRET, body) == compute_signature(SECRET, body)


def test_compute_signature_differs_on_body_change() -> None:
    a = compute_signature(SECRET, b"one")
    b = compute_signature(SECRET, b"two")
    assert a != b


def test_compute_signature_differs_on_secret_change() -> None:
    body = b"same body"
    assert compute_signature(SECRET, body) != compute_signature("other-secret", body)


def test_verify_signature_accepts_correct_sig() -> None:
    body = b"hello there"
    sig = compute_signature(SECRET, body)
    assert verify_signature(SECRET, body, sig) is True


def test_verify_signature_rejects_wrong_sig() -> None:
    body = b"hello there"
    assert verify_signature(SECRET, body, "deadbeef" * 8) is False


def test_verify_signature_rejects_truncated_sig() -> None:
    body = b"hello"
    sig = compute_signature(SECRET, body)
    assert verify_signature(SECRET, body, sig[:-1]) is False


def test_verify_signature_empty_string_rejects() -> None:
    body = b"hello"
    assert verify_signature(SECRET, body, "") is False


# ---------- FastAPI middleware behaviour ----------


@pytest.fixture
def client():
    # `with TestClient(app)` is what fires FastAPI's lifespan handler — bare
    # `TestClient(app)` skips it and app.state.provider / internal_config
    # never get populated, every endpoint then 500s with AttributeError.
    with TestClient(app) as c:
        yield c


def test_healthz_does_not_require_signature(client: TestClient) -> None:
    r = client.get("/healthz")
    assert r.status_code == 200
    body = r.json()
    assert body["status"] == "healthy"


def test_run_turn_without_signature_returns_403(client: TestClient) -> None:
    r = client.post("/run-turn", json={"foo": "bar"})
    assert r.status_code == 403


def test_run_turn_with_wrong_signature_returns_403(client: TestClient) -> None:
    body = b'{"foo":"bar"}'
    r = client.post(
        "/run-turn",
        content=body,
        headers={SIG_HEADER: "deadbeef" * 8, "Content-Type": "application/json"},
    )
    assert r.status_code == 403


def test_reload_config_without_signature_returns_403(client: TestClient) -> None:
    r = client.post("/reload-config")
    assert r.status_code == 403


def test_reload_config_with_correct_signature_succeeds(client: TestClient) -> None:
    body = b""
    sig = compute_signature(SECRET, body)
    r = client.post(
        "/reload-config",
        content=body,
        headers={SIG_HEADER: sig},
    )
    assert r.status_code == 200
    assert r.json() == {"status": "reloaded"}


def test_run_turn_with_correct_signature_but_invalid_payload_returns_422(
    client: TestClient,
) -> None:
    # Signature is valid, but the body is not a legal AgentTurnJob — the
    # handler should reject with 422 (not 403, which would mean the gate
    # never opened).
    body = json.dumps({"not": "a job"}).encode("utf-8")
    sig = compute_signature(SECRET, body)
    r = client.post(
        "/run-turn",
        content=body,
        headers={SIG_HEADER: sig, "Content-Type": "application/json"},
    )
    assert r.status_code == 422
