<?php

declare(strict_types=1);

use LBHurtado\XChange\Contracts\MoneyMovementAccountingDecisionContract;

it('describes money movement accounting candidates without mutating wallets', function () {
    $decision = app(MoneyMovementAccountingDecisionContract::class)->current();

    expect($decision->schema)->toBe('x-change.money-semantics.accounting-decision.v1')
        ->and($decision->status)->toBe('decision_required')
        ->and($decision->read_only)->toBeTrue()
        ->and($decision->current_model)->toBe('debit_at_issuance')
        ->and($decision->recommended_next_model)->toBe('reservation_release_pending_decision')
        ->and($decision->candidate_models)->toHaveCount(3)
        ->and(collect($decision->candidate_models)->pluck('key')->all())->toBe([
            'keep_debit_at_issuance',
            'debit_at_issuance_refund_on_terminal_release',
            'reserve_at_issuance_debit_at_redemption',
        ])
        ->and($decision->required_decisions)->toHaveCount(5)
        ->and($decision->redactions['mutates_wallets'])->toBeFalse()
        ->and($decision->redactions['reserves_funds'])->toBeFalse()
        ->and($decision->redactions['releases_funds'])->toBeFalse()
        ->and($decision->redactions['refunds_funds'])->toBeFalse();
});
