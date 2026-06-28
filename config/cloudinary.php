<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Cloudinary Configuration
|--------------------------------------------------------------------------
|
| The Cloudinary URL holds API credentials and is env-only per
| engineering-standards §10. Read via `config('cloudinary.url')` so
| `config:cache` works correctly.
|
| Identity engine uses this via
| {@see Kalaanba\Modules\Identity\Infrastructure\Avatar\AvatarDriverFactory}
| when `users.avatar.driver=cloudinary`.
|
*/

return [
    'url' => env('CLOUDINARY_URL'),
];
