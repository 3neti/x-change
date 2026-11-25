<?php

declare(strict_types=1);

use Bavix\Wallet\Models\Transaction;
use Bavix\Wallet\Models\Wallet;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use LBHurtado\EmiCore\Contracts\AccountTransferProvider;
use LBHurtado\EmiCore\Contracts\DirectQrFundingInstructionIssuer;
use LBHurtado\EmiCore\Data\Funding\FundingDestinationData;
use LBHurtado\EmiCore\Data\Funding\FundingInstructionRequestData;
use LBHurtado\EmiCore\Data\Funding\FundingInstructionsData;
use LBHurtado\EmiCore\Data\Funding\FundingQrCodeData;
use LBHurtado\EmiCore\Data\Funding\FundingVerificationData;
use LBHurtado\EmiCore\Data\Funding\ProviderFundingObservationData;
use LBHurtado\EmiCore\Data\Providers\AccountTransferEvidenceData;
use LBHurtado\EmiCore\Data\Providers\AccountTransferRequestData;
use LBHurtado\EmiCore\Data\Providers\AccountTransferResultData;
use LBHurtado\EmiCore\Enums\AccountTransferStatus;
use LBHurtado\EmiCore\Models\ProviderFundingObservation;
use LBHurtado\PaymentGateway\Exceptions\NetbankFundingNotVerified;
use LBHurtado\PaymentGateway\Funding\NetbankFundingApiClient;
use LBHurtado\PaymentGateway\Funding\NetbankFundingProviderAdapter;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\Wallet\Treasury\Enums\TreasuryPositionPurpose;
use LBHurtado\Wallet\Treasury\Models\TreasuryAllocation;
use LBHurtado\Wallet\Treasury\Models\TreasuryInventory;
use LBHurtado\Wallet\Treasury\Models\TreasuryPosition;
use LBHurtado\Wallet\Treasury\Models\TreasuryPositionOperation;
use LBHurtado\XChange\Actions\Banking\AssignNetbankAccount;
use LBHurtado\XChange\Actions\Funding\PrepareOnDemandPayCodeIssuance;
use LBHurtado\XChange\Actions\Funding\RequestOnDemandPayCodeIssuanceRetry;
use LBHurtado\XChange\Actions\Funding\SettleVerifiedFundingIntent;
use LBHurtado\XChange\Actions\Funding\VerifyFundingIntent;
use LBHurtado\XChange\Actions\PayCode\EstimatePayCodeCost;
use LBHurtado\XChange\Actions\Redemption\SubmitPayCodeClaim;
use LBHurtado\XChange\Contracts\BankingAccountSelectorContract;
use LBHurtado\XChange\Contracts\FundingDestinationResolverContract;
use LBHurtado\XChange\Contracts\ProviderRuntimeSettingsResolverContract;
use LBHurtado\XChange\Contracts\TreasuryAccountPortfolioProvisioningContract;
use LBHurtado\XChange\Contracts\VerifiedTreasuryFundingAllocationContract;
use LBHurtado\XChange\Data\Funding\FundingIntentVerificationData;
use LBHurtado\XChange\Data\FundingDecisionData;
use LBHurtado\XChange\Data\PricingEstimateData;
use LBHurtado\XChange\Enums\FundingIntentPurpose;
use LBHurtado\XChange\Enums\FundingIntentStatus;
use LBHurtado\XChange\Enums\FundingVerificationTrigger;
use LBHurtado\XChange\Enums\OnDemandIssuanceFundingBasis;
use LBHurtado\XChange\Enums\PayCodeIssuanceFundingOrderStatus;
use LBHurtado\XChange\Exceptions\FundingDestinationUnavailable;
use LBHurtado\XChange\Exceptions\FundingSettlementDenied;
use LBHurtado\XChange\Exceptions\InsufficientWalletBalance;
use LBHurtado\XChange\Exceptions\PayCodeIssuanceFailed;
use LBHurtado\XChange\Jobs\Funding\ResumeOnDemandPayCodeIssuanceJob;
use LBHurtado\XChange\Models\DisbursementReconciliation;
use LBHurtado\XChange\Models\FundingDestinationPreference;
use LBHurtado\XChange\Models\FundingIntent;
use LBHurtado\XChange\Models\FundingSettlement;
use LBHurtado\XChange\Models\PayCodeIssuanceFundingOrder;
use LBHurtado\XChange\Models\ReserveTransfer;
use LBHurtado\XChange\Services\Banking\AssignedBankIssuanceReadiness;
use LBHurtado\XChange\Services\Banking\AssignedBankTreasuryConnection;
use LBHurtado\XChange\Services\Banking\AssignedFundingTreasuryConnection;
use LBHurtado\XChange\Services\Banking\SelectedAccountClientFunds;
use LBHurtado\XChange\Services\Banking\VoucherBankSourceSnapshot;
use LBHurtado\XChange\Services\CheckProviderSourceAccountReadiness;
use LBHurtado\XChange\Services\Cockpit\OnDemandIssuanceFundingOrderPresenter;
use LBHurtado\XChange\Services\Commercial\ProvisionCommercialBaselines;
use LBHurtado\XChange\Services\Funding\FundingProviderAdapterRegistry;
use LBHurtado\XChange\Services\Funding\OnDemandIssuanceFundingRequirement;
use LBHurtado\XChange\Services\Funding\ReserveNetbankVca;
use LBHurtado\XChange\Services\ProviderAwareFundingPolicy;
use LBHurtado\XChange\Services\Reserve\AdvanceReserveBackedIssuance;
use LBHurtado\XChange\Services\Reserve\ReserveTransferJournal;
use LBHurtado\XChange\Services\Treasury\PreparePayCodeTreasuryIssuance;
use LBHurtado\XChange\Services\Treasury\TreasuryAccountBalanceReadModel;
use LBHurtado\XChange\Services\Treasury\TreasuryPreflightService;
use LBHurtado\XChange\Services\Treasury\TreasuryProviderConnectionCatalog;
use LBHurtado\XChange\Services\Treasury\TreasuryProvisioningService;
use LBHurtado\XChange\Tests\Fakes\FakeFundingProviderAdapter;
use LBHurtado\XJournal\Models\ExecutionJournalEntry;
use LBHurtado\XJournal\Services\ExecutionJournalRecorder;

function bankTreasuryConfiguration(string $inventory, string $resource): array
{
    return [
        'provider' => 'netbank', 'mode' => 'required', 'currency' => 'PHP',
        'inventory_reference' => $inventory, 'settlement_resource_reference' => $resource,
        'settlement_resource_type' => 'cash_at_bank', 'custody_mode' => 'provider_projection',
    ];
}

it('binds an assigned account to its own immutable Treasury connection', function () {
    $owner = actingAsTestUser();
    $assignment = app(AssignNetbankAccount::class)->handle($owner, [
        'account_number' => '991100001234', 'account_name' => 'Example', 'customer_id' => '1234', 'vca_alias' => '54321',
    ]);
    $catalog = new TreasuryProviderConnectionCatalog([
        'shared' => bankTreasuryConfiguration('inventory:shared', 'resource:shared'),
        'personal' => bankTreasuryConfiguration('inventory:personal', 'resource:personal'),
        'other' => bankTreasuryConfiguration('inventory:other', 'resource:other'),
    ]);
    $bindings = new AssignedBankTreasuryConnection($catalog);
    expect($bindings->bind($owner, $assignment->id, 'personal')->reference)->toBe('personal')
        ->and($bindings->resolve($owner, $assignment->provider_account_link_id)->inventoryReference)->toBe('inventory:personal')
        ->and($bindings->bind($owner, $assignment->id, 'personal')->reference)->toBe('personal');
    expect(fn () => $bindings->bind($owner, $assignment->id, 'other'))->toThrow(RuntimeException::class, 'cannot be changed');
    $other = actingAsTestUser();
    expect(fn () => $bindings->resolve($other, $assignment->provider_account_link_id))->toThrow(RuntimeException::class);
});

it('rejects a Treasury connection sharing the platform bank inventory or resource', function (string $inventory, string $resource) {
    $owner = actingAsTestUser();
    $assignment = app(AssignNetbankAccount::class)->handle($owner, [
        'account_number' => '991100001234', 'account_name' => 'Example', 'customer_id' => '1234', 'vca_alias' => '54321',
    ]);
    $bindings = new AssignedBankTreasuryConnection(new TreasuryProviderConnectionCatalog([
        'shared' => bankTreasuryConfiguration('inventory:shared', 'resource:shared'),
        'personal' => bankTreasuryConfiguration($inventory, $resource),
    ]));
    expect(fn () => $bindings->bind($owner, $assignment->id, 'personal'))->toThrow(RuntimeException::class, 'separate inventory and settlement resource')
        ->and($assignment->fresh()->treasury_connection_reference)->toBeNull();
})->with([
    ['inventory:shared', 'resource:personal'],
    ['inventory:personal', 'resource:shared'],
]);

