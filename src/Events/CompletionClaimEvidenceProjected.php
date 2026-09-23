<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

final class CompletionClaimEvidenceProjected implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    /** @param array<string, int|string> $payload */
    public function __construct(public readonly array $payload) {}
}
