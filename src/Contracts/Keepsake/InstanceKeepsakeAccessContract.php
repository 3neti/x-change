<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Contracts\Keepsake;

use Illuminate\Contracts\Auth\Authenticatable;

interface InstanceKeepsakeAccessContract
{
    /** @param array<string, mixed> $grant */
    public function canDownload(Authenticatable $actor, array $grant): bool;
}
