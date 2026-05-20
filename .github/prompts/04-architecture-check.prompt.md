---
mode: architect
description: Architecture review for a Work Packet.
---
Review Work Packet ${input:wpId}.

Confirm:
- Engine boundaries respected (no cross-schema joins, no FanBuzz writes to RP/Match/Competition).
- Event flows correct (outbox pattern, Redis Streams transport, idempotent listeners).
- Read/write ownership clear.
- Trust + Moderation gates positioned correctly.
- Idempotency-Key handling on all user-triggered writes.
- Discipline Rules from `docs/Architecture/Build_Plan.md` honoured.

Propose an **ADR** in `docs/adr/NNNN-<slug>.md` if any new architectural pattern, boundary change, or cross-engine contract is introduced.

Output the **Architecture Review** block defined in the architect chat mode.
