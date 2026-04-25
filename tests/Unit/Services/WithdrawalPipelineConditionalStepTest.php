<?php

declare(strict_types=1);

use LBHurtado\XChange\Contracts\WithdrawalPipelineStepContract;
use LBHurtado\XChange\Data\WithdrawalPipelineContextData;
use LBHurtado\XChange\Enums\WithdrawalPipelineStepGroup;
use LBHurtado\XChange\Services\WithdrawalPipeline;

class AlwaysRunWithdrawalTestStep implements WithdrawalPipelineStepContract
{
    public static bool $ran = false;

    public static function group(): WithdrawalPipelineStepGroup
    {
        return WithdrawalPipelineStepGroup::PRE_AUTH;
    }

    public static function description(): string
    {
        return 'Always-run test step.';
    }

    public static function shouldRun(WithdrawalPipelineContextData $context): bool
    {
        return true;
    }

    public function handle(WithdrawalPipelineContextData $context, Closure $next): mixed
    {
        self::$ran = true;

        return $next($context);
    }
}

class SkippedWithdrawalTestStep implements WithdrawalPipelineStepContract
{
    public static bool $ran = false;

    public static function group(): WithdrawalPipelineStepGroup
    {
        return WithdrawalPipelineStepGroup::PRE_AUTH;
    }

    public static function description(): string
    {
        return 'Skipped test step.';
    }

    public static function shouldRun(WithdrawalPipelineContextData $context): bool
    {
        return false;
    }

    public function handle(WithdrawalPipelineContextData $context, Closure $next): mixed
    {
        self::$ran = true;

        return $next($context);
    }
}

beforeEach(function () {
    AlwaysRunWithdrawalTestStep::$ran = false;
    SkippedWithdrawalTestStep::$ran = false;
});

it('runs only withdrawal pipeline steps whose shouldRun returns true', function () {
    $pipeline = app(WithdrawalPipeline::class);

    // If your constructor is public and accepts pipeline + steps:
    $pipeline = new WithdrawalPipeline(
        pipeline: app(\Illuminate\Pipeline\Pipeline::class),
        steps: [
            AlwaysRunWithdrawalTestStep::class,
            SkippedWithdrawalTestStep::class,
        ],
    );

    $context = new WithdrawalPipelineContextData(
        voucher: issueVoucher(),
        payload: [],
    );

    $result = $pipeline->process($context);

    expect($result)->toBe($context)
        ->and(AlwaysRunWithdrawalTestStep::$ran)->toBeTrue()
        ->and(SkippedWithdrawalTestStep::$ran)->toBeFalse();
});
