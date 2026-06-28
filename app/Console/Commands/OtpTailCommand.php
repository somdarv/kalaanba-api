<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Streams dev OTP codes from the application log so a local tester can read
 * the code without SMS. Cross-platform (polls the file — no pcntl, unlike
 * `pail`), and local-only so it can never surface OTPs elsewhere.
 *
 * Usage (run in a second terminal):  php artisan otp:tail
 */
final class OtpTailCommand extends Command
{
    protected $signature = 'otp:tail';

    protected $description = 'Stream dev OTP codes from the log (local only).';

    public function handle(): int
    {
        if (! $this->laravel->environment('local')) {
            $this->error('otp:tail is available in the local environment only.');

            return self::FAILURE;
        }

        $this->info('Watching for OTPs… request a code in the app. (Ctrl+C to stop)');
        $this->watch(storage_path('logs/laravel.log'));
    }

    /**
     * Poll the log forever, emitting each new OTP. Typed `never` so the caller
     * knows it does not return (and PHPStan is satisfied about `handle()`).
     */
    private function watch(string $path): never
    {
        $offset = is_file($path) ? (int) filesize($path) : 0;

        while (true) {
            clearstatcache(false, $path);
            $size = is_file($path) ? (int) filesize($path) : 0;

            if ($size < $offset) {
                $offset = 0; // log was rotated / truncated
            }

            if ($size > $offset) {
                $offset = $this->emitNewOtps($path, $offset);
            }

            usleep(400_000);
        }
    }

    private function emitNewOtps(string $path, int $offset): int
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            return $offset;
        }

        fseek($handle, $offset);
        while (($line = fgets($handle)) !== false) {
            if (preg_match('/\[DEV OTP\] (\S+) -> (\d+)/', $line, $m) === 1) {
                $this->line(sprintf('  <fg=cyan>%s</>  →  <fg=yellow;options=bold>%s</>', $m[1], $m[2]));
            }
        }

        $position = ftell($handle);
        fclose($handle);

        return $position === false ? $offset : $position;
    }
}
