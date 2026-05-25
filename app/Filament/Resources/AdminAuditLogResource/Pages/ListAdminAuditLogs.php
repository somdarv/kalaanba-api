<?php

declare(strict_types=1);

namespace App\Filament\Resources\AdminAuditLogResource\Pages;

use App\Filament\Resources\AdminAuditLogResource;
use Filament\Resources\Pages\ListRecords;

class ListAdminAuditLogs extends ListRecords
{
    protected static string $resource = AdminAuditLogResource::class;
}
