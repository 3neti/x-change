<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Support\WithdrawalPipeline;

use LBHurtado\XChange\Enums\WithdrawalPipelineStepGroup;

trait HasWithdrawalPipelineStepMetadata
{
    public static function description(): string
    {
        return static::class;
    }

    public static function group(): WithdrawalPipelineStepGroup
    {
        return WithdrawalPipelineStepGroup::INTEGRATION;
    }
}
