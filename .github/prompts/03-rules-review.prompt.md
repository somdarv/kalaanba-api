---
mode: engine-owner
description: Validate the change against an engine's locked rules.
---
For engine **${input:engine}**, review Work Packet ${input:wpId}.

1. Load `docs/engines/${input:engine}/` (read every file).
2. List every rule that applies to this change.
3. State whether the change respects each rule (cite file + section).
4. List invariants that must hold after the change.
5. List any rule violation (blocking).
6. List configurability gaps (values that should live in admin config but don't yet).
7. Confirm events your engine will emit and consume for this change.

End with: **APPROVED** or **CHANGES REQUESTED** plus reasons.
