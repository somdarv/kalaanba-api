<?php

declare(strict_types=1);

namespace App\Filament\Resources\OutboxEventResource\Pages;

use App\Filament\Resources\OutboxEventResource;
use Filament\Resources\Pages\ListRecords;

class ListOutboxEvents extends ListRecords
{
    protected static string $resource = OutboxEventResource::class;
}
