<?php

declare(strict_types=1);

namespace Kalaanba\Support\Audit;

/**
 * Port — AdminAuditMiddleware pushes entries through this seam. Production
 * binding is DatabaseAdminAuditWriter; tests can swap an in-memory fake.
 */
interface AdminAuditWriter
{
    public function write(AdminAuditEntry $entry): void;
}
