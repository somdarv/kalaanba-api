---
description: "Always-on engineering craftsmanship standards. Auto-loaded into every Copilot chat. Applies to all code written in either kalaanba-api or kalaanba-front. Read this BEFORE writing any code at Stage 6 (Implementation) and BEFORE reviewing at Stage 8 (QA)."
applyTo: "**"
---

# Engineering Standards (Craftsmanship Constitution)

These standards apply to **every line of code** in Kalaanba. They are layered **on top of** `copilot-instructions.md` (engine boundaries, configurability, Trust gates, etc.) — never in place of them. When the two conflict, `copilot-instructions.md` wins; otherwise both apply.

Treat every rule below as a hard rule unless the rule itself says "guideline" or "soft limit". If a task requires breaking a hard rule, the task is wrong — refuse and escalate to the Architect.

---

## 1. File Size & Module Shape

### Hard limits
- **No source file exceeds 400 lines.** If it would, split it. Tests count separately.
- **No function/method exceeds 50 lines** (excluding signature + braces). If it would, extract.
- **No class exceeds 250 lines.** If it would, the class has more than one responsibility — split.
- **No file has more than one default export.** Named exports preferred everywhere.

### Soft limits (guideline — justify in PR if exceeded)
- Functions ≤ 25 lines, ≤ 4 parameters.
- Classes ≤ 7 public methods.
- React components ≤ 200 lines of JSX + logic combined.

### When to split
A file is too big when **any of**:
- You scroll to find the function you're editing.
- Two unrelated concerns share the same file.
- A change to concern A forces re-reading concern B.
- Imports exceed ~20 lines.

---

## 2. Naming

- **Verbs for functions/methods, nouns for variables/classes.** `calculateStake()`, not `stake()`.
- **No abbreviations except project-canonical ones** (RP, WP, ADR, OTP, TTL, DAU, KPI). Spell out `idempotency`, `repository`, `competition` — never `idem`, `repo` (the variable, not the git repo), `comp`.
- **Booleans start with `is`, `has`, `can`, `should`** — `isVerified`, `hasTrustClearance`, `canIssueChallenge`, `shouldDistribute`.
- **Internal keys are `snake_case` strings** (`result_confirmed`, `stood_ground`). Display strings are configurable labels, never used in logic.
- **Event names follow `engine.action`** — `match.result_confirmed`, `rp.locked`, `challenge.stake_locked`.
- **DB columns are `snake_case`.** Models / DTOs use the language's idiom (camelCase in TS, snake_case in PHP property names that map to columns is acceptable; PHP method names camelCase).
- **No Hungarian notation.** No type prefixes (`strName`, `iCount`). No `I` prefix on interfaces in TS.
- **One name per concept.** If you call it `wallet` here and `purse` there, pick one and rename.

---

## 3. Reusability & Composition

### Backend (Laravel)
- **Domain logic lives in `app/Modules/<Engine>/Domain/`.** It is **framework-agnostic** — no Eloquent, no HTTP, no Facades inside domain classes.
- **Application services** (`Application/`) orchestrate domain + persistence + events. One public method per use case.
- **Repositories** (`Infrastructure/`) are the only place Eloquent is touched outside of read-projections.
- **HTTP controllers** (`Http/Controllers/`) are thin: validate → call application service → return resource. Never more than ~30 lines.
- **Reusable cross-module code goes in `app/Support/`** (e.g. `Money`, `IdempotencyKey`, `OutboxEnvelope`). It must not depend on any engine module.
- **DRY at the domain level, WET at the boundary.** Two controllers may legitimately repeat 3 lines of validation — that's fine. Two domain rules being almost-duplicates is a bug.

### Frontend (Next.js)
- **Components live in `src/components/<area>/`.** Three flavors:
  1. `ui/` — pure, presentational, no data fetching, no business logic. Reusable across the app.
  2. `<feature>/` — feature-bound containers that compose `ui/` primitives and call hooks.
  3. `site/` — top-level shell pieces (header, footer, banners).
