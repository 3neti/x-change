<?php

declare(strict_types=1);

use Bavix\Wallet\Exceptions\BalanceIsEmpty;
use Bavix\Wallet\Exceptions\InsufficientFunds;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LBHurtado\EmiCore\Contracts\AccountTransferProvider;
use LBHurtado\EmiCore\Contracts\IdempotentAccountTransferProvider;
use LBHurtado\EmiCore\Data\Providers\AccountTransferEvidenceData;
use LBHurtado\EmiCore\Data\Providers\AccountTransferRequestData;
use LBHurtado\EmiCore\Data\Providers\AccountTransferResultData;
use LBHurtado\EmiCore\Enums\AccountTransferStatus;
use LBHurtado\EmiCore\Models\ProviderFundingObservation;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryInventoryOperationContract;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryPositionReadModelContract;
use LBHurtado\Wallet\Treasury\Data\TreasuryInventoryData;
use LBHurtado\Wallet\Treasury\Data\TreasuryInventoryRecognitionData;
use LBHurtado\Wallet\Treasury\Enums\TreasuryPositionPurpose;
use LBHurtado\Wallet\Treasury\Exceptions\TreasuryInvariantViolation;
use LBHurtado\Wallet\Treasury\Models\TreasuryInventory;
use LBHurtado\XChange\Actions\Banking\AssignNetbankAccount;
use LBHurtado\XChange\Actions\Funding\SettleVerifiedFundingIntent;
use LBHurtado\XChange\Actions\PayCode\EstimatePayCodeCost;
use LBHurtado\XChange\Actions\Redemption\SubmitPayCodeClaim;
use LBHurtado\XChange\Contracts\TreasuryAccountPortfolioProvisioningContract;
use LBHurtado\XChange\Contracts\TreasuryPrincipalReferenceResolverContract;
use LBHurtado\XChange\Contracts\WalletAccessContract;
use LBHurtado\XChange\Data\PricingEstimateData;
use LBHurtado\XChange\Enums\FundingIntentStatus;
use LBHurtado\XChange\Exceptions\CommercialPricingChanged;
use LBHurtado\XChange\Exceptions\FundingDestinationUnavailable;
use LBHurtado\XChange\Jobs\Reserve\AdvanceReserveIssuanceJob;
use LBHurtado\XChange\Models\CommercialSale;
use LBHurtado\XChange\Models\FundingDestinationPreference;
use LBHurtado\XChange\Models\FundingIntent;
use LBHurtado\XChange\Models\ReserveTransfer;
use LBHurtado\XChange\Services\Banking\AssignedBankTreasuryConnection;
use LBHurtado\XChange\Services\Banking\VoucherBankSourceSnapshot;
use LBHurtado\XChange\Services\CheckProviderSourceAccountReadiness;
use LBHurtado\XChange\Services\Commercial\PayCodeCommercialSaleService;
use LBHurtado\XChange\Services\Configuration\InstructionCapabilityReadinessRegistry;
use LBHurtado\XChange\Services\Reserve\AdvanceReserveBackedIssuance;
use LBHurtado\XChange\Services\Reserve\BuildReserveTransferRequest;
use LBHurtado\XChange\Services\Reserve\DurableReserveTransfer;
use LBHurtado\XChange\Services\Reserve\IssueReserveBackedPayCode;
use LBHurtado\XChange\Services\Reserve\PrepareReserveBackedIssuance;
use LBHurtado\XChange\Services\Reserve\ReserveIssuanceSnapshot;
use LBHurtado\XChange\Services\Reserve\ReserveTransferHold;
use LBHurtado\XChange\Services\Reserve\ReserveTransferRelease;
use LBHurtado\XChange\Services\Reserve\ReserveTransferTreasuryPosting;
use LBHurtado\XChange\Services\Treasury\TreasuryAccountBalanceReadModel;
use LBHurtado\XChange\Services\Treasury\TreasuryConfigurationValidator;
use LBHurtado\XChange\Services\Treasury\TreasuryPreflightService;
use LBHurtado\XChange\Services\Treasury\TreasuryProviderConnectionCatalog;
use LBHurtado\XChange\Services\Treasury\TreasuryProvisioningService;
use LBHurtado\XJournal\Models\ExecutionJournalEntry;
use LBHurtado\XJournal\Services\ExecutionJournalRecorder;

function reserveRequest(string $reference = 'reserve-1', int $amount = 1000): AccountTransferRequestData
{
    return new AccountTransferRequestData('netbank', $reference, 'stable-key', '111-111-11111-1', '1234', '222-222-22222-2', $amount, 'PHP', 'Source', 'Reserve', 'NETBANK');
}

it('persists frozen transfer details and rejects a conflicting replay', function (): void {
    $service = app(DurableReserveTransfer::class);
    $transfer = $service->prepare(reserveRequest(), 'source', 'reserve');
    expect($service->prepare(reserveRequest(), 'source', 'reserve')->id)->toBe($transfer->id)
        ->and($transfer->amount_minor)->toBe(1000)->and($transfer->status)->toBe('prepared')
        ->and($transfer->toArray())->not->toHaveKey('request_ciphertext');
    expect(fn () => $service->prepare(reserveRequest(amount: 1001), 'source', 'reserve'))->toThrow(RuntimeException::class);
    expect(ReserveTransfer::query()->count())->toBe(1);
});

it('submits once and requires an independent provider status check for settlement', function (): void {
    $provider = Mockery::mock(AccountTransferProvider::class);
    $provider->shouldReceive('providerCode')->andReturn('netbank');
    $provider->shouldReceive('submit')->once()->andReturn(new AccountTransferResultData(AccountTransferStatus::Settled, 'bank-1'));
    $provider->shouldReceive('checkStatus')->once()->with('bank-1')->andReturn(new AccountTransferResultData(AccountTransferStatus::Settled, 'bank-1', new AccountTransferEvidenceData('reserve-1', '111-111-11111-1', '222-222-22222-2', '1234', 1000, 'PHP', 'DIRECT', 0)));
    $service = app(DurableReserveTransfer::class);
    $transfer = readyReserveTransfer($service);
    expect($service->submit(bindReserveForTest($transfer), $provider)->status)->toBe('pending');
    expect($service->submit(bindReserveForTest($transfer), $provider)->status)->toBe('pending');
    expect($service->check($transfer, $provider)->status)->toBe('provider_settled');
    $entry = ExecutionJournalEntry::query()->where('event_type', 'reserve_transfer.bank_settled')->sole();
    expect(data_get($entry->payload, 'treasury_posted'))->toBeFalse()
        ->and(json_encode($entry->toArray()))->not->toContain('111-111-11111-1')->not->toContain('stable-key');
});

it('never automatically resubmits an uncertain outcome', function (): void {
    $provider = Mockery::mock(AccountTransferProvider::class);
    $provider->shouldReceive('providerCode')->andReturn('netbank');
    $provider->shouldReceive('submit')->once()->andThrow(new RuntimeException('network timeout'));
    $service = app(DurableReserveTransfer::class);
    $transfer = readyReserveTransfer($service);
    expect($service->submit(bindReserveForTest($transfer), $provider)->status)->toBe('uncertain')
        ->and($service->submit(bindReserveForTest($transfer), $provider)->status)->toBe('uncertain');
    expect(fn () => $service->check($transfer, $provider))->toThrow(RuntimeException::class);
});

it('rejects idempotency-key reuse for a different reference', function (): void {
    $service = app(DurableReserveTransfer::class);
    $service->prepare(reserveRequest(), 'source', 'reserve');
    expect(fn () => $service->prepare(reserveRequest('reserve-2'), 'source', 'reserve'))->toThrow(QueryException::class);
    expect(ReserveTransfer::query()->count())->toBe(1);
});

it('does not accept a status for another provider transaction', function (): void {
    $provider = Mockery::mock(AccountTransferProvider::class);
    $provider->shouldReceive('providerCode')->andReturn('netbank');
    $provider->shouldReceive('submit')->once()->andReturn(new AccountTransferResultData(AccountTransferStatus::Pending, 'bank-1'));
    $provider->shouldReceive('checkStatus')->once()->andReturn(new AccountTransferResultData(AccountTransferStatus::Settled, 'bank-2'));
    $service = app(DurableReserveTransfer::class);
    $transfer = readyReserveTransfer($service);
    $service->submit(bindReserveForTest($transfer), $provider);
    expect($service->check($transfer, $provider)->status)->toBe('pending');
});

it('retains the submitting state without resending after an interrupted worker', function (): void {
    $provider = Mockery::mock(AccountTransferProvider::class);
    $provider->shouldReceive('providerCode')->andReturn('netbank');
    $provider->shouldNotReceive('submit');
    $service = app(DurableReserveTransfer::class);
    $transfer = readyReserveTransfer($service);
    $transfer->forceFill(['status' => 'submitting', 'submitted_at' => now()])->save();
    expect($service->submit(bindReserveForTest($transfer), $provider)->status)->toBe('submitting');
});

it('puts an unexpected bank amount or fee into review without marking it settled', function (int $amount, int $fee): void {
    $provider = Mockery::mock(AccountTransferProvider::class);
    $provider->shouldReceive('providerCode')->andReturn('netbank');
    $provider->shouldReceive('submit')->once()->andReturn(new AccountTransferResultData(AccountTransferStatus::Pending, 'bank-1'));
    $provider->shouldReceive('checkStatus')->once()->andReturn(new AccountTransferResultData(AccountTransferStatus::Settled, 'bank-1', new AccountTransferEvidenceData('reserve-1', '111-111-11111-1', '222-222-22222-2', '1234', $amount, 'PHP', 'DIRECT', $fee)));
    $service = app(DurableReserveTransfer::class);
    $transfer = readyReserveTransfer($service);
    $service->submit(bindReserveForTest($transfer), $provider);
    $result = $service->check($transfer, $provider);
    expect($result->status)->toBe('review_required')->and($result->provider_settled_at)->toBeNull()
        ->and($result->toArray())->not->toHaveKey('bank_evidence_ciphertext');
})->with([[1001, 0], [1000, 1]]);

it('rolls back bank settlement if its journal cannot be recorded', function (): void {
    $provider = Mockery::mock(AccountTransferProvider::class);
    $provider->shouldReceive('providerCode')->andReturn('netbank');
    $provider->shouldReceive('submit')->once()->andReturn(new AccountTransferResultData(AccountTransferStatus::Pending, 'bank-1'));
    $provider->shouldReceive('checkStatus')->once()->andReturn(new AccountTransferResultData(AccountTransferStatus::Settled, 'bank-1', new AccountTransferEvidenceData('reserve-1', '111-111-11111-1', '222-222-22222-2', '1234', 1000, 'PHP', 'DIRECT', 0)));
    $service = app(DurableReserveTransfer::class);
    $transfer = readyReserveTransfer($service);
    $service->submit(bindReserveForTest($transfer), $provider);
    $journal = Mockery::mock(ExecutionJournalRecorder::class);
    $journal->shouldReceive('record')->once()->andThrow(new RuntimeException('Journal unavailable.'));
    app()->instance(ExecutionJournalRecorder::class, $journal);
    expect(fn () => $service->check($transfer, $provider))->toThrow(RuntimeException::class, 'Journal unavailable.');
    expect($transfer->refresh()->status)->toBe('pending')->and($transfer->provider_settled_at)->toBeNull();
});

it('never submits before the treasury hold exists', function (): void {
    $provider = Mockery::mock(AccountTransferProvider::class);
    $provider->shouldReceive('providerCode')->andReturn('netbank');
    $provider->shouldNotReceive('submit');
    $service = app(DurableReserveTransfer::class);
    $transfer = $service->prepare(reserveRequest(), 'source', 'reserve');
    expect($service->submit(bindReserveForTest($transfer), $provider)->status)->toBe('prepared');
});

