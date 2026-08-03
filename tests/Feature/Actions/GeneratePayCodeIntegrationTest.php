<?php

declare(strict_types=1);

use Bavix\Wallet\Models\Wallet;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryInventoryOperationContract;
use LBHurtado\Wallet\Treasury\Data\TreasuryInventoryData;
use LBHurtado\Wallet\Treasury\Data\TreasuryInventoryRecognitionData;
use LBHurtado\Wallet\Treasury\Enums\TreasuryPositionPurpose;
use LBHurtado\Wallet\Treasury\Models\TreasuryInventory;
use LBHurtado\Wallet\Treasury\Models\TreasuryPosition;
use LBHurtado\XChange\Actions\Commercial\SettleCommercialProviderCost;
use LBHurtado\XChange\Actions\PayCode\GeneratePayCode;
use LBHurtado\XChange\Contracts\CommercialPartnerResolverContract;
use LBHurtado\XChange\Contracts\ProviderFundingPolicyContract;
use LBHurtado\XChange\Contracts\TreasuryAccountPortfolioProvisioningContract;
use LBHurtado\XChange\Contracts\VerifiedTreasuryFundingAllocationContract;
use LBHurtado\XChange\Contracts\VoucherLifecycleServiceContract;
use LBHurtado\XChange\Data\Commercial\ProviderCostEvidenceData;
use LBHurtado\XChange\Data\DebitData;
use LBHurtado\XChange\Data\FundingDecisionData;
use LBHurtado\XChange\Data\PayCode\GeneratePayCodeResultData;
use LBHurtado\XChange\Exceptions\CommercialSaleConflict;
use LBHurtado\XChange\Exceptions\InsufficientWalletBalance;
use LBHurtado\XChange\Exceptions\PayCodeIssuanceFailed;
use LBHurtado\XChange\Models\CommercialAllocation;
use LBHurtado\XChange\Models\CommercialProviderCostSettlement;
use LBHurtado\XChange\Models\CommercialSale;
use LBHurtado\XChange\Tests\Fakes\User;

it('generates a pay code end to end and debits the issuer wallet', function () {
    $user = actingAsTestUser(1_000_000);

    config()->set('app.url', 'https://example.test');

    $wallet = $user->wallet()->where('slug', 'platform')->first();
    expect($wallet)->not->toBeNull();

    $balanceBefore = (float) $wallet->balance;

    $payload = array_merge(validPayCodePayload(), [
        'issuer_id' => $user->id,
    ]);

    $action = app(GeneratePayCode::class);

    $result = $action->handle($payload);

    expect($result)->toBeInstanceOf(GeneratePayCodeResultData::class);

    expect($result->voucher_id)->not->toBeNull();
    expect($result->code)->toBeString();
    expect($result->amount)->toBe(100.0);
    expect($result->currency)->toBe('PHP');

    expect($result->issuer->id)->toBe($user->id);

    expect($result->cost->currency)->toBe('PHP');
    expect($result->cost->total)->toBeGreaterThan(0);

    expect((float) $result->wallet['balance_before'])->toBe($balanceBefore);
    expect((float) $result->wallet['balance_after'])->toBeLessThan($balanceBefore);

    expect($result->debit->id)->not->toBeNull();

    expect($result->links->redeem)->toContain($result->code);
    expect($result->links->redeem_path)->toContain($result->code);
    expect($result->links->redeem)->toBe("https://example.test/x/claim/{$result->code}");
    expect($result->links->redeem_path)->toBe("/x/claim/{$result->code}");

    $wallet->refresh();

    expect((float) $result->wallet['balance_before'])->toBe($balanceBefore);
    expect((float) $result->wallet['balance_after'])->toBeLessThan($balanceBefore);
    expect((float) $wallet->balance)->toBe((float) $result->wallet['balance_after']);

    expect($result->debit)->toBeInstanceOf(DebitData::class);
    expect($result->debit)->toHaveKey('id');

    $voucher = Voucher::query()->find($result->voucher_id);

    expect($voucher)->not->toBeNull();
    expect($voucher?->code)->toBe($result->code);
    expect($voucher?->instructions)->not->toBeNull();
    expect(data_get($voucher?->instructions, 'cash.amount'))->toBe(100.0);
});

