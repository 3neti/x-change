<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Cockpit;

use Spatie\LaravelData\Data;

final class CockpitPayCodeTerminalControlData extends Data
{
    public function __construct(
        public readonly bool $can_expire = false,
        public readonly bool $can_cancel = false,
        public readonly ?string $blocked_reason = null,
        public readonly string $status = 'blocked',
    ) {}
}
