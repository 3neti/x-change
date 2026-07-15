<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Execution;

use LBHurtado\XChange\Contracts\ExecutionCashDisbursementPollerContract;
use LBHurtado\XChange\Lifecycle\Runners\Support\LifecycleDisbursementPoller;

final class LifecycleExecutionCashDisbursementPoller implements ExecutionCashDisbursementPollerContract
{
    public function __construct(
        private readonly LifecycleDisbursementPoller $poller,
    ) {}

    public function poll(string $code, array $options = []): array
    {
        return $this->poller->poll(
            code: $code,
            timeout: (int) data_get($options, 'timeout', 180),
            poll: max(1, (int) data_get($options, 'poll', 10)),
            maxPolls: data_get($options, 'max_polls') === null
                ? null
                : max(1, (int) data_get($options, 'max_polls')),
            acceptPending: (bool) data_get($options, 'accept_pending', false),
        );
    }
}
