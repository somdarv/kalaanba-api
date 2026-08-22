<?php

declare(strict_types=1);

namespace Kalaanba\Modules\PlayerAffiliation\Infrastructure\Media;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Kalaanba\Modules\PlayerAffiliation\Application\PlayerMediaDriver;
use RuntimeException;

/**
 * Resolves the active {@see PlayerMediaDriver} from `player.media.driver`.
 *
 * Fails loudly and by name when a driver is selected without the settings it
 * needs. A driver that silently falls back to local storage in production
 * writes every player's face onto one web server's disk and reports success,
 * and nobody finds out until the box is replaced.
 */
final readonly class PlayerMediaDriverFactory
{
    public function __construct(
        private ConfigRepository $config,
        private FilesystemFactory $filesystem,
    ) {}

    public function make(): PlayerMediaDriver
    {
        $driver = (string) $this->config->get('player.media.driver', 'local');

        return match ($driver) {
            'local' => new LocalPlayerMediaDriver(
                filesystem: $this->filesystem,
                disk: (string) $this->config->get('player.media.local.disk', 'public'),
            ),
            'r2' => $this->makeR2(),
            default => throw new RuntimeException(sprintf(
                'player.media.driver_misconfigured: unknown driver "%s"',
                $driver,
            )),
        };
    }

    private function makeR2(): R2PlayerMediaDriver
    {
        $publicUrl = (string) $this->config->get('player.media.r2.public_url', '');

        if ($publicUrl === '') {
            throw new RuntimeException(
                'player.media.driver_misconfigured: R2_PUBLIC_URL is required when player.media.driver=r2. '
                .'Enable the bucket\'s r2.dev domain or attach a custom domain, then set it. '
                .'Without it a stored object has no address the browser can fetch.',
            );
        }

        if ((string) $this->config->get('filesystems.disks.r2.key', '') === '') {
            throw new RuntimeException(
                'player.media.driver_misconfigured: R2_ACCESS_KEY_ID and R2_SECRET_ACCESS_KEY are required '
                .'when player.media.driver=r2. These are the S3 key pair from R2 > Manage R2 API Tokens, '
                .'not a Cloudflare account API token.',
            );
        }

        return new R2PlayerMediaDriver(
            filesystem: $this->filesystem,
            disk: (string) $this->config->get('player.media.r2.disk', 'r2'),
            publicBaseUrl: $publicUrl,
        );
    }
}