it('rejects binding the same Treasury connection to different accounts', function () {
    $owner = actingAsTestUser();
    $first = app(AssignNetbankAccount::class)->handle($owner, [
        'account_number' => '991100001234', 'account_name' => 'One', 'customer_id' => '1234', 'vca_alias' => '54321',
    ]);
    $second = app(AssignNetbankAccount::class)->handle($owner, [
        'account_number' => '991100001235', 'account_name' => 'Two', 'customer_id' => '1235', 'vca_alias' => '54322',
    ]);
    $bindings = new AssignedBankTreasuryConnection(new TreasuryProviderConnectionCatalog([
        'personal' => bankTreasuryConfiguration('inventory:personal', 'resource:personal'),
    ]));
    $bindings->bind($owner, $first->id, 'personal');
    expect(fn () => $bindings->bind($owner, $second->id, 'personal'))->toThrow(RuntimeException::class, 'already assigned');
});

it('fails closed if a bound Treasury connection is later pointed at different resources', function () {
    $owner = actingAsTestUser();
    $assignment = app(AssignNetbankAccount::class)->handle($owner, [
        'account_number' => '991100001234', 'account_name' => 'Example', 'customer_id' => '1234', 'vca_alias' => '54321',
    ]);
    $original = new AssignedBankTreasuryConnection(new TreasuryProviderConnectionCatalog([
        'personal' => bankTreasuryConfiguration('inventory:personal', 'resource:personal'),
    ]));
    $original->bind($owner, $assignment->id, 'personal');
    $changed = new AssignedBankTreasuryConnection(new TreasuryProviderConnectionCatalog([
        'personal' => bankTreasuryConfiguration('inventory:different', 'resource:different'),
    ]));
    expect(fn () => $changed->resolve($owner, $assignment->provider_account_link_id))
        ->toThrow(RuntimeException::class, 'no longer matches');
});

it('rejects the configured platform inventory even if it is the only catalog connection', function () {
    config()->set('x-change.funding.providers.netbank.treasury', [
        'inventory_reference' => 'inventory:platform', 'settlement_resource_reference' => 'resource:platform',
    ]);
    $owner = actingAsTestUser();
    $assignment = app(AssignNetbankAccount::class)->handle($owner, [
        'account_number' => '991100001234', 'account_name' => 'Example', 'customer_id' => '1234', 'vca_alias' => '54321',
    ]);
    $bindings = new AssignedBankTreasuryConnection(new TreasuryProviderConnectionCatalog([
        'personal' => bankTreasuryConfiguration('inventory:platform', 'resource:platform'),
    ]));
    expect(fn () => $bindings->bind($owner, $assignment->id, 'personal'))
        ->toThrow(RuntimeException::class, 'separate inventory and settlement resource');
});

it('freezes the assigned Treasury connection with the Pay Code source', function () {
    $owner = actingAsTestUser();
    $voucher = issueVoucher();
    $assignment = app(AssignNetbankAccount::class)->handle($owner, [
        'account_number' => '991100001234', 'account_name' => 'Example', 'customer_id' => '1234', 'vca_alias' => '54321',
    ]);
    $assignment->providerAccountLink->forceFill([
        'status' => 'ready', 'ready_at' => now(), 'capabilities' => ['disbursement'],
    ])->save();
    $catalog = new TreasuryProviderConnectionCatalog([
        'personal' => bankTreasuryConfiguration('inventory:personal', 'resource:personal'),
    ]);
    app()->instance(TreasuryProviderConnectionCatalog::class, $catalog);
    app(AssignedBankTreasuryConnection::class)->bind($owner, $assignment->id, 'personal');
    $snapshot = app(VoucherBankSourceSnapshot::class)
        ->freeze($voucher, $assignment->provider_account_link_id);

    expect($snapshot)->toMatchArray([
        'treasury_connection_reference' => 'personal',
        'treasury_inventory_reference' => 'inventory:personal',
        'treasury_resource_reference' => 'resource:personal',
    ]);
});

it('refuses assigned-account Treasury reservation when the Pay Code did not freeze the connection', function () {
    enableNetbankTreasuryForTests();
    config()->set('x-change.commercial.enabled', true);
    $connections = config('x-change.treasury.connections');
    $connections['netbank-personal'] = bankTreasuryConfiguration('inventory:personal', 'resource:personal');
    config()->set('x-change.treasury.connections', $connections);
    app()->forgetInstance(TreasuryProviderConnectionCatalog::class);
    $owner = actingAsTestUser();
    $voucher = issueVoucher();
    $assignment = app(AssignNetbankAccount::class)->handle($owner, [
        'account_number' => '991100001234', 'account_name' => 'Example', 'customer_id' => '1234', 'vca_alias' => '54321',
    ]);
    app(AssignedBankTreasuryConnection::class)->bind($owner, $assignment->id, 'netbank-personal');
    $funding = FundingDecisionData::allowed(
        authority: 'local_ledger', availableMinor: 10_000, requiredMinor: 2_500, currency: 'PHP',
        meta: ['provider' => 'netbank', 'topology' => 'ledger_pooled', 'assigned_bank_account_link_id' => $assignment->provider_account_link_id],
    );

    expect(fn () => app(PreparePayCodeTreasuryIssuance::class)->handle(
        $owner, validPayCodePayload(25.0), ['voucher_id' => $voucher->id, 'amount' => 25.0, 'currency' => 'PHP'], $funding,
    ))->toThrow(PayCodeIssuanceFailed::class, 'not frozen');
});

it('rejects issuance source freezing when its Treasury binding is missing', function () {
    $owner = actingAsTestUser();
    $voucher = issueVoucher();
    $assignment = app(AssignNetbankAccount::class)->handle($owner, [
        'account_number' => '991100001234', 'account_name' => 'Example', 'customer_id' => '1234', 'vca_alias' => '54321',
    ]);
    $assignment->providerAccountLink->forceFill([
        'status' => 'ready', 'ready_at' => now(), 'capabilities' => ['disbursement'],
    ])->save();
    expect(fn () => app(VoucherBankSourceSnapshot::class)->freeze($voucher, $assignment->provider_account_link_id, requireTreasury: true))
        ->toThrow(RuntimeException::class, 'no Treasury connection');
    expect(app(VoucherBankSourceSnapshot::class)->forVoucher($voucher))->toBeNull();
});

it('matches a frozen funding destination to the assigned owner, bank account and Treasury resources', function () {
    $owner = actingAsTestUser();
    $assignment = app(AssignNetbankAccount::class)->handle($owner, [
        'account_number' => '991100001234', 'account_name' => 'Example', 'customer_id' => '1234', 'vca_alias' => '54321',
    ]);
    $catalog = new TreasuryProviderConnectionCatalog([
        'personal' => bankTreasuryConfiguration('inventory:personal', 'resource:personal'),
    ]);
    $binding = new AssignedBankTreasuryConnection($catalog);
    $binding->bind($owner, $assignment->id, 'personal');
    $resolver = new AssignedFundingTreasuryConnection($binding);
    $snapshot = [
        'provider' => 'netbank', 'mode' => 'dedicated', 'bankAccountNumber' => '991-100-001234', 'routingAlias' => '54321',
        'metadata' => [
            'assigned_bank_account_link_id' => $assignment->provider_account_link_id,
            'treasury_connection_reference' => 'personal', 'treasury_inventory_reference' => 'inventory:personal',
            'treasury_resource_reference' => 'resource:personal',
        ],
    ];
    expect($resolver->resolve($owner, 'netbank', 'PHP', $snapshot)->reference)->toBe('personal');
    $differentNumber = $snapshot;
    $differentNumber['bankAccountNumber'] = '991100001235';
    expect(fn () => $resolver->resolve($owner, 'netbank', 'PHP', $differentNumber))
        ->toThrow(FundingSettlementDenied::class, 'does not match');
    $differentAlias = $snapshot;
    $differentAlias['routingAlias'] = '54322';
    expect(fn () => $resolver->resolve($owner, 'netbank', 'PHP', $differentAlias))
        ->toThrow(FundingSettlementDenied::class, 'alias does not match');
    $missingAlias = $snapshot;
    unset($missingAlias['routingAlias']);
    expect(fn () => $resolver->resolve($owner, 'netbank', 'PHP', $missingAlias))
        ->toThrow(FundingSettlementDenied::class, 'alias does not match');
    $differentInventory = $snapshot;
    $differentInventory['metadata']['treasury_inventory_reference'] = 'inventory:shared';
    expect(fn () => $resolver->resolve($owner, 'netbank', 'PHP', $differentInventory))
        ->toThrow(FundingSettlementDenied::class, 'binding has changed');
    $other = actingAsTestUser();
    expect(fn () => $resolver->resolve($other, 'netbank', 'PHP', $snapshot))
        ->toThrow(FundingSettlementDenied::class);
});

