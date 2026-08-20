<?php

declare(strict_types=1);

use Bavix\Wallet\Models\Wallet;
use Illuminate\Validation\ValidationException;
use LBHurtado\Voucher\Data\VoucherOperationalSummaryData;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\Voucher\Services\VoucherSlicePlanFactory;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryInventoryOperationContract;
use LBHurtado\Wallet\Treasury\Data\TreasuryInventoryData;
use LBHurtado\Wallet\Treasury\Data\TreasuryInventoryRecognitionData;
use LBHurtado\Wallet\Treasury\Enums\TreasuryPositionPurpose;
use LBHurtado\Wallet\Treasury\Models\TreasuryInventory;
use LBHurtado\Wallet\Treasury\Models\TreasuryPosition;
use LBHurtado\XChange\Actions\Commercial\ApprovePartnerCommissionPayout;
use LBHurtado\XChange\Actions\Commercial\RequestPartnerCommissionPayout;
use LBHurtado\XChange\Actions\Commercial\SettleCommercialProviderCost;
use LBHurtado\XChange\Actions\Commercial\SettlePartnerCommissionPayout;
use LBHurtado\XChange\Actions\PayCode\GeneratePayCode;
use LBHurtado\XChange\Contracts\CommercialComponentEconomicsResolverContract;
use LBHurtado\XChange\Contracts\CommercialOfferingResolverContract;
use LBHurtado\XChange\Contracts\CommercialPartnerResolverContract;
use LBHurtado\XChange\Contracts\ProviderFundingPolicyContract;
use LBHurtado\XChange\Contracts\TreasuryAccountPortfolioProvisioningContract;
use LBHurtado\XChange\Contracts\VerifiedTreasuryFundingAllocationContract;
use LBHurtado\XChange\Contracts\VoucherLifecycleServiceContract;
use LBHurtado\XChange\Data\Commercial\PartnerCommissionPayoutEvidenceData;
use LBHurtado\XChange\Data\Commercial\ProviderCostEvidenceData;
use LBHurtado\XChange\Data\DebitData;
use LBHurtado\XChange\Data\FundingDecisionData;
use LBHurtado\XChange\Data\PayCode\GeneratePayCodeResultData;
use LBHurtado\XChange\Enums\CommercialActivationAuthority;
use LBHurtado\XChange\Enums\CommercialBillableEventStatus;
use LBHurtado\XChange\Enums\CommercialOfferingOrigin;
use LBHurtado\XChange\Exceptions\CommercialSaleConflict;
use LBHurtado\XChange\Exceptions\InsufficientWalletBalance;
use LBHurtado\XChange\Exceptions\PayCodeIssuanceFailed;
use LBHurtado\XChange\Models\CommercialAllocation;
use LBHurtado\XChange\Models\CommercialBillableEvent;
use LBHurtado\XChange\Models\CommercialOfferingActivation;
use LBHurtado\XChange\Models\CommercialProviderCostSettlement;
use LBHurtado\XChange\Models\CommercialSale;
use LBHurtado\XChange\Models\PartnerCommissionPayout;
use LBHurtado\XChange\Services\Commercial\ActivateCommercialComponentEconomics;
use LBHurtado\XChange\Services\Commercial\ActivateCommercialRecipientDesignation;
use LBHurtado\XChange\Services\Commercial\CommercialComponentEconomicsManifestCompiler;
use LBHurtado\XChange\Services\Commercial\CommercialControlReadModel;
use LBHurtado\XChange\Services\Commercial\PersistCommercialComponentEconomicsManifest;
use LBHurtado\XChange\Services\Commercial\ProvisionCommercialBaselines;
use LBHurtado\XChange\Tests\Fakes\User;
use LBHurtado\XCommerce\Data\CommercialComponentEconomicsSetData;
use LBHurtado\XJournal\Models\ExecutionJournalEntry;
use LBHurtado\XProvisioning\Data\CommercialRecipientDesignationData;

beforeEach(function (): void {
    config()->set('x-change.commercial.legal_trace.legal_entity_reference', 'legal-entity:x-change:test');
    config()->set('x-change.commercial.legal_trace.profile_version', 'test-v1');
    app(ProvisionCommercialBaselines::class)->provision('commissioning-manifest:generate-pay-code-integration');
});

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

