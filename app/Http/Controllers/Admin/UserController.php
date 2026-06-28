<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Models\User;
use App\Services\Admin\AdminAccessCodeVerifier;
use App\Services\Admin\AdminUserActionException;
use App\Services\Admin\AdminUserActions;
use App\Services\Admin\AdminUserDirectory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Admin Users section — pre-alpha tester support (WP-20260624).
 *
 * Gated by `auth:sanctum` + `super_admin` (route group). Destructive actions
 * (set password, force-verify) additionally require the admin access code
 * (ADR-0005, gate = "confirm destructive actions"). Every mutation is audited
 * automatically by AdminAuditMiddleware with secret redaction.
 *
 * Never exposes a password, password hash, OTP, or full phone number.
 */
final class UserController extends Controller
{
    public function __construct(
        private readonly AdminUserDirectory $directory,
        private readonly AdminUserActions $actions,
        private readonly AdminAccessCodeVerifier $accessCode,
    ) {}

    public function index(Request $request): JsonResponse
    {
        return new JsonResponse($this->directory->list([
            'search' => $request->query('search'),
            'status' => $request->query('status'),
            'auth_method' => $request->query('auth_method'),
            'sort' => $request->query('sort'),
            'per_page' => $request->query('per_page'),
        ]));
    }

    public function show(string $id): JsonResponse
    {
        return $this->wrap($this->directory->present($this->find($id)));
    }

    public function setPassword(Request $request, string $id): JsonResponse
    {
        $this->assertAccessCode($request);
        $data = $request->validate(['password' => ['required', 'string', 'min:10', 'max:255']]);

        return $this->run($id, fn (User $u) => $this->actions->setPassword($u, $data['password']));
    }

    public function forceVerify(Request $request, string $id): JsonResponse
    {
        $this->assertAccessCode($request);
        $data = $request->validate(['channel' => ['required', 'in:phone,email']]);

        return $this->run($id, fn (User $u) => $this->actions->forceVerify($u, $data['channel']));
    }

    public function updatePhone(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'phone_e164' => ['required', 'string', 'max:16', 'regex:/^\+[1-9]\d{6,14}$/'],
        ]);

        return $this->run($id, fn (User $u) => $this->actions->updatePhone($u, $data['phone_e164']));
    }

    public function updateEmail(Request $request, string $id): JsonResponse
    {
        $data = $request->validate(['email' => ['required', 'email', 'max:255']]);

        return $this->run($id, fn (User $u) => $this->actions->updateEmail($u, $data['email']));
    }

    public function disable(string $id): JsonResponse
    {
        return $this->run($id, fn (User $u) => $this->actions->disable($u));
    }

    public function enable(string $id): JsonResponse
    {
        return $this->run($id, fn (User $u) => $this->actions->enable($u));
    }

    public function archive(Request $request, string $id): JsonResponse
    {
        $this->assertAccessCode($request);

        return $this->run($id, fn (User $u) => $this->actions->archive($u));
    }

    public function resendOtp(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'phone_e164' => ['required', 'string', 'max:16', 'regex:/^\+[1-9]\d{6,14}$/'],
        ]);

        return $this->run($id, fn (User $u) => $this->actions->resendOtp($u, $data['phone_e164']));
    }

    public function clearLockout(string $id): JsonResponse
    {
        return $this->run($id, fn (User $u) => $this->actions->clearLockout($u));
    }

    /**
     * Run an action against a user, then return the refreshed projection.
     *
     * @param  callable(User):void  $action
     */
    private function run(string $id, callable $action): JsonResponse
    {
        $user = $this->find($id);

        try {
            $action($user);
        } catch (AdminUserActionException $e) {
            return $this->error($e->status, $e->errorCode, $e->getMessage());
        }

        return $this->wrap($this->directory->present($user->refresh()));
    }

    private function find(string $id): User
    {
        $user = User::query()->find($id);
        if ($user === null) {
            abort(404, 'admin.users.not_found');
        }

        return $user;
    }

    private function assertAccessCode(Request $request): void
    {
        $code = (string) ($request->header('X-Admin-Access-Code')
            ?? $request->input('access_code', ''));

        if ($code === '' || ! $this->accessCode->verify($code)) {
            abort(response()->json([
                'error' => [
                    'code' => 'admin.access_code_invalid',
                    'message' => 'A valid admin access code is required for this action.',
                ],
            ], 403));
        }
    }

    /** @param array<string,mixed> $data */
    private function wrap(array $data): JsonResponse
    {
        return new JsonResponse(['data' => $data]);
    }

    private function error(int $status, string $code, string $message): JsonResponse
    {
        return new JsonResponse([
            'error' => ['code' => $code, 'message' => $message],
        ], $status);
    }
}
