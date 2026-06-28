<?php

declare(strict_types=1);

namespace Kalaanba\Modules\Identity\Infrastructure\Avatar;

use Cloudinary\Cloudinary;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Kalaanba\Modules\Identity\Application\AvatarDriver;
use Kalaanba\Modules\Identity\IdentityServiceProvider;
use RuntimeException;

/**
 * Resolves the active {@see AvatarDriver} implementation from
 * `config('users.avatar.driver')`. Throws a configuration-aware error
 * when the driver string is unknown or when Cloudinary is selected but
 * `CLOUDINARY_URL` env is missing.
 *
 * Bound in {@see IdentityServiceProvider}.
 */
final readonly class AvatarDriverFactory
{
    public function __construct(private ConfigRepository $config) {}

    public function make(): AvatarDriver
    {
        $driver = (string) $this->config->get('users.avatar.driver', 'local');

        return match ($driver) {
            'local' => new LocalAvatarDriver(
                disk: (string) $this->config->get('users.avatar.local.disk', 'public'),
            ),
            'cloudinary' => $this->makeCloudinary(),
            default => throw new RuntimeException(sprintf(
                'identity.avatar.driver_misconfigured: unknown driver "%s"',
                $driver,
            )),
        };
    }

    private function makeCloudinary(): CloudinaryAvatarDriver
    {
        $url = (string) $this->config->get('cloudinary.url', '');

        if ($url === '') {
            throw new RuntimeException(
                'identity.avatar.driver_misconfigured: CLOUDINARY_URL env is required when users.avatar.driver=cloudinary',
            );
        }

        $folder = (string) $this->config->get(
            'users.avatar.cloudinary.folder',
            'kalaanba-'.app()->environment().'/avatars',
        );

        return new CloudinaryAvatarDriver(
            cloudinary: new Cloudinary($url),
            folder: $folder,
        );
    }
}
