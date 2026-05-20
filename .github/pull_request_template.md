## Work Packet

- **WP ID:**
- **Primary engine:**
- **Secondary engines:**
- **Build Plan stage advanced:**

## Pipeline Sign-off

- [ ] 1. Intake (Product Steward)
- [ ] 2. Impact Map (Product Steward)
- [ ] 3. Rules Review (Engine Owner — per engine)
- [ ] 4. Architecture Check (Architect)
- [ ] 5. Contracts updated (Contract Designer)
- [ ] 6. Implementation (Implementer)
- [ ] 7. Security Review (Security Reviewer)
- [ ] 8. QA Plan + Tests (QA Engineer)
- [ ] 9. Docs Updated (Docs Scribe)
- [ ] 10. Release Packet (Release Captain)

## Engine Boundary Self-Check

- [ ] This PR does not cross engine boundaries except via events/contracts.
- [ ] No hardcoded configurable values.
- [ ] Audit/ledger entries created where required.
- [ ] Trust gates respected (no RP/standings/awards/stats without `result_confirmed` + Trust clearance).
- [ ] Moderation gates respected (no public output without Moderation clearance).
- [ ] Public/private visibility respected (no evidence/dispute/admin notes/minor data leakage).
- [ ] Idempotency-Key handled on all user-triggered writes.
- [ ] Money in integer minor units (pesewas) — no floats.
- [ ] RP changes via `rp_ledger` only — no in-place balance updates.
- [ ] Archive, don't delete.

## Craftsmanship Self-Check

Reference: [`.github/instructions/engineering-standards.instructions.md`](.github/instructions/engineering-standards.instructions.md)

**File & module shape**
- [ ] No source file exceeds 400 lines.
- [ ] No function exceeds 50 lines; no class exceeds 250 lines.
- [ ] Each file has a single responsibility.

**Naming & layering**
- [ ] Naming follows the standards (verbs for functions, booleans `is/has/can`, `engine.action` events, snake_case internal keys).
- [ ] Domain code is framework-agnostic; controllers are thin; repositories own queries.
- [ ] Frontend has no business calculation; UI consumes hooks, hooks consume the API client.

**Database**
- [ ] Every new FK is indexed.
- [ ] Hot-path queries have been `EXPLAIN`-ed; composite/partial indexes added where needed.
- [ ] Migrations are reversible and split (schema vs backfill).
- [ ] No cross-schema foreign keys.

**API**
- [ ] All new endpoints live under `/api/v1/` (or current major). No silent breaking changes.
- [ ] Standard envelope + error shape used.
- [ ] Pagination on every list endpoint.
- [ ] OpenAPI spec updated in `contracts/api/`.
- [ ] Generated TS client regenerated and committed.

**Events**
- [ ] Events follow `engine.action_past_tense` and carry the canonical envelope.
- [ ] `schema_version` bumped if payload shape changed.
- [ ] Producer writes outbox in same transaction as the domain write.
- [ ] Consumers are idempotent.

**Errors, logs, security**
- [ ] Typed domain exceptions; no `catch (\Throwable)` in domain.
- [ ] Structured JSON logs; no secrets/PII/OTPs/evidence URLs in logs.
- [ ] Inputs validated by FormRequest **and** Zod where relevant.

**Tests**
- [ ] ≥ 80% coverage on domain code touched.
- [ ] Contract test added for every new event and endpoint.
- [ ] Config-flip test added for every new config key.


## Contracts touched

- **Events:**
- **APIs:**
- **Config keys:**

## Migration & Rollback

- **Migration steps:**
- **Rollback plan:**
- **Compensating actions (if needed):**

## Risk

- **Risk level:** low | medium | high | critical
- **Reason:**

## Docs

- **Engine docs touched:**
- **ADRs created:**
- **Glossary terms added:**

## Sankofa

- [ ] Journal will be auto-updated by Sankofa on the next assistant turn after merge discussion. Do not hand-edit `docs/JOURNAL.md`.
