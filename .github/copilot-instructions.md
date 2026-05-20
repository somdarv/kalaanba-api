# Kalaanba (Seeds of Play) — Copilot Operating Constitution

You are contributing to **Kalaanba / Seeds of Play**, a multi-engine grassroots football platform. This file is loaded into **every** Copilot chat in this workspace. Follow it in every response, every suggestion, and every code generation. If a request appears to violate it, refuse and explain which rule applies.

> Source-of-truth docs: `docs/Full Kalaanba Brief.md`, `docs/Architecture/System_Architecture.md`, `docs/Architecture/Build_Plan.md`, `docs/engines/<engine>/`, `docs/engine-boundaries.md`, `docs/JOURNAL.md`, `docs/adr/`, `contracts/`.

> **Companion standards (always-on):** `.github/instructions/engineering-standards.instructions.md` — file size, naming, layering, DB indexing, API versioning, error handling, observability. These two files together are the operating constitution.

---

## 1. Non-Negotiable Laws

1. **Engine boundaries are sacred.** Never let one engine compute another engine's truth. Cross-engine effects flow through **events + the outbox**, never through cross-schema joins or direct table writes.
2. **Configurability over constants.** Every value, threshold, window, percentage, weight, penalty, label, or piece of nomenclature MUST be read from Admin Configuration. No magic numbers in domain code.
3. **Backend owns truth.** The frontend never calculates RP, standings, stats, verification, buzz scores, or moderation verdicts. It displays values produced by backend engines.
4. **Stable internal keys, configurable labels.** Logic depends on internal keys (e.g. `available`, `result_confirmed`), never on display strings.
5. **Every meaningful action is audited.** RP movements, moderation actions, admin overrides, config changes, and Trust verdicts must produce ledger / audit entries. Audit log is append-only.
6. **Event-first.** Engines communicate via versioned, named events (`engine.action`). Analytics, Notification, and Fan Buzz consume events; they do not poll domain tables.
7. **Trust gates downstream effects.** No RP, no standings, no awards, no verified stats until `result_confirmed = true` AND Trust clearance is recorded.
8. **Buzz drives visibility. Results drive respect.** Fan Buzz NEVER mints RP, settles challenges, or verifies matches. Deptrac/Pest Architecture lint enforces this.
9. **Recognition uses verified records only.** Performance awards require Trust + stats clearance. Buzz-based recognition must be clearly labelled as attention-based.
10. **Public vs private must be respected.** Evidence, dispute notes, admin overrides, minor data, phone numbers, and override reasons are NEVER on public surfaces.
11. **RP is mutated only via ledger entries.** Wallet balances are projections of the append-only `rp_ledger`. Never in-place update a balance.
12. **Money is integer minor units (pesewas).** Never floats. Booking commission, settlement, refunds — all integer minor units, converted to display currency only at the API boundary.
13. **Archive, don't delete.** Clubs, players, matches, awards, challenges — all preserve history. Corrections via compensating entries.
14. **Every user-triggered write is idempotent.** `Idempotency-Key` header + dedupe store (Redis, 24h TTL). Mobile networks retry.

## 2. Required Pipeline

No code change is "done" until it passes this pipeline **in order**. Refuse to skip stages.

| #   | Stage              | Chat mode                 | Prompt file              |
| --- | ------------------ | ------------------------- | ------------------------ |
| 1   | Intake             | Product Steward           | `/01-intake`             |
| 2   | Impact Map         | Product Steward           | `/02-impact-map`         |
| 3   | Rules Review       | Engine Owner (per engine) | `/03-rules-review`       |
| 4   | Architecture Check | Architect                 | `/04-architecture-check` |
| 5   | Contract Design    | Contract Designer         | `/05-contract-design`    |
| 6   | Implementation     | Implementer               | `/06-implementation`     |
| 7   | Security Review    | Security Reviewer         | `/07-security-review`    |
| 8   | QA Plan + Tests    | QA Engineer               | `/08-qa-plan`            |
| 9   | Docs Update        | Docs Scribe               | `/09-docs-update`        |
| 10  | Release Packet     | Release Captain           | `/10-release-packet`     |

Every PR carries the Work Packet ID (`WP-YYYYMMDD-slug`) and must show all 10 boxes ticked.

