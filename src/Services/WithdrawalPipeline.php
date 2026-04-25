<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services;

use Illuminate\Pipeline\Pipeline;
use LBHurtado\XChange\Data\WithdrawalPipelineContextData;

class WithdrawalPipeline
{
    /**
     * @param  array<int, class-string|object>  $steps
     */
    public function __construct(
        protected Pipeline $pipeline,
        protected array $steps = [],
    ) {}

    public function process(WithdrawalPipelineContextData $context): WithdrawalPipelineContextData
    {
        return $this->pipeline
            ->send($context)
            ->through($this->steps)
            ->thenReturn();
    }
}