- **Hooks live in `src/hooks/`** and own data-fetching + state shape. Components consume hooks; they don't call `fetch` directly.
- **API client lives in `src/lib/api/`.** Generated from OpenAPI where possible. Components never construct URLs or set headers.
- **No business calculation in the UI.** The backend owns truth (Constitution Law 3). UI formats and displays.
- **Server components by default.** Add `"use client"` only when a component actually needs interactivity or browser APIs.

### Rule of three
A pattern repeated **3 times** must be extracted. Twice is a coincidence; three times is duplication.

### Avoid premature abstraction
Do not introduce a base class, generic, or shared util to support a single caller. Wait until the second caller arrives and the third is foreseeable.

---

## 4. Backend Best Practices (Laravel 11 + PHP 8.3)

- **Strict types on.** Every PHP file starts with `declare(strict_types=1);`.
- **Constructor property promotion** for DTOs and value objects. `readonly` for immutables.
- **Enums for state machines.** No string-typed status columns without a backing PHP enum.
- **`Model::preventLazyLoading()`, `preventAccessingMissingAttributes()`, `preventSilentlyDiscardingAttributes()`** enabled outside production.
- **Eager-load relationships at the query site.** N+1 is treated as a bug, not a perf tweak.
- **No facades inside domain code.** Inject dependencies via the constructor.
- **No `Auth::user()` inside services.** Pass the user/actor as an explicit argument.
- **Form Requests for validation.** Controllers do not call `$request->validate()` inline.
- **API Resources for serialization.** Never return raw Eloquent models from a controller.
- **Database transactions are explicit** (`DB::transaction(fn () => ...)`), and the outbox write is **inside the same transaction** as the domain write.
- **Idempotency**: every user-triggered write checks `Idempotency-Key` against a Redis dedupe store (24h TTL) before doing work.
- **Money is `App\Support\Money`** wrapping integer pesewas. Never raw int, never float.
- **Time is `Carbon` UTC end-to-end.** Convert to display tz only at the API boundary.
- **No global state** (no static caches, no singletons that hold mutable state).
- **Queues**: default queue is for ordinary work; named queues for distinct SLAs (`integrity`, `notifications`, `awards`, `analytics`).

---

## 5. Frontend Best Practices (Next.js 15 + TS strict)

- **TypeScript strict mode is mandatory.** No `any` without a `// eslint-disable-next-line` and a one-line justification.
- **Zod schemas at every IO boundary** (form input, API response parsing). Generated types from OpenAPI for the happy path; Zod for runtime validation where the source is untrusted.
- **React Hook Form for every form.** No ad-hoc `useState` form management.
- **TanStack Query for every server-state read.** No `useEffect(() => fetch(...), [])`.
- **No global state libraries** (Redux, Zustand) until a real cross-cutting case appears. Server state ≠ client state.
- **Suspense + streaming** for slow data on server components; skeletons (KxSkeleton) for client.
- **Accessibility is non-negotiable**: every interactive element keyboard-reachable, every image with meaningful content has alt text, color is never the only signal, focus rings preserved.
- **`next/image` for raster images.** Never raw `<img>` for content images.
- **`next/link` for internal navigation.** Never `<a href>` for internal routes.
- **No inline styles for design tokens.** Use Tailwind utilities mapped to the `--kx-*` token system in `globals.css`.
- **One responsibility per component.** A `MatchCard` displays; it does not fetch + display + open modals + log analytics.

---

## 6. Database & Data Layer

### Schema discipline
- **Every table has** `id` (UUIDv7 preferred), `created_at`, `updated_at`. Soft-deletable tables have `archived_at` (never `deleted_at` semantically — see Constitution Law 13).
- **Schema-per-engine.** Tables live in `<engine>.table_name`. No cross-schema foreign keys (Constitution Law 1; enforced in CI).
- **Naming**: tables plural snake_case (`match_events`), columns snake_case, FK columns `<entity>_id`.
- **Enums in DB are Postgres enum types** when the set is stable; `varchar` + CHECK when evolving.
- **Append-only tables** (`rp_ledger`, `outbox_events`, `admin_audit_log`, `analytics_events`) have no `updated_at` and no UPDATE/DELETE permission for the application role.

