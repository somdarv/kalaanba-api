<?php

declare(strict_types=1);

namespace Kalaanba\Modules\AdminGovernance\Domain;

use Kalaanba\Support\Config\ConfigValue as SharedConfigValue;

/**
 * DEPRECATED: ConfigValue has been moved to Kalaanba\Support\Config\ConfigValue.
 *
 * This re-export is kept for backward compatibility during the migration.
 * All new code should import from \Kalaanba\Support\Config\ConfigValue directly.
 *
 * @deprecated Use Kalaanba\Support\Config\ConfigValue instead.
 */
class ConfigValue extends SharedConfigValue {}