it('persists a canonical slice plan through issuance and exposes it to results and x ray', function (
    string $presentationMode,
    float $amount,
    string $expectedBadge,
    array $expectedLabels,
) {
    $user = actingAsTestUser(1_000_000);
    $factory = app(VoucherSlicePlanFactory::class);
    $plan = match ($presentationMode) {
        'fixed' => $factory->equal(7_500, 'PHP', 3),
        'open' => $factory->flexible(15_000, 'PHP', 4, 2_500),
        'named' => $factory->scheduled(10_000, 'PHP', [
            ['id' => 'morning', 'label' => 'Morning fare', 'amount_minor' => 4_000],
            ['id' => 'evening', 'label' => 'Evening fare', 'amount_minor' => 6_000],
        ]),
    };
    $payload = validPayCodePayload($amount, overrides: [
        'issuer_id' => $user->id,
        'slice_plan' => $plan->canonicalArray(),
        'metadata' => [
            'custom' => [
                'cockpit' => [
                    'slice_plan' => [
                        'schema' => 'x-change.cockpit.slice-plan.v1',
                        'mode' => $presentationMode,
                    ],
                ],
            ],
        ],
    ]);

    $result = app(GeneratePayCode::class)->handle($payload);
    $voucher = Voucher::query()->findOrFail($result->voucher_id);
    $summary = VoucherOperationalSummaryData::fromInstructions($voucher->instructions);

    expect($voucher->instructions->slice_plan?->canonicalArray())
        ->toBe($plan->canonicalArray())
        ->and(collect($summary->instruction_badges)->pluck('label')->all())
        ->toContain($expectedBadge);

    auth()->logout();
    config()->set('x-ray.disclosure.guest.show_remaining_slices', 'if_allowed_by_voucher');
    $response = $this->postJson(xchangeApi('pay-codes/x-ray'), [
        'code' => $voucher->code,
        'channel' => 'claim',
    ])->assertOk();
    $sliceDisclosure = collect($response->json('data.xray.disclosures'))
        ->firstWhere('key', 'remaining_slices');

    expect($sliceDisclosure)->toBeArray()
        ->and(collect($sliceDisclosure['value'])->pluck('label')->all())
        ->toBe($expectedLabels);
})->with([
    'equal' => ['fixed', 75.0, 'Divisible · 3 slices', ['Slice 1', 'Slice 2', 'Slice 3']],
    'flexible' => ['open', 150.0, 'Divisible · Flexible', ['Remaining capacity']],
    'scheduled' => ['named', 100.0, 'Divisible · 2 labeled slices', ['Morning fare', 'Evening fare']],
]);

it('rejects a stale cockpit slice presentation before issuing or charging', function () {
    $user = actingAsTestUser(1_000_000);
    $wallet = $user->wallet()->where('slug', 'platform')->firstOrFail();
    $balanceBefore = (float) $wallet->balance;
    $voucherCountBefore = Voucher::query()->count();
    $payload = validPayCodePayload(75, overrides: [
        'issuer_id' => $user->id,
        'metadata' => [
            'custom' => [
                'cockpit' => [
                    'slice_plan' => [
                        'schema' => 'x-change.cockpit.slice-plan.v1',
                        'mode' => 'fixed',
                    ],
                ],
            ],
        ],
    ]);

    expect(fn () => app(GeneratePayCode::class)->handle($payload))
        ->toThrow(ValidationException::class, 'outdated Quick Generate session');

    expect(Voucher::query()->count())->toBe($voucherCountBefore)
        ->and((float) $wallet->fresh()->balance)->toBe($balanceBefore);
});

