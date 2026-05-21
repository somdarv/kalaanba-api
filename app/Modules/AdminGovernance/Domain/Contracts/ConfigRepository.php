<?php

declare(strict_types=1);

namespace Kalaanba\Modules\AdminGovernance\Domain\Contracts;

use Kalaanba\Support\Config\Contracts\ConfigRepository as SharedConfigRepository;

/**
 * DEPRECATED: ConfigRepository has been moved to Kalaanba\Support\Config\Contracts\ConfigRepository.
 *
 * This re-export is kept for backward compatibility during the migration.
 * All new code should import from \Kalaanba\Support\Config\Contracts\ConfigRepository directly.
 *
 * @deprecated Use Kalaanba\Support\Config\Contracts\ConfigRepository instead.
 */
interface ConfigRepository extends SharedConfigRepository {}
