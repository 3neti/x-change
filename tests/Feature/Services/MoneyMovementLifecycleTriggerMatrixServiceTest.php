<?php

declare(strict_types=1);

use LBHurtado\XChange\Contracts\MoneyMovementLifecycleTriggerMatrixContract;

it('describes lifecycle money movement triggers without enabling them', function () {
    $matrix = app(MoneyMovementLifecycleTriggerMatrixContract::class)->current();

    expect($matrix->schema)->toBe('x-change.money-semantics.lifecycle-trigger-matrix.v1')
        ->and($matrix->status)->toBe('planning_only')
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
        ->and(collect($matrix->triggers)->pluck('enabled')->unique()->all())->toBe([false])
        ->and($matrix->open_questions)->toHaveCount(4)
        ->and($matrix->redactions['mutates_wallets'])->toBeFalse()
        ->and($matrix->redactions['reserves_funds'])->toBeFalse()
        ->and($matrix->redactions['captures_funds'])->toBeFalse()
        ->and($matrix->redactions['releases_funds'])->toBeFalse()
        ->and($matrix->redactions['reverses_funds'])->toBeFalse();
});
