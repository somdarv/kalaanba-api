---
mode: docs-scribe
description: Update docs, ADRs, glossary, changelog.
---
Update documentation for Work Packet ${input:wpId}.

Touch only:
- `docs/engines/<engine>/` if rules or behaviours changed.
- `docs/adr/NNNN-<slug>.md` if a new architectural decision was made.
- `docs/glossary.md` if new terms were introduced.
- The PR description with a one-line changelog entry referencing the WP ID.

Do NOT touch:
- `docs/JOURNAL.md` (Sankofa-owned).
- `PRODUCT.md`, `AGENTS.md`, `CLAUDE.md`, `README.md` unless explicitly required.

Output the Docs Update block defined in the chat mode.
