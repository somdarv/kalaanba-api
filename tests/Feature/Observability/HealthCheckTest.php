<?php

declare(strict_types=1);

it('returns 200 with status ok when DB and Redis are reachable', function (): void {
    $response = $this->getJson('/api/v1/health');

    $response->assertOk()
        ->assertJsonPath('status', 'ok')
        ->assertJsonPath('service', 'kalaanba-api')
        ->assertJsonPath('checks.database.ok', true)
        ->assertJsonPath('checks.redis.ok', true)
        ->assertJsonStructure([
            'status',
            'service',
            'environment',
            'time',
            'checks' => [
                'database' => ['ok', 'latency_ms'],
                'redis' => ['ok', 'latency_ms'],
            ],
        ]);
});

it('echoes the request id header back on the health response', function (): void {
    $response = $this->withHeaders(['X-Request-Id' => 'health-corr-1'])
        ->getJson('/api/v1/health');

    $response->assertOk();
    expect($response->headers->get('X-Request-Id'))->toBe('health-corr-1');
});
