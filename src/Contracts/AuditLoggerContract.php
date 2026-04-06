<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Contracts;

interface AuditLoggerContract
{
    /**
     * Record an audit event.
     *
     * @param  string  $event
     * @param  array<string, mixed>  $payload
     */
    public function log(string $event, array $payload = []): void;
}
