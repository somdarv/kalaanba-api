<?php

declare(strict_types=1);

namespace Kalaanba\Support\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate a route behind the Super Admin role. Used by governance / audit
 * endpoints. Returns a standardized 403 envelope on denial.
 */
final class RequireSuperAdminMiddleware
{
    private const ERROR_CODE = 'auth.super_admin_only';

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user instanceof User || ! $user->role->isSuperAdmin()) {
            return new JsonResponse([
                'error' => [
                    'code' => self::ERROR_CODE,
                    'message' => 'This endpoint is restricted to platform Super Admins.',
                    'details' => [],
                    'request_id' => (string) ($request->headers->get('X-Request-Id') ?? ''),
                ],
            ], 403);
        }

        return $next($request);
    }
}
