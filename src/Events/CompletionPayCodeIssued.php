<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

final class CompletionPayCodeIssued implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public readonly array $payload) {}
}