it('allocates verified funding only to the assigned account Treasury connection', function () {
    enableNetbankTreasuryForTests();
    $connections = config('x-change.treasury.connections');
    $connections['netbank-personal'] = bankTreasuryConfiguration('inventory:personal', 'resource:personal');
    config()->set('x-change.treasury.connections', $connections);
    foreach ([
        TreasuryProviderConnectionCatalog::class,
        TreasuryPreflightService::class,
        TreasuryProvisioningService::class,
        TreasuryAccountPortfolioProvisioningContract::class,
        VerifiedTreasuryFundingAllocationContract::class,
    ] as $abstract) {
        app()->forgetInstance($abstract);
    }
    $owner = actingAsTestUser(0);
    $assignment = app(AssignNetbankAccount::class)->handle($owner, [
        'account_number' => '991100001234', 'account_name' => 'Example', 'customer_id' => '1234', 'vca_alias' => '54321',
    ]);
    app(AssignedBankTreasuryConnection::class)->bind($owner, $assignment->id, 'netbank-personal');
    FundingDestinationPreference::query()->updateOrCreate(
        ['owner_type' => $owner->getMorphClass(), 'owner_id' => $owner->getKey(), 'provider_code' => 'netbank'],
        ['mode' => 'dedicated', 'provider_account_link_id' => $assignment->provider_account_link_id],
    );
    $settings = Mockery::mock(ProviderRuntimeSettingsResolverContract::class);
    $settings->shouldReceive('provider')->andReturn('netbank');
    app()->instance(ProviderRuntimeSettingsResolverContract::class, $settings);
    expect(app(SelectedAccountClientFunds::class)->forOwner($owner))
        ->toMatchArray(['mode' => 'dedicated', 'amount_minor' => 0]);
    $pricing = new PricingEstimateData(currency: 'PHP', total: 5, pay_code_value: 16, account_debit: 5);
    $requirement = app(OnDemandIssuanceFundingRequirement::class)->forBasis(
        $owner, $pricing, OnDemandIssuanceFundingBasis::Shortfall,
    );
    expect($requirement->reservedClientFundsMinor)->toBe(0)->and($requirement->externalAmountMinor)->toBe(500);
    expect(fn () => app(PrepareOnDemandPayCodeIssuance::class)->handle(
        $owner, [], $pricing, 'personal-account-not-yet-supported', OnDemandIssuanceFundingBasis::Shortfall,
    ))->toThrow(RuntimeException::class, 'On-demand funding for the selected bank account is not yet available.');
    expect(PayCodeIssuanceFundingOrder::query()->where('issuer_id', (string) $owner->getKey())->count())->toBe(0);
    $snapshot = [
        'provider' => 'netbank', 'mode' => 'dedicated', 'bankAccountNumber' => '991100001234', 'routingAlias' => '54321',
        'fingerprint' => hash('sha256', 'frozen-personal-destination'),
        'metadata' => [
            'assigned_bank_account_link_id' => $assignment->provider_account_link_id,
            'on_demand_customer_id' => '1234',
            'treasury_connection_reference' => 'netbank-personal', 'treasury_inventory_reference' => 'inventory:personal',
            'treasury_resource_reference' => 'resource:personal',
        ],
    ];
    $wallet = $owner->wallet()->where('slug', 'platform')->firstOrFail();
    $allocation = app(VerifiedTreasuryFundingAllocationContract::class)->allocate(
        'wallet:'.$wallet->uuid, 'netbank', 2500, 'PHP', 'fixture:personal-funding',
        ['funding_destination_snapshot' => $snapshot],
    );
    $replay = app(VerifiedTreasuryFundingAllocationContract::class)->allocate(
        'wallet:'.$wallet->uuid, 'netbank', 2500, 'PHP', 'fixture:personal-funding',
        ['funding_destination_snapshot' => $snapshot],
    );
    $transaction = Transaction::query()->findOrFail($allocation->destinationTransactionId);
    $position = TreasuryPosition::query()
        ->where('position_reference', $allocation->destinationPositionReference)->sole();
    $balances = app(TreasuryAccountBalanceReadModel::class);
    expect(app(SelectedAccountClientFunds::class)->forOwner($owner))
        ->toMatchArray(['mode' => 'dedicated', 'amount_minor' => 2500]);
    expect($position->connection_reference)->toBe('netbank-personal')
        ->and($balances->connectionBalanceMinor($owner, 'netbank', 'netbank-personal', 'PHP'))->toBe(2500)
        ->and($balances->connectionBalanceMinor($owner, 'netbank', 'netbank-primary', 'PHP'))->toBeNull()
        ->and($replay->transferUuid)->toBe($allocation->transferUuid)
        ->and(json_encode($transaction->meta))->not->toContain('991100001234')
        ->and($allocation->amountMinor)->toBe(2500)
        ->and(TreasuryPosition::query()
            ->whereMorphedTo('principal', $owner)->where('connection_reference', 'netbank-primary')
            ->where('purpose', TreasuryPositionPurpose::ClientFunds)->count())->toBe(0);

    $assignment->providerAccountLink->forceFill([
        'status' => 'ready', 'ready_at' => now(), 'capabilities' => ['disbursement'],
    ])->save();
    $providerReadiness = Mockery::mock(CheckProviderSourceAccountReadiness::class);
    $providerReadiness->shouldReceive('handle')->once()->with(2500, 'netbank', '991100001234')
        ->andReturn(['ready' => true, 'checked' => true, 'currency' => 'PHP', 'available_balance_minor' => 2500]);
    $issuanceReadiness = new AssignedBankIssuanceReadiness(
        app(BankingAccountSelectorContract::class),
        app(AssignedBankTreasuryConnection::class), $balances, $providerReadiness,
    );
    $decision = $issuanceReadiness->assess($owner, $assignment->provider_account_link_id, 2500);
    expect($decision->allowed)->toBeTrue()
        ->and($decision->meta['assigned_bank_account_link_id'])->toBe($assignment->provider_account_link_id)
        ->and($decision->meta['treasury_connection_reference'])->toBe('netbank-personal');
    $order = PayCodeIssuanceFundingOrder::query()->create([
        'account_reference' => 'wallet:'.$wallet->uuid,
        'issuer_type' => $owner::class, 'issuer_id' => (string) $owner->getKey(),
        'provider' => 'netbank', 'connection_reference' => 'netbank-personal',
        'funding_basis' => OnDemandIssuanceFundingBasis::FullAmount,
        'instructions_ciphertext' => [], 'instructions_fingerprint' => str_repeat('a', 64),
        'pricing_snapshot_ciphertext' => [], 'pricing_fingerprint' => str_repeat('b', 64),
        'required_amount_minor' => 2500, 'reserved_client_funds_minor' => 0,
        'on_demand_amount_minor' => 2500, 'reconciliation_adjustment_minor' => 0,
        'expected_payment_minor' => 2500, 'currency' => 'PHP',
        'status' => PayCodeIssuanceFundingOrderStatus::Issuing,
        'funded_at' => now(), 'version' => 1,
        'metadata' => ['funding_source' => [
            'mode' => 'dedicated', 'account_link_id' => $assignment->provider_account_link_id,
            'connection_reference' => 'netbank-personal', 'destination_fingerprint' => $snapshot['fingerprint'],
        ]],
        'idempotency_key_hash' => hash('sha256', 'frozen-source-order'),
        'idempotency_fingerprint' => hash('sha256', 'frozen-source-order-input'),
        'expires_at' => now()->addHour(),
    ]);
    $intent = FundingIntent::query()->create([
        'account_reference' => $order->account_reference, 'provider_code' => 'netbank',
        'purpose' => FundingIntentPurpose::OnDemandIssuance,
        'expected_amount_minor' => 2500, 'currency' => 'PHP',
        'status' => FundingIntentStatus::Settled,
        'version' => 1, 'idempotency_key_hash' => hash('sha256', 'frozen-source-intent'),
        'idempotency_fingerprint' => hash('sha256', 'frozen-source-intent-input'),
        'created_by_type' => $owner::class, 'created_by_id' => (string) $owner->getKey(),
        'destination_snapshot_ciphertext' => $snapshot,
        'metadata' => ['funding_order_reference' => $order->reference],
    ]);
    $order->forceFill(['funding_intent_id' => $intent->getKey()])->saveQuietly();
    $preference = FundingDestinationPreference::query()->whereMorphedTo('owner', $owner)->where('provider_code', 'netbank')->firstOrFail();
    $preference->forceFill(['mode' => 'shared', 'provider_account_link_id' => null])->save();
    $readiness = Mockery::mock(CheckProviderSourceAccountReadiness::class);
    $readiness->shouldReceive('handle')->once()->with(2500, 'netbank', '991100001234')
        ->andReturn(['ready' => true, 'checked' => true, 'currency' => 'PHP', 'available_balance_minor' => 2500]);
    app()->instance(CheckProviderSourceAccountReadiness::class, $readiness);
    config()->set('x-change.commercial.enabled', true);
    $decision = app(ProviderAwareFundingPolicy::class)->assertCanIssue(
        $owner, $wallet, 2500, ['funded_order' => $order, 'currency' => 'PHP'],
    );
    expect($decision->meta['assigned_bank_account_link_id'])->toBe($assignment->provider_account_link_id)
        ->and($decision->meta['treasury_connection_reference'])->toBe('netbank-personal');
    $order->forceFill(['connection_reference' => 'netbank-primary'])->saveQuietly();
    expect(fn () => app(ProviderAwareFundingPolicy::class)->assertCanIssue(
        $owner, $wallet, 2500, ['funded_order' => $order, 'currency' => 'PHP'],
    ))->toThrow(FundingSettlementDenied::class, 'does not match');
    $order->forceFill(['connection_reference' => 'netbank-personal'])->saveQuietly();
    $details = $assignment->details_ciphertext;
    $changedDetails = $details;
    $changedDetails['customer_id'] = '5678';
    $assignment->forceFill(['details_ciphertext' => $changedDetails])->save();
    expect(fn () => app(ProviderAwareFundingPolicy::class)->assertCanIssue(
        $owner, $wallet, 2500, ['funded_order' => $order, 'currency' => 'PHP'],
    ))->toThrow(FundingSettlementDenied::class, 'customer ID has changed');
    $assignment->forceFill(['details_ciphertext' => $details])->save();
    $intent->forceFill(['status' => FundingIntentStatus::Reversed])->saveQuietly();
    expect(fn () => app(ProviderAwareFundingPolicy::class)->assertCanIssue(
        $owner, $wallet, 2500, ['funded_order' => $order, 'currency' => 'PHP'],
    ))->toThrow(FundingSettlementDenied::class, 'not bound');
    $intent->forceFill(['status' => FundingIntentStatus::Settled, 'destination_snapshot_ciphertext' => ['mode' => 'shared']])->saveQuietly();
    $order->forceFill(['connection_reference' => 'netbank-primary', 'metadata' => ['funding_source' => ['mode' => 'shared']]])->saveQuietly();
    $preference->forceFill(['mode' => 'dedicated', 'provider_account_link_id' => $assignment->provider_account_link_id])->save();
    $settings->shouldReceive('topology')->andReturn('ledger_pooled');
    expect(fn () => app(ProviderAwareFundingPolicy::class)->assertCanIssue(
        $owner, $wallet, 2500, ['funded_order' => $order, 'currency' => 'PHP'],
    ))->toThrow(InsufficientWalletBalance::class, 'Issuer wallet cannot afford');
    $order->forceFill(['connection_reference' => 'different-shared-connection'])->saveQuietly();
    expect(fn () => app(ProviderAwareFundingPolicy::class)->assertCanIssue(
        $owner, $wallet, 2500, ['funded_order' => $order, 'currency' => 'PHP'],
    ))->toThrow(InsufficientWalletBalance::class, 'Treasury connection changed');
    $otherOwner = actingAsTestUser(0);
    expect(fn () => app(ProviderAwareFundingPolicy::class)->assertCanIssue(
        $otherOwner, $otherOwner->wallet()->where('slug', 'platform')->firstOrFail(), 2500,
        ['funded_order' => $order, 'currency' => 'PHP'],
    ))->toThrow(InsufficientWalletBalance::class, 'order is unavailable');
    $preference->forceFill(['mode' => 'dedicated', 'provider_account_link_id' => $assignment->provider_account_link_id])->save();
    expect(fn () => $issuanceReadiness->assess($owner, $assignment->provider_account_link_id, 2501))
        ->toThrow(InsufficientWalletBalance::class, 'client funds');
    $preference = FundingDestinationPreference::query()->whereMorphedTo('owner', $owner)->where('provider_code', 'netbank')->firstOrFail();
    $preference->forceFill(['mode' => 'shared', 'provider_account_link_id' => null])->save();
    expect(app(SelectedAccountClientFunds::class)->forOwner($owner))
        ->toMatchArray(['mode' => 'shared', 'amount_minor' => 0]);
    $preference->forceFill(['mode' => 'dedicated', 'provider_account_link_id' => $assignment->provider_account_link_id])->save();
    $assignment->forceFill(['treasury_connection_reference' => null])->save();
    expect(app(SelectedAccountClientFunds::class)->forOwner($owner))
        ->toMatchArray(['mode' => 'unavailable', 'amount_minor' => null]);
});