it('does not emit the brick math float deprecation during voucher cash persistence', function () {
    $user = actingAsTestUser(1_000_000);

    $payload = array_merge(validPayCodePayload(25.0), [
        'issuer_id' => $user->id,
    ]);

    $deprecations = [];

    set_error_handler(function (int $severity, string $message, string $file, int $line) use (&$deprecations): bool {
        if (! str_contains($message, 'Passing floats to BigNumber::of()')) {
            return false;
        }

        $deprecations[] = [
            'severity' => $severity,
            'message' => $message,
            'file' => $file,
            'line' => $line,
            'trace_files' => collect(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS))
                ->pluck('file')
                ->filter()
                ->values()
                ->all(),
        ];

        return true;
    });

    try {
        $result = app(GeneratePayCode::class)->handle($payload);
    } finally {
        restore_error_handler();
    }

    expect($result)->toBeInstanceOf(GeneratePayCodeResultData::class)
        ->and($result->amount)->toBe(25.0)
        ->and($deprecations)->toBeEmpty();
});

it('fails end to end when issuer wallet cannot afford pay code generation', function () {
    $user = actingAsTestUser(0);

    $payload = array_merge(validPayCodePayload(100.0, 'INSTAPAY', ['inputs' => ['fields' => ['selfie']]]), [
        'issuer_id' => $user->id,
    ]);

    $action = app(GeneratePayCode::class);

    expect(fn () => $action->handle($payload))
        ->toThrow(InsufficientWalletBalance::class);
});

it('synchronizes the compatibility ledger and reserves Treasury principal for issuance', function () {
    $user = actingAsTestUser(0);
    enableNetbankTreasuryForTests();
    config()->set('x-change.commercial.enabled', true);
    app(TreasuryAccountPortfolioProvisioningContract::class)->provision(
        $user,
        ['netbank-primary'],
    );
    app(VerifiedTreasuryFundingAllocationContract::class)->allocate(
        accountReference: 'wallet:'.$user->wallet->uuid,
        provider: 'netbank',
        amountMinor: 5_000,
        currency: 'PHP',
        evidenceReference: 'netbank:cockpit-issuance:compatibility-sync',
    );
    $funding = Mockery::mock(ProviderFundingPolicyContract::class);
    $funding->shouldReceive('assertCanIssue')
        ->once()
        ->andReturn(FundingDecisionData::allowed(
            authority: 'local_ledger',
            availableMinor: 5_000,
            requiredMinor: 2_420,
            currency: 'PHP',
            meta: [
                'provider' => 'netbank',
                'topology' => 'ledger_pooled',
            ],
        ));
    app()->instance(ProviderFundingPolicyContract::class, $funding);
    $wallet = $user->wallet()->where('slug', 'platform')->firstOrFail();

    expect((int) $wallet->balanceInt)->toBe(0);

    $result = app(GeneratePayCode::class)->handle(validPayCodePayload(
        5,
        'INSTAPAY',
        [
            'inputs' => ['fields' => ['mobile']],
            'feedback' => [
                'email' => null,
                'mobile' => null,
                'webhook' => null,
            ],
            'provider' => 'netbank',
            'metadata' => [
                'issuer_id' => (string) $user->getKey(),
            ],
        ],
    ));

    $wallet->refresh();
    $accountDebitMinor = (int) round(
        ($result->cost->account_debit ?? (5 + $result->cost->total)) * 100,
    );
    $clientFundsMinor = treasuryClientFundsLedger($user)->getBalanceIntAttribute();
    $payCodeReserve = TreasuryPosition::query()
        ->whereMorphedTo('principal', $user)
        ->where('provider', 'netbank')
        ->where('purpose', TreasuryPositionPurpose::PayCodeReserve)
        ->sole();
    $payCodeReserveMinor = Wallet::query()
        ->findOrFail($payCodeReserve->internal_ledger_id)
        ->getBalanceIntAttribute();

    expect($result->wallet['balance_before'])->toBe(5_000)
        ->and((int) $wallet->balanceInt)->toBe(5_000 - $accountDebitMinor)
        ->and($clientFundsMinor)->toBe(5_000 - $accountDebitMinor)
        ->and($payCodeReserveMinor)->toBe(500)
        ->and(data_get(
            Voucher::query()->findOrFail($result->voucher_id)->metadata,
            'treasury.pay_code_reservation.amount_minor',
        ))->toBe(500);
});