### Indexing
- **Index every foreign key.** Always.
- **Index every column used in a `WHERE`, `ORDER BY`, or `JOIN` on a hot path.** Run `EXPLAIN` before merging if the query is on a hot read.
- **Composite indexes for compound queries** — put the most-selective column first, the column used in equality before range.
- **Partial indexes** for columns that are mostly null or where a filter is always applied (e.g. `WHERE archived_at IS NULL`).
- **Avoid over-indexing**: each index slows writes. If an index isn't used in `pg_stat_user_indexes` after 30 days in production, drop it.
- **Time-series tables are partitioned** monthly (`analytics_events`, `admin_audit_log`).

### Migrations
- **One concern per migration.** A migration adds a column **or** an index **or** a constraint — not three at once.
- **Migrations are reversible.** `down()` is implemented and tested. If truly irreversible, document why and provide a manual rollback in the PR.
- **Backfills are separate migrations from schema changes** so they can be re-run independently.
- **No `ALTER TABLE` on hot tables without** `CONCURRENTLY` or a documented maintenance window.
- **Never edit a migration that has been merged to main.** Add a new one.

### Queries
- **No raw SQL inside controllers or domain code.** Repositories own queries.
- **No N+1.** Use `with()` (Laravel) eager loads or DataLoader-style batching.
- **Pagination is mandatory** on every list endpoint. Default page size 25, max 100.
- **Read-write split**: read-heavy endpoints use the replica connection; writes always go to primary.

---

## 7. API Design & Versioning

### URL & versioning
- **All public APIs live under `/api/v1/`.** Breaking changes go to `/api/v2/` — never silently changed under `/v1/`.
- **Versions are additive within a major.** Adding fields is non-breaking. Removing or changing the type of a field requires a new major.
- **Deprecate, don't delete.** Mark old endpoints with `Deprecation` and `Sunset` HTTP headers. Keep them alive for ≥ 90 days after the replacement ships.

### Request/response shape
- **REST verbs map cleanly**: GET (read), POST (create), PATCH (partial update), PUT (full replace — rare), DELETE (archive, not delete).
- **Resource URLs are plural nouns**: `/api/v1/matches`, `/api/v1/matches/{id}`, `/api/v1/matches/{id}/events`.
- **Filtering is via query params**, not nested routes: `/api/v1/matches?status=result_confirmed`.
- **Every response has the same envelope**:
  ```json
  { "data": ..., "meta": { "request_id": "...", "api_version": "v1" } }
  ```
- **Error envelope is standardized** (defined in `contracts/api/`):
  ```json
  { "error": { "code": "rp.insufficient_balance", "message": "...", "details": { ... }, "request_id": "..." } }
  ```
- **Error codes are stable strings** (`engine.specific_reason`). Never expose stack traces or raw exception messages.

### Headers
- **`Idempotency-Key` is required** on every POST/PATCH/PUT that mutates state. Server rejects with `400` if absent on writes.
- **`Authorization: Bearer <token>`** for authenticated calls.
- **`Accept-Language`** respected for configurable labels (logic stays on internal keys).
- **`X-Request-Id`** generated server-side if not provided, echoed on every response.

### Contracts
- **Every endpoint has an OpenAPI 3.1 spec** in `contracts/api/<engine>/`.
- **The TS client is generated** — never hand-written. CI fails if generated client drifts from spec.
- **Backwards-compatibility tests** run against the previous major's contract on every PR.

### Rate limiting & pagination
- **All public endpoints are rate-limited.** Tiers: anonymous (lowest), authenticated, internal (highest).
- **Pagination uses `?cursor=<opaque>&limit=<n>`** for hot endpoints (feeds, ledgers). Offset pagination only for admin views.
- **Hot list endpoints expose `etag` + honor `If-None-Match`.**

