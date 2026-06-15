"""Prompt construction + persona templates for the agent worker.

Three personas are baked: ``professional``, ``casual``, ``gentle``.
Owners can also paste a ``system_prompt_suffix`` (length-capped at the
contract layer; injection-screened by :func:`screen_prompt_suffix` here).

The turn-prompt builder produces the full system + user prompt the LLM
sees. It includes:

- The persona instructions.
- The optional owner-customised suffix (screened).
- A scope-bounded reminder ("you can only help with booking; out-of-scope
  requests must return ``out_of_scope``").
- The current phase + the allowed next phases for this turn.
- The rolling message window.
- The last customer message.
- The strict JSON-output contract.

The output is two strings — ``(system, user)`` — that the LLM adapter
forwards verbatim. Adapters never alter prompts; this is the only place
that decides what the model sees.
"""
from __future__ import annotations

import json
import re
from dataclasses import dataclass

from contracts import BusinessConfig, TurnContext
from state_machine import Phase, allowed_next

# Known prompt-injection patterns. Conservative deny list — the goal is
# to reject obviously-malicious owner-supplied suffixes at write time, not
# to be a complete jailbreak defence (the structured-output schema is the
# actual safety boundary).
_INJECTION_PATTERNS = [
    re.compile(r"ignore (the )?(previous|prior|above) (instructions|prompt)", re.I),
    re.compile(r"disregard (the )?(previous|prior|above)", re.I),
    re.compile(r"system\s*[:=]\s*", re.I),
    re.compile(r"</?(system|assistant|user)>", re.I),
    re.compile(r"\bjailbreak\b", re.I),
    re.compile(r"\bDAN\b"),
    re.compile(r"you are (now )?(a |an )?(?!booking)", re.I),
]


class PromptInjectionDetected(ValueError):
    """Raised when an owner-supplied suffix trips a known injection pattern."""


def screen_prompt_suffix(suffix: str) -> str:
    """Return the suffix if clean, else raise.

    Run at scope-config write time by Laravel; re-run at turn-build time
    here so a stale persisted suffix can't slip past a later policy bump.
    """
    text = suffix.strip()
    if not text:
        return ""
    for pat in _INJECTION_PATTERNS:
        if pat.search(text):
            raise PromptInjectionDetected(
                f"system-prompt suffix matches blocked pattern: {pat.pattern}"
            )
    return text


# ---------- Persona templates ----------


@dataclass(frozen=True)
class Persona:
    name: str
    style_line: str
    examples: tuple[str, ...]


_PERSONAS: dict[str, Persona] = {
    "professional": Persona(
        name="professional",
        style_line=(
            "Use a professional tone: complete sentences, courteous, no slang. "
            "Confirm details with concrete numbers (addresses, times, durations)."
        ),
        examples=(
            "Good afternoon — this is the booking line for {business}.",
            "I have you down for Thursday the 19th at 10:00 AM at 1432 Oak Street.",
        ),
    ),
    "casual": Persona(
        name="casual",
        style_line=(
            "Use a casual, friendly tone: contractions, short sentences, no jargon. "
            "Still confirm details with concrete numbers."
        ),
        examples=(
            "Hey, this is {business}'s booking line — happy to help.",
            "Got it, Thursday the 19th at 10am at 1432 Oak St — does that work?",
        ),
    ),
    "gentle": Persona(
        name="gentle",
        style_line=(
            "Use a gentle, reassuring tone: explicit about what you can and can't "
            "do, never pushy. Acknowledge the customer's situation before asking "
            "the next question."
        ),
        examples=(
            "Hi — sorry to hear about the issue. I'd like to help get you on the books.",
            "If Thursday at 10am doesn't work, no problem; I can look for another time.",
        ),
    ),
}


def get_persona(name: str) -> Persona:
    """Look up a persona by name; falls back to ``professional``."""
    return _PERSONAS.get(name, _PERSONAS["professional"])


# ---------- Scope guard ----------

SCOPE_GUARD_INSTRUCTION = (
    "You are a booking assistant for the business described above. "
    "You ONLY help with: identifying the requested service, collecting the "
    "service address, collecting timing preferences, gathering details "
    "(including photos), proposing a slot, and routing the booking to the "
    "owner for confirmation. "
    "If the customer asks about anything outside booking — pricing "
    "negotiation, technical advice, off-script chat, anything not in the "
    "configured service catalog or service area — set `next_phase` to "
    "`out_of_scope` and reply with a brief redirect that offers to have "
    "someone call them back. "
    "You NEVER commit to a booking on your own; the owner confirms every "
    "booking. You NEVER promise pricing. You NEVER share information about "
    "other customers."
)


