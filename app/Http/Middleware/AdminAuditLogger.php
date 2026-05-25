<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Admin\AdminAuditLog;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Kalaanba\Support\Auth\Role;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * AdminAuditLogger — write-side of Constitution L5 for the God Mode panel.
 *
 * Captures one append-only `admin_audit_log` row per mutating request that
 * traverses the `/admin` panel. Read-only GETs are ignored to keep the
 * ledger focused on actions that changed state.
 *
 * Redaction rules (security-required):
 *  - `password`, `password_confirmation`, `current_password`,
 *    `token`, `remember_token`, `api_token`, `secret` keys → `[redacted]`
 *  - request body limited to first 8 KB after redaction
 *  - never logs file uploads
 *
 * Failure isolation: a logging failure NEVER fails the user request. We
 * swallow the throwable so the panel keeps working even if the audit
 * connection is unavailable.
 */
class AdminAuditLogger
{
    /** @var array<int, string> */
    private const REDACTED_KEYS = [
        'password',
        'password_confirmation',
        'current_password',
        'token',
        'remember_token',
        'api_token',
        'secret',
        '_token',
    ];

    /** @var array<int, string> */
    private const MUTATING_METHODS = ['POST', 'PUT', 'PATCH', 'DELETE'];

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->headers->has('X-Request-Id')) {
            $request->headers->set('X-Request-Id', (string) Str::uuid());
        }

        /** @var Response $response */
        $response = $next($request);

        if (! in_array($request->getMethod(), self::MUTATING_METHODS, true)) {
            return $response;
        }

        $this->record($request, $response);

        return $response;
    }

    private function record(Request $request, Response $response): void
    {
        try {
            $user = $request->user();
            if ($user === null) {
                return;
            }

            $actorId = (string) ($user->getAuthIdentifier() ?? '');
            $role = $user->role ?? null;
            $actorRole = $role instanceof Role ? $role->value : (is_string($role) ? $role : 'unknown');

            AdminAuditLog::query()->create([
                'id' => (string) Str::uuid(),
                'actor_id' => $actorId,
                'actor_role' => $actorRole,
                'request_id' => (string) $request->headers->get('X-Request-Id', (string) Str::uuid()),
                'route' => optional($request->route())->getName(),
                'method' => $request->getMethod(),
                'path' => mb_substr($request->path(), 0, 2000),
                'response_status' => $response->getStatusCode(),
                'payload_redacted' => $this->redact($request->except(['_token'])),
                'occurred_at' => now(),
            ]);
        } catch (Throwable) {
            // L5 is best-effort at the middleware layer; never break the request.
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function redact(array $payload): array
    {
        $out = [];
        foreach ($payload as $key => $value) {
            if (in_array(strtolower($key), self::REDACTED_KEYS, true)) {
                $out[$key] = '[redacted]';

                continue;
            }
            if (is_array($value)) {
                $out[$key] = $this->redact($value);

                continue;
            }
            $out[$key] = is_scalar($value) || $value === null ? $value : '[object]';
        }

        // 8 KB cap on the serialised payload to keep audit rows small.
        $encoded = json_encode($out);
        if ($encoded !== false && mb_strlen($encoded) > 8192) {
            return ['_truncated' => true, '_size_bytes' => mb_strlen($encoded)];
        }

        return $out;
    }
}
