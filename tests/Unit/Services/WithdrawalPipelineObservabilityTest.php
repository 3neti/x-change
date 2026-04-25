<?php

declare(strict_types=1);

use LBHurtado\XChange\Contracts\WithdrawalPipelineStepContract;
use LBHurtado\XChange\Data\WithdrawalPipelineContextData;
use LBHurtado\XChange\Enums\WithdrawalPipelineStepGroup;
use LBHurtado\XChange\Services\WithdrawalPipeline;

class ObservedRunStep implements WithdrawalPipelineStepContract
{
    public static function group(): WithdrawalPipelineStepGroup
    {
        return WithdrawalPipelineStepGroup::PRE_AUTH;
    }

    public static function description(): string
    {
        return 'Observed run step.';
    }

    public static function shouldRun(WithdrawalPipelineContextData $context): bool
    {
        return true;
    }

    public function handle(WithdrawalPipelineContextData $context, Closure $next): mixed
    {
        return $next($context);
    }
}

class ObservedSkippedStep implements WithdrawalPipelineStepContract
{
    public static function group(): WithdrawalPipelineStepGroup
    {
        return WithdrawalPipelineStepGroup::PRE_AUTH;
    }

    public static function description(): string
    {
        return 'Observed skipped step.';
    }

    public static function shouldRun(WithdrawalPipelineContextData $context): bool
    {
        return false;
    }

    public function handle(WithdrawalPipelineContextData $context, Closure $next): mixed
    {
        return $next($context);
    }
}

class ObservedFailingStep implements WithdrawalPipelineStepContract
{
    public static function group(): WithdrawalPipelineStepGroup
    {
        return WithdrawalPipelineStepGroup::EXECUTION;
    }

    public static function description(): string
    {
        return 'Observed failing step.';
    }

    public static function shouldRun(WithdrawalPipelineContextData $context): bool
    {
        return true;
    }

    public function handle(WithdrawalPipelineContextData $context, Closure $next): mixed
    {
        throw new RuntimeException('Observed failure.');
    }
}

it('records ran and skipped withdrawal pipeline steps', function () {
    $pipeline = new WithdrawalPipeline(
        pipeline: app(\Illuminate\Pipeline\Pipeline::class),
        steps: [
            ObservedRunStep::class,
            ObservedSkippedStep::class,
        ],
    );

    $context = new WithdrawalPipelineContextData(
        voucher: issueVoucher(),
        payload: [],
    );

    $result = $pipeline->process($context);

    expect($result->stepTrace)->toHaveCount(2)
        ->and($result->stepTrace[0]->step)->toBe(ObservedSkippedStep::class)
        ->and($result->stepTrace[0]->status)->toBe('skipped')
        ->and($result->stepTrace[1]->step)->toBe(ObservedRunStep::class)
        ->and($result->stepTrace[1]->status)->toBe('ran');
});

it('records failed withdrawal pipeline step before rethrowing', function () {
    $pipeline = new WithdrawalPipeline(
        pipeline: app(\Illuminate\Pipeline\Pipeline::class),
        steps: [
            ObservedFailingStep::class,
        ],
    );

    $context = new WithdrawalPipelineContextData(
        voucher: issueVoucher(),
        payload: [],
    );

    try {
        $pipeline->process($context);
    } catch (RuntimeException $e) {
        expect($context->stepTrace)->toHaveCount(1)
            ->and($context->stepTrace[0]->step)->toBe(ObservedFailingStep::class)
            ->and($context->stepTrace[0]->status)->toBe('failed')
            ->and($context->stepTrace[0]->error)->toBe('Observed failure.');

        throw $e;
    }
})->throws(RuntimeException::class, 'Observed failure.');
