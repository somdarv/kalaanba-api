<?php

declare(strict_types=1);

namespace App\Http\Resources\Auth;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read User $resource
 */
final class SessionResource extends JsonResource
{
    public function __construct(User $user, private readonly string $plainTextToken, private readonly ?string $expiresAt)
    {
        parent::__construct($user);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'token' => $this->plainTextToken,
            'token_type' => 'Bearer',
            'expires_at' => $this->expiresAt,
            'user' => [
                'id' => (string) $this->resource->getKey(),
                'name' => $this->resource->name,
                'email' => $this->resource->email,
                'role' => $this->resource->role->value,
            ],
        ];
    }
}