function verifiedPersonalPaidOrderFixture(): array
{
    Queue::fake();
    Http::preventStrayRequests();
    enableNetbankTreasuryForTests();
    config()->set('x-change.commercial.enabled', true);
    config()->set('x-change.commercial.legal_trace.legal_entity_reference', 'legal-entity:x-change:test');
    config()->set('x-change.commercial.legal_trace.profile_version', 'test-v1');
    app(ProvisionCommercialBaselines::class)->provision('commissioning-manifest:personal-on-demand');
    config()->set('x-change.funding.providers.netbank.enabled', true);
    config()->set('x-change.funding.dedicated_netbank_vca_enabled', true);
    config()->set('x-change.funding.dedicated_netbank_direct_qr_enabled', true);
    config()->set('x-change.issuance_funding.on_demand.assigned_netbank_enabled', true);
    $connections = config('x-change.treasury.connections');
    $connections['netbank-personal'] = bankTreasuryConfiguration('inventory:personal', 'resource:personal');
    config()->set('x-change.treasury.connections', $connections);
    foreach ([TreasuryProviderConnectionCatalog::class, TreasuryPreflightService::class,
        TreasuryProvisioningService::class, TreasuryAccountPortfolioProvisioningContract::class] as $abstract) {
        app()->forgetInstance($abstract);
    }
    $owner = actingAsTestUser(0);
    $assignment = app(AssignNetbankAccount::class)->handle($owner, [
        'account_number' => '991100001234', 'account_name' => 'Example', 'customer_id' => '1234', 'vca_alias' => '54321',
    ]);
    app(AssignedBankTreasuryConnection::class)->bind($owner, $assignment->id, 'netbank-personal');
    $assignment->providerAccountLink->forceFill([
        'status' => 'ready', 'ready_at' => now(), 'capabilities' => ['funding', 'disbursement'],
        'metadata' => ['funding_assignment' => ['scope' => 'fresh_intents_only', 'fresh_since' => now()->subMinute()->toIso8601String()]],
    ])->save();
    FundingDestinationPreference::query()->create([
        'owner_type' => $owner::class, 'owner_id' => $owner->getKey(), 'provider_code' => 'netbank',
        'mode' => 'dedicated', 'provider_account_link_id' => $assignment->provider_account_link_id,
    ]);
    $settings = Mockery::mock(ProviderRuntimeSettingsResolverContract::class);
    $settings->shouldReceive('provider')->andReturn('netbank');
    $settings->shouldReceive('topology')->andReturn('ledger_pooled');
    $settings->shouldReceive('isEnabled')->andReturn(true);
    app()->instance(ProviderRuntimeSettingsResolverContract::class, $settings);
    $wallet = $owner->wallet()->where('slug', 'platform')->firstOrFail();
    $destination = new FundingDestinationData(
        provider: 'netbank', mode: 'dedicated', destinationType: 'bank_account', accountReference: 'wallet:'.$wallet->uuid,
        displayReference: 'Personal', fingerprint: hash('sha256', 'personal-destination'), verificationStatus: 'routing_configured',
        bankAccountNumber: '991100001234', bankAccountName: 'Example', routingAlias: '54321', metadata: [
            'assigned_bank_account_link_id' => $assignment->provider_account_link_id,
            'netbank_instruction_mode' => 'direct_qr', 'treasury_connection_reference' => 'netbank-personal',
            'treasury_inventory_reference' => 'inventory:personal', 'treasury_resource_reference' => 'resource:personal',
        ],
    );
    $resolver = Mockery::mock(FundingDestinationResolverContract::class);
    $resolver->shouldReceive('resolve')->twice()->andReturn($destination);
    app()->instance(FundingDestinationResolverContract::class, $resolver);
    $adapter = new class extends FakeFundingProviderAdapter implements DirectQrFundingInstructionIssuer
    {
        public function createDirectQrFundingInstructions(FundingInstructionRequestData $request): FundingInstructionsData
        {
            $address = $request->destination->routingAlias.$request->metadata['reserved_vca_reference'];

            return new FundingInstructionsData(
                provider: 'netbank', providerReference: $address, amountMinor: $request->amountMinor,
                currency: $request->currency, fundingAddress: $address,
                qrCode: new FundingQrCodeData(
                    mimeType: 'image/png', base64Payload: 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQIHWP4z8DwHwAFgAI/ScLttAAAAABJRU5ErkJggg==', qrMode: 'dynamic',
                    transactionType: 'p2m', embeddedAmount: true, providerGenerated: true,
                ),
            );
        }
    };
    app()->instance(FundingProviderAdapterRegistry::class,
        new FundingProviderAdapterRegistry([$adapter]));
    app()->instance(ReserveNetbankVca::class,
        new class extends ReserveNetbankVca
        {
            protected function nextReference(): string
            {
                return '7654321';
            }
        });
    $instructions = validPayCodePayload(25);
    data_set($instructions, 'inputs.fields', []);
    $pricing = app(EstimatePayCodeCost::class)->handle($instructions);
    $assignment->providerAccountLink->forceFill(['capabilities' => ['funding']])->save();
    expect(fn () => app(PrepareOnDemandPayCodeIssuance::class)->handle(
        $owner, $instructions, $pricing, 'unready-personal-on-demand', OnDemandIssuanceFundingBasis::FullAmount,
    ))->toThrow(FundingDestinationUnavailable::class);
    expect(PayCodeIssuanceFundingOrder::query()->count())->toBe(0);
    $assignment->providerAccountLink->forceFill(['capabilities' => ['funding', 'disbursement']])->save();
    $order = app(PrepareOnDemandPayCodeIssuance::class)->handle(
        $owner, $instructions, $pricing, 'personal-on-demand', OnDemandIssuanceFundingBasis::FullAmount,
    );
    expect($order->connection_reference)->toBe('netbank-personal')
        ->and(data_get($order->metadata, 'funding_source.account_link_id'))->toBe($assignment->provider_account_link_id)
        ->and($order->fundingIntent->funding_address_ciphertext)->toBe('543217654321')
        ->and(data_get($order->fundingIntent->instructions_ciphertext, 'qr_code.embedded_amount'))->toBeTrue()
        ->and(data_get($order->fundingIntent->destination_snapshot_ciphertext, 'metadata.treasury_connection_reference'))->toBe('netbank-personal');
    $presented = app(OnDemandIssuanceFundingOrderPresenter::class)->present($order);
    expect(data_get($presented, 'funding_selector.qr_ph.fixed_amount'))->toBeTrue()
        ->and(data_get($presented, 'funding_selector.qr_ph.amount_minor'))->toBe($order->expected_payment_minor)
        ->and(data_get($presented, 'funding_selector.bank_transfer.instructions.funding_address'))->toBe('543217654321');
    FundingDestinationPreference::query()->whereMorphedTo('owner', $owner)->update(['mode' => 'shared', 'provider_account_link_id' => null]);
    $replay = app(PrepareOnDemandPayCodeIssuance::class)->handle(
        $owner, $instructions, $pricing, 'personal-on-demand', OnDemandIssuanceFundingBasis::FullAmount,
    );
    expect($replay->is($order))->toBeTrue()
        ->and($replay->connection_reference)->toBe('netbank-personal');
    $intent = $order->fundingIntent;
    $adapter->fundingObservation = new ProviderFundingObservationData(
        provider: 'netbank', providerTransactionId: 'personal-on-demand-settlement',
        grossAmountMinor: $intent->expected_amount_minor, feeAmountMinor: 0,
        netAmountMinor: $intent->expected_amount_minor, currency: 'PHP', providerStatus: 'settled',
        verificationSource: 'netbank-fixture', payloadHash: hash('sha256', 'personal-on-demand-settlement'),
        fundingAddress: 'sha256:'.hash('sha256', $intent->funding_address_ciphertext),
        providerAccountReference: 'sha256:'.hash('sha256', $intent->funding_address_ciphertext),
        occurredAt: now()->toDateTimeImmutable(), settledAt: now()->toDateTimeImmutable(),
        metadata: ['destination_verified' => true, 'exact_vca_verified' => true],
    );
    $verified = app(VerifyFundingIntent::class)->handle(
        $intent, new FundingIntentVerificationData(
            trigger: FundingVerificationTrigger::Operator, actorId: (string) $owner->getKey(),
        ),
    );
    expect($verified->status)->toBe(FundingIntentStatus::Verified)
        ->and($adapter->lastVerification->allowDestinationHistoryFallback)->toBeFalse();
    $observation = ProviderFundingObservation::query()->findOrFail($verified->matched_observation_id);
    $observationMetadata = $observation->metadata;
    $observation->forceFill(['metadata' => ['destination_verified' => true]])->saveQuietly();
    expect(fn () => app(SettleVerifiedFundingIntent::class)->handle($verified))
        ->toThrow(FundingSettlementDenied::class, 'exact dedicated funding VCA');
    $observation->forceFill(['metadata' => $observationMetadata])->saveQuietly();
    $settlement = app(SettleVerifiedFundingIntent::class)->handle($verified);
    expect($order->refresh()->status)->toBe(PayCodeIssuanceFundingOrderStatus::Funded)
        ->and($order->fundingIntent->refresh()->status)->toBe(FundingIntentStatus::Settled)
        ->and($order->connection_reference)->toBe('netbank-personal');
    $repeated = app(SettleVerifiedFundingIntent::class)->handle($verified->refresh());
    expect($repeated->is($settlement))->toBeTrue();
    $readiness = Mockery::mock(CheckProviderSourceAccountReadiness::class);
    $readiness->shouldReceive('handle')->withAnyArgs()->andReturn([
        'ready' => true, 'checked' => true, 'currency' => 'PHP', 'available_balance_minor' => 100000,
    ]);
    app()->instance(CheckProviderSourceAccountReadiness::class, $readiness);

    return [$owner, $assignment, $order, $intent];
}

