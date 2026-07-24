<?php

declare(strict_types=1);

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryInventoryOperationContract;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryInventoryPositionReadModelContract;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryPositionOperationContract;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryPositionReadModelContract;
use LBHurtado\Wallet\Treasury\Data\TreasuryInventoryAdjustmentData;
use LBHurtado\Wallet\Treasury\Data\TreasuryInventoryData;
use LBHurtado\Wallet\Treasury\Data\TreasuryInventoryRecognitionData;
use LBHurtado\Wallet\Treasury\Data\TreasuryPositionData;
use LBHurtado\Wallet\Treasury\Data\TreasuryPositionDerecognitionData;
use LBHurtado\Wallet\Treasury\Data\TreasuryPositionReservationData;
use LBHurtado\Wallet\Treasury\Enums\TreasuryPositionPurpose;
use LBHurtado\Wallet\Treasury\Models\TreasuryInventoryOperation;
use LBHurtado\Wallet\Treasury\Models\TreasuryPositionOperation;
use LBHurtado\XChange\Contracts\TreasuryPrincipalReferenceResolverContract;
use LBHurtado\XChange\Contracts\VerifiedTreasuryFundingAllocationContract;
use LBHurtado\XChange\Models\LifecycleMoneyRun;

it('corrects a legacy provider fee posting once through append-only operations', function () {
    $fixture = legacyPayCodeFeePostingFixture();
    $arguments = [
        'run' => $fixture['run']->reference,
        '--json' => true,
    ];

    $dryRunExit = Artisan::call(
        'x-change:treasury:correct-pay-code-fee-posting',
        $arguments,
    );
    $dryRun = json_decode(Artisan::output(), true);
    $commitExit = Artisan::call(
        'x-change:treasury:correct-pay-code-fee-posting',
        [...$arguments, '--commit' => true],
    );
    $commitOutput = Artisan::output();
    $committed = json_decode($commitOutput, true);
    $replayExit = Artisan::call(
        'x-change:treasury:correct-pay-code-fee-posting',
        [...$arguments, '--commit' => true],
    );
    $replayed = json_decode(Artisan::output(), true);
    $clientFunds = app(TreasuryPositionReadModelContract::class)
        ->find($fixture['client_funds']->positionReference);
    $inventory = app(
        TreasuryInventoryPositionReadModelContract::class,
    )->find('inventory:netbank:vca-cash');
    $run = $fixture['run']->refresh();

    expect($dryRunExit)->toBe(Command::SUCCESS)
        ->and($dryRun['status'])->toBe('dry_run')
        ->and($dryRun['excess_fee_amount_minor'])->toBe(1_000)
        ->and($dryRun['committed'])->toBeFalse()
        ->and($commitExit)->toBe(Command::SUCCESS, $commitOutput)
        ->and($committed['status'])->toBe('corrected')
        ->and($committed['beneficiary_amount_minor'])->toBe(1_250)
        ->and($committed['excess_fee_amount_minor'])->toBe(1_000)
        ->and($replayExit)->toBe(Command::SUCCESS)
        ->and($replayed['status'])->toBe('already_corrected')
        ->and($clientFunds?->balanceMinor)->toBe(8_750)
        ->and($inventory?->balanceMinor)->toBe(8_750)
        ->and(data_get(
            $run->result_summary,
            'treasury_settlement.provider_inventory_outflow_minor',
        ))->toBe(1_250)
        ->and(data_get(
            $run->result_summary,
            'treasury_settlement.configured_rail_fee_minor',
        ))->toBe(1_000)
        ->and(data_get(
            $run->result_summary,
            'treasury_settlement.sender_system_charge_minor',
        ))->toBe(1_500)
        ->and(data_get(
            $run->result_summary,
            'accounting_boundary.outbound_treasury_posting',
        ))->toBe('provider_principal_only')
        ->and(TreasuryInventoryOperation::query()
            ->where('operation_reference', $committed['inventory_correction_reference'])
            ->count())->toBe(1)
        ->and(TreasuryPositionOperation::query()
            ->where('operation_reference', $committed['position_recognition_reference'])
            ->count())->toBe(1)
        ->and(TreasuryPositionOperation::query()
            ->where('operation_reference', $committed['position_allocation_reference'])
            ->count())->toBe(1);
});

