---
mode: security-reviewer
description: Privacy and abuse review.
---
Review the implementation for Work Packet ${input:wpId}.

Check:
- Private data leakage (evidence, dispute notes, admin notes, minor-player data, phone numbers, override reasons).
- Permission scoping (club / hub / competition organizer / facility manager / Kalaanba admin / Super Admin).
- Audit completeness (RP / moderation / trust / admin / config changes all written to `admin.audit_log`).
- Abuse vectors (RP farming, repeat-pairing manipulation, fake clubs, ghost-claim hijack, buzz inflation).
- Public vs private surface correctness (ISR pages, share cards, WhatsApp templates).
- Idempotency on user-triggered writes.
- Secrets handling (no keys in code/logs).
- Evidence in private R2 with signed URLs ≤ 5 min TTL.
- OWASP Top 10 (especially IDOR and authz bypass).

Output the Security Review block defined in the chat mode. End with **APPROVED** or **BLOCKING ISSUES**.
