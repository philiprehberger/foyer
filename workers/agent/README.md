# Foyer Agent Worker

FastAPI sidecar that orchestrates the conversational booking agent. Runs under supervisord on the production EC2 host (`numprocs=2` from day one); locally via `uvicorn`.

## What it does

1. Receives an `AgentTurn` job (POSTed to `/run-turn` from Laravel).
2. Loads turn context (last N messages, business config, current phase) via the Laravel internal API.
3. Builds the system + user prompt (persona + scope guard + state-machine-aware allowed-next-phases hint + JSON output contract).
4. Calls the configured LLM provider (`mock` by default; `anthropic` / `openai` stubbed).
5. Parses + validates the response against `infra/contracts/agent-turn-response.schema.json`. Retries up to 2× with a focused repair prompt on parse failure; on the third failure escalates to `human_handoff`.
6. Re-validates the proposed transition against the state machine.
7. Tracks per-business per-day cost; degrades to `cheap_mode` at 100% of the ceiling, kills the conversation at 150%.
8. POSTs the result (or escalation) back to Laravel via the same HMAC'd internal API.

## Endpoints

| Method | Path             | Purpose                                                                 |
|--------|------------------|-------------------------------------------------------------------------|
| GET    | `/healthz`       | Liveness + provider info.                                               |
| POST   | `/run-turn`      | Run one `AgentTurn` end-to-end. Requires `X-Foyer-Internal-Sig`.        |
| POST   | `/reload-config` | Clear the in-process cost ledger. Requires `X-Foyer-Internal-Sig`.      |

All non-healthz endpoints require `X-Foyer-Internal-Sig: hmac_sha256(secret, body)` matching `FOYER_INTERNAL_SECRET`. Bind to `127.0.0.1` only — never expose to the public internet.

## State machine

`greet → identify_service → collect_address → collect_timing → collect_details → propose_slot → request_human_confirm → confirm_to_customer → completed`

Plus terminals reachable from any non-terminal phase: `abandon`, `out_of_scope`, `human_handoff`.

Invariants asserted in tests:

- `completed` is unreachable without passing through `propose_slot`.
- `abandon` is reachable from every non-terminal phase.
- `out_of_scope` and `human_handoff` are terminal.

Encoded in `state_machine.py` as an explicit `_ALLOWED` transition table; the Hypothesis property-based test (`tests/test_state_machine_invariants.py`) walks the graph from every starting phase and asserts the invariants hold.

## Structured output

LLM responses must match `infra/contracts/agent-turn-response.schema.json`:

```json
{
  "next_phase": "...",
  "reply_text": "...",
  "intents": [{"name": "...", "args": {}}],
  "confidence": 0.0,
  "tokens_in": 0,
  "tokens_out": 0,
  "cost_micros": 0,
  "model": "..."
}
```

Parse failure path: 2 retries with a focused repair prompt; on the third failure the worker POSTs `EscalatePayload{reason: "parse_failure_exhausted"}` and Laravel flips the conversation to `human_handoff`.

## Mock provider triggers

The mock LLM provider is deterministic and drives every test. It honours three special tokens in the user message:

- `OUTSIDE_AREA` → forces `next_phase: out_of_scope`
- `STOP_TEST` → forces `next_phase: human_handoff`
- `BAD_JSON` → returns malformed JSON to exercise the retry path

Otherwise it advances to the next phase in the happy path.

## Cost ceiling

Per-business per-day budget in micros (1_000_000 = $1.00). Default from `FOYER_DEFAULT_COST_CEILING_MICROS=500000`; overridable per business via `BusinessConfig.cost_ceiling_micros`.

State transitions:

- `NORMAL` → `CHEAP` at ≥ 100% of ceiling — model swaps to `business.cheap_mode_model` (default `claude-haiku`).
- `CHEAP` → `KILLED` at ≥ 150% of ceiling — worker escalates `human_handoff`, no further LLM calls.

In-process counter; refreshed from Laravel's durable `llm_cost_daily` rollup on the next turn after a `POST /reload-config`.

## Local dev

```bash
cd workers/agent
python -m venv .venv && .venv/bin/pip install -e '.[dev]'
export FOYER_INTERNAL_SECRET="$(openssl rand -hex 32)"
export FOYER_LARAVEL_INTERNAL_BASE_URL=http://127.0.0.1:8000
export FOYER_LLM_PROVIDER=mock
export FOYER_LLM_MODEL=mock-default
.venv/bin/uvicorn app:app --host 127.0.0.1 --port 8800 --reload
```

Health check:

```bash
curl http://127.0.0.1:8800/healthz
```

## Running tests

```bash
.venv/bin/pytest -q
```

Tests use pytest + pytest-asyncio + hypothesis. No external services required — the mock provider + an injected `InternalAPIClient` cover the orchestrator path.

## Environment variables

| Name                              | Default                                      | Purpose                                                   |
|-----------------------------------|----------------------------------------------|-----------------------------------------------------------|
| `FOYER_INTERNAL_SECRET`           | `local-dev-foyer-internal-secret` (dev only) | HMAC secret shared with Laravel internal API.             |
| `FOYER_LARAVEL_INTERNAL_BASE_URL` | `http://127.0.0.1:8000`                      | Base URL for the Laravel internal API.                    |
| `FOYER_LLM_PROVIDER`              | `mock`                                       | `mock` \| `anthropic` \| `openai`.                        |
| `FOYER_LLM_MODEL`                 | `mock-default`                               | Default model identifier.                                 |
| `FOYER_DEFAULT_COST_CEILING_MICROS` | `500000` (set in Laravel; per-business overrides)| Per-business per-day cap in micros.                  |
| `ANTHROPIC_API_KEY`               | —                                            | Required when provider is `anthropic`.                    |
| `OPENAI_API_KEY`                  | —                                            | Required when provider is `openai`.                       |

## Supervisord

See `supervisord/foyer-agent-worker.conf` for the production-shaped entry. Drop into `/etc/supervisor/conf.d/`; reload with:

```bash
sudo supervisorctl reread && sudo supervisorctl update
```

Default `numprocs=2`; tune via Horizon queue-depth alerts + the k6 load test in Phase 10.

## Not in scope for this worker

- Direct DB access. The worker never opens a Postgres connection. All state flows through the internal API.
- Twilio outbound. Twilio is a Horizon PHP worker, not Python.
- Calendar API calls. The orchestrator emits `intents`; Laravel queues the calendar work.
- Photo handling. Photos go through the `photo-sanitize` Horizon worker; the agent receives a description, not bytes.