it('keeps a paid order in review after a confirmed reserve rejection', function () {
    [$owner, $assignment, $order, $intent] = verifiedPersonalPaidOrderFixture();
    config()->set('x-change.reserve_transfer.enabled', true);
    config()->set('x-change.reserve_transfer.destination_bank_code', 'CUOBPHM2XXX');
    config()->set('omnipay.gateways.netbank.options.sourceAccountNumber', '222-222-22222-2');
    config()->set('payment-gateway.netbank.funding.corporate_account_number', '222-222-22222-2');
    config()->set('omnipay.gateways.netbank.options.senderCustomerId', '9999');
    config()->set('payment-gateway.netbank.funding.corporate_account_name', 'Platform Reserve');
    $job = new ResumeOnDemandPayCodeIssuanceJob($order->getKey());
    app()->call([$job, 'handle']);
    app()->call([$job, 'handle']);
    $transfer = ReserveTransfer::query()->where('funding_order_id', $order->getKey())->sole();
    expect($order->refresh()->status)->toBe(PayCodeIssuanceFundingOrderStatus::Issuing)
        ->and($transfer->status)->toBe('held')
        ->and($transfer->bank_account_link_id)->toBe($assignment->provider_account_link_id)
        ->and($transfer->source_treasury_connection)->toBe('netbank-personal')
        ->and($order->fundingIntent->refresh()->status)->toBe(FundingIntentStatus::Settled)
        ->and(FundingSettlement::query()->where('funding_intent_id', $intent->getKey())->count())->toBe(1);
    $hold = TreasuryPositionOperation::query()->with('destinationPosition')->where('operation_reference', $transfer->hold_operation_reference)->sole();
    $heldWallet = Wallet::query()->findOrFail($hold->destinationPosition->internal_ledger_id);
    $heldBefore = (int) $heldWallet->balance;
    $request = AccountTransferRequestData::from($transfer->request_ciphertext);
    $provider = Mockery::mock(AccountTransferProvider::class);
    $provider->shouldReceive('providerCode')->andReturn('netbank');
    $provider->shouldReceive('submit')->once()->andReturn(new AccountTransferResultData(AccountTransferStatus::Pending, 'bank-rejected'));
    $provider->shouldReceive('checkStatus')->once()->andReturn(new AccountTransferResultData(
        AccountTransferStatus::Rejected, 'bank-rejected',
        new AccountTransferEvidenceData($request->reference, $request->sourceAccountNumber,
            $request->destinationAccountNumber, $request->sourceCustomerId, $request->amountMinor, $request->currency, 'DIRECT', 0),
    ));
    $advance = app(AdvanceReserveBackedIssuance::class);
    expect($advance->advance($transfer, $owner, $provider)->status)->toBe('pending');
    expect($advance->advance($transfer, $owner, $provider)->status)->toBe('rejected')
        ->and($order->refresh()->status)->toBe(PayCodeIssuanceFundingOrderStatus::IssuanceAttention)
        ->and((int) $heldWallet->fresh()->balance)->toBe($heldBefore)
        ->and($order->voucher_id)->toBeNull()
        ->and($order->fundingIntent->refresh()->status)->toBe(FundingIntentStatus::Settled)
        ->and(FundingSettlement::query()->where('funding_intent_id', $intent->getKey())->count())->toBe(1)
        ->and(app(RequestOnDemandPayCodeIssuanceRetry::class)->availability($order)['eligible'])->toBeFalse();
    expect($advance->advance($transfer, $owner, $provider)->status)->toBe('rejected')
        ->and(ExecutionJournalEntry::query()->where('event_type', 'reserve_transfer.funded_order_rejected')->where('subject_id', (string) $transfer->getKey())->count())->toBe(1);
});

