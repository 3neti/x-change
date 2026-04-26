<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data;

use Spatie\LaravelData\Data;

class ApprovalWorkflowResultData extends Data
{
    public function __construct(
        public string $status,
        public array $next_actions = [],
        public array $requirements = [],
        public array $meta = [],
        public array $messages = [],
    ) {}
}
