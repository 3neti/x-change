<?php

declare(strict_types=1);

use LBHurtado\XChange\Contracts\MoneyMovementLifecycleTriggerMatrixContract;

it('marks implemented cancellation and expiry release triggers as operational', function () {
    $matrix = app(MoneyMovementLifecycleTriggerMatrixContract::class)->current();

    expect($matrix->schema)->toBe('x-change.money-semantics.lifecycle-trigger-matrix.v1')
        ->and($matrix->status)->toBe('partially_operational')
        ->and($matrix->read_only)->toBeTrue()
        ->and($matrix->triggers)->toHaveCount(6)
        ->and(collect($matrix->triggers)->pluck('event')->all())->toBe([
            'pay_code_issued',
            'pay_code_redeemed',
            'pay_code_partially_claimed',
            'pay_code_expired',
            'pay_code_cancelled',
            'provider_disbursement_failed_after_capture',
        ])
        ->and(collect($matrix->triggers)
            ->whereIn('event', ['pay_code_expired', 'pay_code_cancelled'])
            ->pluck('enabled')
            ->unique()
            ->all())->toBe([true])
        ->and(collect($matrix->triggers)
            ->reject(fn (array $trigger): bool => in_array(
                $trigger['event'],
                ['pay_code_expired', 'pay_code_cancelled'],
                true,
            ))
            ->pluck('enabled')
            ->unique()
            ->all())->toBe([false])
        ->and($matrix->open_questions)->toHaveCount(3)
        ->and($matrix->redactions['mutates_wallets'])->toBeTrue()
        ->and($matrix->redactions['reserves_funds'])->toBeFalse()
        ->and($matrix->redactions['captures_funds'])->toBeFalse()
        ->and($matrix->redactions['releases_funds'])->toBeTrue()
        ->and($matrix->redactions['reverses_funds'])->toBeFalse();
});