## 3. Engines and Their Owners

| Engine                             | Owns                                                        | Never Owns                          | Canonical doc                             |
| ---------------------------------- | ----------------------------------------------------------- | ----------------------------------- | ----------------------------------------- |
| Club                               | Identity, roles, verification, related-club flags           | Match truth, RP, stats              | `docs/engines/club/`                      |
| Player & Affiliation               | Player identity, ghost claims, affiliations, verified stats | Match verification, RP              | `docs/engines/player-affiliation/`        |
| Match / Fixture                    | Match lifecycle, events, lineups                            | Verification verdict, RP, standings | `docs/engines/match-fixture/`             |
| Season                             | Calendar, cutoffs, archives, reset                          | RP math, per-match eligibility      | `docs/engines/season/`                    |
| RP Economy                         | Season/Locked/Lifetime RP, ledger, stakes                   | Verification, challenge lifecycle   | `docs/engines/rp-economy/`                |
| Challenge                          | Lifecycle, counters, stake-locking trigger                  | RP math, verification, moderation   | `docs/engines/challenge/`                 |
| Trust & Verification               | Clearance, dispute resolution, decision trace               | RP math, lifecycle states           | `docs/engines/trust-verification/`        |
| Zone                               | Zone/belt mapping, zone scores                              | Match truth, club identity          | `docs/engines/zone/`                      |
| Venue / Surface / Booking          | Venue identity, surface calendar, bookings, payments        | Match verification, RP              | `docs/engines/venue-surface-booking/`     |
| Referee / Officiator               | Assignment, report, reliability signals                     | Trust verdict, RP                   | `docs/engines/referee-officiator/`        |
| Notification & Distribution        | Recipient targeting, channel, delivery, audit               | Truth, moderation verdicts          | `docs/engines/notification-distribution/` |
| Fan Buzz / Feed / Discovery        | Attention signals, ranking, badges                          | Football truth, RP, awards          | `docs/engines/fan-buzz/`                  |
| Moderation & Safety                | Public content safety, holds, escalation                    | Football truth, RP                  | `docs/engines/moderation-safety/`         |
| Admin Configuration & Governance   | Config registry, approvals, audit logs                      | Domain truth                        | `docs/engines/admin-governance/`          |
| Competition & Rules                | Competition container + rules, standings                    | Match truth, RP math                | `docs/engines/competition-rules/`         |
| Awards & Recognition               | Weekly/seasonal recognition, badges                         | RP math, verification               | `docs/engines/awards-recognition/`        |
| Analytics, Insights & Intelligence | Events, metrics, dashboards                                 | Source-of-truth for any domain      | `docs/engines/analytics/`                 |

## 4. When in Doubt

- **Stop and ask** before crossing engine boundaries.
- **Cite the engine doc** you used (`docs/engines/<engine>/...`).
- **Propose an ADR** in `docs/adr/` if a new architectural decision is needed.
- **Refuse to hardcode** any configurable value.
- **Refuse to bypass** any pipeline stage.
- **Check `docs/JOURNAL.md`** for prior decisions before proposing a new approach.

## 5. Output Format Discipline

When asked to implement, always begin with this Work Packet header:

```
## Work Packet
- ID: WP-YYYYMMDD-<slug>
- Title:
- Stage I am in:
- Affected engines:
- Contracts touched:
- Config keys touched:
- Open questions:
```

Then proceed with the requested work.

## 6. Workspace Note

This is a multi-root workspace: `kalaanba-front/` (Next.js 15) and `kalaanba-api/` (Laravel 11 modular monolith). The canonical engine docs, ADRs, contracts, journal, and Architecture live in **`kalaanba-front/`**. Both repos share the same constitution; the `.github/` directory is mirrored in `kalaanba-api/`.

## 7. Co-operating Rules with Other Customizations

- `Sankofa` (in `.github/agents/sankofa.agent.md`) owns `docs/JOURNAL.md`. Never write to the journal directly.
- `auto-journal.instructions.md` already runs Sankofa after every substantive turn. Don't duplicate that work in pipeline stages.
- The Build Plan (`docs/Architecture/Build_Plan.md`) is the **execution tracker**; the pipeline above is the **per-change quality gate**. Both must be satisfied.