it('fails closed when the compatibility ledger exceeds authoritative Client Funds', function () {
    $user = actingAsTestUser(6_000);
    enableNetbankTreasuryForTests();
    config()->set('x-change.commercial.enabled', true);
    app(TreasuryAccountPortfolioProvisioningContract::class)->provision(
        $user,
        ['netbank-primary'],
    );
    app(VerifiedTreasuryFundingAllocationContract::class)->allocate(
        accountReference: 'wallet:'.$user->wallet->uuid,
        provider: 'netbank',
        amountMinor: 5_000,
        currency: 'PHP',
        evidenceReference: 'netbank:cockpit-issuance:over-attribution',
    );
    $funding = Mockery::mock(ProviderFundingPolicyContract::class);
    $funding->shouldReceive('assertCanIssue')
        ->once()
        ->andReturn(FundingDecisionData::allowed(
            authority: 'local_ledger',
            availableMinor: 5_000,
            requiredMinor: 2_420,
            currency: 'PHP',
            meta: [
                'provider' => 'netbank',
                'topology' => 'ledger_pooled',
            ],
        ));
    app()->instance(ProviderFundingPolicyContract::class, $funding);
    $wallet = $user->wallet()->where('slug', 'platform')->firstOrFail();
    $voucherCount = Voucher::query()->count();

    expect(fn () => app(GeneratePayCode::class)->handle(validPayCodePayload(
        5,
        'INSTAPAY',
        [
            'inputs' => ['fields' => ['mobile']],
            'feedback' => [
                'email' => null,
                'mobile' => null,
                'webhook' => null,
            ],
            'provider' => 'netbank',
            'metadata' => [
                'issuer_id' => (string) $user->getKey(),
            ],
        ],
    )))->toThrow(
        PayCodeIssuanceFailed::class,
        'The Pay Code compatibility ledger exceeds authoritative Client Funds and requires review.',
    );

    $wallet->refresh();

    expect((int) $wallet->balanceInt)->toBe(6_000)
        ->and(treasuryClientFundsLedger($user)->getBalanceIntAttribute())->toBe(5_000)
        ->and(Voucher::query()->count())->toBe($voucherCount);
});