function setupReserveHoldFixture(int $funds = 1000): array
{
    enableNetbankTreasuryForTests();
    $owner = actingAsTestUser(0);
    if ($funds > 0) {
        fundTestUserClientFunds($owner, $funds);
    }
    $connections = config('x-change.treasury.connections');
    $connections['netbank-reserve'] = [
        'provider' => 'netbank', 'mode' => 'required', 'currency' => 'PHP', 'decimal_places' => 2,
        'inventory_reference' => 'inventory:reserve', 'settlement_resource_reference' => 'resource:reserve',
        'settlement_resource_type' => 'cash_at_bank', 'custody_mode' => 'provider_projection',
    ];
    config()->set('x-change.treasury.connections', $connections);
    config()->set('x-change.funding.providers.netbank.treasury', ['inventory_reference' => 'inventory:reserve', 'settlement_resource_reference' => 'resource:reserve']);
    config()->set('payment-gateway.netbank.funding.corporate_account_number', '222-222-22222-2');
    config()->set('omnipay.gateways.netbank.options.sourceAccountNumber', '222-222-22222-2');
    config()->set('omnipay.gateways.netbank.options.senderCustomerId', '9876');
    foreach ([TreasuryProviderConnectionCatalog::class, TreasuryPreflightService::class, TreasuryProvisioningService::class, TreasuryAccountPortfolioProvisioningContract::class, TreasuryConfigurationValidator::class] as $abstract) {
        app()->forgetInstance($abstract);
    }
    $assignment = app(AssignNetbankAccount::class)->handle($owner, [
        'account_number' => '111-111-11111-1', 'account_name' => 'Example', 'customer_id' => '1234', 'vca_alias' => '54321',
    ]);
    app(AssignedBankTreasuryConnection::class)->bind($owner, $assignment->id, 'netbank-primary');
    $assignment->providerAccountLink->forceFill(['status' => 'ready', 'ready_at' => now(), 'capabilities' => ['disbursement']])->save();
    $readiness = Mockery::mock(CheckProviderSourceAccountReadiness::class);
    $readiness->shouldReceive('handle')->andReturn(['ready' => true, 'checked' => true, 'currency' => 'PHP', 'available_balance_minor' => 100000]);
    app()->instance(CheckProviderSourceAccountReadiness::class, $readiness);

    return [$owner, $assignment];
}

it('holds only the assigned owner funds exactly once before bank submission', function (): void {
    [$owner, $assignment] = setupReserveHoldFixture();
    $transfer = app(DurableReserveTransfer::class)->prepare(reserveRequest(), 'netbank-primary', 'netbank-reserve');
    $action = app(ReserveTransferHold::class);
    $held = $action->hold($transfer, $owner, $assignment->provider_account_link_id);
    expect($held->status)->toBe('held')->and($held->hold_operation_reference)->not->toBeNull();
    expect($action->hold($held, $owner, $assignment->provider_account_link_id)->hold_operation_reference)->toBe($held->hold_operation_reference);
    $balances = app(TreasuryAccountBalanceReadModel::class);
    expect($balances->connectionBalanceMinor($owner, 'netbank', 'netbank-primary', 'PHP'))->toBe(0);
    expect(ExecutionJournalEntry::query()->where('event_type', 'reserve_transfer.hold_created')->count())->toBe(1);
    $provider = Mockery::mock(AccountTransferProvider::class);
    $provider->shouldReceive('providerCode')->andReturn('netbank');
    $provider->shouldReceive('submit')->once()->andReturn(new AccountTransferResultData(AccountTransferStatus::Pending, 'bank-1'));
    expect(app(DurableReserveTransfer::class)->submit(bindReserveForTest($held), $provider)->status)->toBe('pending');
});

it('does not hold more than the account has and rejects another owner', function (): void {
    [$owner, $assignment] = setupReserveHoldFixture(999);
    $transfer = app(DurableReserveTransfer::class)->prepare(reserveRequest(), 'netbank-primary', 'netbank-reserve');
    $action = app(ReserveTransferHold::class);
    $other = actingAsTestUser(0);
    expect(fn () => $action->hold($transfer, $other, $assignment->provider_account_link_id))->toThrow(FundingDestinationUnavailable::class);
    expect(fn () => $action->hold($transfer, $owner, $assignment->provider_account_link_id))->toThrow(InsufficientFunds::class);
    expect($transfer->refresh()->status)->toBe('prepared');
});

it('refuses a platform destination if the configured funding and payout accounts disagree', function (): void {
    [$owner, $assignment] = setupReserveHoldFixture();
    config()->set('payment-gateway.netbank.funding.corporate_account_number', '333-333-33333-3');
    $transfer = app(DurableReserveTransfer::class)->prepare(reserveRequest(), 'netbank-primary', 'netbank-reserve');
    expect(fn () => app(ReserveTransferHold::class)->hold($transfer, $owner, $assignment->provider_account_link_id))
        ->toThrow(RuntimeException::class, 'does not match');
    expect($transfer->refresh()->status)->toBe('prepared');
});

function readyReserveTransfer(DurableReserveTransfer $service): ReserveTransfer
{
    [$owner, $assignment] = setupReserveHoldFixture();
    $transfer = $service->prepare(reserveRequest(), 'netbank-primary', 'netbank-reserve');

    return app(ReserveTransferHold::class)->hold($transfer, $owner, $assignment->provider_account_link_id);
}

it('refuses to submit a forged or unrelated hold', function (): void {
    $provider = Mockery::mock(AccountTransferProvider::class);
    $provider->shouldReceive('providerCode')->andReturn('netbank');
    $provider->shouldNotReceive('submit');
    $service = app(DurableReserveTransfer::class);
    $transfer = $service->prepare(reserveRequest(), 'source', 'reserve');
    $transfer->forceFill(['status' => 'held', 'hold_operation_reference' => 'forged'])->save();
    expect(fn () => $service->submit(bindReserveForTest($transfer), $provider))->toThrow(RuntimeException::class, 'hold is invalid');
    expect($transfer->refresh()->status)->toBe('held');
});

it('moves confirmed bank backing and owner reserve exactly once between treasury connections', function (): void {
    [$owner, $assignment] = setupReserveHoldFixture();
    $inventory = app(TreasuryInventoryOperationContract::class);
    $inventory->registerInventory(new TreasuryInventoryData(
        'inventory:netbank:vca-cash', 'cash_at_bank', 'PHP', 0, 'requested', 'test-source-inventory', 'resource:netbank:corporate-vca',
    ));
    $inventory->recognize(new TreasuryInventoryRecognitionData(
        'test-source-recognition', 'inventory:netbank:vca-cash', 'resource:netbank:corporate-vca', 1000, 'PHP', 'requested', 'test-source-recognition-key', externalReference: 'test-provider-deposit',
    ));
    $provider = Mockery::mock(AccountTransferProvider::class);
    $provider->shouldReceive('providerCode')->andReturn('netbank');
    $provider->shouldReceive('submit')->once()->andReturn(new AccountTransferResultData(AccountTransferStatus::Pending, 'bank-1'));
    $provider->shouldReceive('checkStatus')->once()->andReturn(new AccountTransferResultData(AccountTransferStatus::Settled, 'bank-1', new AccountTransferEvidenceData('reserve-1', '111-111-11111-1', '222-222-22222-2', '1234', 1000, 'PHP', 'DIRECT', 0)));
    $service = app(DurableReserveTransfer::class);
    $transfer = $service->prepare(reserveRequest(), 'netbank-primary', 'netbank-reserve');
    $held = app(ReserveTransferHold::class)->hold($transfer, $owner, $assignment->provider_account_link_id);
    $service->submit(bindReserveForTest($held), $provider);
    $settled = $service->check($held, $provider);
    $posting = app(ReserveTransferTreasuryPosting::class);
    $posted = $posting->post($settled);
    expect($posted->status)->toBe('treasury_posted')->and($posted->treasury_posted_at)->not->toBeNull();
    expect($posting->post($posted)->status)->toBe('treasury_posted');
    $inventories = TreasuryInventory::query()->whereIn('inventory_reference', ['inventory:netbank:vca-cash', 'inventory:reserve'])->pluck('balance_minor', 'inventory_reference');
    expect($inventories['inventory:netbank:vca-cash'])->toBe(0)->and($inventories['inventory:reserve'])->toBe(1000);
    $positions = app(TreasuryPositionReadModelContract::class)->forPrincipal(app(TreasuryPrincipalReferenceResolverContract::class)->resolve($owner));
    $destination = collect($positions)->first(fn ($p) => $p->connectionReference === 'netbank-reserve' && $p->purpose === TreasuryPositionPurpose::PayCodeReserve);
    $source = collect($positions)->first(fn ($p) => $p->connectionReference === 'netbank-primary' && $p->purpose === TreasuryPositionPurpose::PayCodeReserve);
    expect($source->balanceMinor)->toBe(0)->and($destination->balanceMinor)->toBe(1000);
    expect(ExecutionJournalEntry::query()->where('event_type', 'reserve_transfer.treasury_posted')->count())->toBe(1);
});

it('retains the confirmed bank settlement and original hold if treasury posting cannot complete', function (): void {
    [$owner, $assignment] = setupReserveHoldFixture();
    $provider = Mockery::mock(AccountTransferProvider::class);
    $provider->shouldReceive('providerCode')->andReturn('netbank');
    $provider->shouldReceive('submit')->once()->andReturn(new AccountTransferResultData(AccountTransferStatus::Pending, 'bank-1'));
    $provider->shouldReceive('checkStatus')->once()->andReturn(new AccountTransferResultData(AccountTransferStatus::Settled, 'bank-1', new AccountTransferEvidenceData('reserve-1', '111-111-11111-1', '222-222-22222-2', '1234', 1000, 'PHP', 'DIRECT', 0)));
    $service = app(DurableReserveTransfer::class);
    $transfer = $service->prepare(reserveRequest(), 'netbank-primary', 'netbank-reserve');
    $held = app(ReserveTransferHold::class)->hold($transfer, $owner, $assignment->provider_account_link_id);
    $service->submit(bindReserveForTest($held), $provider);
    $settled = $service->check($held, $provider);
    expect(fn () => app(ReserveTransferTreasuryPosting::class)->post($settled))
        ->toThrow(TreasuryInvariantViolation::class);
    expect($settled->refresh()->status)->toBe('provider_settled')->and($settled->treasury_posted_at)->toBeNull();
    $positions = app(TreasuryPositionReadModelContract::class)->forPrincipal(app(TreasuryPrincipalReferenceResolverContract::class)->resolve($owner));
    $source = collect($positions)->first(fn ($p) => $p->connectionReference === 'netbank-primary' && $p->purpose === TreasuryPositionPurpose::PayCodeReserve);
    expect($source->balanceMinor)->toBe(1000);
    expect(ExecutionJournalEntry::query()->where('event_type', 'reserve_transfer.treasury_posted')->count())->toBe(0);
});

it('refuses a transfer when the bank cannot confirm the source balance', function (array $observation): void {
    [$owner, $assignment] = setupReserveHoldFixture();
    $readiness = Mockery::mock(CheckProviderSourceAccountReadiness::class);
    $readiness->shouldReceive('handle')->once()->andReturn($observation);
    app()->instance(CheckProviderSourceAccountReadiness::class, $readiness);
    $transfer = app(DurableReserveTransfer::class)->prepare(reserveRequest(), 'netbank-primary', 'netbank-reserve');
    expect(fn () => app(ReserveTransferHold::class)->hold($transfer, $owner, $assignment->provider_account_link_id))->toThrow(RuntimeException::class, 'bank cannot confirm');
    expect($transfer->refresh()->status)->toBe('prepared');
})->with([
    [['ready' => true, 'checked' => true, 'currency' => 'PHP', 'available_balance_minor' => 999]],
    [['ready' => true, 'checked' => false, 'currency' => 'PHP', 'available_balance_minor' => 1000]],
    [['ready' => true, 'checked' => true, 'currency' => 'USD', 'available_balance_minor' => 1000]],
]);

it('refuses to submit if the frozen transfer amount has been changed', function (): void {
    $service = app(DurableReserveTransfer::class);
    $transfer = readyReserveTransfer($service);
    $transfer->forceFill(['amount_minor' => 1])->save();
    $provider = Mockery::mock(AccountTransferProvider::class);
    $provider->shouldReceive('providerCode')->andReturn('netbank');
    $provider->shouldNotReceive('submit');
    expect(fn () => $service->submit(bindReserveForTest($transfer), $provider))->toThrow(RuntimeException::class, 'frozen transfer request has changed');
    expect($transfer->refresh()->status)->toBe('held');
});

