<?php

declare(strict_types=1);

namespace Kalaanba\Support\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Kalaanba\Support\Auth\Scope\ScopeResolver;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route-level scope gate.
 *
 * Usage in routes:
 *   Route::middleware(['auth:sanctum', 'scope:club'])
 *        ->put('/api/v1/clubs/{club}', ...);
 *
 * The middleware reads the {scope} route parameter (e.g. {club}), resolves
 * it via the bound ScopeResolver, and returns 403 with code
 * `auth.out_of_scope` on miss. Platform admins are passed through
 * unconditionally by the DenyAllScopeResolver (Build Plan §0.6).
 */
final class ScopeMiddleware
{
    /** @var array<int, string> */
    private const array ALLOWED_SCOPES = ['hub', 'club', 'competition', 'venue'];

    private const string ERROR_CODE = 'auth.out_of_scope';

    public function __construct(private readonly ScopeResolver $resolver) {}

    public function handle(Request $request, Closure $next, string $scope): Response
    {
        if (! in_array($scope, self::ALLOWED_SCOPES, true)) {
            return $this->deny($request, 'unknown_scope', $scope);
        }

        $user = $request->user();

        if (! $user instanceof User) {
            return $this->deny($request, 'unauthenticated', $scope);
        }

        $scopeId = (string) $request->route($scope);

        if ($scopeId === '') {
            return $this->deny($request, 'missing_scope_id', $scope);
        }

        if (! $this->resolver->userHasScope($user, $scope, $scopeId)) {
            return $this->deny($request, 'membership_required', $scope);
        }

        return $next($request);
    }

    private function deny(Request $request, string $reason, string $scope): JsonResponse
    {
        return new JsonResponse([
            'error' => [
                'code' => self::ERROR_CODE,
                'message' => 'You do not have access to this resource.',
                'details' => ['scope' => $scope, 'reason' => $reason],
                'request_id' => (string) $request->header('X-Request-Id', ''),
            ],
        ], 403);
    }
}
