<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Support\Logging;

use LBHurtado\XChange\Contracts\AuditLoggerContract;

class NullAuditLogger implements AuditLoggerContract
{
    public function log(string $event, array $payload = []): void
    {
        // Intentionally no-op.
    }
}
