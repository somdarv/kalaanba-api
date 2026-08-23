<?php

declare(strict_types=1);

namespace Kalaanba\Support\Media;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use RuntimeException;

/**
 * Resolves an {@see ImageStore} from a config prefix, so each engine keeps its
 * own switch (`club.media.driver`, and whatever the next one is called) while
 * sharing one implementation.
 *
 * Fails loudly and by name when a store is selected without the settings it
 * needs. One that silently fell back to local storage in production would write
 * every club crest onto a single web server's disk, report success, and nobody
 * would find out until the box was replaced.
 */
final readonly class ImageStoreFactory
{
    public function __construct(
        private ConfigRepository $config,
        private FilesystemFactory $filesystem,
    ) {}

    /**
     * @param  string  $prefix  Config namespace, e.g. `club.media`.
     */
    public function make(string $prefix): ImageStore
    {
        $driver = (string) $this->config->get($prefix.'.driver', 'local');

        return match ($driver) {
            'local' => new LocalImageStore(
                filesystem: $this->filesystem,
                disk: (string) $this->config->get($prefix.'.local.disk', 'public'),
            ),
            'r2' => $this->makeR2($prefix),
            default => throw new RuntimeException(sprintf(
                'media.driver_misconfigured: unknown driver "%s" for %s.driver',
                $driver,
                $prefix,
            )),
        };
    }

    private function makeR2(string $prefix): R2ImageStore
    {
        $publicUrl = (string) $this->config->get($prefix.'.r2.public_url', '');

        if ($publicUrl === '') {
            throw new RuntimeException(sprintf(
                'media.driver_misconfigured: %s.r2.public_url is required when %s.driver=r2. '
                .'Enable the bucket\'s r2.dev domain or attach a custom domain, then set it. '
                .'Without it a stored object has no address the browser can fetch.',
                $prefix,
                $prefix,
            ));
        }

        if ((string) $this->config->get('filesystems.disks.r2.key', '') === '') {
            throw new RuntimeException(
                'media.driver_misconfigured: R2_ACCESS_KEY_ID and R2_SECRET_ACCESS_KEY are required. '
                .'These are the S3 key pair from R2 > Manage R2 API Tokens, not a Cloudflare account API token.',
            );
        }

        return new R2ImageStore(
            filesystem: $this->filesystem,
            disk: 'r2',
            publicBaseUrl: $publicUrl,
        );
    }
}