it('rolls all treasury movements back if posting the journal fails', function (): void {
    [$owner, $assignment] = setupReserveHoldFixture();
    $inventory = app(TreasuryInventoryOperationContract::class);
    $inventory->registerInventory(new TreasuryInventoryData('inventory:netbank:vca-cash', 'cash_at_bank', 'PHP', 0, 'requested', 'test-source-inventory', 'resource:netbank:corporate-vca'));
    $inventory->recognize(new TreasuryInventoryRecognitionData('test-source-recognition', 'inventory:netbank:vca-cash', 'resource:netbank:corporate-vca', 1000, 'PHP', 'requested', 'test-source-recognition-key', externalReference: 'test-provider-deposit'));
    $provider = Mockery::mock(AccountTransferProvider::class);
    $provider->shouldReceive('providerCode')->andReturn('netbank');
    $provider->shouldReceive('submit')->once()->andReturn(new AccountTransferResultData(AccountTransferStatus::Pending, 'bank-1'));
    $provider->shouldReceive('checkStatus')->once()->andReturn(new AccountTransferResultData(AccountTransferStatus::Settled, 'bank-1', new AccountTransferEvidenceData('reserve-1', '111-111-11111-1', '222-222-22222-2', '1234', 1000, 'PHP', 'DIRECT', 0)));
    $service = app(DurableReserveTransfer::class);
    $transfer = $service->prepare(reserveRequest(), 'netbank-primary', 'netbank-reserve');
    $held = app(ReserveTransferHold::class)->hold($transfer, $owner, $assignment->provider_account_link_id);
    $service->submit(bindReserveForTest($held), $provider);
    $settled = $service->check($held, $provider);
    $recorder = Mockery::mock(ExecutionJournalRecorder::class);
    $recorder->shouldReceive('record')->once()->andThrow(new RuntimeException('Journal unavailable.'));
    app()->instance(ExecutionJournalRecorder::class, $recorder);
    expect(fn () => app(ReserveTransferTreasuryPosting::class)->post($settled))->toThrow(RuntimeException::class, 'Journal unavailable.');
    expect($settled->refresh()->status)->toBe('provider_settled');
    expect(TreasuryInventory::query()->where('inventory_reference', 'inventory:netbank:vca-cash')->value('balance_minor'))->toBe(1000);
    expect(TreasuryInventory::query()->where('inventory_reference', 'inventory:reserve')->exists())->toBeFalse();
    $positions = app(TreasuryPositionReadModelContract::class)->forPrincipal(app(TreasuryPrincipalReferenceResolverContract::class)->resolve($owner));
    $source = collect($positions)->first(fn ($p) => $p->connectionReference === 'netbank-primary' && $p->purpose === TreasuryPositionPurpose::PayCodeReserve);
    expect($source->balanceMinor)->toBe(1000);
});

it('releases an unsubmitted hold once back to the same owner', function (): void {
    $service = app(DurableReserveTransfer::class);
    [$owner, $assignment] = setupReserveHoldFixture();
    $held = app(ReserveTransferHold::class)->hold($service->prepare(reserveRequest(), 'netbank-primary', 'netbank-reserve'), $owner, $assignment->provider_account_link_id);
    $release = app(ReserveTransferRelease::class);
    expect($release->release($held)->status)->toBe('released')->and($release->release($held)->status)->toBe('released');
    expect(app(TreasuryAccountBalanceReadModel::class)->connectionBalanceMinor($owner, 'netbank', 'netbank-primary', 'PHP'))->toBe(1000);
    expect(ExecutionJournalEntry::query()->where('event_type', 'reserve_transfer.hold_released')->count())->toBe(1);
});

it('retains the hold for an uncertain bank result', function (): void {
    $service = app(DurableReserveTransfer::class);
    $held = readyReserveTransfer($service);
    $provider = Mockery::mock(AccountTransferProvider::class);
    $provider->shouldReceive('providerCode')->andReturn('netbank');
    $provider->shouldReceive('submit')->once()->andReturn(new AccountTransferResultData(AccountTransferStatus::Uncertain));
    $uncertain = $service->submit(bindReserveForTest($held), $provider);
    expect(fn () => app(ReserveTransferRelease::class)->release($uncertain))->toThrow(RuntimeException::class, 'pending or uncertain');
    expect($uncertain->refresh()->status)->toBe('uncertain');
});

it('releases a rejected transfer only after matching bank evidence is checked', function (): void {
    [$owner, $assignment] = setupReserveHoldFixture();
    $service = app(DurableReserveTransfer::class);
    $held = app(ReserveTransferHold::class)->hold($service->prepare(reserveRequest(), 'netbank-primary', 'netbank-reserve'), $owner, $assignment->provider_account_link_id);
    $provider = Mockery::mock(AccountTransferProvider::class);
    $provider->shouldReceive('providerCode')->andReturn('netbank');
    $provider->shouldReceive('submit')->once()->andReturn(new AccountTransferResultData(AccountTransferStatus::Rejected, 'bank-1'));
    $provider->shouldReceive('checkStatus')->once()->andReturn(new AccountTransferResultData(AccountTransferStatus::Rejected, 'bank-1', new AccountTransferEvidenceData('reserve-1', '111-111-11111-1', '222-222-22222-2', '1234', 1000, 'PHP', 'DIRECT', 0)));
    $pending = $service->submit(bindReserveForTest($held), $provider);
    expect($pending->status)->toBe('pending');
    expect(fn () => app(ReserveTransferRelease::class)->release($pending))->toThrow(RuntimeException::class);
    $rejected = $service->check($pending, $provider);
    expect($rejected->status)->toBe('rejected');
    expect(app(ReserveTransferRelease::class)->release($rejected)->status)->toBe('released');
    expect(app(TreasuryAccountBalanceReadModel::class)->connectionBalanceMinor($owner, 'netbank', 'netbank-primary', 'PHP'))->toBe(1000);
});

it('retries an uncertain transfer only with its identical frozen request and idempotency key', function (): void {
    $service = app(DurableReserveTransfer::class);
    $held = readyReserveTransfer($service);
    $seen = [];
    $provider = Mockery::mock(IdempotentAccountTransferProvider::class);
    $provider->shouldReceive('providerCode')->andReturn('netbank');
    $provider->shouldReceive('submit')->twice()->andReturnUsing(function (AccountTransferRequestData $request) use (&$seen): AccountTransferResultData {
        $seen[] = $request->toArray();

        return count($seen) === 1 ? new AccountTransferResultData(AccountTransferStatus::Uncertain) : new AccountTransferResultData(AccountTransferStatus::Pending, 'bank-1');
    });
    $uncertain = $service->submit(bindReserveForTest($held), $provider);
    expect($uncertain->status)->toBe('uncertain');
    $pending = $service->retryUncertain($uncertain, $provider);
    expect($pending->status)->toBe('pending')->and($seen[1])->toBe($seen[0]);
    expect($service->retryUncertain($pending, $provider)->status)->toBe('pending');
});

it('refuses uncertain replay when the provider does not declare idempotent transfers', function (): void {
    $service = app(DurableReserveTransfer::class);
    $held = readyReserveTransfer($service);
    $provider = Mockery::mock(AccountTransferProvider::class);
    $provider->shouldReceive('providerCode')->andReturn('netbank');
    $provider->shouldReceive('submit')->once()->andReturn(new AccountTransferResultData(AccountTransferStatus::Uncertain));
    $uncertain = $service->submit(bindReserveForTest($held), $provider);
    expect(fn () => $service->retryUncertain($uncertain, $provider))->toThrow(RuntimeException::class, 'does not guarantee safe transfer replay');
});

it('does not lock client funds when the hold journal cannot be written', function (): void {
    [$owner, $assignment] = setupReserveHoldFixture();
    $transfer = app(DurableReserveTransfer::class)->prepare(reserveRequest(), 'netbank-primary', 'netbank-reserve');
    $recorder = Mockery::mock(ExecutionJournalRecorder::class);
    $recorder->shouldReceive('record')->once()->andThrow(new RuntimeException('Journal unavailable.'));
    app()->instance(ExecutionJournalRecorder::class, $recorder);
    expect(fn () => app(ReserveTransferHold::class)->hold($transfer, $owner, $assignment->provider_account_link_id))->toThrow(RuntimeException::class, 'Journal unavailable.');
    expect($transfer->refresh()->status)->toBe('prepared');
    expect(app(TreasuryAccountBalanceReadModel::class)->connectionBalanceMinor($owner, 'netbank', 'netbank-primary', 'PHP'))->toBe(1000);
});

it('uses the frozen source after the owner changes the default account', function (): void {
    [$owner, $assignment] = setupReserveHoldFixture();
    $preference = FundingDestinationPreference::query()->create([
        'owner_type' => $owner->getMorphClass(), 'owner_id' => $owner->getKey(), 'provider_code' => 'netbank',
        'mode' => 'dedicated', 'provider_account_link_id' => $assignment->provider_account_link_id,
    ]);
    $service = app(DurableReserveTransfer::class);
    $held = app(ReserveTransferHold::class)->hold($service->prepare(reserveRequest(), 'netbank-primary', 'netbank-reserve'), $owner, $assignment->provider_account_link_id);
    $preference->forceFill(['mode' => 'shared', 'provider_account_link_id' => null])->save();
    $provider = Mockery::mock(AccountTransferProvider::class);
    $provider->shouldReceive('providerCode')->andReturn('netbank');
    $provider->shouldReceive('submit')->once()->with(Mockery::on(fn ($request) => $request->sourceAccountNumber === '111-111-11111-1' && $request->destinationAccountNumber === '222-222-22222-2'))
        ->andReturn(new AccountTransferResultData(AccountTransferStatus::Pending, 'bank-1'));
    expect($service->submit(bindReserveForTest($held), $provider)->status)->toBe('pending');
});

function bindReserveForTest(ReserveTransfer $transfer): ReserveTransfer
{
    $transfer->refresh();
    if ($transfer->status !== 'held' || $transfer->owner === null) {
        return $transfer;
    }
    $estimate = Mockery::mock(EstimatePayCodeCost::class);
    $estimate->shouldReceive('handle')->andReturn(new PricingEstimateData(currency: 'PHP', total: 0, customer_charge_minor: 0));
    app()->instance(EstimatePayCodeCost::class, $estimate);

    return app(ReserveIssuanceSnapshot::class)->bind($transfer, $transfer->owner, validPayCodePayload($transfer->amount_minor / 100));
}

it('requires a frozen issuance before submitting the transfer', function (): void {
    [$owner, $assignment] = setupReserveHoldFixture();
    $service = app(DurableReserveTransfer::class);
    $transfer = $service->prepare(reserveRequest(), 'netbank-primary', 'netbank-reserve');
    $held = app(ReserveTransferHold::class)->hold($transfer, $owner, $assignment->provider_account_link_id);
    $provider = Mockery::mock(AccountTransferProvider::class);
    $provider->shouldReceive('providerCode')->andReturn('netbank');
    $provider->shouldNotReceive('submit');
    expect(fn () => $service->submit($held, $provider))->toThrow(RuntimeException::class, 'matching frozen issuance');
    expect($held->refresh()->status)->toBe('held');
});

it('binds one owner and price to the intended issuance and rejects changes', function (): void {
    [$owner, $assignment] = setupReserveHoldFixture();
    $service = app(DurableReserveTransfer::class);
    $transfer = $service->prepare(reserveRequest(), 'netbank-primary', 'netbank-reserve');
    $held = app(ReserveTransferHold::class)->hold($transfer, $owner, $assignment->provider_account_link_id);
    $bound = bindReserveForTest($held);
    $snapshot = app(ReserveIssuanceSnapshot::class);
    expect($bound->toArray())->not->toHaveKey('issuance_snapshot_ciphertext');
    expect(data_get($bound->issuance_snapshot_ciphertext, 'instructions.metadata.custom.claim_evidence.requirements'))
        ->toContain('selfie', 'signature');
    expect($snapshot->bind($bound, $owner, validPayCodePayload(10))->id)->toBe($bound->id);
    expect(fn () => $snapshot->bind($bound, $owner, validPayCodePayload(11)))->toThrow(RuntimeException::class, 'does not match');
    expect(fn () => $snapshot->bind($bound, $owner, validPayCodePayload(10, overrides: ['rider' => ['message' => 'changed']])))->toThrow(RuntimeException::class, 'already bound');
    $other = actingAsTestUser(0);
    expect(fn () => $snapshot->bind($bound, $other, validPayCodePayload(10)))->toThrow(RuntimeException::class, 'owner');
});

