---
mode: product-steward
description: Convert a raw request into a Work Packet.
---
Take the following raw request and produce a Work Packet.

Raw request:
${input:request}

Output exactly this structure:

## Work Packet
- **ID:** WP-YYYYMMDD-<slug>
- **Title:**
- **Problem:**
- **User value:**
- **Primary engine:**
- **Secondary engines:**
- **Public surfaces affected:**
- **Private surfaces affected:**
- **Configurable values involved:**
- **Build Plan stage(s) advanced:**
- **Success criteria:**
- **Out of scope:**
- **Open questions:**

Then list the **next stage** and which chat mode should handle it (likely Engine Owner per affected engine, then Architect).