it('never resubmits an uncertain paid-order reserve transfer or requests another payment', function () {
    [$owner, $assignment, $order, $intent] = verifiedPersonalPaidOrderFixture();
    config()->set('x-change.reserve_transfer.enabled', true);
    config()->set('x-change.reserve_transfer.destination_bank_code', 'CUOBPHM2XXX');
    config()->set('omnipay.gateways.netbank.options.sourceAccountNumber', '222-222-22222-2');
    config()->set('payment-gateway.netbank.funding.corporate_account_number', '222-222-22222-2');
    config()->set('omnipay.gateways.netbank.options.senderCustomerId', '9999');
    config()->set('payment-gateway.netbank.funding.corporate_account_name', 'Platform Reserve');
    $job = new ResumeOnDemandPayCodeIssuanceJob($order->getKey());
    app()->call([$job, 'handle']);
    app()->call([$job, 'handle']);
    $transfer = ReserveTransfer::query()->where('funding_order_id', $order->getKey())->sole();
    expect($order->refresh()->status)->toBe(PayCodeIssuanceFundingOrderStatus::Issuing)
        ->and($transfer->status)->toBe('held')
        ->and($transfer->bank_account_link_id)->toBe($assignment->provider_account_link_id)
        ->and($transfer->source_treasury_connection)->toBe('netbank-personal')
        ->and($order->fundingIntent->refresh()->status)->toBe(FundingIntentStatus::Settled)
        ->and(FundingSettlement::query()->where('funding_intent_id', $intent->getKey())->count())->toBe(1);
    $provider = Mockery::mock(AccountTransferProvider::class);
    $provider->shouldReceive('providerCode')->andReturn('netbank');
    $provider->shouldReceive('submit')->once()->andReturn(new AccountTransferResultData(AccountTransferStatus::Uncertain));
    $provider->shouldNotReceive('checkStatus');
    $advance = app(AdvanceReserveBackedIssuance::class);
    expect($advance->advance($transfer, $owner, $provider)->status)->toBe('uncertain')
        ->and($advance->advance($transfer, $owner, $provider)->status)->toBe('uncertain')
        ->and($order->refresh()->status)->toBe(PayCodeIssuanceFundingOrderStatus::Issuing)
        ->and($order->voucher_id)->toBeNull()
        ->and($order->fundingIntent->refresh()->status)->toBe(FundingIntentStatus::Settled)
        ->and(FundingSettlement::query()->where('funding_intent_id', $intent->getKey())->count())->toBe(1);
    $presented = app(OnDemandIssuanceFundingOrderPresenter::class)->present($order->fresh());
    expect(data_get($presented, 'lifecycle.current'))->toBe('attention')
        ->and(data_get($presented, 'lifecycle.message'))->toContain('do not pay again')
        ->and(data_get($presented, 'order.can_cancel'))->toBeFalse();
});

it('keeps the paid funding hold when reserve pricing no longer matches the order', function () {
    [$owner, $assignment, $order, $intent] = verifiedPersonalPaidOrderFixture();
    config()->set('x-change.reserve_transfer.enabled', true);
    config()->set('x-change.reserve_transfer.destination_bank_code', 'CUOBPHM2XXX');
    config()->set('omnipay.gateways.netbank.options.sourceAccountNumber', '222-222-22222-2');
    config()->set('payment-gateway.netbank.funding.corporate_account_number', '222-222-22222-2');
    config()->set('omnipay.gateways.netbank.options.senderCustomerId', '9999');
    config()->set('payment-gateway.netbank.funding.corporate_account_name', 'Platform Reserve');
    $changedEstimate = Mockery::mock(EstimatePayCodeCost::class);
    $changedEstimate->shouldReceive('handle')->andReturn(new PricingEstimateData(currency: 'PHP', total: 0, customer_charge_minor: 0));
    app()->instance(EstimatePayCodeCost::class, $changedEstimate);
    $job = new ResumeOnDemandPayCodeIssuanceJob($order->getKey());
    expect(fn () => app()->call([$job, 'handle']))->toThrow(RuntimeException::class);
    expect($order->refresh()->status)->toBe(PayCodeIssuanceFundingOrderStatus::Funded)
        ->and($order->voucher_id)->toBeNull()
        ->and(TreasuryAllocation::query()->where('allocation_reference', $order->treasury_hold_reference)->sole()->balance_minor)->toBe($order->required_amount_minor)
        ->and(ReserveTransfer::query()->where('funding_order_id', $order->getKey())->exists())->toBeFalse()
        ->and(FundingSettlement::query()->where('funding_intent_id', $intent->getKey())->count())->toBe(1);
});

it('links a paid order to one reserve transfer using its frozen account', function () {
    [$owner, $assignment, $order, $intent] = verifiedPersonalPaidOrderFixture();
    config()->set('x-change.reserve_transfer.enabled', true);
    config()->set('x-change.reserve_transfer.destination_bank_code', 'CUOBPHM2XXX');
    config()->set('omnipay.gateways.netbank.options.sourceAccountNumber', '222-222-22222-2');
    config()->set('payment-gateway.netbank.funding.corporate_account_number', '222-222-22222-2');
    config()->set('omnipay.gateways.netbank.options.senderCustomerId', '9999');
    config()->set('payment-gateway.netbank.funding.corporate_account_name', 'Platform Reserve');
    $job = new ResumeOnDemandPayCodeIssuanceJob($order->getKey());
    app()->call([$job, 'handle']);
    app()->call([$job, 'handle']);
    $transfer = ReserveTransfer::query()->where('funding_order_id', $order->getKey())->sole();
    expect($order->refresh()->status)->toBe(PayCodeIssuanceFundingOrderStatus::Issuing)
        ->and($transfer->status)->toBe('held')
        ->and($transfer->bank_account_link_id)->toBe($assignment->provider_account_link_id)
        ->and($transfer->source_treasury_connection)->toBe('netbank-personal')
        ->and($order->fundingIntent->refresh()->status)->toBe(FundingIntentStatus::Settled)
        ->and(FundingSettlement::query()->where('funding_intent_id', $intent->getKey())->count())->toBe(1);
    $duplicate = $transfer->replicate();
    $duplicate->forceFill([
        'reference' => 'rsv-'.str_repeat('f', 32), 'idempotency_hash' => str_repeat('f', 64),
        'hold_operation_reference' => null, 'fee_hold_operation_reference' => null,
    ]);
    expect(fn () => DB::transaction(fn () => $duplicate->save()))->toThrow(QueryException::class);
    $request = AccountTransferRequestData::from($transfer->request_ciphertext);
    $provider = Mockery::mock(AccountTransferProvider::class);
    $provider->shouldReceive('providerCode')->andReturn('netbank');
    $provider->shouldReceive('submit')->once()->andReturn(new AccountTransferResultData(AccountTransferStatus::Pending, 'bank-paid-order'));
    $provider->shouldReceive('checkStatus')->once()->andReturn(new AccountTransferResultData(
        AccountTransferStatus::Settled, 'bank-paid-order',
        new AccountTransferEvidenceData($request->reference, $request->sourceAccountNumber,
            $request->destinationAccountNumber, $request->sourceCustomerId, $request->amountMinor, $request->currency, 'DIRECT', 0),
    ));
    $advance = app(AdvanceReserveBackedIssuance::class);
    expect($advance->advance($transfer, $owner, $provider)->status)->toBe('pending');
    expect($order->refresh()->status)->toBe(PayCodeIssuanceFundingOrderStatus::Issuing);
    $issued = $advance->advance($transfer, $owner, $provider);
    expect($issued->status)->toBe('issued')->and($order->refresh()->status)->toBe(PayCodeIssuanceFundingOrderStatus::Issued)
        ->and($order->voucher_id)->toBe($issued->voucher_id)
        ->and(FundingSettlement::query()->where('funding_intent_id', $intent->getKey())->count())->toBe(1);
    app()->call([$job, 'handle']);
    expect($advance->advance($issued, $owner, $provider)->voucher_id)->toBe($issued->voucher_id)
        ->and(ReserveTransfer::query()->where('funding_order_id', $order->getKey())->count())->toBe(1)
        ->and($order->events()->where('event_type', 'pay_code_issued')->count())->toBe(1)
        ->and(ExecutionJournalEntry::query()->where('event_type', 'reserve_transfer.funding_order_linked')->where('subject_id', (string) $transfer->getKey())->count())->toBe(1)
        ->and(ExecutionJournalEntry::query()->where('event_type', 'reserve_transfer.pay_code_issued')->where('subject_id', (string) $transfer->getKey())->count())->toBe(1)
        ->and(FundingSettlement::query()->where('funding_intent_id', $intent->getKey())->count())->toBe(1);
});