it('rejects a correction when the provider observation does not equal the excess fee', function () {
    $fixture = legacyPayCodeFeePostingFixture();
    $summary = $fixture['run']->result_summary;
    data_set(
        $summary,
        'accounting.after_claim.connections.0.provider_observation.difference_minor',
        999,
    );
    $fixture['run']->forceFill(['result_summary' => $summary])->save();

    $exitCode = Artisan::call(
        'x-change:treasury:correct-pay-code-fee-posting',
        [
            'run' => $fixture['run']->reference,
            '--commit' => true,
            '--json' => true,
        ],
    );

    expect($exitCode)->toBe(Command::FAILURE)
        ->and(TreasuryInventoryOperation::query()
            ->where('operation_reference', 'like', 'pay-code-fee-boundary-inventory-correction:%')
            ->count())->toBe(0)
        ->and(TreasuryPositionOperation::query()
            ->where('operation_reference', 'like', 'pay-code-fee-boundary-position-%')
            ->count())->toBe(0);
});

/**
 * @return array{
 *     run: LifecycleMoneyRun,
 *     client_funds: TreasuryPositionData
 * }
 */
function legacyPayCodeFeePostingFixture(): array
{
    enableNetbankTreasuryForTests();
    $owner = actingAsTestUser();
    $account = $owner->wallet()->where('slug', 'platform')->sole();
    $inventoryOperations = app(TreasuryInventoryOperationContract::class);
    $inventoryOperations->registerInventory(new TreasuryInventoryData(
        inventoryReference: 'inventory:netbank:vca-cash',
        resourceType: 'cash_at_bank',
        currency: 'PHP',
        capacityMinor: 0,
        status: 'requested',
        idempotencyKey: 'register:inventory:netbank:vca-cash',
        externalReference: 'resource:netbank:corporate-vca',
    ));
    $inventoryOperations->recognize(new TreasuryInventoryRecognitionData(
        operationReference: 'funding-recognition:legacy-fee-test',
        inventoryReference: 'inventory:netbank:vca-cash',
        settlementResourceReference: 'resource:netbank:corporate-vca',
        amountMinor: 10_000,
        currency: 'PHP',
        status: 'requested',
        idempotencyKey: 'funding-recognition-key:legacy-fee-test',
        externalReference: 'netbank:legacy-fee-test',
    ));
    app(VerifiedTreasuryFundingAllocationContract::class)->allocate(
        accountReference: 'wallet:'.$account->uuid,
        provider: 'netbank',
        amountMinor: 10_000,
        currency: 'PHP',
        evidenceReference: 'netbank:legacy-fee-test',
    );
    $principalReference = app(
        TreasuryPrincipalReferenceResolverContract::class,
    )->resolve($owner);
    $accountPositions = app(TreasuryPositionReadModelContract::class)
        ->forPrincipal($principalReference);
    $clientFunds = collect($accountPositions)
        ->first(
            fn (TreasuryPositionData $position): bool => $position->purpose
                === TreasuryPositionPurpose::ClientFunds,
        );
    $reserve = collect($accountPositions)
        ->first(
            fn (TreasuryPositionData $position): bool => $position->purpose
                === TreasuryPositionPurpose::PayCodeReserve,
        );

    if (
        ! $clientFunds instanceof TreasuryPositionData
        || ! $reserve instanceof TreasuryPositionData
    ) {
        throw new RuntimeException('The legacy fee fixture requires client and reserve Positions.');
    }

    $voucher = new Voucher([
        'code' => 'LEGACY-FEE-'.str()->upper(str()->random(6)),
        'metadata' => [
            'instructions' => [
                'cash' => [
                    'amount' => 12.50,
                    'currency' => 'PHP',
                    'validation' => [
                        'country' => 'PH',
                    ],
                    'settlement_rail' => 'INSTAPAY',
                    'fee_strategy' => 'absorb',
                ],
                'inputs' => ['fields' => []],
                'feedback' => [],
                'rider' => [],
                'count' => 1,
            ],
            'disbursement' => [
                'amount' => 12.50,
                'fee_amount' => 1_000,
                'total_cost' => 2_250,
            ],
        ],
    ]);
    $voucher->owner()->associate($owner);
    $voucher->save();
    $positionOperations = app(TreasuryPositionOperationContract::class);
    $reservationReference = 'pay-code-position-reservation:legacy-fee-test';
    $derecognitionReference = 'pay-code-position-derecognition:legacy-fee-test';
    $inventoryReference = 'pay-code-inventory-outflow:legacy-fee-test';
    $positionOperations->reserve(new TreasuryPositionReservationData(
        operationReference: $reservationReference,
        sourcePositionReference: $clientFunds->positionReference,
        destinationPositionReference: $reserve->positionReference,
        amountMinor: 2_250,
        currency: 'PHP',
        idempotencyKey: 'pay-code-position-reservation-key:legacy-fee-test',
        externalReference: 'pay-code:'.$voucher->getKey(),
    ));
    $positionOperations->derecognize(
        new TreasuryPositionDerecognitionData(
            operationReference: $derecognitionReference,
            sourcePositionReference: $reserve->positionReference,
            amountMinor: 2_250,
            currency: 'PHP',
            idempotencyKey: 'pay-code-position-derecognition-key:legacy-fee-test',
            externalReference: 'netbank:legacy-fee-test',
        ),
    );
    $inventoryOperations->adjust(new TreasuryInventoryAdjustmentData(
        operationReference: $inventoryReference,
        inventoryReference: 'inventory:netbank:vca-cash',
        deltaAmountMinor: -2_250,
        currency: 'PHP',
        status: 'requested',
        idempotencyKey: 'pay-code-inventory-outflow-key:legacy-fee-test',
        externalReference: 'netbank:legacy-fee-test',
    ));
    $run = LifecycleMoneyRun::query()->create([
        'reference' => 'LEGACY-FEE-RUN-'.str()->upper(str()->random(6)),
        'scenario_key' => 'treasury_live_basic_cash',
        'run_reference_hash' => hash('sha256', (string) str()->uuid()),
        'run_fingerprint' => hash('sha256', (string) str()->uuid()),
        'issuer_type' => $owner->getMorphClass(),
        'issuer_id' => $owner->getKey(),
        'provider_code' => 'netbank',
        'amount_minor' => 1_250,
        'currency' => 'PHP',
        'status' => 'provider_sync_pending',
        'voucher_id' => $voucher->getKey(),
        'result_summary' => [
            'provider_transfer_succeeded' => true,
            'treasury_settlement' => [
                'reservation_operation_reference' => $reservationReference,
                'derecognition_operation_reference' => $derecognitionReference,
                'inventory_adjustment_operation_reference' => $inventoryReference,
                'beneficiary_amount_minor' => 1_250,
                'provider_fee_amount_minor' => 1_000,
                'provider_outflow_minor' => 2_250,
            ],
            'accounting' => [
                'before_issuance' => [
                    'account' => [
                        'legacy_compatibility_balance_minor' => 1_000_000,
                    ],
                ],
                'after_issuance' => [
                    'account' => [
                        'legacy_compatibility_balance_minor' => 997_250,
                    ],
                ],
                'after_claim' => [
                    'connections' => [[
                        'reference' => 'netbank-primary',
                        'provider' => 'netbank',
                        'provider_observation' => [
                            'balance_minor' => 8_750,
                            'difference_minor' => 1_000,
                        ],
                    ]],
                ],
            ],
            'accounting_boundary' => [
                'outbound_treasury_posting' => 'treasury_position_and_inventory_posted',
            ],
        ],
        'started_at' => now()->subMinute(),
        'completed_at' => now(),
    ]);

    return [
        'run' => $run,
        'client_funds' => $clientFunds,
    ];
}