---

## 8. Events & Outbox

- **Event name format**: `engine.action_past_tense` (`match.result_confirmed`, `rp.transferred`, `challenge.stake_locked`).
- **Every event carries the canonical envelope** defined in `contracts/events/README.md`: `event_id` (UUIDv7), `event_name`, `schema_version`, `occurred_at`, `actor_id`, `actor_role`, `source`, `payload`.
- **Events are versioned.** Adding fields to payload is non-breaking. Removing or retyping requires `schema_version` bump and a parallel consumer migration.
- **Producers write to the outbox in the same DB transaction as the domain write.** Outbox relay publishes to Redis Streams. Consumers are idempotent (dedupe by `event_id` + `listener_name`).
- **Never publish directly from a domain method.** Always through the outbox.

---

## 9. Configuration

- **Every configurable value is registered in `contracts/config/<engine>/<key>.yaml`** with: `key`, `engine`, `scope`, `value_type`, `default`, `approval_level`, `description`.
- **Code reads via `Config::get(key, scope, at?)`** (Laravel side). Never `env()` outside `config/*.php`. Never `process.env` outside `next.config.ts` or env loaders.
- **No magic numbers, ever.** If you see a literal `5`, `30`, `0.05`, `100` in domain code, it's a config key in disguise.
- **Effective-dated reads.** Past values must be reconstructable: when computing historical RP, read the config value that was effective at the match's `occurred_at`.

---

## 10. Error Handling & Logging

- **Exceptions are typed** — domain exceptions extend a base `DomainException`. Infrastructure failures extend `InfrastructureException`. Don't throw raw `\Exception`.
- **Catch narrowly.** Never `catch (\Throwable $e)` in domain code; do it only at the HTTP/queue boundary.
- **Log structured JSON.** Every log line has `timestamp`, `level`, `request_id`, `event`, `context`. No `dd()`, no `var_dump`, no `console.log` in committed code.
- **Never log secrets, PII, OTPs, phone numbers, or evidence URLs.** Phone numbers must be hashed or last-4-only.
- **Sentry captures unhandled errors automatically.** Re-throw after enrichment; don't swallow.
- **User-facing errors are configurable strings.** Internal codes (`rp.insufficient_balance`) are stable; messages can be translated/relabeled.

---

## 11. Security (layered on Constitution + OWASP Top 10)

- **Every endpoint has explicit authorization** via Policies (Laravel) or middleware. No "implicitly public" routes.
- **Mass assignment is blocked**: `$fillable` is empty by default; use explicit DTOs.
- **CSRF on stateful endpoints.** Sanctum SPA mode for first-party Next app.
- **Rate limiting on auth + OTP endpoints** — strict (5/min by IP and by phone).
- **Inputs validated by Form Request (PHP) + Zod (TS).** Two layers, not one.
- **R2 evidence bucket is private** — access only via signed URLs (5-min TTL), audit-logged.
- **Signed URLs never appear in logs.**
- **Secrets via `.env` + Vault.** Never committed. CI scans for leaked secrets.
- **Dependencies kept current**: Dependabot + Renovate. Critical CVEs patched within 7 days.
- **SQL injection impossible**: always parameterized queries via the ORM or `DB::statement(..., $bindings)`.
- **XSS impossible on Next**: never `dangerouslySetInnerHTML` with user input; if necessary, sanitize with `DOMPurify`.

---

## 12. Testing

- **Pyramid: many unit, fewer integration, few e2e.**
- **Unit tests are deterministic.** No real clocks, no real network, no real filesystem. Inject a `Clock`, mock the gateway, use in-memory storage.
- **Integration tests live in `tests/Feature/`** (Laravel) and exercise the full stack including DB + outbox + listener.
- **Contract tests** validate every event payload and every API response against the schemas in `contracts/`.
- **Config-flip tests** prove behavior changes when a config value changes — no recompile.
- **Architecture tests** (Pest Architecture / Deptrac) enforce boundaries in CI.
- **Coverage threshold: 80% lines on domain code.** Below that fails CI. Infra/HTTP shells are exempt.
- **Test names describe behavior**: `it("locks stake when both parties confirm the challenge")`. Not `testHappyPath()`.