it('characterizes the complete Treasury issuance waterfall and cancellation boundary', function () {
    $user = actingAsTestUser(0);
    enableNetbankTreasuryForTests();
    config()->set('x-change.commercial.enabled', true);
    app(TreasuryAccountPortfolioProvisioningContract::class)->provision(
        $user,
        ['netbank-primary'],
    );
    app(VerifiedTreasuryFundingAllocationContract::class)->allocate(
        accountReference: 'wallet:'.$user->wallet->uuid,
        provider: 'netbank',
        amountMinor: 50_000,
        currency: 'PHP',
        evidenceReference: 'netbank:accounting-characterization',
    );
    recognizeAccountingInventoryForTest(
        50_000,
        'accounting-characterization',
    );
    $funding = Mockery::mock(ProviderFundingPolicyContract::class);
    $funding->shouldReceive('assertCanIssue')
        ->once()
        ->andReturn(FundingDecisionData::allowed(
            authority: 'local_ledger',
            availableMinor: 50_000,
            requiredMinor: 50_000,
            currency: 'PHP',
            meta: [
                'provider' => 'netbank',
                'topology' => 'ledger_pooled',
            ],
        ));
    app()->instance(ProviderFundingPolicyContract::class, $funding);

    $inventoryBefore = (int) TreasuryInventory::query()->sum('balance_minor');
    $clientFundsBefore = treasuryPositionBalanceForPurpose(
        TreasuryPositionPurpose::ClientFunds,
        $user,
    );

    $result = app(GeneratePayCode::class)->handle(validPayCodePayload(
        12.50,
        'INSTAPAY',
        [
            'inputs' => ['fields' => ['mobile']],
            'feedback' => [
                'email' => null,
                'mobile' => null,
                'webhook' => null,
            ],
            'provider' => 'netbank',
            'metadata' => [
                'issuer_id' => (string) $user->getKey(),
            ],
        ],
    ));

    $principalMinor = 1_250;
    $commercialChargeMinor = (int) round($result->cost->total * 100);
    $accountDebitMinor = (int) round($result->cost->account_debit * 100);
    $sale = CommercialSale::query()
        ->with('allocations')
        ->where('source_commercial_event_reference', 'pay-code-generation:voucher:'.$result->voucher_id)
        ->sole();
    $allocationTotalMinor = (int) $sale->allocations->sum('amount_minor');
    $commercialBalances = collect([
        TreasuryPositionPurpose::ProviderCostPayable,
        TreasuryPositionPurpose::ProductRevenue,
        TreasuryPositionPurpose::PartnerCommissionPayable,
        TreasuryPositionPurpose::CommercialRevenue,
    ])->mapWithKeys(fn (TreasuryPositionPurpose $purpose): array => [
        $purpose->value => treasuryPositionBalanceForPurpose($purpose),
    ]);

    expect($accountDebitMinor)->toBe($principalMinor + $commercialChargeMinor)
        ->and($sale->total_price_minor)->toBe($commercialChargeMinor)
        ->and(data_get($sale->snapshot, 'accounting_context'))->toMatchArray([
            'schema_version' => 2,
            'provider' => 'netbank',
            'connection_reference' => 'netbank-primary',
            'settlement_rail' => 'INSTAPAY',
            'currency' => 'PHP',
            'product_reference' => 'product:pay-code',
            'recognition_policy_reference' => 'recognition:pay-code-issuance:v1',
            'expected_provider_cost_minor' => 1_000,
        ])
        ->and($allocationTotalMinor)->toBe($commercialChargeMinor)
        ->and(CommercialAllocation::query()->where('commercial_sale_id', $sale->getKey())->count())
        ->toBe(4)
        ->and(treasuryPositionBalanceForPurpose(
            TreasuryPositionPurpose::ClientFunds,
            $user,
        ))->toBe($clientFundsBefore - $accountDebitMinor)
        ->and(treasuryPositionBalanceForPurpose(
            TreasuryPositionPurpose::PayCodeReserve,
            $user,
        ))->toBe($principalMinor)
        ->and(treasuryPositionBalanceForPurpose(
            TreasuryPositionPurpose::CommercialClearing,
        ))->toBe(0)
        ->and((int) $commercialBalances->sum())->toBe($commercialChargeMinor)
        ->and((int) TreasuryInventory::query()->sum('balance_minor'))->toBe($inventoryBefore);

    app(VoucherLifecycleServiceContract::class)->cancel(
        (string) $result->voucher_id,
        ['reason' => 'accounting_characterization'],
    );

    expect(treasuryPositionBalanceForPurpose(
        TreasuryPositionPurpose::ClientFunds,
        $user,
    ))->toBe($clientFundsBefore - $commercialChargeMinor)
        ->and(treasuryPositionBalanceForPurpose(
            TreasuryPositionPurpose::PayCodeReserve,
            $user,
        ))->toBe(0)
        ->and((int) collect([
            TreasuryPositionPurpose::ProviderCostPayable,
            TreasuryPositionPurpose::ProductRevenue,
            TreasuryPositionPurpose::PartnerCommissionPayable,
            TreasuryPositionPurpose::CommercialRevenue,
        ])->sum(fn (TreasuryPositionPurpose $purpose): int => treasuryPositionBalanceForPurpose($purpose)))
        ->toBe($commercialChargeMinor)
        ->and((int) TreasuryInventory::query()->sum('balance_minor'))->toBe($inventoryBefore);
});