it('does not emit the brick math float deprecation during voucher cash persistence', function () {
    $user = actingAsTestUser(1_000_000);

    $payload = array_merge(validPayCodePayload(25.0, 'INSTAPAY', [
        'inputs' => ['fields' => ['selfie']],
    ]), [
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
        ->and(data_get(
            Voucher::query()->findOrFail($result->voucher_id)->instructions,
            'metadata.custom.claim_evidence.requirements',
        ))->toContain('selfie', 'signature')
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
        ->with(['allocations', 'billableEvents'])
        ->where('source_commercial_event_reference', 'pay-code-generation:voucher:'.$result->voucher_id)
        ->sole();
    $allocationTotalMinor = (int) $sale->allocations->sum('amount_minor');
    $commercialBalances = collect([
        TreasuryPositionPurpose::ProviderCostPayable,
        TreasuryPositionPurpose::ProductRevenue,
        TreasuryPositionPurpose::PartnerCommissionPayable,
        TreasuryPositionPurpose::RoyaltyPayable,
        TreasuryPositionPurpose::CommercialRevenue,
    ])->mapWithKeys(fn (TreasuryPositionPurpose $purpose): array => [
        $purpose->value => treasuryPositionBalanceForPurpose($purpose),
    ]);
    $controlPositions = collect(app(CommercialControlReadModel::class)->build(
        app(CommercialOfferingResolverContract::class)->resolve('pay_code'),
    )['position_balances'])->keyBy('purpose');

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
            'expected_provider_cost_minor' => 0,
        ])
        ->and($allocationTotalMinor)->toBe($commercialChargeMinor)
        ->and(CommercialAllocation::query()->where('commercial_sale_id', $sale->getKey())->count())
        ->toBe(3)
        ->and($sale->billableEvents)->toHaveCount(3)
        ->and((int) $sale->billableEvents->sum('total_amount_minor'))->toBe($commercialChargeMinor)
        ->and($sale->billableEvents->every(
            static fn (CommercialBillableEvent $event): bool => $event->status === CommercialBillableEventStatus::Posted
                && $event->event_type === 'pay_code.issued_with_component'
                && $event->recognition_policy_reference === 'recognition:pay-code-issuance:v1'
                && $event->recognition_policy_version === 1
                && data_get($event->recognition_policy_snapshot, 'trigger') === 'commercial_sale.accepted'
                && data_get($event->recognition_policy_snapshot, 'timing') === 'immediate'
                && preg_match('/^[a-f0-9]{64}$/', (string) $event->recognition_policy_hash) === 1
                && $event->quantity === 1
                && $event->total_amount_minor === $event->unit_amount_minor,
        ))->toBeTrue()
        ->and(ExecutionJournalEntry::query()
            ->where('correlation_id', 'commercial-sale:'.$sale->reference)
            ->where('event_type', 'commercial.billable_event.posted')
            ->count())->toBe(3)
        ->and($sale->allocations->every(
            static fn (CommercialAllocation $allocation): bool => $allocation->category === 'service_provider_payable'
                && data_get($allocation->metadata, 'designation_reference') === 'designation:commissioning:3neti:v1'
                && filled(data_get($allocation->metadata, 'component_reference')),
        ))->toBeTrue()
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
        ->and(treasuryPositionBalanceForPurpose(TreasuryPositionPurpose::RoyaltyPayable))
        ->toBe($commercialChargeMinor)
        ->and($controlPositions->get(TreasuryPositionPurpose::RoyaltyPayable->value))->toMatchArray([
            'current_minor' => $commercialChargeMinor,
            'lifetime_allocated_minor' => $commercialChargeMinor,
            'settled_minor' => 0,
            'remaining_minor' => $commercialChargeMinor,
            'reconciled' => true,
        ])
        ->and(treasuryPositionBalanceForPurpose(TreasuryPositionPurpose::ProviderCostPayable))->toBe(0)
        ->and(treasuryPositionBalanceForPurpose(TreasuryPositionPurpose::PartnerCommissionPayable))->toBe(0)
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
            TreasuryPositionPurpose::RoyaltyPayable,
            TreasuryPositionPurpose::CommercialRevenue,
        ])->sum(fn (TreasuryPositionPurpose $purpose): int => treasuryPositionBalanceForPurpose($purpose)))
        ->toBe($commercialChargeMinor)
        ->and((int) TreasuryInventory::query()->sum('balance_minor'))->toBe($inventoryBefore);

    expect(CommercialBillableEvent::query()
        ->where('commercial_sale_id', $sale->getKey())
        ->where('status', CommercialBillableEventStatus::Posted->value)
        ->count())->toBe(3);
});

