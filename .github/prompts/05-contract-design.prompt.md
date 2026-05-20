---
mode: contract-designer
description: Design or update contracts for a Work Packet.
---
For Work Packet ${input:wpId}, design or update:

- **Event schemas** in `contracts/events/<engine>/<event>.v<N>.json`
- **API contracts** in `contracts/api/<engine>/<endpoint>.v<N>.yaml` (OpenAPI fragments)
- **Config keys** in `contracts/config/<engine>/<key>.json`

Rules:
- Event names: `engine.action` (e.g. `match.result_confirmed`, `trust.match_cleared`, `rp.transferred`, `challenge.accepted`).
- Version everything. If breaking, bump major version and write a migration note.
- Every config key declares: scope (platform/season/hub/zone/competition/entity), value type, default, approval level (Low/Med/High/Critical), description.
- Use stable internal keys; labels are configurable separately.

Output the per-contract format defined in the Contract Designer chat mode.
