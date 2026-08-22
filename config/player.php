<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Player & Affiliation Engine — Configuration
|--------------------------------------------------------------------------
|
| Seed defaults for player media storage and upload limits. Every value is
| admin-configurable per Constitution Law 2 — the keys live here as seed
| defaults and are mirrored into the admin_config table at install time.
|
| R2 credentials are env-only (R2_ACCESS_KEY_ID / R2_SECRET_ACCESS_KEY) and
| never stored in admin config (engineering-standards §10). The bucket, the
| public base URL and the driver choice ARE config, so an environment can be
| repointed without a deploy.
|
| Engine doc: docs/engines/player-affiliation/ §7.
| Contract:   contracts/api/player/post-players-id-media.v1.yaml.
|
*/

return [

    'media' => [
        // 'local' (dev/CI — storage/app/public) or 'r2' (Cloudflare R2).
        'driver' => env('PLAYER_MEDIA_DRIVER', 'local'),

        // Maximum upload size in bytes. 4 MiB, per the media contract.
        //
        // Generous on purpose: the frontend already shrinks a picked photo to
        // a 512px square before sending, so anything arriving near this ceiling
        // came from a client that skipped that step. The limit is here to stop
        // abuse, not to police ordinary phones.
        'max_bytes' => (int) env('PLAYER_MEDIA_MAX_BYTES', 4 * 1024 * 1024),

        // Accepted MIME types. The server re-sniffs with finfo rather than
        // trusting the client's Content-Type header.
        'allowed_mime' => [
            'image/jpeg',
            'image/png',
            'image/webp',
        ],

        // Uploads per minute, per player.
        'throttle' => [
            'per_minute' => (int) env('PLAYER_MEDIA_THROTTLE_PER_MINUTE', 10),
        ],

        'local' => [
            'disk' => env('PLAYER_MEDIA_LOCAL_DISK', 'public'),
        ],

        'r2' => [
            'disk' => env('PLAYER_MEDIA_R2_DISK', 'r2'),

            // The address the BROWSER fetches a stored object from: the
            // bucket's r2.dev domain, or a custom domain. NOT the S3 endpoint,
            // which is private and resolves for nobody without a signature.
            // Required when driver=r2; the factory refuses to build without it.
            'public_url' => env('R2_PUBLIC_URL', ''),
        ],
    ],

];
