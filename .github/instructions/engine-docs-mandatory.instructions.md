---
description: "Always-on rule: before writing or reviewing any code that touches an engine, READ the canonical engine doc under docs/engines/<engine>/ and CITE it in your response."
applyTo: "**"
---

# Engine Docs Are Mandatory

The engine documents under `docs/engines/<engine>/` are the **source of truth** for what each engine owns, the rules it enforces, the events it emits, the configuration keys it exposes, and the boundaries it respects.

Treat them the same way you'd treat a public API spec: **never write code that touches an engine without first reading its doc.**

## When this rule fires

This rule applies to **every** turn that involves:
- Implementing or modifying code in `app/Modules/<Engine>/` (backend) or any frontend feature that maps to an engine.
- Designing a contract (API, event, or config key) for an engine.
- Writing tests that assert engine behaviour.
- Reviewing a PR that touches engine code.
- Answering a question about how an engine works, what it owns, or how it interacts with another engine.
- Picking which engine a new piece of work belongs to.

If the turn touches engines at all, this rule fires.

## What you MUST do

1. **Identify the engine(s) involved.** Use the ownership table in `.github/copilot-instructions.md` §3 if unsure.
2. **Open the engine doc(s)** at `docs/engines/<engine>/`. Read the sections relevant to the change — at minimum: ownership, lifecycle/state machine, configurable keys, emitted events, and boundaries with neighbouring engines.
3. **Cite the doc explicitly** in your response. Format: `Per docs/engines/<engine>/<filename>.md §<section> ...`. A bare link is not enough — quote or paraphrase the specific rule you relied on.
4. **If the doc is silent** on the point you need, say so. Then either (a) propose an ADR in `docs/adr/`, or (b) escalate to the Engine Owner chat mode for that engine. Do **not** invent the rule.
5. **If the doc contradicts the code or contracts**, the doc wins until an ADR overrides it. Flag the contradiction.

## What you MUST NOT do

- Write engine-touching code without citing the engine doc.
- Paraphrase the doc loosely if the rule is precise (e.g. "I think the stake-lock window is around 24 hours" — read the doc, get the exact key and default).
- Cite `docs/Full Kalaanba Brief.md` as a substitute for the engine doc. The Brief is a high-level overview; the engine doc is canonical.
- Cite stale memory or training data about how the engine *probably* works. Read the file.
- Cross engine boundaries without consulting BOTH engines' docs (and `docs/engine-boundaries.md` if interaction patterns are involved).

## Companion docs (read alongside the engine doc when relevant)

- `docs/engine-boundaries.md` — cross-engine interaction patterns (what flows through events, what's forbidden).
- `docs/Architecture/System_Architecture.md` — the platform-wide architecture rules.
- `docs/Architecture/Build_Plan.md` — execution sequencing (which engines exist when).
- `docs/adr/` — past architectural decisions. Search before proposing a new approach.
- `docs/JOURNAL.md` (read-only — Sankofa owns writes) — historical decisions and reversals.
- `contracts/` — the binding spec for APIs, events, and config keys for the engine.

## Engine index (canonical doc location per engine)

| Engine | Doc folder |
|---|---|
| Club | `docs/engines/club/` |
| Player & Affiliation | `docs/engines/player-affiliation/` |
| Match / Fixture | `docs/engines/match-fixture/` |
| Season | `docs/engines/season/` |
| RP Economy | `docs/engines/rp-economy/` |
| Challenge | `docs/engines/challenge/` |
| Trust & Verification | `docs/engines/trust-verification/` |
| Zone | `docs/engines/zone/` |
| Venue / Surface / Booking | `docs/engines/venue-surface-booking/` |
| Referee / Officiator | `docs/engines/referee-officiator/` |
| Notification & Distribution | `docs/engines/notification-distribution/` |
| Fan Buzz / Feed / Discovery | `docs/engines/fan-buzz/` |
| Moderation & Safety | `docs/engines/moderation-safety/` |
| Admin Configuration & Governance | `docs/engines/admin-governance/` |
| Competition & Rules | `docs/engines/competition-rules/` |
| Awards & Recognition | `docs/engines/awards-recognition/` |
| Analytics, Insights & Intelligence | `docs/engines/analytics/` |

## Enforcement

- Implementer (Stage 6) pre-flight checklist will refuse to proceed without an engine-doc citation.
- QA Engineer (Stage 8) refuses sign-off if the implementation contradicts the cited doc.
- Architect (Stage 4) refuses approval if the change adds rules not present in the engine doc and no ADR justifies the gap.
- PR template includes an "Engine docs cited" checkbox.

## Refusal trigger

If you find yourself about to write engine code without having opened the engine doc this turn — **stop**. Open the doc. Then proceed.
