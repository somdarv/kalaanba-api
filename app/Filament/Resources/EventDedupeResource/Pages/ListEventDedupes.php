<?php

declare(strict_types=1);

namespace App\Filament\Resources\EventDedupeResource\Pages;

use App\Filament\Resources\EventDedupeResource;
use Filament\Resources\Pages\ListRecords;

class ListEventDedupes extends ListRecords
{
    protected static string $resource = EventDedupeResource::class;
}
