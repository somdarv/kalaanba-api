<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Touches `users.last_seen_at` once per minute for the authenticated user.
 *
 * Heartbeat is throttled via the cache layer (key `last_seen_at:{userId}`,
 * TTL = `$throttleSeconds`) so that high-frequency request bursts (Filament
 * Livewire polls, API spamming) do not hammer the users table with
 * UPDATEs. The DB write is skipped entirely when the cache key is set.
 *
 * Defensive: any DB / cache failure is swallowed so a heartbeat issue can
 * never break the actual request — the column is best-effort presence
 * information for operators, not auth state.
 */
class TouchLastSeenAt
{
    /** Minimum interval between persistent writes per user. */
    private const THROTTLE_SECONDS = 60;

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        try {
            $this->record($request);
        } catch (Throwable) {
            // best-effort; never break the request path.
        }

        return $response;
    }

    private function record(Request $request): void
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return;
        }

        $id = (string) $user->getAuthIdentifier();
        $key = "last_seen_at:{$id}";

        if (Cache::has($key)) {
            return;
        }

        Cache::put($key, 1, self::THROTTLE_SECONDS);

        $user->forceFill(['last_seen_at' => Carbon::now('UTC')])->save();
    }
}