it('preserves the paid order and its funding hold when reserve-backed recovery is not yet connected', function () {
    [$owner, $assignment, $order, $intent] = verifiedPersonalPaidOrderFixture();
    $job = new ResumeOnDemandPayCodeIssuanceJob($order->getKey());
    expect(fn () => app()->call([$job, 'handle']))
        ->toThrow(RuntimeException::class, 'verified payment remains held');
    expect($order->refresh()->status)->toBe(PayCodeIssuanceFundingOrderStatus::Funded)
        ->and($order->voucher_id)->toBeNull()
        ->and($order->treasury_hold_reference)->not->toBeNull()
        ->and(TreasuryAllocation::query()->where('allocation_reference', $order->treasury_hold_reference)->sole()->balance_minor)->toBe($order->required_amount_minor)
        ->and($order->fundingIntent->refresh()->status)->toBe(FundingIntentStatus::Settled)
        ->and(FundingSettlement::query()->where('funding_intent_id', $intent->getKey())->count())->toBe(1);
});

it('issues a reserve-backed Pay Code from verified personal on-demand funding after the default changes', function () {
    [$owner, $assignment, $order, $intent] = verifiedPersonalPaidOrderFixture();
    config()->set('x-change.reserve_transfer.enabled', true);
    config()->set('x-change.reserve_transfer.destination_bank_code', 'CUOBPHM2XXX');
    config()->set('omnipay.gateways.netbank.options.sourceAccountNumber', '222-222-22222-2');
    config()->set('payment-gateway.netbank.funding.corporate_account_number', '222-222-22222-2');
    config()->set('omnipay.gateways.netbank.options.senderCustomerId', '9999');
    config()->set('payment-gateway.netbank.funding.corporate_account_name', 'Platform Reserve');
    $job = new ResumeOnDemandPayCodeIssuanceJob($order->getKey());
    app()->call([$job, 'handle']);
    app()->call([$job, 'handle']);
    $transfer = ReserveTransfer::query()->where('funding_order_id', $order->getKey())->sole();
    $request = AccountTransferRequestData::from($transfer->request_ciphertext);
    $bank = Mockery::mock(AccountTransferProvider::class);
    $bank->shouldReceive('providerCode')->andReturn('netbank');
    $bank->shouldReceive('submit')->once()->andReturn(new AccountTransferResultData(AccountTransferStatus::Pending, 'bank-paid-order'));
    $bank->shouldReceive('checkStatus')->once()->andReturn(new AccountTransferResultData(
        AccountTransferStatus::Settled, 'bank-paid-order',
        new AccountTransferEvidenceData($request->reference, $request->sourceAccountNumber,
            $request->destinationAccountNumber, $request->sourceCustomerId, $request->amountMinor, $request->currency, 'DIRECT', 0),
    ));
    $advance = app(AdvanceReserveBackedIssuance::class);
    expect($advance->advance($transfer, $owner, $bank)->status)->toBe('pending');
    expect($order->refresh()->status)->toBe(PayCodeIssuanceFundingOrderStatus::Issuing);
    expect($advance->advance($transfer, $owner, $bank)->status)->toBe('issued');
    expect($order->refresh()->status)->toBe(PayCodeIssuanceFundingOrderStatus::Issued)
        ->and($order->voucher_id)->not->toBeNull()
        ->and(FundingSettlement::query()->where('funding_intent_id', $intent->getKey())->count())->toBe(1);
    $voucher = Voucher::query()->findOrFail($order->voucher_id);
    $source = app(VoucherBankSourceSnapshot::class)->forVoucher($voucher);
    expect($source['mode'])->toBe('platform_reserve')
        ->and($source['treasury_connection_reference'])->toBe('netbank-primary')
        ->and($source['account_number'])->toBe('222-222-22222-2');
    expect(TreasuryPosition::query()->whereMorphedTo('principal', $owner)
        ->where('connection_reference', 'netbank-primary')->where('purpose', TreasuryPositionPurpose::PayCodeReserve)->exists())->toBeTrue();
    $reserve = TreasuryPosition::query()->whereMorphedTo('principal', $owner)
        ->where('connection_reference', 'netbank-primary')->where('purpose', TreasuryPositionPurpose::PayCodeReserve)->sole();
    $reserveWallet = Wallet::query()->findOrFail($reserve->internal_ledger_id);
    $reserveBefore = (int) $reserveWallet->balance;
    $inventory = TreasuryInventory::query()->where('inventory_reference', 'inventory:netbank:vca-cash')->sole();
    $inventoryBefore = (int) $inventory->balance_minor;
    $provider = fakePayoutProvider()->willReturnPendingResult(
        transactionId: 'PERSONAL-ON-DEMAND-PAYOUT', provider: 'netbank',
    )->willResolveCheckStatusAsSuccessful(transactionId: 'PERSONAL-ON-DEMAND-PAYOUT', provider: 'netbank');
    $claim = app(SubmitPayCodeClaim::class)->handle($voucher, [
        'mobile' => '09173011987', 'recipient_country' => 'PH',
        'bank_account' => ['bank_code' => 'GXCHPHM2XXX', 'account_number' => '09173011987'],
    ]);
    $this->artisan('x-change:claim:deliver-treasury-payouts', ['--limit' => 10])->assertSuccessful();
    $reconciliation = DisbursementReconciliation::query()->where('voucher_id', $voucher->getKey())->sole();
    expect($reconciliation->status)->toBe('pending')
        ->and((int) $reserveWallet->fresh()->balance)->toBe($reserveBefore)
        ->and((int) $inventory->fresh()->balance_minor)->toBe($inventoryBefore)
        ->and($provider->lastRequest?->source_account?->account_number)->toBe('222-222-22222-2')
        ->and($provider->lastRequest?->source_account?->customer_id)->toBe('9999');
    $this->artisan('xchange:reconcile:pending', ['--json' => true])->assertSuccessful();
    expect($reconciliation->refresh()->status)->toBe('succeeded')
        ->and($reconciliation->internal_status)->toBe('finalized')
        ->and((int) $reserveWallet->fresh()->balance)->toBe($reserveBefore - 2500)
        ->and((int) $inventory->fresh()->balance_minor)->toBe($inventoryBefore - 2500)
        ->and(ExecutionJournalEntry::query()
            ->where('event_type', 'pay_code.disbursement.settled')->where('subject_id', (string) $voucher->getKey())->count())->toBe(1)
        ->and($provider->disburseCallCount)->toBe(1);
    $this->artisan('xchange:reconcile:pending', ['--json' => true])->assertSuccessful();
    expect($provider->disburseCallCount)->toBe(1)
        ->and(ExecutionJournalEntry::query()
            ->where('event_type', 'pay_code.disbursement.settled')->where('subject_id', (string) $voucher->getKey())->count())->toBe(1);
});

it('requires the exact personal VCA in NetBank history for on-demand funding', function () {
    $address = '543217654321';
    $transaction = [
        'amount' => ['cur' => 'PHP', 'num' => '2500'], 'date' => now()->toIso8601String(),
        'description' => 'EXTERNAL_TRANSFER_INCOMING',
        'destination_account' => ['account_alias' => $address], 'fees' => [],
        'status' => 'Settled', 'transaction_id' => 'fixture-credit', 'type' => 'Credit',
        'updated' => now()->toIso8601String(),
    ];
    $client = Mockery::mock(NetbankFundingApiClient::class);
    $client->shouldReceive('allTransactions')->with($address, '991100001234')->times(5)
        ->andReturnUsing(function () use (&$transaction): Generator {
            yield $transaction;
        });
    $provider = new NetbankFundingProviderAdapter($client);
    $destination = new FundingDestinationData(
        provider: 'netbank', mode: 'dedicated', destinationType: 'bank_account', accountReference: 'wallet:test',
        displayReference: 'Personal', fingerprint: hash('sha256', 'vca-fixture'), verificationStatus: 'routing_configured',
        bankAccountNumber: '991100001234', bankAccountName: 'Example', routingAlias: '54321',
        metadata: ['netbank_instruction_mode' => 'direct_qr', 'require_exact_vca' => true],
    );
    $verification = new FundingVerificationData(
        provider: 'netbank', fundingIntentReference: 'fixture-order', expectedAmountMinor: 2500,
        currency: 'PHP', fundingAddress: $address, destination: $destination,
    );
    expect($provider->verifyFunding($verification)->providerTransactionId)->toBe('fixture-credit')
        ->and(data_get($provider->verifyFunding($verification)->metadata, 'exact_vca_verified'))->toBeTrue();
    $transaction['destination_account']['account_alias'] = '543210000000';
    expect(fn () => $provider->verifyFunding($verification))
        ->toThrow(NetbankFundingNotVerified::class);
    unset($transaction['destination_account']['account_alias']);
    expect(fn () => $provider->verifyFunding($verification))
        ->toThrow(NetbankFundingNotVerified::class);
    $verification->destination->metadata['require_exact_vca'] = false;
    expect($provider->verifyFunding($verification)->providerTransactionId)->toBe('fixture-credit');
});

