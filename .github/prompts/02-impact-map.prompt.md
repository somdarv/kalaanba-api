---
mode: product-steward
description: Map every engine and contract touched by a Work Packet.
---
Given Work Packet ${input:wpId}, produce an Impact Map.

## Impact Map
| Engine | Read | Write | Events emitted | Events consumed | Config keys | Notes |
|---|---|---|---|---|---|---|

Then list:
- Cross-engine risks (e.g. Buzz writing RP — forbidden).
- Trust / RP / Moderation gates that apply.
- ADRs that may be required.
- Idempotency-Key strategy.
- Outbox events vs in-app inbox vs WhatsApp distribution.
- Build Plan tasks (cite by stage + phase + bullet text).
