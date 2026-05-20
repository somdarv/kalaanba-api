---
mode: implementer
description: Implement a Work Packet against approved contracts.
---
Implement Work Packet ${input:wpId}.

## Pre-flight checklist (refuse to proceed if any fail)
- [ ] Architect approved
- [ ] Engine Owner(s) approved
- [ ] Contracts exist in `contracts/` and are versioned
- [ ] Config keys registered
- [ ] No hardcoded values planned
- [ ] Audit logging planned where required (RP / moderation / trust / admin)
- [ ] Events to emit identified
- [ ] Idempotency strategy chosen (`Idempotency-Key` header + Redis dedupe)

## Implementation rules
- Backend in `kalaanba-api/app/Modules/<Engine>/...` — respect the module shape (`Domain/Application/Infrastructure/Http/Listeners/Jobs/Contracts/Policies/Config/Tests`).
- Schema-per-module migrations. No cross-schema foreign keys.
- Cross-engine effects go through outbox events only.
- Frontend in `kalaanba-front/src/...` (Next.js 15 App Router). Never compute backend truth client-side.
- RP changes append to `rp_ledger`; balance is a projection.
- Money in integer minor units (pesewas).
- Archive, don't delete.

Then implement, including tests scaffolded for the QA Engineer stage. Cite each file you touched.
