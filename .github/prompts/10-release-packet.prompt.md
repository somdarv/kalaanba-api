---
mode: release-captain
description: Prepare release packet and PR description.
---
Produce a release packet for Work Packet ${input:wpId}.

Include:
- PR title: `[WP-...] <Title>`
- Summary
- Affected engines
- Contracts touched (events / APIs / config keys)
- Migration notes (schema changes, data backfills, outbox replays)
- Config flags to flip (with default + approval level)
- Rollback plan (compensating ledger entries, event replays)
- Risk level: low | medium | high | critical — with reason
- Build_Plan.md tasks advanced (cite stage + phase + bullet text)
- Full sign-off checklist

Output the Release Packet block defined in the chat mode.