it('settles provider costs only from exact authoritative cash-movement evidence', function () {
    $user = actingAsTestUser(0);
    enableNetbankTreasuryForTests();
    config()->set('x-change.commercial.enabled', true);
    app(TreasuryAccountPortfolioProvisioningContract::class)->provision(
        $user,
        ['netbank-primary'],
    );
    app(VerifiedTreasuryFundingAllocationContract::class)->allocate(
        accountReference: 'wallet:'.$user->wallet->uuid,
        provider: 'netbank',
        amountMinor: 50_000,
        currency: 'PHP',
        evidenceReference: 'netbank:provider-cost-settlement',
    );
    recognizeAccountingInventoryForTest(
        50_000,
        'provider-cost-settlement',
    );
    $funding = Mockery::mock(ProviderFundingPolicyContract::class);
    $funding->shouldReceive('assertCanIssue')
        ->once()
        ->andReturn(FundingDecisionData::allowed(
            authority: 'local_ledger',
            availableMinor: 50_000,
            requiredMinor: 50_000,
            currency: 'PHP',
            meta: [
                'provider' => 'netbank',
                'topology' => 'ledger_pooled',
            ],
        ));
    app()->instance(ProviderFundingPolicyContract::class, $funding);
    $result = app(GeneratePayCode::class)->handle(validPayCodePayload(
        12.50,
        'INSTAPAY',
        [
            'inputs' => ['fields' => ['mobile']],
            'feedback' => [
                'email' => null,
                'mobile' => null,
                'webhook' => null,
            ],
            'provider' => 'netbank',
            'metadata' => [
                'issuer_id' => (string) $user->getKey(),
            ],
        ],
    ));
    $sale = CommercialSale::query()
        ->where('source_commercial_event_reference', 'pay-code-generation:voucher:'.$result->voucher_id)
        ->sole();
    $inventoryBefore = (int) TreasuryInventory::query()->sum('balance_minor');
    $providerCostBefore = treasuryPositionBalanceForPurpose(
        TreasuryPositionPurpose::ProviderCostPayable,
    );
    $settlement = app(SettleCommercialProviderCost::class);
    $mismatchEvidence = new ProviderCostEvidenceData(
        commercialSaleReference: $sale->reference,
        provider: 'netbank',
        connectionReference: 'netbank-primary',
        evidenceType: 'account_debit',
        evidenceReference: 'netbank:fee-debit:mismatch',
        cashMovementObserved: true,
        observedAmountMinor: 900,
        currency: 'PHP',
        observedAt: now()->toRfc3339String(),
        idempotencyKey: 'provider-cost:mismatch',
    );

    $review = $settlement->execute($mismatchEvidence);
    $reviewReplay = $settlement->execute($mismatchEvidence);

    expect($review->status)->toBe('review_required')
        ->and($reviewReplay->getKey())->toBe($review->getKey())
        ->and($review->variance_amount_minor)->toBe(-100)
        ->and(treasuryPositionBalanceForPurpose(
            TreasuryPositionPurpose::ProviderCostPayable,
        ))->toBe($providerCostBefore)
        ->and((int) TreasuryInventory::query()->sum('balance_minor'))->toBe($inventoryBefore);

    $exactEvidence = new ProviderCostEvidenceData(
        commercialSaleReference: $sale->reference,
        provider: 'netbank',
        connectionReference: 'netbank-primary',
        evidenceType: 'account_debit',
        evidenceReference: 'netbank:fee-debit:exact',
        cashMovementObserved: true,
        observedAmountMinor: 1_000,
        currency: 'PHP',
        observedAt: now()->toRfc3339String(),
        idempotencyKey: 'provider-cost:exact',
    );
    $posted = $settlement->execute($exactEvidence);
    $postedReplay = $settlement->execute($exactEvidence);

    expect($posted->status)->toBe('settled')
        ->and($postedReplay->getKey())->toBe($posted->getKey())
        ->and($posted->variance_amount_minor)->toBe(0)
        ->and($posted->position_operation_reference)->not->toBeNull()
        ->and($posted->inventory_operation_reference)->not->toBeNull()
        ->and(treasuryPositionBalanceForPurpose(
            TreasuryPositionPurpose::ProviderCostPayable,
        ))->toBe($providerCostBefore - 1_000)
        ->and((int) TreasuryInventory::query()->sum('balance_minor'))->toBe($inventoryBefore - 1_000)
        ->and(CommercialProviderCostSettlement::query()->count())->toBe(2)
        ->and(fn () => $settlement->execute(new ProviderCostEvidenceData(
            commercialSaleReference: $sale->reference,
            provider: 'netbank',
            connectionReference: 'netbank-primary',
            evidenceType: 'account_debit',
            evidenceReference: 'netbank:fee-debit:changed',
            cashMovementObserved: true,
            observedAmountMinor: 1_000,
            currency: 'PHP',
            observedAt: now()->toRfc3339String(),
            idempotencyKey: 'provider-cost:exact',
        )))->toThrow(CommercialSaleConflict::class, 'different evidence');
});