it('rejects changed pricing and tampered frozen instructions', function (): void {
    [$owner, $assignment] = setupReserveHoldFixture();
    $service = app(DurableReserveTransfer::class);
    $held = app(ReserveTransferHold::class)->hold(
        $service->prepare(reserveRequest(), 'netbank-primary', 'netbank-reserve'),
        $owner,
        $assignment->provider_account_link_id,
    );
    $bound = bindReserveForTest($held);
    $estimator = Mockery::mock(EstimatePayCodeCost::class);
    $estimator->shouldReceive('handle')->andReturn(new PricingEstimateData(currency: 'PHP', total: 6, customer_charge_minor: 600));
    app()->instance(EstimatePayCodeCost::class, $estimator);
    $snapshotter = app(ReserveIssuanceSnapshot::class);
    expect(fn () => $snapshotter->bind($bound, $owner, validPayCodePayload(10)))->toThrow(RuntimeException::class, 'already bound');
    $snapshot = $bound->issuance_snapshot_ciphertext;
    $snapshot['instructions']['cash']['amount'] = 20;
    $bound->forceFill(['issuance_snapshot_ciphertext' => $snapshot])->save();
    expect(fn () => $snapshotter->assertFrozen($bound->fresh()))->toThrow(RuntimeException::class, 'matching frozen issuance');
});

it('holds the issuance fee alongside the principal and releases both after cancellation', function (): void {
    [$owner, $assignment] = setupReserveHoldFixture(1500);
    $transfer = app(DurableReserveTransfer::class)->prepare(reserveRequest(), 'netbank-primary', 'netbank-reserve');
    $held = app(ReserveTransferHold::class)->hold($transfer, $owner, $assignment->provider_account_link_id);
    $estimator = Mockery::mock(EstimatePayCodeCost::class);
    $estimator->shouldReceive('handle')->andReturn(new PricingEstimateData(currency: 'PHP', total: 5, customer_charge_minor: 500));
    app()->instance(EstimatePayCodeCost::class, $estimator);
    $snapshotter = app(ReserveIssuanceSnapshot::class);
    $bound = $snapshotter->bind($held, $owner, validPayCodePayload(10));
    $snapshotter->assertFeeHold($bound);
    expect($bound->fee_minor)->toBe(500)->and($bound->fee_hold_operation_reference)->not->toBeNull();
    $balances = app(TreasuryAccountBalanceReadModel::class);
    expect($balances->connectionBalanceMinor($owner, 'netbank', 'netbank-primary', 'PHP'))->toBe(0);
    expect(app(ReserveTransferRelease::class)->release($bound)->status)->toBe('released');
    expect($balances->connectionBalanceMinor($owner, 'netbank', 'netbank-primary', 'PHP'))->toBe(1500);
    expect(app(ReserveTransferRelease::class)->release($bound)->status)->toBe('released');
    expect($balances->connectionBalanceMinor($owner, 'netbank', 'netbank-primary', 'PHP'))->toBe(1500);
});

it('cannot bind an issuance when the selected account lacks the fee', function (): void {
    [$owner, $assignment] = setupReserveHoldFixture(1499);
    $transfer = app(DurableReserveTransfer::class)->prepare(reserveRequest(), 'netbank-primary', 'netbank-reserve');
    $held = app(ReserveTransferHold::class)->hold($transfer, $owner, $assignment->provider_account_link_id);
    $estimator = Mockery::mock(EstimatePayCodeCost::class);
    $estimator->shouldReceive('handle')->andReturn(new PricingEstimateData(currency: 'PHP', total: 5, customer_charge_minor: 500));
    app()->instance(EstimatePayCodeCost::class, $estimator);
    expect(fn () => app(ReserveIssuanceSnapshot::class)->bind($held, $owner, validPayCodePayload(10)))->toThrow(InsufficientFunds::class);
    expect($held->refresh()->issuance_snapshot_fingerprint)->toBeNull()->and($held->fee_hold_operation_reference)->toBeNull();
});

it('freezes the platform payout account and customer when the hold is created', function (): void {
    [$owner, $assignment] = setupReserveHoldFixture();
    $transfer = app(DurableReserveTransfer::class)->prepare(reserveRequest(), 'netbank-primary', 'netbank-reserve');
    $held = app(ReserveTransferHold::class)->hold($transfer, $owner, $assignment->provider_account_link_id);
    config()->set('omnipay.gateways.netbank.options.sourceAccountNumber', '999-999-99999-9');
    config()->set('omnipay.gateways.netbank.options.senderCustomerId', 'other');
    $binding = $held->refresh()->treasury_binding_ciphertext;
    expect($binding['reserve_payout_provider'])->toBe('netbank')
        ->and($binding['reserve_payout_account'])->toBe('222-222-22222-2')
        ->and($binding['reserve_payout_customer'])->toBe('9876');
});

it('keeps both principal and fee reserved when the bank outcome is uncertain', function (): void {
    [$owner, $assignment] = setupReserveHoldFixture(1500);
    $service = app(DurableReserveTransfer::class);
    $held = app(ReserveTransferHold::class)->hold($service->prepare(reserveRequest(), 'netbank-primary', 'netbank-reserve'), $owner, $assignment->provider_account_link_id);
    $estimator = Mockery::mock(EstimatePayCodeCost::class);
    $estimator->shouldReceive('handle')->andReturn(new PricingEstimateData(currency: 'PHP', total: 5, customer_charge_minor: 500));
    app()->instance(EstimatePayCodeCost::class, $estimator);
    $bound = app(ReserveIssuanceSnapshot::class)->bind($held, $owner, validPayCodePayload(10));
    $provider = Mockery::mock(AccountTransferProvider::class);
    $provider->shouldReceive('providerCode')->andReturn('netbank');
    $provider->shouldReceive('submit')->once()->andReturn(new AccountTransferResultData(AccountTransferStatus::Uncertain));
    expect($service->submit($bound, $provider)->status)->toBe('uncertain');
    expect(fn () => app(ReserveTransferRelease::class)->release($bound))->toThrow(RuntimeException::class, 'pending or uncertain');
    expect(app(TreasuryAccountBalanceReadModel::class)->connectionBalanceMinor($owner, 'netbank', 'netbank-primary', 'PHP'))->toBe(0);
});

it('accepts the canonical redeemable voucher type and rejects payable instructions', function (): void {
    [$owner, $assignment] = setupReserveHoldFixture();
    $transfer = app(DurableReserveTransfer::class)->prepare(reserveRequest(), 'netbank-primary', 'netbank-reserve');
    $held = app(ReserveTransferHold::class)->hold($transfer, $owner, $assignment->provider_account_link_id);
    $estimator = Mockery::mock(EstimatePayCodeCost::class);
    $estimator->shouldReceive('handle')->andReturn(new PricingEstimateData(currency: 'PHP'));
    app()->instance(EstimatePayCodeCost::class, $estimator);
    $snapshotter = app(ReserveIssuanceSnapshot::class);
    expect(fn () => $snapshotter->bind($held, $owner, validPayCodePayload(10, overrides: ['voucher_type' => 'payable'])))
        ->toThrow(RuntimeException::class, 'does not match');
    expect($snapshotter->bind($held, $owner, validPayCodePayload(10, overrides: ['voucher_type' => 'redeemable']))->issuance_snapshot_fingerprint)
        ->not->toBeNull();
});

it('cannot confirm bank settlement if the frozen issuance is changed', function (): void {
    $provider = Mockery::mock(AccountTransferProvider::class);
    $provider->shouldReceive('providerCode')->andReturn('netbank');
    $provider->shouldReceive('submit')->once()->andReturn(new AccountTransferResultData(AccountTransferStatus::Pending, 'bank-1'));
    $provider->shouldReceive('checkStatus')->once()->andReturn(new AccountTransferResultData(AccountTransferStatus::Settled, 'bank-1', new AccountTransferEvidenceData('reserve-1', '111-111-11111-1', '222-222-22222-2', '1234', 1000, 'PHP', 'DIRECT', 0)));
    $service = app(DurableReserveTransfer::class);
    $transfer = bindReserveForTest(readyReserveTransfer($service));
    $service->submit($transfer, $provider);
    $snapshot = $transfer->refresh()->issuance_snapshot_ciphertext;
    $snapshot['instructions']['cash']['amount'] = 20;
    $transfer->forceFill(['issuance_snapshot_ciphertext' => $snapshot])->save();
    expect(fn () => $service->check($transfer, $provider))->toThrow(RuntimeException::class, 'matching frozen issuance');
    expect($transfer->refresh()->status)->toBe('pending')->and($transfer->provider_settled_at)->toBeNull();
});

it('refuses treasury posting if the frozen platform payout account differs from the bank destination', function (): void {
    [$owner, $assignment] = setupReserveHoldFixture();
    $provider = Mockery::mock(AccountTransferProvider::class);
    $provider->shouldReceive('providerCode')->andReturn('netbank');
    $provider->shouldReceive('submit')->once()->andReturn(new AccountTransferResultData(AccountTransferStatus::Pending, 'bank-1'));
    $provider->shouldReceive('checkStatus')->once()->andReturn(new AccountTransferResultData(AccountTransferStatus::Settled, 'bank-1', new AccountTransferEvidenceData('reserve-1', '111-111-11111-1', '222-222-22222-2', '1234', 1000, 'PHP', 'DIRECT', 0)));
    $service = app(DurableReserveTransfer::class);
    $transfer = $service->prepare(reserveRequest(), 'netbank-primary', 'netbank-reserve');
    $held = bindReserveForTest(app(ReserveTransferHold::class)->hold($transfer, $owner, $assignment->provider_account_link_id));
    $service->submit($held, $provider);
    $settled = $service->check($held, $provider);
    $binding = $settled->treasury_binding_ciphertext;
    $binding['reserve_payout_account'] = '333-333-33333-3';
    $settled->forceFill(['treasury_binding_ciphertext' => $binding])->save();
    expect(fn () => app(ReserveTransferTreasuryPosting::class)->post($settled))->toThrow(RuntimeException::class, 'binding has changed');
    expect($settled->refresh()->status)->toBe('provider_settled')->and($settled->treasury_posted_at)->toBeNull();
});

