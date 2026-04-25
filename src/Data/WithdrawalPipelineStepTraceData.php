<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data;

use LBHurtado\XChange\Enums\WithdrawalPipelineStepGroup;
use Spatie\LaravelData\Data;

class WithdrawalPipelineStepTraceData extends Data
{
    public function __construct(
        public string $step,
        public WithdrawalPipelineStepGroup $group,
        public string $description,
        public string $status,
        public ?string $error = null,
    ) {}
}
