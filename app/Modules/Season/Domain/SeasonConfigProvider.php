<?php

declare(strict_types=1);

namespace Kalaanba\Modules\Season\Domain;

/**
 * Port for loading season-related configuration. Implementations live in
 * Infrastructure (Admin Config-backed) so Application code stays free of
 * framework / persistence concerns (deptrac: Application → Domain + Support).
 */
interface SeasonConfigProvider
{
    public function load(): SeasonConfig;
}