it('does not bind an issuance for a suspended account', function (): void {
    [$owner, $assignment] = setupReserveHoldFixture();
    $transfer = app(DurableReserveTransfer::class)->prepare(reserveRequest(), 'netbank-primary', 'netbank-reserve');
    $held = app(ReserveTransferHold::class)->hold($transfer, $owner, $assignment->provider_account_link_id);
    DB::table('x_change_account_lifecycles')->insert([
        'owner_type' => $owner->getMorphClass(), 'owner_id' => (string) $owner->getKey(), 'status' => 'suspended',
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $estimator = Mockery::mock(EstimatePayCodeCost::class);
    $estimator->shouldReceive('handle')->andReturn(new PricingEstimateData(currency: 'PHP'));
    app()->instance(EstimatePayCodeCost::class, $estimator);
    expect(fn () => app(ReserveIssuanceSnapshot::class)->bind($held, $owner, validPayCodePayload(10)))
        ->toThrow(RuntimeException::class, 'account is suspended');
    expect($held->refresh()->issuance_snapshot_fingerprint)->toBeNull();
});

it('rejects a stale accepted price before bank submission', function (): void {
    [$owner, $assignment] = setupReserveHoldFixture();
    $transfer = app(DurableReserveTransfer::class)->prepare(reserveRequest(), 'netbank-primary', 'netbank-reserve');
    $held = app(ReserveTransferHold::class)->hold($transfer, $owner, $assignment->provider_account_link_id);
    $estimator = Mockery::mock(EstimatePayCodeCost::class);
    $estimator->shouldReceive('handle')->andReturn(new PricingEstimateData(currency: 'PHP', commercial_offering_reference: 'current', commercial_offering_version: 2, commercial_offering_snapshot_hash: 'current-hash'));
    app()->instance(EstimatePayCodeCost::class, $estimator);
    $input = validPayCodePayload(10, overrides: ['_pricing' => ['offering_reference' => 'old', 'offering_version' => 1, 'offering_snapshot_hash' => 'old-hash']]);
    expect(fn () => app(ReserveIssuanceSnapshot::class)->bind($held, $owner, $input))
        ->toThrow(CommercialPricingChanged::class);
    expect($held->refresh()->issuance_snapshot_fingerprint)->toBeNull();
});

it('rejects unavailable claim capabilities before bank submission', function (): void {
    [$owner, $assignment] = setupReserveHoldFixture();
    $transfer = app(DurableReserveTransfer::class)->prepare(reserveRequest(), 'netbank-primary', 'netbank-reserve');
    $held = app(ReserveTransferHold::class)->hold($transfer, $owner, $assignment->provider_account_link_id);
    app()->instance(InstructionCapabilityReadinessRegistry::class,
        new InstructionCapabilityReadinessRegistry([]));
    expect(fn () => app(ReserveIssuanceSnapshot::class)->bind($held, $owner, validPayCodePayload(10)))
        ->toThrow(ValidationException::class);
    expect($held->refresh()->issuance_snapshot_fingerprint)->toBeNull();
});

function postedReserveForIssuance(bool $billable = false, bool $plain = false): array
{
    [$owner, $assignment] = setupReserveHoldFixture($billable ? 10000 : 1000);
    if (! $billable) {
        config()->set('x-change.commercial.billing.mode', 'informational');
    } else {
        config()->set('x-change.commercial.enabled', true);
        config()->set('x-change.commercial.billing.mode', 'billable');
        provisionTestCommercialOfferings();
    }
    $inventory = app(TreasuryInventoryOperationContract::class);
    $inventory->registerInventory(new TreasuryInventoryData('inventory:netbank:vca-cash', 'cash_at_bank', 'PHP', 0, 'requested', 'test-source-inventory', 'resource:netbank:corporate-vca'));
    $inventory->recognize(new TreasuryInventoryRecognitionData('test-source-recognition', 'inventory:netbank:vca-cash', 'resource:netbank:corporate-vca', $billable ? 10000 : 1000, 'PHP', 'requested', 'test-source-recognition-key', externalReference: 'test-provider-deposit'));
    $provider = Mockery::mock(AccountTransferProvider::class);
    $provider->shouldReceive('providerCode')->andReturn('netbank');
    $provider->shouldReceive('submit')->once()->andReturn(new AccountTransferResultData(AccountTransferStatus::Pending, 'bank-1'));
    $provider->shouldReceive('checkStatus')->once()->andReturn(new AccountTransferResultData(AccountTransferStatus::Settled, 'bank-1', new AccountTransferEvidenceData('reserve-1', '111-111-11111-1', '222-222-22222-2', '1234', 1000, 'PHP', 'DIRECT', 0)));
    $transfers = app(DurableReserveTransfer::class);
    $held = app(ReserveTransferHold::class)->hold($transfers->prepare(reserveRequest(), 'netbank-primary', 'netbank-reserve'), $owner, $assignment->provider_account_link_id);
    $input = validPayCodePayload(10);
    if ($plain) {
        $input['inputs']['fields'] = [];
    }
    if (! $billable) {
        $estimate = Mockery::mock(EstimatePayCodeCost::class);
        $estimate->shouldReceive('handle')->andReturn(new PricingEstimateData(currency: 'PHP', total: 0, customer_charge_minor: 0));
        app()->instance(EstimatePayCodeCost::class, $estimate);
    }
    $bound = app(ReserveIssuanceSnapshot::class)->bind($held, $owner, $input);
    $transfers->submit($bound, $provider);

    return [$owner, app(ReserveTransferTreasuryPosting::class)->post($transfers->check($held, $provider))];
}

it('issues exactly one Pay Code from a posted reserve using the real issuance service', function (): void {
    [$owner, $posted] = postedReserveForIssuance();
    $action = app(IssueReserveBackedPayCode::class);
    $voucher = $action->issue($posted, $owner);
    expect($action->issue($posted, $owner)->id)->toBe($voucher->id);
    expect($posted->refresh()->status)->toBe('issued')->and($posted->voucher_id)->toBe($voucher->id);
    expect(data_get($voucher->metadata, 'treasury.pay_code_reservation.connection_reference'))->toBe('netbank-reserve')
        ->and(data_get($voucher->metadata, 'treasury.pay_code_reservation.amount_minor'))->toBe(1000);
    $source = app(VoucherBankSourceSnapshot::class)->forVoucher($voucher);
    expect($source['account_number'])->toBe('222-222-22222-2')->and($source['customer_id'])->toBe('9876');
    expect(app(TreasuryAccountBalanceReadModel::class)->connectionBalanceMinor($owner, 'netbank', 'netbank-primary', 'PHP'))->toBe(0);
});

it('rolls back issuance and the wallet projection if the issuance journal fails', function (): void {
    [$owner, $posted] = postedReserveForIssuance();
    $wallet = app(WalletAccessContract::class)->resolveForUser($owner);
    $walletBefore = (int) app(WalletAccessContract::class)->getBalance($wallet);
    $voucherCount = Voucher::query()->count();
    $journal = Mockery::mock(ExecutionJournalRecorder::class);
    $journal->shouldReceive('record')->once()->andThrow(new RuntimeException('Journal unavailable.'));
    app()->instance(ExecutionJournalRecorder::class, $journal);
    expect(fn () => app(IssueReserveBackedPayCode::class)->issue($posted, $owner))
        ->toThrow(RuntimeException::class, 'Journal unavailable.');
    expect($posted->refresh()->status)->toBe('treasury_posted')->and($posted->voucher_id)->toBeNull()
        ->and(Voucher::query()->count())->toBe($voucherCount);
    expect((int) app(WalletAccessContract::class)->getBalance($wallet->fresh()))->toBe($walletBefore);
});

it('does not issue another account owner\'s reserve', function (): void {
    [$owner, $posted] = postedReserveForIssuance();
    $other = actingAsTestUser(0);
    expect(fn () => app(IssueReserveBackedPayCode::class)->issue($posted, $other))
        ->toThrow(RuntimeException::class, 'belongs to another account');
    expect($posted->refresh()->status)->toBe('treasury_posted');
});

it('posts the held issuance fee through the real commercial service', function (): void {
    [$owner, $posted] = postedReserveForIssuance(billable: true);
    expect($posted->fee_minor)->toBeGreaterThan(0);
    $voucher = app(IssueReserveBackedPayCode::class)->issue($posted, $owner);
    $sale = CommercialSale::query()->where('acceptance_event_reference', 'pay-code-issued:voucher:'.$voucher->id)->sole();
    expect($sale->status)->toBe('posted')->and($sale->total_price_minor)->toBe($posted->fee_minor);
    expect(app(TreasuryAccountBalanceReadModel::class)->connectionBalanceMinor($owner, 'netbank', 'netbank-primary', 'PHP'))
        ->toBe(10000 - 1000 - $posted->fee_minor);
});

it('rolls back Pay Code issuance and the fee release if commercial posting fails', function (): void {
    [$owner, $posted] = postedReserveForIssuance(billable: true);
    $voucherCount = Voucher::query()->count();
    $before = app(TreasuryAccountBalanceReadModel::class)->connectionBalanceMinor($owner, 'netbank', 'netbank-primary', 'PHP');
    $sale = Mockery::mock(PayCodeCommercialSaleService::class);
    $sale->shouldReceive('post')->once()->andThrow(new RuntimeException('Commercial posting unavailable.'));
    app()->instance(PayCodeCommercialSaleService::class, $sale);
    expect(fn () => app(IssueReserveBackedPayCode::class)->issue($posted, $owner))
        ->toThrow(RuntimeException::class, 'Commercial posting unavailable.');
    expect($posted->refresh()->status)->toBe('treasury_posted')->and($posted->voucher_id)->toBeNull()
        ->and(Voucher::query()->count())->toBe($voucherCount)
        ->and(app(TreasuryAccountBalanceReadModel::class)->connectionBalanceMinor($owner, 'netbank', 'netbank-primary', 'PHP'))->toBe($before);
});

it('uses the frozen platform source when the reserve-backed Pay Code is claimed', function (): void {
    config()->set('x-change.provider_runtime.default_provider', 'netbank');
    config()->set('x-change.provider_runtime.providers.netbank.enabled', true);
    config()->set('omnipay.gateways.netbank.options.rails.INSTAPAY', ['enabled' => true, 'min_amount' => 1, 'max_amount' => 5000000, 'fee' => 0]);
    [$owner, $posted] = postedReserveForIssuance(plain: true);
    config()->set('omnipay.gateways.netbank.options.sourceAccountNumber', '999-999-99999-9');
    config()->set('omnipay.gateways.netbank.options.senderCustomerId', 'different');
    $voucher = app(IssueReserveBackedPayCode::class)->issue($posted, $owner);
    $provider = fakePayoutProvider()->willReturnSuccessfulResult(transactionId: 'RESERVE-PAYOUT-1', provider: 'netbank');
    $result = app(SubmitPayCodeClaim::class)->handle($voucher, [
        'mobile' => '09173011987', 'recipient_country' => 'PH',
        'bank_account' => ['bank_code' => 'GXCHPHM2XXX', 'account_number' => '09173011987'],
        '_meta' => ['idempotency_key' => 'reserve-claim-1'],
    ]);
    expect($result->status)->toBe('succeeded')
        ->and($provider->lastRequest?->source_account?->account_number)->toBe('222-222-22222-2')
        ->and($provider->lastRequest?->source_account?->customer_id)->toBe('9876');
    expect(TreasuryInventory::query()->where('inventory_reference', 'inventory:reserve')->value('balance_minor'))->toBe(0);
    expect(ExecutionJournalEntry::query()->where('event_type', 'reserve_transfer.pay_code_issued')->count())->toBe(1)
        ->and(ExecutionJournalEntry::query()->where('event_type', 'pay_code.disbursement.settled')->count())->toBe(1);
});

it('refuses reserve-backed issuance inside an existing transaction', function (): void {
    [$owner, $posted] = postedReserveForIssuance();
    DB::transaction(function () use ($posted, $owner): void {
        expect(fn () => app(IssueReserveBackedPayCode::class)->issue($posted, $owner))
            ->toThrow(RuntimeException::class, 'must own its database transaction');
    });
    expect($posted->refresh()->status)->toBe('treasury_posted');
});

it('leaves another owner client funds unchanged during reserve-backed issuance', function (): void {
    [$owner, $posted] = postedReserveForIssuance();
    $other = actingAsTestUser(0);
    fundTestUserClientFunds($other, 700);
    $balances = app(TreasuryAccountBalanceReadModel::class);
    expect($balances->connectionBalanceMinor($other, 'netbank', 'netbank-primary', 'PHP'))->toBe(700);
    app(IssueReserveBackedPayCode::class)->issue($posted, $owner);
    expect($balances->connectionBalanceMinor($other, 'netbank', 'netbank-primary', 'PHP'))->toBe(700);
});

it('settles fixture funding and carries it through reserve, issuance and claim exactly once', function (): void {
    config()->set('x-change.provider_runtime.default_provider', 'netbank');
    config()->set('x-change.provider_runtime.providers.netbank.enabled', true);
    config()->set('omnipay.gateways.netbank.options.rails.INSTAPAY', ['enabled' => true, 'min_amount' => 1, 'max_amount' => 5000000, 'fee' => 0]);
    [$owner, $assignment] = setupReserveHoldFixture(0);
    config()->set('x-change.commercial.enabled', true);
    config()->set('x-change.commercial.billing.mode', 'informational');
    $wallet = $owner->wallet()->where('slug', 'platform')->firstOrFail();
    $address = '543217654321';
    $observation = ProviderFundingObservation::query()->create([
        'observation_key' => hash('sha256', 'lifecycle-funding'), 'provider_code' => 'netbank',
        'provider_transaction_id' => 'lifecycle-funding', 'provider_operation_id' => 'lifecycle-operation', 'request_id' => 'lifecycle-request',
        'funding_address' => $address, 'provider_account_reference' => 'fixture-personal',
        'gross_amount_minor' => 1500, 'fee_amount_minor' => 0, 'net_amount_minor' => 1500, 'currency' => 'PHP',
        'provider_status' => 'settled', 'occurred_at' => now()->subMinute(), 'settled_at' => now(),
        'verification_source' => 'fixture', 'payload_hash' => hash('sha256', 'lifecycle-evidence'),
        'metadata' => ['destination_verified' => true, 'exact_vca_verified' => true],
    ]);
    $snapshot = [
        'provider' => 'netbank', 'mode' => 'dedicated', 'bankAccountNumber' => '111-111-11111-1', 'routingAlias' => '54321',
        'fingerprint' => hash('sha256', 'fixture-destination'), 'metadata' => [
            'assigned_bank_account_link_id' => $assignment->provider_account_link_id,
            'treasury_connection_reference' => 'netbank-primary', 'treasury_inventory_reference' => 'inventory:netbank:vca-cash',
            'treasury_resource_reference' => 'resource:netbank:corporate-vca',
        ],
    ];
    $intent = FundingIntent::query()->create([
        'account_reference' => 'wallet:'.$wallet->uuid, 'provider_code' => 'netbank', 'expected_amount_minor' => 1500,
        'currency' => 'PHP', 'status' => FundingIntentStatus::Verified, 'version' => 4,
        'idempotency_key_hash' => hash('sha256', 'lifecycle-funding-key'), 'idempotency_fingerprint' => hash('sha256', 'lifecycle-fingerprint'),
        'created_by_type' => 'test', 'created_by_id' => 'operator', 'provider_reference' => 'lifecycle-vca',
        'provider_request_id' => 'lifecycle-request', 'funding_address_ciphertext' => $address, 'funding_address_hash' => hash('sha256', $address),
        'matched_observation_id' => $observation->id, 'provider_transaction_id' => 'lifecycle-funding',
        'destination_snapshot_ciphertext' => $snapshot, 'instructions_created_at' => now()->subMinutes(2),
        'evidence_received_at' => now()->subMinute(), 'verified_at' => now(), 'expires_at' => now()->addMinutes(30), 'metadata' => ['source' => 'test'],
    ]);
    $settler = app(SettleVerifiedFundingIntent::class);
    $settledFunding = $settler->handle($intent);
    expect($settler->handle($intent->refresh())->id)->toBe($settledFunding->id);
    $balances = app(TreasuryAccountBalanceReadModel::class);
    expect($balances->connectionBalanceMinor($owner, 'netbank', 'netbank-primary', 'PHP'))->toBe(1500);
    config()->set('payment-gateway.netbank.funding.corporate_account_name', 'Platform Reserve');
    config()->set('x-change.reserve_transfer.destination_bank_code', 'NETBANK');
    $estimate = Mockery::mock(EstimatePayCodeCost::class);
    $estimate->shouldReceive('handle')->andReturn(new PricingEstimateData(currency: 'PHP', total: 0, customer_charge_minor: 0));
    app()->instance(EstimatePayCodeCost::class, $estimate);
    $input = validPayCodePayload(10);
    $input['inputs']['fields'] = [];
    $bound = app(PrepareReserveBackedIssuance::class)->prepareSelected($owner, $assignment->provider_account_link_id, 1000, 'PHP', 'lifecycle-issuance', $input);
    $provider = Mockery::mock(AccountTransferProvider::class);
    $provider->shouldReceive('providerCode')->andReturn('netbank');
    $provider->shouldReceive('submit')->once()->andReturn(new AccountTransferResultData(AccountTransferStatus::Pending, 'bank-1'));
    $provider->shouldReceive('checkStatus')->twice()->andReturn(
        new AccountTransferResultData(AccountTransferStatus::Pending, 'bank-1'),
        new AccountTransferResultData(AccountTransferStatus::Settled, 'bank-1', new AccountTransferEvidenceData($bound->reference, '111-111-11111-1', '222-222-22222-2', '1234', 1000, 'PHP', 'DIRECT', 0)),
    );
    app()->instance(get_class($provider), $provider);
    config()->set('x-change.reserve_transfer.provider_class', get_class($provider));
    config()->set('x-change.reserve_transfer.enabled', true);
    config()->set('x-change.reserve_transfer.live_submission_enabled', true);
    $job = new AdvanceReserveIssuanceJob($bound->id);
    $advance = app(AdvanceReserveBackedIssuance::class);
    $job->handle($advance);
    expect($bound->refresh()->status)->toBe('pending');
    $job->handle($advance);
    expect($bound->refresh()->status)->toBe('pending');
    $job->handle($advance);
    $completed = $bound->refresh();
    expect($completed->status)->toBe('issued');
    $voucher = Voucher::query()->findOrFail($completed->voucher_id);
    $job->handle($advance);
    expect($bound->refresh()->voucher_id)->toBe($voucher->id);
    $payout = fakePayoutProvider()->willReturnSuccessfulResult(transactionId: 'lifecycle-payout', provider: 'netbank');
    $result = app(SubmitPayCodeClaim::class)->handle($voucher, [
        'mobile' => '09173011987', 'recipient_country' => 'PH',
        'bank_account' => ['bank_code' => 'GXCHPHM2XXX', 'account_number' => '09173011987'],
        '_meta' => ['idempotency_key' => 'lifecycle-claim'],
    ]);
    expect($result->status)->toBe('succeeded')->and($payout->lastRequest?->source_account?->account_number)->toBe('222-222-22222-2')
        ->and($balances->connectionBalanceMinor($owner, 'netbank', 'netbank-primary', 'PHP'))->toBe(500)
        ->and(TreasuryInventory::query()->where('inventory_reference', 'inventory:netbank:vca-cash')->value('balance_minor'))->toBe(500)
        ->and(TreasuryInventory::query()->where('inventory_reference', 'inventory:reserve')->value('balance_minor'))->toBe(0);
});

it('does not resubmit an uncertain transfer during advancement', function (): void {
    $transfers = app(DurableReserveTransfer::class);
    $transfer = bindReserveForTest(readyReserveTransfer($transfers));
    $owner = $transfer->owner;
    $provider = Mockery::mock(AccountTransferProvider::class);
    $provider->shouldReceive('providerCode')->andReturn('netbank');
    $provider->shouldReceive('submit')->once()->andReturn(new AccountTransferResultData(AccountTransferStatus::Uncertain));
    $provider->shouldNotReceive('checkStatus');
    $advance = app(AdvanceReserveBackedIssuance::class);
    expect($advance->advance($transfer, $owner, $provider)->status)->toBe('uncertain')
        ->and($advance->advance($transfer, $owner, $provider)->status)->toBe('uncertain');
});

it('returns an issued transfer on a stale treasury posting retry', function (): void {
    [$owner, $posted] = postedReserveForIssuance();
    $stale = $posted->fresh();
    $voucher = app(IssueReserveBackedPayCode::class)->issue($posted, $owner);
    $replayed = app(ReserveTransferTreasuryPosting::class)->post($stale);
    expect($replayed->status)->toBe('issued')->and($replayed->voucher_id)->toBe($voucher->id);
});

it('reconciles a pending reserve-backed claim without paying twice', function (): void {
    config()->set('x-change.provider_runtime.default_provider', 'netbank');
    config()->set('x-change.provider_runtime.providers.netbank.enabled', true);
    config()->set('omnipay.gateways.netbank.options.rails.INSTAPAY', ['enabled' => true, 'min_amount' => 1, 'max_amount' => 5000000, 'fee' => 0]);
    [$owner, $posted] = postedReserveForIssuance(plain: true);
    $voucher = app(IssueReserveBackedPayCode::class)->issue($posted, $owner);
    $provider = fakePayoutProvider()->willReturnPendingResult(transactionId: 'reserve-pending', provider: 'netbank')
        ->willResolveCheckStatusAsSuccessful(transactionId: 'reserve-pending', provider: 'netbank');
    $result = app(SubmitPayCodeClaim::class)->handle($voucher, [
        'mobile' => '09173011987', 'recipient_country' => 'PH',
        'bank_account' => ['bank_code' => 'GXCHPHM2XXX', 'account_number' => '09173011987'],
        '_meta' => ['idempotency_key' => 'reserve-pending-claim'],
    ]);
    expect($result->status)->toBe('pending_review')
        ->and(TreasuryInventory::query()->where('inventory_reference', 'inventory:reserve')->value('balance_minor'))->toBe(1000);
    $this->artisan('xchange:reconcile:pending', ['--json' => true])->assertSuccessful();
    $this->artisan('xchange:reconcile:pending', ['--json' => true])->assertSuccessful();
    expect(data_get($voucher->refresh()->metadata, 'treasury.pay_code_reservation.status'))->toBe('settled')
        ->and(TreasuryInventory::query()->where('inventory_reference', 'inventory:reserve')->value('balance_minor'))->toBe(0)
        ->and($provider->disburseCallCount)->toBe(1)
        ->and(ExecutionJournalEntry::query()->where('event_type', 'pay_code.disbursement.settled')->where('subject_id', (string) $voucher->id)->count())->toBe(1);
});

it('never submits a bank transfer inside an enclosing transaction', function (): void {
    $transfers = app(DurableReserveTransfer::class);
    $held = bindReserveForTest(readyReserveTransfer($transfers));
    $provider = Mockery::mock(AccountTransferProvider::class);
    $provider->shouldNotReceive('submit');
    DB::transaction(function () use ($transfers, $held, $provider): void {
        expect(fn () => $transfers->submit($held, $provider))->toThrow(RuntimeException::class, 'must own its database transaction');
    });
    expect($held->refresh()->status)->toBe('held')->and($held->submitted_at)->toBeNull();
});

it('atomically prepares the held transfer and issuance and replays it once', function (): void {
    [$owner, $assignment] = setupReserveHoldFixture();
    $estimate = Mockery::mock(EstimatePayCodeCost::class);
    $estimate->shouldReceive('handle')->andReturn(new PricingEstimateData(currency: 'PHP', total: 0, customer_charge_minor: 0));
    app()->instance(EstimatePayCodeCost::class, $estimate);
    $prepare = app(PrepareReserveBackedIssuance::class);
    $transfer = $prepare->prepare(reserveRequest(), 'netbank-primary', 'netbank-reserve', $owner, $assignment->provider_account_link_id, validPayCodePayload(10));
    $replayed = $prepare->prepare(reserveRequest(), 'netbank-primary', 'netbank-reserve', $owner, $assignment->provider_account_link_id, validPayCodePayload(10));
    expect($transfer->status)->toBe('held')->and($transfer->issuance_snapshot_fingerprint)->not->toBeNull()
        ->and($replayed->id)->toBe($transfer->id)->and(ReserveTransfer::query()->count())->toBe(1);
});

it('rolls back a newly prepared transfer and principal hold when issuance instructions fail', function (): void {
    [$owner, $assignment] = setupReserveHoldFixture();
    $prepare = app(PrepareReserveBackedIssuance::class);
    expect(fn () => $prepare->prepare(reserveRequest(), 'netbank-primary', 'netbank-reserve', $owner, $assignment->provider_account_link_id, validPayCodePayload(11)))
        ->toThrow(RuntimeException::class, 'issuance does not match');
    expect(ReserveTransfer::query()->count())->toBe(0);
    expect(app(TreasuryAccountBalanceReadModel::class)->connectionBalanceMinor($owner, 'netbank', 'netbank-primary', 'PHP'))->toBe(1000);
});

it('rolls back the principal hold if the selected account cannot cover the issuance fee', function (): void {
    [$owner, $assignment] = setupReserveHoldFixture();
    $estimate = Mockery::mock(EstimatePayCodeCost::class);
    $estimate->shouldReceive('handle')->andReturn(new PricingEstimateData(currency: 'PHP', total: 5, customer_charge_minor: 500));
    app()->instance(EstimatePayCodeCost::class, $estimate);
    $prepare = app(PrepareReserveBackedIssuance::class);
    expect(fn () => $prepare->prepare(reserveRequest(), 'netbank-primary', 'netbank-reserve', $owner, $assignment->provider_account_link_id, validPayCodePayload(10)))
        ->toThrow(BalanceIsEmpty::class);
    expect(ReserveTransfer::query()->count())->toBe(0)
        ->and(app(TreasuryAccountBalanceReadModel::class)->connectionBalanceMinor($owner, 'netbank', 'netbank-primary', 'PHP'))->toBe(1000);
});

it('marks an interrupted submission uncertain without repeating the transfer', function (): void {
    $service = app(DurableReserveTransfer::class);
    $transfer = bindReserveForTest(readyReserveTransfer($service));
    $transfer->forceFill(['status' => 'submitting', 'submitted_at' => now()->subMinutes(11)])->save();
    $result = $service->markInterruptedUncertain($transfer);
    expect($result->status)->toBe('uncertain')
        ->and($service->markInterruptedUncertain($transfer)->status)->toBe('uncertain')
        ->and($result->hold_operation_reference)->not->toBeNull()
        ->and(ExecutionJournalEntry::query()->where('event_type', 'reserve_transfer.submission_uncertain')->count())->toBe(1);
});

it('does not mark a recent submission uncertain while it may still be in progress', function (): void {
    $service = app(DurableReserveTransfer::class);
    $transfer = bindReserveForTest(readyReserveTransfer($service));
    $transfer->forceFill(['status' => 'submitting', 'submitted_at' => now()])->save();
    expect(fn () => $service->markInterruptedUncertain($transfer))->toThrow(RuntimeException::class, 'may still be in progress');
    expect($transfer->refresh()->status)->toBe('submitting');
});

it('allows only the transfer owner to recover an interrupted submission', function (): void {
    $service = app(DurableReserveTransfer::class);
    $transfer = bindReserveForTest(readyReserveTransfer($service));
    $owner = $transfer->owner;
    $other = actingAsTestUser(0);
    $transfer->forceFill(['status' => 'submitting', 'submitted_at' => now()->subMinutes(11)])->save();
    $advance = app(AdvanceReserveBackedIssuance::class);
    expect(fn () => $advance->recoverInterrupted($transfer, $other))->toThrow(RuntimeException::class, 'unavailable');
    expect($transfer->refresh()->status)->toBe('submitting')
        ->and($advance->recoverInterrupted($transfer, $owner)->status)->toBe('uncertain');
});

it('keeps the interrupted submission unchanged when its recovery journal fails', function (): void {
    $service = app(DurableReserveTransfer::class);
    $transfer = bindReserveForTest(readyReserveTransfer($service));
    $transfer->forceFill(['status' => 'submitting', 'submitted_at' => now()->subMinutes(11)])->save();
    $recorder = Mockery::mock(ExecutionJournalRecorder::class);
    $recorder->shouldReceive('record')->once()->andThrow(new RuntimeException('Journal unavailable.'));
    app()->instance(ExecutionJournalRecorder::class, $recorder);
    expect(fn () => $service->markInterruptedUncertain($transfer))->toThrow(RuntimeException::class, 'Journal unavailable.');
    expect($transfer->refresh()->status)->toBe('submitting');
});

it('exposes only safe reserve issuance status to its owner', function (): void {
    $transfer = bindReserveForTest(readyReserveTransfer(app(DurableReserveTransfer::class)));
    $response = $this->getJson(route('x-change.cockpit.quick-generate.reserve-issuances.show', ['reference' => $transfer->reference]));
    $response->assertOk()->assertJsonPath('status', 'processing')->assertJsonPath('amount_minor', 1000)
        ->assertJsonPath('pay_code', null)->assertHeader('Cache-Control', 'no-store, private');
    expect($response->getContent())->not->toContain('stable-key')->not->toContain('111-111-11111-1');
    actingAsTestUser(0);
    $this->getJson(route('x-change.cockpit.quick-generate.reserve-issuances.show', ['reference' => $transfer->reference]))->assertNotFound();
});

it('shows review instead of claiming issuance for uncertain or inconsistent transfers', function (): void {
    $transfer = bindReserveForTest(readyReserveTransfer(app(DurableReserveTransfer::class)));
    $url = route('x-change.cockpit.quick-generate.reserve-issuances.show', ['reference' => $transfer->reference]);
    $transfer->forceFill(['status' => 'uncertain'])->save();
    $this->getJson($url)->assertOk()->assertJsonPath('status', 'needs_review')->assertJsonPath('pay_code', null);
    $transfer->forceFill(['status' => 'issued', 'voucher_id' => null])->save();
    $this->getJson($url)->assertOk()->assertJsonPath('status', 'needs_review')->assertJsonPath('pay_code', null);
});

it('shows the issued Pay Code after reserve-backed issuance completes', function (): void {
    [$owner, $posted] = postedReserveForIssuance();
    $voucher = app(IssueReserveBackedPayCode::class)->issue($posted, $owner);
    $this->getJson(route('x-change.cockpit.quick-generate.reserve-issuances.show', ['reference' => $posted->reference]))
        ->assertOk()->assertJsonPath('status', 'issued')->assertJsonPath('pay_code', $voucher->code);
});

it('records a late provider response for the same interrupted attempt without resending', function (): void {
    $service = app(DurableReserveTransfer::class);
    $transfer = bindReserveForTest(readyReserveTransfer($service));
    $provider = Mockery::mock(AccountTransferProvider::class);
    $provider->shouldReceive('providerCode')->andReturn('netbank');
    $provider->shouldReceive('submit')->once()->andReturnUsing(function () use ($service, $transfer): AccountTransferResultData {
        $this->travel(11)->minutes();
        expect($service->markInterruptedUncertain($transfer)->status)->toBe('uncertain');

        return new AccountTransferResultData(AccountTransferStatus::Pending, 'late-bank-reference');
    });
    $result = $service->submit($transfer, $provider);
    expect($result->status)->toBe('pending')->and($result->provider_transaction_id)->toBe('late-bank-reference');
});

it('does not apply an old provider response to a newer submission attempt', function (): void {
    $service = app(DurableReserveTransfer::class);
    $transfer = bindReserveForTest(readyReserveTransfer($service));
    $provider = Mockery::mock(AccountTransferProvider::class);
    $provider->shouldReceive('providerCode')->andReturn('netbank');
    $provider->shouldReceive('submit')->once()->andReturnUsing(function () use ($transfer): AccountTransferResultData {
        $transfer->fresh()->forceFill(['status' => 'submitting', 'submitted_at' => now()->addMinute()])->save();

        return new AccountTransferResultData(AccountTransferStatus::Pending, 'old-bank-reference');
    });
    $result = $service->submit($transfer, $provider);
    expect($result->status)->toBe('submitting')->and($result->provider_transaction_id)->toBeNull();
});

it('builds a stable reserve request from the selected account and platform configuration', function (): void {
    [$owner, $assignment] = setupReserveHoldFixture();
    config()->set('payment-gateway.netbank.funding.corporate_account_name', 'Platform Reserve');
    config()->set('x-change.reserve_transfer.destination_bank_code', 'NETBANK');
    $builder = app(BuildReserveTransferRequest::class);
    $built = $builder->build($owner, $assignment->provider_account_link_id, 1000, 'PHP', 'issuance-key');
    $again = $builder->build($owner, $assignment->provider_account_link_id, 1000, 'PHP', 'issuance-key');
    expect($built['request']->toArray())->toBe($again['request']->toArray())
        ->and(strlen($built['request']->idempotencyKey))->toBe(36)
        ->and($builder->build($owner, $assignment->provider_account_link_id, 1000, 'PHP', 'other-issuance-key')['request']->idempotencyKey)->not->toBe($built['request']->idempotencyKey)
        ->and($built['request']->sourceAccountNumber)->toBe('111-111-11111-1')
        ->and($built['request']->sourceCustomerId)->toBe('1234')
        ->and($built['request']->destinationAccountNumber)->toBe('222-222-22222-2')
        ->and($built['request']->destinationName)->toBe('Platform Reserve')
        ->and($built['source_connection'])->toBe('netbank-primary')
        ->and($built['reserve_connection'])->toBe('netbank-reserve');
});

it('refuses a reserve request when platform routing details are missing', function (): void {
    [$owner, $assignment] = setupReserveHoldFixture();
    config()->set('x-change.reserve_transfer.destination_bank_code', null);
    expect(fn () => app(BuildReserveTransferRequest::class)->build($owner, $assignment->provider_account_link_id, 1000, 'PHP', 'issuance-key'))
        ->toThrow(RuntimeException::class, 'details are incomplete');
    expect(ReserveTransfer::query()->count())->toBe(0);
});

it('refuses to build a reserve request for a different account owner', function (): void {
    [$owner, $assignment] = setupReserveHoldFixture();
    $other = actingAsTestUser(0);
    expect(fn () => app(BuildReserveTransferRequest::class)->build($other, $assignment->provider_account_link_id, 1000, 'PHP', 'issuance-key'))
        ->toThrow(FundingDestinationUnavailable::class);
});

it('prepares one frozen issuance from the selected bank account without contacting the bank', function (): void {
    [$owner, $assignment] = setupReserveHoldFixture();
    config()->set('payment-gateway.netbank.funding.corporate_account_name', 'Platform Reserve');
    config()->set('x-change.reserve_transfer.destination_bank_code', 'NETBANK');
    $estimate = Mockery::mock(EstimatePayCodeCost::class);
    $estimate->shouldReceive('handle')->andReturn(new PricingEstimateData(currency: 'PHP', total: 0, customer_charge_minor: 0));
    app()->instance(EstimatePayCodeCost::class, $estimate);
    $prepare = app(PrepareReserveBackedIssuance::class);
    $transfer = $prepare->prepareSelected($owner, $assignment->provider_account_link_id, 1000, 'PHP', 'issuance-key', validPayCodePayload(10));
    $again = $prepare->prepareSelected($owner, $assignment->provider_account_link_id, 1000, 'PHP', 'issuance-key', validPayCodePayload(10));
    expect($transfer->status)->toBe('held')->and($transfer->bank_account_link_id)->toBe($assignment->provider_account_link_id)
        ->and($transfer->request_ciphertext['sourceAccountNumber'])->toBe('111-111-11111-1')
        ->and($transfer->request_ciphertext['destinationAccountNumber'])->toBe('222-222-22222-2')
        ->and($again->id)->toBe($transfer->id)->and(ReserveTransfer::query()->count())->toBe(1);
});

it('rejects a changed issuance amount on the same selected-account key', function (): void {
    [$owner, $assignment] = setupReserveHoldFixture(2000);
    config()->set('payment-gateway.netbank.funding.corporate_account_name', 'Platform Reserve');
    config()->set('x-change.reserve_transfer.destination_bank_code', 'NETBANK');
    $estimate = Mockery::mock(EstimatePayCodeCost::class);
    $estimate->shouldReceive('handle')->andReturn(new PricingEstimateData(currency: 'PHP', total: 0, customer_charge_minor: 0));
    app()->instance(EstimatePayCodeCost::class, $estimate);
    $prepare = app(PrepareReserveBackedIssuance::class);
    $transfer = $prepare->prepareSelected($owner, $assignment->provider_account_link_id, 1000, 'PHP', 'issuance-key', validPayCodePayload(10));
    expect(fn () => $prepare->prepareSelected($owner, $assignment->provider_account_link_id, 1100, 'PHP', 'issuance-key', validPayCodePayload(11)))
        ->toThrow(RuntimeException::class, 'bound to different instructions');
    expect($transfer->refresh()->status)->toBe('held')->and(ReserveTransfer::query()->count())->toBe(1)
        ->and(app(TreasuryAccountBalanceReadModel::class)->connectionBalanceMinor($owner, 'netbank', 'netbank-primary', 'PHP'))->toBe(1000);
});

it('rejects changed voucher instructions on the same selected-account key', function (): void {
    [$owner, $assignment] = setupReserveHoldFixture();
    config()->set('payment-gateway.netbank.funding.corporate_account_name', 'Platform Reserve');
    config()->set('x-change.reserve_transfer.destination_bank_code', 'NETBANK');
    $estimate = Mockery::mock(EstimatePayCodeCost::class);
    $estimate->shouldReceive('handle')->andReturn(new PricingEstimateData(currency: 'PHP', total: 0, customer_charge_minor: 0));
    app()->instance(EstimatePayCodeCost::class, $estimate);
    $prepare = app(PrepareReserveBackedIssuance::class);
    $payload = validPayCodePayload(10);
    $transfer = $prepare->prepareSelected($owner, $assignment->provider_account_link_id, 1000, 'PHP', 'issuance-key', $payload);
    data_set($payload, 'rider.message', 'Changed after submission');
    expect(fn () => $prepare->prepareSelected($owner, $assignment->provider_account_link_id, 1000, 'PHP', 'issuance-key', $payload))
        ->toThrow(RuntimeException::class, 'different instructions');
    expect($transfer->refresh()->status)->toBe('held')->and(ReserveTransfer::query()->count())->toBe(1);
});

it('lists only the signed-in owner reserve issuances for refresh recovery', function (): void {
    $transfer = bindReserveForTest(readyReserveTransfer(app(DurableReserveTransfer::class)));
    $url = route('x-change.cockpit.quick-generate.reserve-issuances.index');
    $this->getJson($url)->assertOk()->assertJsonCount(1, 'issuances')->assertJsonPath('issuances.0.reference', $transfer->reference);
    actingAsTestUser(0);
    $this->getJson($url)->assertOk()->assertJsonCount(0, 'issuances');
});

it('recovers a stalled submission only for its owner without calling the bank', function (): void {
    $transfer = bindReserveForTest(readyReserveTransfer(app(DurableReserveTransfer::class)));
    $owner = $transfer->owner;
    $transfer->forceFill(['status' => 'submitting', 'submitted_at' => now()->subMinutes(11)])->save();
    $url = route('x-change.cockpit.quick-generate.reserve-issuances.recover', ['reference' => $transfer->reference]);
    actingAsTestUser(0);
    $this->postJson($url)->assertNotFound();
    expect($transfer->refresh()->status)->toBe('submitting');
    $this->actingAs($owner)->postJson($url)->assertOk()->assertJsonPath('status', 'needs_review');
    expect($transfer->refresh()->status)->toBe('uncertain');
});

it('reports a conflict when recovery is requested before the submission timeout', function (): void {
    $transfer = bindReserveForTest(readyReserveTransfer(app(DurableReserveTransfer::class)));
    $transfer->forceFill(['status' => 'submitting', 'submitted_at' => now()])->save();
    $this->postJson(route('x-change.cockpit.quick-generate.reserve-issuances.recover', ['reference' => $transfer->reference]))
        ->assertStatus(409);
    expect($transfer->refresh()->status)->toBe('submitting');
});

it('requires sign-in to list or recover reserve issuances', function (): void {
    auth()->logout();
    $this->getJson(route('x-change.cockpit.quick-generate.reserve-issuances.index'))->assertUnauthorized();
    $this->postJson(route('x-change.cockpit.quick-generate.reserve-issuances.recover', ['reference' => 'rsv-example']))->assertUnauthorized();
});

it('replays a pending selected-account issuance without using changed account settings or resubmitting', function (): void {
    [$owner, $assignment] = setupReserveHoldFixture();
    config()->set('payment-gateway.netbank.funding.corporate_account_name', 'Platform Reserve');
    config()->set('x-change.reserve_transfer.destination_bank_code', 'NETBANK');
    $estimate = Mockery::mock(EstimatePayCodeCost::class);
    $estimate->shouldReceive('handle')->once()->andReturn(new PricingEstimateData(currency: 'PHP', total: 0, customer_charge_minor: 0));
    app()->instance(EstimatePayCodeCost::class, $estimate);
    $prepare = app(PrepareReserveBackedIssuance::class);
    $payload = validPayCodePayload(10);
    $transfer = $prepare->prepareSelected($owner, $assignment->provider_account_link_id, 1000, 'PHP', 'issuance-key', $payload);
    $provider = Mockery::mock(AccountTransferProvider::class);
    $provider->shouldReceive('providerCode')->andReturn('netbank');
    $provider->shouldReceive('submit')->once()->andReturn(new AccountTransferResultData(AccountTransferStatus::Pending, 'bank-1'));
    app(DurableReserveTransfer::class)->submit($transfer, $provider);
    config()->set('x-change.reserve_transfer.destination_bank_code', null);
    $replayed = $prepare->prepareSelected($owner, $assignment->provider_account_link_id, 1000, 'PHP', 'issuance-key', $payload);
    expect($replayed->id)->toBe($transfer->id)->and($replayed->status)->toBe('pending')
        ->and($replayed->provider_transaction_id)->toBe('bank-1')->and(ReserveTransfer::query()->count())->toBe(1);
});

it('does not dispatch reserve issuance work while the feature is disabled', function (): void {
    Bus::fake();
    readyReserveTransfer(app(DurableReserveTransfer::class));
    config()->set('x-change.reserve_transfer.enabled', false);
    $this->artisan('xchange:reserve:dispatch-issuances')->assertSuccessful();
    Bus::assertNotDispatched(AdvanceReserveIssuanceJob::class);
});

it('dispatches pending reserve work but never automatically retries an uncertain transfer', function (): void {
    Bus::fake();
    $transfer = bindReserveForTest(readyReserveTransfer(app(DurableReserveTransfer::class)));
    config()->set('x-change.reserve_transfer.enabled', true);
    $this->artisan('xchange:reserve:dispatch-issuances')->assertSuccessful();
    Bus::assertDispatched(AdvanceReserveIssuanceJob::class, fn ($job) => $job->transferId === $transfer->id);
    Bus::fake();
    $transfer->forceFill(['status' => 'uncertain'])->save();
    $this->artisan('xchange:reserve:dispatch-issuances')->assertSuccessful();
    Bus::assertNotDispatched(AdvanceReserveIssuanceJob::class);
});

it('advances a reserve issuance job using the registered provider only when enabled', function (): void {
    $transfer = bindReserveForTest(readyReserveTransfer(app(DurableReserveTransfer::class)));
    $provider = Mockery::mock(AccountTransferProvider::class);
    $provider->shouldReceive('providerCode')->andReturn('netbank');
    $provider->shouldReceive('submit')->once()->andReturn(new AccountTransferResultData(AccountTransferStatus::Pending, 'job-bank-1'));
    app()->instance(get_class($provider), $provider);
    config()->set('x-change.reserve_transfer.provider_class', null);
    config()->set('emi.account_transfer_providers.netbank', get_class($provider));
    $job = new AdvanceReserveIssuanceJob($transfer->id);
    config()->set('x-change.reserve_transfer.enabled', false);
    $job->handle(app(AdvanceReserveBackedIssuance::class));
    expect($transfer->refresh()->status)->toBe('held');
    config()->set('x-change.reserve_transfer.enabled', true);
    $job->handle(app(AdvanceReserveBackedIssuance::class));
    expect($transfer->refresh()->status)->toBe('held');
    config()->set('x-change.reserve_transfer.live_submission_enabled', true);
    $job->handle(app(AdvanceReserveBackedIssuance::class));
    expect($transfer->refresh()->status)->toBe('pending')->and($transfer->provider_transaction_id)->toBe('job-bank-1');
});

it('hands a dedicated quick-generate issuance to the reserve flow without contacting the bank', function (): void {
    [$owner, $assignment] = setupReserveHoldFixture();
    FundingDestinationPreference::query()->create([
        'owner_type' => $owner->getMorphClass(), 'owner_id' => $owner->id, 'provider_code' => 'netbank',
        'mode' => 'dedicated', 'provider_account_link_id' => $assignment->provider_account_link_id,
    ]);
    config()->set('x-change.reserve_transfer.enabled', true);
    config()->set('payment-gateway.netbank.funding.corporate_account_name', 'Platform Reserve');
    config()->set('x-change.reserve_transfer.destination_bank_code', 'NETBANK');
    config()->set('x-change.issuance_funding.on_demand.enabled', false);
    config()->set('x-change.provider_runtime.default_provider', 'netbank');
    $estimate = Mockery::mock(EstimatePayCodeCost::class);
    $estimate->shouldReceive('handle')->andReturn(new PricingEstimateData(currency: 'PHP', total: 0, customer_charge_minor: 0));
    app()->instance(EstimatePayCodeCost::class, $estimate);
    $payload = validPayCodePayload(10);
    $response = $this->withHeader('Idempotency-Key', 'quick-reserve-fixture')->postJson(route('x-change.cockpit.quick-generate.store'), $payload);
    $response->assertStatus(202)->assertJsonPath('schema', 'x-change.cockpit.reserve-issuance.v1')->assertJsonPath('status', 'processing');
    $this->withHeader('Idempotency-Key', 'quick-reserve-fixture')->postJson(route('x-change.cockpit.quick-generate.store'), $payload)
        ->assertStatus(202)->assertJsonPath('reference', $response->json('reference'));
    expect(ReserveTransfer::query()->count())->toBe(1)->and(ReserveTransfer::query()->sole()->status)->toBe('held');
});

it('does not bypass reserve-backed issuance for a dedicated account batch', function (): void {
    [$owner, $assignment] = setupReserveHoldFixture(2000);
    FundingDestinationPreference::query()->create([
        'owner_type' => $owner->getMorphClass(), 'owner_id' => $owner->id, 'provider_code' => 'netbank',
        'mode' => 'dedicated', 'provider_account_link_id' => $assignment->provider_account_link_id,
    ]);
    config()->set('x-change.reserve_transfer.enabled', true);
    config()->set('x-change.issuance_funding.on_demand.enabled', false);
    config()->set('x-change.provider_runtime.default_provider', 'netbank');
    $estimate = Mockery::mock(EstimatePayCodeCost::class);
    $estimate->shouldReceive('handle')->andReturn(new PricingEstimateData(currency: 'PHP', total: 0, customer_charge_minor: 0));
    app()->instance(EstimatePayCodeCost::class, $estimate);
    $payload = validPayCodePayload(10);
    $payload['count'] = 2;
    $this->withHeader('Idempotency-Key', 'quick-reserve-batch')->postJson(route('x-change.cockpit.quick-generate.store'), $payload)
        ->assertUnprocessable()->assertJsonValidationErrors('count');
    expect(ReserveTransfer::query()->count())->toBe(0);
});

it('requires dedicated account funding before reserve-backed on-demand issuance', function (): void {
    [$owner, $assignment] = setupReserveHoldFixture(0);
    FundingDestinationPreference::query()->create([
        'owner_type' => $owner->getMorphClass(), 'owner_id' => $owner->id, 'provider_code' => 'netbank',
        'mode' => 'dedicated', 'provider_account_link_id' => $assignment->provider_account_link_id,
    ]);
    config()->set('x-change.reserve_transfer.enabled', true);
    config()->set('x-change.issuance_funding.on_demand.enabled', true);
    config()->set('x-change.issuance_funding.on_demand.basis', 'full_amount');
    config()->set('x-change.provider_runtime.default_provider', 'netbank');
    $estimate = Mockery::mock(EstimatePayCodeCost::class);
    $estimate->shouldReceive('handle')->andReturn(new PricingEstimateData(currency: 'PHP', total: 10, customer_charge_minor: 1000));
    app()->instance(EstimatePayCodeCost::class, $estimate);
    $this->withHeader('Idempotency-Key', 'quick-reserve-shortfall')->postJson(route('x-change.cockpit.quick-generate.store'), validPayCodePayload(10))
        ->assertUnprocessable()->assertJsonValidationErrors('submission');
    expect(ReserveTransfer::query()->count())->toBe(0);
});

it('releases the selected account reservation after independently verified rejection', function (): void {
    $service = app(DurableReserveTransfer::class);
    $transfer = bindReserveForTest(readyReserveTransfer($service));
    $owner = $transfer->owner;
    $provider = Mockery::mock(AccountTransferProvider::class);
    $provider->shouldReceive('providerCode')->andReturn('netbank');
    $provider->shouldReceive('submit')->once()->andReturn(new AccountTransferResultData(AccountTransferStatus::Pending, 'bank-1'));
    $provider->shouldReceive('checkStatus')->once()->andReturn(new AccountTransferResultData(AccountTransferStatus::Rejected, 'bank-1', new AccountTransferEvidenceData('reserve-1', '111-111-11111-1', '222-222-22222-2', '1234', 1000, 'PHP', 'DIRECT', 0)));
    $advance = app(AdvanceReserveBackedIssuance::class);
    expect($advance->advance($transfer, $owner, $provider)->status)->toBe('pending');
    expect($advance->advance($transfer, $owner, $provider)->status)->toBe('released')
        ->and(app(TreasuryAccountBalanceReadModel::class)->connectionBalanceMinor($owner, 'netbank', 'netbank-primary', 'PHP'))->toBe(1000)
        ->and($advance->advance($transfer, $owner, $provider)->status)->toBe('released');
});
