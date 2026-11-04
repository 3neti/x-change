<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Queue\Concerns;

use DateTimeInterface;

trait ProviderVerificationRetryPolicy
{
    public int $maxExceptions = 5;

    public function retryUntil(): DateTimeInterface
    {
        return now()->addDay();
    }
}
