<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Contracts;

use Closure;
use LBHurtado\XChange\Data\WithdrawalPipelineContextData;
use LBHurtado\XChange\Enums\WithdrawalPipelineStepGroup;

interface WithdrawalPipelineStepContract
{
    public static function group(): WithdrawalPipelineStepGroup;

    public static function description(): string;

    public function handle(WithdrawalPipelineContextData $context, Closure $next): mixed;
}
