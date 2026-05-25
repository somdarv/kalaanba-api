<?php

declare(strict_types=1);

namespace App\Filament\Resources\AnalyticsEventResource\Pages;

use App\Filament\Resources\AnalyticsEventResource;
use Filament\Resources\Pages\ListRecords;

class ListAnalyticsEvents extends ListRecords
{
    protected static string $resource = AnalyticsEventResource::class;
}
