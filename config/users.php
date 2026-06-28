<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Identity Engine — Users / Profile Configuration
|--------------------------------------------------------------------------
|
| Defaults for profile validation, avatar storage driver selection, and
| public-projection throttling. Every value is admin-configurable per
| Constitution Law 2 — the keys live here as seed defaults and are
| mirrored into the admin_config table at install time.
|
| Cloudinary credentials are env-only (CLOUDINARY_URL) — never stored in
| admin config (engineering-standards §10).
|
| Engine doc: docs/engines/identity/Identity_Engine_System_Document.md §10
|
*/

return [

    'profile' => [
        'name_min' => env('USERS_PROFILE_NAME_MIN', 2),
        'name_max' => env('USERS_PROFILE_NAME_MAX', 60),
    ],

    'avatar' => [
        // 'local' (default — writes to storage/app/public/avatars)
        // 'cloudinary' (production — uploads via cloudinary/cloudinary_php)
        'driver' => env('USERS_AVATAR_DRIVER', 'local'),

        // Maximum upload size in bytes. 2 MiB default.
        'max_bytes' => (int) env('USERS_AVATAR_MAX_BYTES', 2 * 1024 * 1024),

        // Accepted MIME types — server re-sniffs after upload.
        'allowed_mime' => [
            'image/jpeg',
            'image/png',
            'image/webp',
        ],

        'local' => [
            // Laravel filesystem disk used by LocalAvatarDriver.
            'disk' => env('USERS_AVATAR_LOCAL_DISK', 'public'),
        ],

        'cloudinary' => [
            // Folder prefix inside the Cloudinary cloud. Lets us namespace
            // by environment (kalaanba-dev/avatars vs kalaanba-prod/avatars).
            'folder' => env(
                'USERS_AVATAR_CLOUDINARY_FOLDER',
                'kalaanba-'.env('APP_ENV', 'local').'/avatars',
            ),

            // Unsigned upload preset name for future direct browser uploads
            // from the Next.js frontend. Not used by the backend driver.
            'upload_preset' => env('USERS_AVATAR_CLOUDINARY_UPLOAD_PRESET', 'kalaanba_avatars'),
        ],

        // Per-user rate limit on POST /users/me/avatar — defends storage
        // bandwidth + Cloudinary quota.
        'throttle' => [
            'per_minute' => (int) env('USERS_AVATAR_THROTTLE_PER_MINUTE', 10),
        ],
    ],

    'public_profile' => [
        // Per-IP rate limit on anonymous GET /users/{id}. Prevents
        // enumeration / scraping from day one.
        'throttle' => [
            'anonymous_per_minute' => (int) env('USERS_PUBLIC_PROFILE_ANONYMOUS_PER_MINUTE', 60),
        ],
    ],

];
