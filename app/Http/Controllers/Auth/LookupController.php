<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Requests\Auth\LookupRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Kalaanba\Modules\Identity\Application\Lookup\LookupAccountHandler;

/**
 * POST /api/v1/auth/lookup
 *
 * Identifier-first branch signal: resolves whether a phone/email maps to an
 * existing active account so the entry screen can pick returning-vs-new copy
 * and flow. Read-only, no Idempotency-Key (not a write). Strictly throttled
 * via `throttle:lookup`. Returns no PII — only `{ exists, channel }`.
 *
 * Engine doc: docs/engines/identity/Identity_Engine_System_Document.md §4, §12.
 * ADR: docs/adr/0004-identifier-first-progressive-auth.md.
 * Contract: contracts/api/identity/post-auth-lookup.v1.yaml.
 */
final class LookupController extends Controller
{
    public function __construct(
        private readonly LookupAccountHandler $handler,
    ) {}

    public function store(LookupRequest $request): JsonResponse
    {
        $result = $this->handler->handle((string) $request->validated()['identifier']);

        return new JsonResponse([
            'data' => [
                'exists' => $result->exists,
                'channel' => $result->channel,
            ],
        ]);
    }
}
