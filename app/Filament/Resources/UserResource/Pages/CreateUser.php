<?php

declare(strict_types=1);

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Hash;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // New users created from the panel must have a temporary password set;
        // operators use Reset Password afterwards. We seed a random one to
        // avoid blank-password records (L1 — never bypass auth invariants).
        $data['password'] ??= Hash::make(bin2hex(random_bytes(12)));

        return $data;
    }
}
