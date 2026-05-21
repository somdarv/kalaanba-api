<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Kalaanba\Support\Auth\PhoneHash;
use Kalaanba\Support\Auth\Role;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'role' => Role::Fan->value,
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function withRole(Role $role): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => $role->value,
        ]);
    }

    public function archived(): static
    {
        return $this->state(fn (array $attributes) => [
            'archived_at' => now(),
        ]);
    }

    public function withPhone(string $phoneE164): static
    {
        $hasher = app(PhoneHash::class);

        return $this->state(fn (array $attributes) => [
            'phone_e164_hash' => $hasher->hash($phoneE164),
            'phone_e164_last4' => $hasher->last4($phoneE164),
        ]);
    }
}