it('does not infer a provider-cost settlement from service-provider royalties', function () {
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
    $royaltyBefore = treasuryPositionBalanceForPurpose(TreasuryPositionPurpose::RoyaltyPayable);
    $evidence = new ProviderCostEvidenceData(
        commercialSaleReference: $sale->reference,
        provider: 'netbank',
        connectionReference: 'netbank-primary',
        evidenceType: 'account_debit',
        evidenceReference: 'netbank:fee-debit:not-authorized',
        cashMovementObserved: true,
        observedAmountMinor: 1_000,
        currency: 'PHP',
        observedAt: now()->toRfc3339String(),
        idempotencyKey: 'provider-cost:not-authorized',
    );

    expect(fn () => app(SettleCommercialProviderCost::class)->execute($evidence))
        ->toThrow(CommercialSaleConflict::class, 'exactly one explicit provider cost allocation')
        ->and(CommercialProviderCostSettlement::query()->count())->toBe(0)
        ->and(treasuryPositionBalanceForPurpose(TreasuryPositionPurpose::ProviderCostPayable))->toBe(0)
        ->and(treasuryPositionBalanceForPurpose(TreasuryPositionPurpose::RoyaltyPayable))->toBe($royaltyBefore)
        ->and((int) TreasuryInventory::query()->sum('balance_minor'))->toBe($inventoryBefore);
});

