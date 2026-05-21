<?php

declare(strict_types=1);

namespace Kalaanba\Modules\AdminGovernance\Domain\Exceptions;

use Kalaanba\Support\Config\Exceptions\ConfigKeyNotSetException as SharedConfigKeyNotSetException;

/**
 * DEPRECATED: ConfigKeyNotSetException has been moved to Kalaanba\Support\Config\Exceptions\ConfigKeyNotSetException.
 *
 * This re-export is kept for backward compatibility during the migration.
 * All new code should import from \Kalaanba\Support\Config\Exceptions\ConfigKeyNotSetException directly.
 *
 * @deprecated Use Kalaanba\Support\Config\Exceptions\ConfigKeyNotSetException instead.
 */
class ConfigKeyNotSetException extends SharedConfigKeyNotSetException {}