---

## 13. Performance

- **Public read paths target p95 < 200ms** (cached) / < 500ms (uncached).
- **Write paths target p95 < 400ms** end-to-end including outbox write.
- **Every hot read is cached** — Cloudflare edge for public pages, Redis for application-level. Cache keys are namespaced (`kx:standings:v1:competition:{id}`).
- **Invalidation is explicit** — emit a `cache.invalidate` event on writes that change cached data; ISR pages revalidate by tag.
- **No synchronous outbound HTTP in a request lifecycle.** Outbound calls go via queue/job with retries.
- **Bulk operations chunk** — never `SELECT * FROM thousands_table->all()` in memory.

---

## 14. Observability

- **Every request has a `request_id`** propagated through logs, events, and downstream calls.
- **OpenTelemetry traces** every HTTP request and queue job.
- **Metrics emitted at every boundary**: HTTP latency, queue depth, outbox lag, cache hit rate, DB pool saturation.
- **Dashboards are owned per-engine.** Each engine doc links to its Grafana dashboard.
- **Alerts fire on SLOs, not on raw metrics.** "p95 > 1s for 5 min" not "one slow request."

---

## 15. Git & PR Discipline

- **Branch names**: `wp/<wp-id>-<slug>` (e.g. `wp/WP-20260520-stood-ground-cost`).
- **Commits are imperative present tense**: "Add stood-ground cost config key", not "Added" or "Adds".
- **One concern per commit** when feasible; rebase to clean up before review.
- **No commits to `main` directly.** All work goes via PR with the 10-stage checklist filled.
- **PRs stay small.** Soft limit: 400 lines diff (excluding generated files and snapshots). Larger PRs require a justification in the description.
- **Never `git push --force` to a shared branch** without a heads-up.
- **Never bypass pre-commit / pre-push hooks** (`--no-verify`).

---

## 16. Documentation Discipline

- **Code is for humans first.** A function whose intent isn't obvious from its name + 5 lines of body needs a one-line docblock describing **why**, not what.
- **No commented-out code.** Delete it; git remembers.
- **README per engine** in `docs/engines/<slug>/` is the canonical entry point.
- **ADRs for any non-obvious architectural decision** in `docs/adr/`. Don't ship architecture in code comments.
- **Glossary additions** when introducing a new term.
- **Sankofa owns `docs/JOURNAL.md`.** Hands off.

---

## 17. Refusal Triggers

You **MUST refuse** to write code that:
- Hardcodes a configurable value.
- Crosses engine module boundaries directly.
- Updates a balance in place instead of appending to a ledger.
- Uses a float for money.
- Skips `Idempotency-Key` on a user-triggered write.
- Deletes a domain record (must archive).
- Bypasses Trust/Moderation gates.
- Adds an endpoint outside `/api/v1/` (or the current major).
- Returns a raw Eloquent model from a controller.
- Adds business logic to the frontend that shadows backend truth.
- Logs secrets, OTPs, evidence URLs, or unhashed phone numbers.

If asked to do any of the above, explain which rule applies and propose the compliant alternative.

---

## 18. How this file is enforced

- **Auto-loaded** in every Copilot chat in both repos via `applyTo: "**"`.
- **Referenced** by the Implementer chat mode (Stage 6) pre-flight checklist.
- **Audited** by the Security Reviewer (Stage 7) and QA Engineer (Stage 8).
- **CI** runs static checks: Deptrac, Pest Architecture, OpenAPI lint, file-length lint (a custom Pest test counting LOC per file), and dependency vuln scan.
- **PR template** includes a "Craftsmanship self-check" section.

If a rule above is wrong for a real case, propose an ADR rather than violating it silently.