it('accrues an attributed partner commission to the partner principal', function () {
    $user = actingAsTestUser(0);
    $system = enableNetbankTreasuryForTests();
    activatePartnerCommissionEconomicsForTest();
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
    recognizeAccountingInventoryForTest(
        50_000,
        'partner-commission-payout',
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

    expect($allocation->recipient_reference)->toBe('partner:marketing-42')
        ->and(data_get($sale->snapshot, 'accounting_context.partner_reference'))
        ->toBe('partner:marketing-42')
        ->and((int) Wallet::query()
            ->findOrFail($partnerPosition->internal_ledger_id)
            ->balanceInt)->toBe(100)
        ->and(treasuryPositionBalanceForPurpose(
            TreasuryPositionPurpose::PartnerCommissionPayable,
            $system,
        ))->toBe(0);

    $request = app(RequestPartnerCommissionPayout::class)->execute(
        commercialSaleReference: $sale->reference,
        makerReference: 'operator:maker-15',
        idempotencyKey: 'partner-payout-request:marketing-42',
    );
    $requestReplay = app(RequestPartnerCommissionPayout::class)->execute(
        commercialSaleReference: $sale->reference,
        makerReference: 'operator:maker-15',
        idempotencyKey: 'partner-payout-request:marketing-42',
    );

    expect($request->status)->toBe('awaiting_approval')
        ->and($requestReplay->getKey())->toBe($request->getKey())
        ->and(fn () => app(ApprovePartnerCommissionPayout::class)->execute(
            $request,
            'operator:maker-15',
            'partner-payout-approval:invalid',
        ))->toThrow(CommercialSaleConflict::class, 'must be different');

    $approved = app(ApprovePartnerCommissionPayout::class)->execute(
        $request,
        'operator:checker-16',
        'partner-payout-approval:marketing-42',
    );
    $inventoryBefore = (int) TreasuryInventory::query()->sum('balance_minor');
    $evidence = new PartnerCommissionPayoutEvidenceData(
        evidenceReference: 'netbank:partner-payout:marketing-42',
        provider: 'netbank',
        connectionReference: 'netbank-primary',
        amountMinor: 100,
        currency: 'PHP',
        observedAt: now()->toRfc3339String(),
        idempotencyKey: 'partner-payout-settlement:marketing-42',
    );
    $settled = app(SettlePartnerCommissionPayout::class)->execute($approved, $evidence);
    $settledReplay = app(SettlePartnerCommissionPayout::class)->execute($approved, $evidence);

    expect($approved->status)->toBe('approved')
        ->and($settled->status)->toBe('settled')
        ->and($settledReplay->getKey())->toBe($settled->getKey())
        ->and($settled->maker_reference)->toBe('operator:maker-15')
        ->and($settled->checker_reference)->toBe('operator:checker-16')
        ->and((int) Wallet::query()
            ->findOrFail($partnerPosition->internal_ledger_id)
            ->balanceInt)->toBe(0)
        ->and((int) TreasuryInventory::query()->sum('balance_minor'))
        ->toBe($inventoryBefore - 100)
        ->and(PartnerCommissionPayout::query()->count())->toBe(1)
        ->and(ExecutionJournalEntry::query()
            ->where('correlation_id', 'commercial-sale:'.$sale->reference)
            ->where('event_type', 'like', 'commercial.partner_commission.%')
            ->pluck('event_type')
            ->all())->toBe([
                'commercial.partner_commission.requested',
                'commercial.partner_commission.approved',
                'commercial.partner_commission.settled',
            ]);
});

function activatePartnerCommissionEconomicsForTest(): void
{
    $offering = CommercialOfferingActivation::query()
        ->with('offering')
        ->where('profile', 'pay_code')
        ->whereNull('deactivated_at')
        ->sole()
        ->offering;
    $economics = app(CommercialComponentEconomicsResolverContract::class)
        ->resolve('pay_code')
        ->toArray();
    $economics['version'] = 2;

    foreach ($economics['components'] as &$component) {
        if ($component['component_reference'] !== 'cash.amount') {
            continue;
        }

        $component['allocation_schedule'] = [
            'reference' => 'component-allocation:pay_code:cash.amount',
            'version' => 2,
            'currency' => 'PHP',
            'rules' => [
                [
                    'reference' => '3neti-base-share',
                    'sequence' => 10,
                    'line_type' => 'allocation',
                    'category' => 'service_provider_payable',
                    'destination_kind' => 'external_recipient',
                    'recipient_reference' => 'counterparty:3neti',
                    'participant_role' => 'service_aggregator',
                    'fixed_amount_minor' => 1_400,
                    'basis_points' => null,
                    'agreement_reference' => 'agreement:commissioning:institution-3neti:v1',
                    'designation_reference' => 'designation:commissioning:3neti:v1',
                    'tax_policy_reference' => null,
                ],
                [
                    'reference' => 'marketing-partner-share',
                    'sequence' => 20,
                    'line_type' => 'allocation',
                    'category' => 'partner_commission',
                    'destination_kind' => 'external_recipient',
                    'recipient_reference' => 'partner:marketing-42',
                    'participant_role' => 'sales_partner',
                    'fixed_amount_minor' => 100,
                    'basis_points' => null,
                    'agreement_reference' => 'agreement:marketing-42:v1',
                    'designation_reference' => 'designation:marketing-42:v1',
                    'tax_policy_reference' => null,
                ],
            ],
        ];
    }
    unset($component);

    $economicsData = CommercialComponentEconomicsSetData::fromArray($economics);
    $manifest = app(CommercialComponentEconomicsManifestCompiler::class)->compile(
        'pay_code',
        $offering->offering(),
        (string) $offering->manifest_hash,
        $economicsData,
    );
    $persisted = app(PersistCommercialComponentEconomicsManifest::class)->execute(
        offering: $offering,
        manifest: $manifest,
        reference: 'component-economics:pay_code',
        version: 2,
        origin: CommercialOfferingOrigin::MakerCheckerRevision,
        authority: CommercialActivationAuthority::IndependentApproval,
    );
    app(ActivateCommercialComponentEconomics::class)->execute(
        economics: $persisted,
        authority: CommercialActivationAuthority::IndependentApproval,
        activationReference: 'component-economics-test:partner-commission:v2',
        authorizationReference: 'approval:test:partner-commission:v2',
    );
    $designation = new CommercialRecipientDesignationData(
        counterpartyReference: 'partner:marketing-42',
        commercialRole: 'sales_partner',
        componentScope: ['cash.amount'],
        agreementReference: 'agreement:marketing-42:v1',
        settlementDesignationReference: 'designation:marketing-42:v1',
        taxProfileReference: null,
        effectiveFrom: '2026-01-01T00:00:00+00:00',
    );
    app(ActivateCommercialRecipientDesignation::class)->execute(
        designation: $designation,
        origin: 'test_governed_revision',
        authorityReference: 'commercial-designation:test:marketing-42:v1',
        sourceReference: 'approval:test:partner-commission:v2',
        acceptedSnapshotHash: hash('sha256', json_encode($designation->toArray(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)),
    );
}

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
