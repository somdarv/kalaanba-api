# Kalaanba — Modules

Each engine lives in its own module under this folder. The folder name is the **engine slug** matching `docs/engines/<slug>/`.

## Standard layout per module

```
app/Modules/<EngineName>/
├── Domain/          # Framework-agnostic. Entities, value objects, domain services, domain events. NO Laravel imports.
├── Application/     # Use cases / application services. Orchestrate Domain + Infrastructure. Thin.
├── Infrastructure/  # Eloquent models, repositories, external adapters, queue jobs, outbox publishers.
└── Http/            # Controllers, FormRequests, API Resources, routes. Thin. Maps HTTP <-> Application.
```

## Naming

- Module folder: PascalCase matching the engine name. Example: `Club`, `RpEconomy`, `TrustVerification`, `MatchFixture`.
- Namespace: `Kalaanba\Modules\<EngineName>\<Layer>\...` (PSR-4 mapped in `composer.json`).
- The doc slug under `docs/engines/<slug>/` is kebab-case; the module folder is PascalCase. Map: `rp-economy` → `RpEconomy`, `match-fixture` → `MatchFixture`, etc.

## Boundaries (enforced by Deptrac)

- `Http` may depend on `Application`, `Domain`.
- `Application` may depend on `Domain`, `Infrastructure` (via interfaces).
- `Domain` depends on nothing — pure PHP, no `Illuminate\*`.
- `Infrastructure` may depend on `Domain`. It implements repository interfaces declared in `Domain`.
- **No module may directly import another module's `Domain`, `Application`, or `Infrastructure`.** Cross-module talk only via:
    1. Outbox events (Redis Streams).
    2. Shared contracts under `Kalaanba\Support\Contracts` (interfaces only).
    3. Explicit consumer subscriptions in `Application/Subscribers/`.

## Cross-cutting

- `app/Support/` (namespace `Kalaanba\Support\`) holds things shared by ≥3 modules. Currently empty.
- `app/Support/Money/` will hold the integer-minor-units value object (per `engineering-standards.instructions.md` §4).

## Adding a new module

1. Create the engine doc folder at `docs/engines/<slug>/` if it doesn't exist (it should — all 17 engines are pre-stubbed).
2. Create `app/Modules/<PascalName>/{Domain,Application,Infrastructure,Http}/`.
3. Register the module's service provider in `bootstrap/providers.php` (one provider per module).
4. Add the module's routes file under `Http/routes.php` and load it from the provider.
5. Add Deptrac layer rules in `deptrac.yaml`.
6. Add a Pest Architecture test ensuring the module's `Domain` has zero `Illuminate\*` imports.

See also:

- `docs/engines/<slug>/` — canonical engine doc (mandatory citation per `.github/instructions/engine-docs-mandatory.instructions.md`).
- `docs/engine-boundaries.md` — cross-engine flow rules.
- `.github/instructions/engineering-standards.instructions.md` §3, §4 — layering and reusability rules.