it('accrues an attributed partner commission to the partner principal', function () {
    $user = actingAsTestUser(0);
    $system = enableNetbankTreasuryForTests();
    config()->set('x-change.commercial.enabled', true);
    app(TreasuryAccountPortfolioProvisioningContract::class)->provision(
        $user,
        ['netbank-primary'],
    );
    app(VerifiedTreasuryFundingAllocationContract::class)->allocate(
        accountReference: 'wallet:'.$user->wallet->uuid,
        provider: 'netbank',
        amountMinor: 50_000,
        currency: 'PHP',
        evidenceReference: 'netbank:partner-commission-attribution',
    );
    $partner = User::query()->create([
        'name' => 'Accredited Marketing Partner',
        'email' => 'partner@example.test',
        'password' => 'not-a-login-credential',
    ]);
    $partners = Mockery::mock(CommercialPartnerResolverContract::class);
    $partners->shouldReceive('resolve')
        ->once()
        ->with('partner:marketing-42')
        ->andReturn($partner);
    app()->instance(CommercialPartnerResolverContract::class, $partners);
    $funding = Mockery::mock(ProviderFundingPolicyContract::class);
    $funding->shouldReceive('assertCanIssue')
        ->once()
        ->andReturn(FundingDecisionData::allowed(
            authority: 'local_ledger',
            availableMinor: 50_000,
            requiredMinor: 50_000,
            currency: 'PHP',
            meta: [
                'provider' => 'netbank',
                'topology' => 'ledger_pooled',
            ],
        ));
    app()->instance(ProviderFundingPolicyContract::class, $funding);

    $result = app(GeneratePayCode::class)->handle(validPayCodePayload(
        12.50,
        'INSTAPAY',
        [
            'inputs' => ['fields' => ['mobile']],
            'feedback' => [
                'email' => null,
                'mobile' => null,
                'webhook' => null,
            ],
            'provider' => 'netbank',
            'metadata' => [
                'issuer_id' => (string) $user->getKey(),
                'commercial_attribution' => [
                    'partner' => 'partner:marketing-42',
                ],
            ],
        ],
    ));

    $sale = CommercialSale::query()
        ->where('source_commercial_event_reference', 'pay-code-generation:voucher:'.$result->voucher_id)
        ->sole();
    $allocation = CommercialAllocation::query()
        ->where('commercial_sale_id', $sale->getKey())
        ->where('category', 'partner_commission')
        ->sole();
    $partnerPosition = TreasuryPosition::query()
        ->where('position_reference', $allocation->destination_position_reference)
        ->whereMorphedTo('principal', $partner)
        ->sole();

    expect($allocation->recipient_reference)->toBe('partner:direct')
        ->and(data_get($sale->snapshot, 'accounting_context.partner_reference'))
        ->toBe('partner:marketing-42')
        ->and((int) Wallet::query()
            ->findOrFail($partnerPosition->internal_ledger_id)
            ->balanceInt)->toBe(100)
        ->and(treasuryPositionBalanceForPurpose(
            TreasuryPositionPurpose::PartnerCommissionPayable,
            $system,
        ))->toBe(0);
});

