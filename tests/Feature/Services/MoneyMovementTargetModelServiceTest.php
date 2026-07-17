<?php

declare(strict_types=1);

use LBHurtado\XChange\Contracts\MoneyMovementTargetModelContract;

it('records the recommended money movement target model without selecting it', function () {
    $target = app(MoneyMovementTargetModelContract::class)->current();

    expect($target->schema)->toBe('x-change.money-semantics.target-model.v1')
        ->and($target->status)->toBe('pending_human_approval')
        ->and($target->read_only)->toBeTrue()
        ->and($target->current_model)->toBe('debit_at_issuance')
        ->and($target->recommended_model)->toBe('reserve_at_issuance_debit_at_redemption')
        ->and($target->selected_model)->toBeNull()
        ->and($target->requires_human_approval)->toBeTrue()
        ->and($target->approval_requirements)->toHaveCount(5)
        ->and($target->implementation_boundaries)->toHaveCount(5)
        ->and($target->redactions['mutates_wallets'])->toBeFalse()
        ->and($target->redactions['reserves_funds'])->toBeFalse()
        ->and($target->redactions['releases_funds'])->toBeFalse()
        ->and($target->redactions['refunds_funds'])->toBeFalse();
});
