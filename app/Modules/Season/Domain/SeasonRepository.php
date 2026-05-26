<?php

declare(strict_types=1);

namespace Kalaanba\Modules\Season\Domain;

use DateTimeImmutable;

/**
 * Repository port — Domain owns the contract, Infrastructure binds the
 * Eloquent adapter. Constitution §1.1 — engine boundaries enforced via
 * port/adapter, never direct cross-schema joins.
 */
interface SeasonRepository
{
    /** Find by stable `code` (e.g. `2026/2027`). */
    public function findByCode(string $code): ?SeasonView;

    /** Find the season whose [starts_at, archive_window_ends_at] contains $at. */
    public function findContaining(DateTimeImmutable $at): ?SeasonView;

    /** Upsert (idempotent) from a window — used by the bootstrap seeder + tick command. */
    public function upsertFromWindow(SeasonWindow $window, SeasonPhase $initialPhase): SeasonView;

    /** Persist a phase transition. Returns the post-transition view. */
    public function recordPhaseTransition(string $seasonId, SeasonPhase $newPhase, DateTimeImmutable $occurredAt): SeasonView;
}