# ---------- JSON-output contract reminder ----------

_OUTPUT_CONTRACT = """\
You MUST reply with a single JSON object and nothing else — no prose, no
markdown fence, no preamble. The object must match this shape exactly:

{
  "next_phase": "<one of the allowed phases listed above>",
  "reply_text": "<<=1600-char customer-facing message>",
  "intents": [
    {"name": "<intent_name>", "args": {<intent-specific args>}}
  ],
  "confidence": <float 0.0-1.0>,
  "tokens_in": <int>,
  "tokens_out": <int>,
  "cost_micros": <int — your best estimate of the per-turn cost in micros>,
  "model": "<the model identifier you are running on>"
}

If any field is missing, mistyped, or contains extra keys, the orchestrator
will reject the turn and retry. Stick to the shape exactly.
"""


# ---------- Prompt builders ----------


def build_system_prompt(business: BusinessConfig, current_phase: Phase) -> str:
    """Construct the system prompt for a turn.

    Composed of: scope-guard, persona, business config summary, optional
    owner-supplied suffix (re-screened), the current phase + allowed next
    phases, and the JSON output contract.
    """
    persona = get_persona(business.persona)
    suffix = screen_prompt_suffix(business.system_prompt_suffix)
    allowed = sorted(p.value for p in allowed_next(current_phase))

    parts: list[str] = [
        SCOPE_GUARD_INSTRUCTION,
        "",
        f"Business: {business.name}",
        f"Timezone: {business.timezone}",
        f"Services offered: {', '.join(business.service_types) or '(none configured)'}",
        f"Service area: {business.service_area_description or '(unspecified)'}",
        f"Business hours: {business.business_hours_description or '(unspecified)'}",
        "",
        f"Persona ({persona.name}). {persona.style_line}",
    ]
    if persona.examples:
        parts.append("Example phrasings:")
        for ex in persona.examples:
            parts.append(f"  - {ex.format(business=business.name)}")
    if suffix:
        parts.append("")
        parts.append("Additional instructions from the business owner:")
        parts.append(suffix)
    parts.append("")
    parts.append(f"Current phase: {current_phase.value}")
    parts.append(f"Allowed next phases: {', '.join(allowed)}")
    parts.append("")
    parts.append(_OUTPUT_CONTRACT)
    return "\n".join(parts)


def build_user_prompt(ctx: TurnContext) -> str:
    """Construct the user-role prompt.

    Includes the rolling message window (last N from the context) and the
    customer's latest message. The window is text-only — attachments are
    referenced by a placeholder so the LLM doesn't try to inline base64.
    """
    lines: list[str] = ["Conversation so far:"]
    for m in ctx.messages:
        role = m.role
        phase_tag = f" [{m.phase.value}]" if m.phase is not None else ""
        # Trim each line to keep total prompt length sane.
        snippet = m.text if len(m.text) <= 800 else (m.text[:800] + "...")
        lines.append(f"  {role}{phase_tag}: {snippet}")
    lines.append("")
    lines.append("Latest customer message:")
    lines.append(ctx.last_user_message)
    lines.append("")
    lines.append("Reply with the JSON object described in the system prompt.")
    return "\n".join(lines)


def build_repair_prompt(invalid_output: str, error: str) -> str:
    """Build the user-role re-prompt for the parse-failure retry path.

    The repair turn is intentionally terse — repeating the full system
    prompt would burn tokens for no gain since the model already saw it.
    """
    return (
        "Your previous response could not be parsed as the required JSON.\n"
        "Error: "
        f"{error}\n\n"
        "Your previous output (verbatim):\n"
        f"{json.dumps(invalid_output)[:2000]}\n\n"
        "Reply with ONLY the corrected JSON object — no prose, no markdown."
    )


# ---------- Out-of-scope fallback ----------

OUT_OF_SCOPE_FALLBACK = (
    "I can only help with booking — happy to have someone call you back about "
    "that. Want me to pass your number along?"
)
