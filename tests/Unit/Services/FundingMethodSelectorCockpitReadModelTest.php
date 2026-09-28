<?php

declare(strict_types=1);

use LBHurtado\XChange\Services\Cockpit\FundingMethodSelectorCockpitReadModel;

it('presents account funding methods without inventing financial authority', function () {
    $readModel = (new FundingMethodSelectorCockpitReadModel)->forAccountFunding(
        [
            'bank_transfer' => [
                'enabled' => true,
                'reserved_exact_amounts_enabled' => true,
                'sender_reference_authority' => false,
            ],
        ],
        [
            'available' => true,
        ],
    );

    expect($readModel)
        ->toMatchArray([
            'schema' => 'x-change.cockpit.funding-method-selector.v1',
            'context' => 'account_funding',
            'intent_reference' => null,
            'amount' => null,
            'expires_at' => null,
            'status' => 'ready',
        ])
        ->and($readModel['methods'])->toHaveCount(3)
        ->and(collect($readModel['methods'])->pluck('key')->all())
        ->toBe(['qr_ph', 'bank_transfer', 'pay_code'])
        ->and(data_get($readModel, 'bank_transfer.reconciliation_reference'))
        ->toMatchArray([
            'mode' => 'disabled',
            'value' => null,
        ])
        ->and(data_get($readModel, 'bank_transfer.matching_strategies'))
        ->toBe([
            'reserved_exact_amount',
            'destination_account',
            'currency',
            'observation_window',
        ]);
});

it('keeps unavailable methods selectable so the workspace can explain the blocker', function () {
    $readModel = (new FundingMethodSelectorCockpitReadModel)->forAccountFunding(
        [
            'bank_transfer' => [
                'enabled' => false,
                'reserved_exact_amounts_enabled' => false,
            ],
        ],
        [
            'available' => false,
        ],
    );

    $methods = collect($readModel['methods'])->keyBy('key');

    expect($methods->get('qr_ph'))
        ->toMatchArray([
            'available' => false,
            'selectable' => true,
        ])
        ->and($methods->get('bank_transfer'))
        ->toMatchArray([
            'available' => false,
            'selectable' => true,
        ])
        ->and(data_get($readModel, 'bank_transfer.matching_strategies'))
        ->toContain('manual_review')
        ->not->toContain('reserved_exact_amount');
});
