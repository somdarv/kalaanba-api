<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Requests\Auth\CreateSessionRequest;
use App\Http\Resources\Auth\SessionResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

final class SessionController extends Controller
{
    /**
     * POST /api/v1/auth/sessions
     *
     * Email + password bootstrap session. Replaced in WP-B by an OTP-driven
     * flow; both will coexist behind the `auth.allow_password_login` config
     * key once OTP lands.
     */
    public function store(CreateSessionRequest $request): SessionResource
    {
        $validated = $request->validated();

        $user = User::query()
            ->where('email', $validated['email'])
            ->whereNull('archived_at')
            ->first();

        if ($user === null || ! Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Credentials do not match an active account.'],
            ]);
        }

        $deviceName = $validated['device_name'] ?? 'api';
        $expiresAt = Carbon::now('UTC')->addDays(30);

        $token = $user->createToken($deviceName, ['*'], $expiresAt);

        $user->forceFill(['last_seen_at' => Carbon::now('UTC')])->save();

        return new SessionResource(
            $user,
            $token->plainTextToken,
            $expiresAt->toIso8601String(),
        );
    }

    /**
     * DELETE /api/v1/auth/sessions/current
     */
    public function destroyCurrent(Request $request): JsonResponse
    {
        $token = $request->user()?->currentAccessToken();

        if ($token !== null && method_exists($token, 'delete')) {
            $token->delete();
        }

        return new JsonResponse(status: 204);
    }
}