it('characterizes that cancellation does not credit issuer wallet funds today', function () {
    $user = actingAsTestUser(1_000_000);
    $wallet = $user->wallet()->where('slug', 'platform')->firstOrFail();

    $result = app(GeneratePayCode::class)->handle(array_merge(validPayCodePayload(25.0), [
        'issuer_id' => $user->id,
    ]));

    $wallet->refresh();
    $afterIssuance = (int) $wallet->balance;

    app(VoucherLifecycleServiceContract::class)->cancel((string) $result->voucher_id, [
        'reason' => 'money semantics characterization',
    ]);

    $wallet->refresh();

    expect((int) $wallet->balance)->toBe($afterIssuance);
});

it('characterizes that expiry does not credit issuer wallet funds today', function () {
    $user = actingAsTestUser(1_000_000);
    $wallet = $user->wallet()->where('slug', 'platform')->firstOrFail();

    $result = app(GeneratePayCode::class)->handle(array_merge(validPayCodePayload(25.0), [
        'issuer_id' => $user->id,
    ]));

    $wallet->refresh();
    $afterIssuance = (int) $wallet->balance;

    Voucher::query()
        ->whereKey($result->voucher_id)
        ->update(['expires_at' => now()->subMinute()]);

    $wallet->refresh();

    expect((int) $wallet->balance)->toBe($afterIssuance);
});

function treasuryPositionBalanceForPurpose(
    TreasuryPositionPurpose $purpose,
    mixed $principal = null,
): int {
    return (int) TreasuryPosition::query()
        ->when(
            $principal !== null,
            fn ($query) => $query->whereMorphedTo('principal', $principal),
        )
        ->where('provider', 'netbank')
        ->where('connection_reference', 'netbank-primary')
        ->where('purpose', $purpose)
        ->get()
        ->sum(fn (TreasuryPosition $position): int => (int) Wallet::query()
            ->findOrFail($position->internal_ledger_id)
            ->balanceInt);
}

function recognizeAccountingInventoryForTest(
    int $amountMinor,
    string $scope,
): void {
    $inventory = app(TreasuryInventoryOperationContract::class);
    $inventory->registerInventory(new TreasuryInventoryData(
        inventoryReference: 'inventory:netbank:vca-cash',
        resourceType: 'cash_at_bank',
        currency: 'PHP',
        capacityMinor: 0,
        status: 'requested',
        idempotencyKey: 'accounting-inventory-registration:'.$scope,
        externalReference: 'resource:netbank:corporate-vca',
    ));
    $inventory->recognize(new TreasuryInventoryRecognitionData(
        operationReference: 'accounting-inventory-recognition:'.$scope,
        inventoryReference: 'inventory:netbank:vca-cash',
        settlementResourceReference: 'resource:netbank:corporate-vca',
        amountMinor: $amountMinor,
        currency: 'PHP',
        status: 'requested',
        idempotencyKey: 'accounting-inventory-recognition-key:'.$scope,
        effectiveAt: now()->toRfc3339String(),
        externalReference: 'provider-observation:'.$scope,
    ));
}
