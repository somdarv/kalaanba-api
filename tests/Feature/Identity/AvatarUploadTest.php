<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Storage::fake('public');
    config()->set('users.avatar.driver', 'local');
    config()->set('users.avatar.local.disk', 'public');
});

it('POST /users/me/avatar stores the file and returns a URL', function (): void {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $response = $this->withHeaders([
        'Accept' => 'application/json',
        'Idempotency-Key' => (string) Str::uuid(),
    ])->post('/api/v1/users/me/avatar', [
        'file' => UploadedFile::fake()->image('a.png', 100, 100),
    ]);

    $response->assertCreated()
        ->assertJsonStructure(['data' => ['avatar_url']]);

    expect($response->json('data.avatar_url'))->toBeString();
});

it('POST /users/me/avatar rejects oversized uploads with stable error key', function (): void {
    config()->set('users.avatar.max_bytes', 1024); // 1 KiB
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $response = $this->withHeaders([
        'Accept' => 'application/json',
        'Idempotency-Key' => (string) Str::uuid(),
    ])->post('/api/v1/users/me/avatar', [
        'file' => UploadedFile::fake()->image('big.png', 1000, 1000)->size(10),
    ]);

    $response->assertStatus(422)
        ->assertJsonPath('errors.file.0', 'identity.avatar.too_large');
});

it('POST /users/me/avatar rejects disallowed MIME types', function (): void {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $response = $this->withHeaders([
        'Accept' => 'application/json',
        'Idempotency-Key' => (string) Str::uuid(),
    ])->post('/api/v1/users/me/avatar', [
        'file' => UploadedFile::fake()->create('payload.pdf', 10, 'application/pdf'),
    ]);

    $response->assertStatus(422)
        ->assertJsonPath('errors.file.0', 'identity.avatar.mime_disallowed');
});

it('POST /users/me/avatar requires the file field', function (): void {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $response = $this->withHeaders([
        'Accept' => 'application/json',
        'Idempotency-Key' => (string) Str::uuid(),
    ])->post('/api/v1/users/me/avatar', []);

    $response->assertStatus(422)
        ->assertJsonPath('errors.file.0', 'identity.avatar.file_missing');
});

it('POST /users/me/avatar requires auth', function (): void {
    $this->withHeaders(['Accept' => 'application/json'])
        ->post('/api/v1/users/me/avatar', [
            'file' => UploadedFile::fake()->image('a.png'),
        ])
        ->assertUnauthorized();
});