it('never submits a paid-order transfer if its frozen order link is changed', function (): void {
    [$owner, $assignment, $order, $intent] = verifiedPersonalPaidOrderFixture();
    config()->set('x-change.reserve_transfer.enabled', true);
    config()->set('x-change.reserve_transfer.destination_bank_code', 'CUOBPHM2XXX');
    config()->set('omnipay.gateways.netbank.options.sourceAccountNumber', '222-222-22222-2');
    config()->set('payment-gateway.netbank.funding.corporate_account_number', '222-222-22222-2');
    config()->set('omnipay.gateways.netbank.options.senderCustomerId', '9999');
    config()->set('payment-gateway.netbank.funding.corporate_account_name', 'Platform Reserve');
    app()->call([new ResumeOnDemandPayCodeIssuanceJob($order->getKey()), 'handle']);
    $transfer = ReserveTransfer::query()->where('funding_order_id', $order->getKey())->sole();
    $metadata = $order->refresh()->metadata;
    data_set($metadata, 'reserve_transfer_reference', 'wrong-reference');
    $order->forceFill(['metadata' => $metadata])->saveQuietly();
    $provider = Mockery::mock(AccountTransferProvider::class);
    $provider->shouldReceive('providerCode')->andReturn('netbank');
    $provider->shouldNotReceive('submit');

    expect(fn () => app(AdvanceReserveBackedIssuance::class)->advance($transfer, $owner, $provider))
        ->toThrow(RuntimeException::class, 'no longer eligible');
    expect($transfer->refresh()->status)->toBe('held')
        ->and($order->refresh()->voucher_id)->toBeNull()
        ->and($order->fundingIntent->refresh()->status)->toBe(FundingIntentStatus::Settled)
        ->and(FundingSettlement::query()->where('funding_intent_id', $intent->getKey())->count())->toBe(1);
});

it('keeps a paid order held when provider settlement evidence reports an unexpected fee', function (): void {
    [$owner, $assignment, $order, $intent] = verifiedPersonalPaidOrderFixture();
    config()->set('x-change.reserve_transfer.enabled', true);
    config()->set('x-change.reserve_transfer.destination_bank_code', 'CUOBPHM2XXX');
    config()->set('omnipay.gateways.netbank.options.sourceAccountNumber', '222-222-22222-2');
    config()->set('payment-gateway.netbank.funding.corporate_account_number', '222-222-22222-2');
    config()->set('omnipay.gateways.netbank.options.senderCustomerId', '9999');
    config()->set('payment-gateway.netbank.funding.corporate_account_name', 'Platform Reserve');
    app()->call([new ResumeOnDemandPayCodeIssuanceJob($order->getKey()), 'handle']);
    $transfer = ReserveTransfer::query()->where('funding_order_id', $order->getKey())->sole();
    $request = AccountTransferRequestData::from($transfer->request_ciphertext);
    $provider = Mockery::mock(AccountTransferProvider::class);
    $provider->shouldReceive('providerCode')->andReturn('netbank');
    $provider->shouldReceive('submit')->once()->andReturn(new AccountTransferResultData(AccountTransferStatus::Pending, 'bank-fee-review'));
    $provider->shouldReceive('checkStatus')->once()->andReturn(new AccountTransferResultData(
        AccountTransferStatus::Settled, 'bank-fee-review',
        new AccountTransferEvidenceData($request->reference, $request->sourceAccountNumber, $request->destinationAccountNumber,
            $request->sourceCustomerId, $request->amountMinor, $request->currency, 'DIRECT', 10000),
    ));
    $advance = app(AdvanceReserveBackedIssuance::class);
    expect($advance->advance($transfer, $owner, $provider)->status)->toBe('pending');
    expect($advance->advance($transfer, $owner, $provider)->status)->toBe('review_required')
        ->and($order->refresh()->status)->toBe(PayCodeIssuanceFundingOrderStatus::Issuing)
        ->and($order->voucher_id)->toBeNull()
        ->and(data_get(app(OnDemandIssuanceFundingOrderPresenter::class)->present($order), 'lifecycle.current'))->toBe('attention')
        ->and(FundingSettlement::query()->where('funding_intent_id', $intent->getKey())->count())->toBe(1);
});

it('stops a paid-order transfer before bank submission when the frozen funding customer changes', function (): void {
    [$owner, $assignment, $order, $intent] = verifiedPersonalPaidOrderFixture();
    config()->set('x-change.reserve_transfer.enabled', true);
    config()->set('x-change.reserve_transfer.destination_bank_code', 'CUOBPHM2XXX');
    config()->set('omnipay.gateways.netbank.options.sourceAccountNumber', '222-222-22222-2');
    config()->set('payment-gateway.netbank.funding.corporate_account_number', '222-222-22222-2');
    config()->set('omnipay.gateways.netbank.options.senderCustomerId', '9999');
    config()->set('payment-gateway.netbank.funding.corporate_account_name', 'Platform Reserve');
    app()->call([new ResumeOnDemandPayCodeIssuanceJob($order->getKey()), 'handle']);
    $transfer = ReserveTransfer::query()->where('funding_order_id', $order->getKey())->sole();
    $details = $assignment->details_ciphertext;
    $details['customer_id'] = 'different-customer';
    $assignment->forceFill(['details_ciphertext' => $details])->saveQuietly();
    $provider = Mockery::mock(AccountTransferProvider::class);
    $provider->shouldReceive('providerCode')->andReturn('netbank');
    $provider->shouldNotReceive('submit');

    expect(fn () => app(AdvanceReserveBackedIssuance::class)->advance($transfer, $owner, $provider))
        ->toThrow(FundingSettlementDenied::class, 'customer ID has changed');
    expect($transfer->refresh()->status)->toBe('held')
        ->and($order->refresh()->voucher_id)->toBeNull()
        ->and(FundingSettlement::query()->where('funding_intent_id', $intent->getKey())->count())->toBe(1);
});

it('rolls back the paid-order handoff if the linkage journal cannot be recorded', function (): void {
    [$owner, $assignment, $order, $intent] = verifiedPersonalPaidOrderFixture();
    config()->set('x-change.reserve_transfer.enabled', true);
    config()->set('x-change.reserve_transfer.destination_bank_code', 'CUOBPHM2XXX');
    config()->set('omnipay.gateways.netbank.options.sourceAccountNumber', '222-222-22222-2');
    config()->set('payment-gateway.netbank.funding.corporate_account_number', '222-222-22222-2');
    config()->set('omnipay.gateways.netbank.options.senderCustomerId', '9999');
    config()->set('payment-gateway.netbank.funding.corporate_account_name', 'Platform Reserve');
    $journal = Mockery::mock(ExecutionJournalRecorder::class);
    $journal->shouldReceive('record')->once()->andReturn(new ExecutionJournalEntry);
    $journal->shouldReceive('record')->once()->andThrow(new RuntimeException('journal unavailable'));
    app()->instance(ExecutionJournalRecorder::class, $journal);
    app()->forgetInstance(ReserveTransferJournal::class);

    expect(fn () => app()->call([new ResumeOnDemandPayCodeIssuanceJob($order->getKey()), 'handle']))
        ->toThrow(RuntimeException::class, 'journal unavailable');
    expect($order->refresh()->status)->toBe(PayCodeIssuanceFundingOrderStatus::Funded)
        ->and(TreasuryAllocation::query()->where('allocation_reference', $order->treasury_hold_reference)->sole()->balance_minor)->toBe($order->required_amount_minor)
        ->and(ReserveTransfer::query()->where('funding_order_id', $order->getKey())->exists())->toBeFalse()
        ->and(FundingSettlement::query()->where('funding_intent_id', $intent->getKey())->count())->toBe(1);
});

it('does not submit a linked reserve transfer after the original payment is reversed', function (): void {
    [$owner, $assignment, $order, $intent] = verifiedPersonalPaidOrderFixture();
    config()->set('x-change.reserve_transfer.enabled', true);
    config()->set('x-change.reserve_transfer.destination_bank_code', 'CUOBPHM2XXX');
    config()->set('omnipay.gateways.netbank.options.sourceAccountNumber', '222-222-22222-2');
    config()->set('payment-gateway.netbank.funding.corporate_account_number', '222-222-22222-2');
    config()->set('omnipay.gateways.netbank.options.senderCustomerId', '9999');
    config()->set('payment-gateway.netbank.funding.corporate_account_name', 'Platform Reserve');
    app()->call([new ResumeOnDemandPayCodeIssuanceJob($order->getKey()), 'handle']);
    $transfer = ReserveTransfer::query()->where('funding_order_id', $order->getKey())->sole();
    $metadata = $order->refresh()->metadata;
    data_set($metadata, 'provider_reversal', ['source' => 'test']);
    $order->forceFill(['metadata' => $metadata])->saveQuietly();
    $provider = Mockery::mock(AccountTransferProvider::class);
    $provider->shouldReceive('providerCode')->andReturn('netbank');
    $provider->shouldNotReceive('submit');

    expect(fn () => app(AdvanceReserveBackedIssuance::class)->advance($transfer, $owner, $provider))
        ->toThrow(RuntimeException::class, 'no longer eligible');
    expect($transfer->refresh()->status)->toBe('held')
        ->and($order->refresh()->voucher_id)->toBeNull()
        ->and(FundingSettlement::query()->where('funding_intent_id', $intent->getKey())->count())->toBe(1);
});
