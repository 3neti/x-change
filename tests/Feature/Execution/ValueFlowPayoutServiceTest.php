<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use LBHurtado\Contact\Models\Contact;
use LBHurtado\Voucher\Data\ExecutionContextData;
use LBHurtado\Voucher\Enums\VoucherType;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryAllocationOperationContract;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryInventoryOperationContract;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryPositionReadModelContract;
use LBHurtado\Wallet\Treasury\Data\TreasuryAllocationActivationData;
use LBHurtado\Wallet\Treasury\Data\TreasuryInventoryData;
use LBHurtado\Wallet\Treasury\Data\TreasuryInventoryRecognitionData;
use LBHurtado\Wallet\Treasury\Models\TreasuryInventory;
use LBHurtado\Wallet\Treasury\Models\TreasuryPosition;
use LBHurtado\XChange\Contracts\Execution\StoredValueDestinationAuthorityContract;
use LBHurtado\XChange\Contracts\SettlementRailCapabilityRegistryContract;
use LBHurtado\XChange\Data\Execution\StoredValueDestinationAuthorityData;
use LBHurtado\XChange\Models\DisbursementReconciliation;
use LBHurtado\XChange\Models\StoredValueHolderBinding;
use LBHurtado\XChange\Services\Execution\ValueFlowPayoutService;
use LBHurtado\XChange\Services\Execution\WalletStoredValueExecutionGateway;
use LBHurtado\XChange\Services\Treasury\TreasuryPayCodeAccountingService;

function valueFlowPayoutFixture(): array
{
    Queue::fake();
    enableNetbankTreasuryForTests();
    config()->set('x-change.provider_runtime.default_provider', 'netbank');
    config()->set('omnipay.gateways.netbank.options.rails.INSTAPAY', ['enabled' => true, 'min_amount' => 100, 'max_amount' => 5_000_000]);
    $owner = actingAsTestUser(0);
    fundTestUserClientFunds($owner, 10_000);
    $inventory = app(TreasuryInventoryOperationContract::class);
    $inventory->registerInventory(new TreasuryInventoryData(
        inventoryReference: 'inventory:netbank:vca-cash', resourceType: 'cash_at_bank', currency: 'PHP',
        capacityMinor: 0, status: 'requested', idempotencyKey: 'register:value-flow',
        externalReference: 'resource:netbank:corporate-vca',
    ));
    $inventory->recognize(new TreasuryInventoryRecognitionData(
        operationReference: 'value-flow:test-funding', inventoryReference: 'inventory:netbank:vca-cash',
        settlementResourceReference: 'resource:netbank:corporate-vca', amountMinor: 10_000, currency: 'PHP',
        status: 'requested', idempotencyKey: 'value-flow:test-funding', externalReference: 'value-flow:test',
    ));
    $voucher = Voucher::query()->create([
        'code' => Str::upper(Str::random(8)), 'voucher_type' => VoucherType::PAYABLE,
        'owner_type' => $owner->getMorphClass(), 'owner_id' => $owner->getKey(),
        'metadata' => ['instructions' => ['execution' => ['metadata' => ['value_flow' => [
            'destination' => 'instapay.gcash.mobile:09179999999',
        ]]]]],
    ]);
    $voucher->forceFill(['voucher_type' => VoucherType::PAYABLE])->save();
    $voucher->owner()->associate($owner);
    $voucher->save();
    $service = app(ValueFlowPayoutService::class);
    $source = $service->bridge($voucher, 'netbank-primary', 'PHP');

    return [$service, $voucher, $source];
}

it('reserves each collection independently and replays without a second reservation or submission', function (): void {
    [$service, $voucher, $source] = valueFlowPayoutFixture();
    $payout = DB::transaction(fn () => $service->prepare($voucher, $source, 2_500, 'collection-one'));
    $replay = DB::transaction(fn () => $service->prepare($voucher, $source, 2_500, 'collection-one'));
    $second = DB::transaction(fn () => $service->prepare($voucher, $source, 1_000, 'collection-two'));
    expect($replay->id)->toBe($payout->id)
        ->and($second->id)->not->toBe($payout->id)
        ->and(app(TreasuryPositionReadModelContract::class)->find($source->positionReference)->balanceMinor)->toBe(6_500)
        ->and(json_encode($payout->toArray()))->not->toContain('09179999999');
    $provider = fakePayoutProvider()->willReturnPendingResult(provider: 'netbank');
    $service->submit($payout->id);
    $service->submit($payout->id);
    expect($provider->disburseCallCount)->toBe(1)
        ->and($provider->lastRequest->account_number)->toBe('09179999999')
        ->and($payout->fresh()->status)->toBe('pending');
});

it('rejects conflicting replay and rolls back all funding mutations', function (): void {
    [$service, $voucher, $source] = valueFlowPayoutFixture();
    DB::transaction(fn () => $service->prepare($voucher, $source, 2_500, 'collection-one'));
    expect(fn () => DB::transaction(fn () => $service->prepare($voucher, $source, 2_600, 'collection-one')))
        ->toThrow(RuntimeException::class, 'conflicts');
    try {
        DB::transaction(function () use ($service, $voucher, $source): void {
            $service->prepare($voucher, $source, 1_000, 'rolled-back');
            throw new RuntimeException('rollback');
        });
    } catch (RuntimeException) {
    }
    expect(DisbursementReconciliation::query()->count())->toBe(1)
        ->and(app(TreasuryPositionReadModelContract::class)->find($source->positionReference)->balanceMinor)->toBe(7_500);
});

it('keeps rejected payouts payable without changing the voucher or later destinations', function (): void {
    [$service, $voucher, $source] = valueFlowPayoutFixture();
    $payout = DB::transaction(fn () => $service->prepare($voucher, $source, 2_500, 'rejected'));
    fakePayoutProvider()->willReturnFailedResult(provider: 'netbank');
    $service->submit($payout->id);
    $service->converge($payout);
    $recovery = TreasuryPosition::query()->where('position_reference', data_get($payout->meta, 'recovery_position'))->sole();
    expect($payout->fresh()->internal_status)->toBe('recovery_opened')
        ->and(app(TreasuryPositionReadModelContract::class)->find($recovery->position_reference)->balanceMinor)->toBe(2_500)
        ->and(data_get($voucher->fresh()->metadata, 'instructions.execution.metadata.value_flow.destination'))->toBe('instapay.gcash.mobile:09179999999');
    $next = DB::transaction(fn () => $service->prepare($voucher, $source, 1_000, 'later-spend'));
    expect($next->bank_code)->toBe('GXCHPHM2XXX');
});

it('settles a confirmed payout exactly once and never submits an uncertain outcome again', function (): void {
    [$service, $voucher, $source] = valueFlowPayoutFixture();
    $payout = DB::transaction(fn () => $service->prepare($voucher, $source, 2_500, 'paid'));
    $inventory = TreasuryInventory::query()->where('inventory_reference', data_get($payout->meta, 'inventory_reference'))->sole();
    $before = (int) $inventory->balance_minor;
    fakePayoutProvider()->willReturnSuccessfulResult(provider: 'netbank');
    $service->submit($payout->id);
    $service->converge($payout);
    expect($payout->fresh()->internal_status)->toBe('finalized')
        ->and((int) $inventory->fresh()->balance_minor)->toBe($before - 2_500);
    $uncertain = DB::transaction(fn () => $service->prepare($voucher, $source, 1_000, 'uncertain'));
    $provider = fakePayoutProvider()->willThrow();
    $service->submit($uncertain->id);
    $provider->nextException = null;
    $service->submit($uncertain->id);
    expect($uncertain->fresh()->status)->toBe('unknown')
        ->and($uncertain->fresh()->needs_review)->toBeTrue()
        ->and($provider->disburseCallCount)->toBe(0);
});

it('routes every stored value spend by its issued destination rather than collector authority', function (bool $external): void {
    [$payouts, $voucher, $source] = valueFlowPayoutFixture();
    $owner = $voucher->owner;
    $owner->forceFill(['mobile' => '09171234567'])->save();
    $metadata = $voucher->metadata;
    data_set($metadata, 'instructions.execution.driver', 'stored_value');
    if (! $external) {
        data_set($metadata, 'instructions.execution.metadata.value_flow.destination', 'account.mobile:09171234567.client_funds');
    }
    $voucher->forceFill(['voucher_type' => VoucherType::REDEEMABLE, 'metadata' => $metadata])->save();
    $reservation = app(TreasuryPayCodeAccountingService::class)
        ->reserve($owner, $voucher, 'netbank-primary', 10_000, 'PHP');
    app(TreasuryAllocationOperationContract::class)->activate(
        new TreasuryAllocationActivationData(
            operationReference: 'fixed-value-activation', allocationReference: 'fixed-value-allocation',
            backingReservationOperationReference: $reservation->operationReference,
            initialAmountMinor: 10_000, maximumAmountMinor: 10_000, currency: 'PHP',
            replenishable: false, idempotencyKey: 'fixed-value-activation', externalReference: 'fixed-value-voucher',
        ),
    );
    StoredValueHolderBinding::query()->create([
        'voucher_id' => $voucher->getKey(), 'allocation_reference' => 'fixed-value-allocation',
        'reservation_operation_reference' => $reservation->operationReference,
        'activation_operation_reference' => 'fixed-value-activation',
        'holder_type' => $owner->getMorphClass(), 'holder_id' => $owner->getKey(),
        'holder_principal_reference' => $source->principalReference, 'holder_authority_reference' => 'verified-holder',
        'currency' => 'PHP', 'activated_at' => now(),
    ]);
    $authority = Mockery::mock(StoredValueDestinationAuthorityContract::class);
    $authority->shouldReceive('authorize')->times(3)->andReturn(new StoredValueDestinationAuthorityData(
        counterpartyPositionReference: 'collector-cannot-choose-destination', authorityReference: 'authorized-collection', principalReference: 'collector',
    ));
    app()->instance(StoredValueDestinationAuthorityContract::class, $authority);
    $gateway = app(WalletStoredValueExecutionGateway::class);
    $context = fn (string $id) => new ExecutionContextData(
        contact: new Contact, voucherCode: $voucher->code, voucher: $voucher,
        correlation: ['execution_id' => $id],
    );
    $first = $gateway->spend($context('spend-one'), 2_500, 'spend-one');
    $replay = $gateway->spend($context('spend-one'), 2_500, 'spend-one');
    $second = $gateway->spend($context('spend-two'), 1_000, 'spend-two');
    expect($first['remaining_balance'])->toBe(7_500)
        ->and($replay['remaining_balance'])->toBe(7_500)
        ->and($second['remaining_balance'])->toBe(6_500)
        ->and($first['destination_principal_reference'])->toBe($source->principalReference)
        ->and(app(TreasuryPositionReadModelContract::class)->find($source->positionReference)->balanceMinor)->toBe($external ? 0 : 3_500)
        ->and(DisbursementReconciliation::query()->count())->toBe($external ? 2 : 0);
})->with([true, false]);

it('dispatches an unsubmitted durable payout from the reconciliation command and reports it', function (): void {
    [$service, $voucher, $source] = valueFlowPayoutFixture();
    $payout = DB::transaction(fn () => $service->prepare($voucher, $source, 2_500, 'queued-intent'));
    $provider = fakePayoutProvider()->willReturnSuccessfulResult(provider: 'netbank');
    $this->artisan('xchange:reconcile:pending', ['--json' => true])
        ->expectsOutputToContain('"processed":1')->assertSuccessful();
    expect($payout->fresh()->internal_status)->toBe('finalized')
        ->and($provider->disburseCallCount)->toBe(1);
});

it('retains the reservation when provider evidence is incomplete or belongs to another provider', function (string $providerName, string $transactionId): void {
    [$service, $voucher, $source] = valueFlowPayoutFixture();
    $payout = DB::transaction(fn () => $service->prepare($voucher, $source, 2_500, 'uncertain-evidence'));
    $provider = fakePayoutProvider()->willReturnSuccessfulResult(transactionId: $transactionId, provider: $providerName);
    $service->submit($payout->id);
    $service->submit($payout->id);
    expect($payout->fresh()->status)->toBe('unknown')
        ->and($payout->fresh()->needs_review)->toBeTrue()
        ->and($payout->fresh()->internal_status)->toBe('reserved')
        ->and($provider->disburseCallCount)->toBe(1);
    if ($providerName !== 'netbank') {
        expect($payout->fresh()->provider_transaction_id)->toBeNull();
    }
})->with([['another-provider', 'other-transaction'], ['netbank', '']]);

it('rejects an unsupported transfer before reserving funds or creating a payout', function (): void {
    [, $voucher, $source] = valueFlowPayoutFixture();
    $rails = Mockery::mock(SettlementRailCapabilityRegistryContract::class);
    $rails->shouldReceive('assertSupports')->once()->andThrow(new RuntimeException('Transfer amount exceeds this rail.'));
    app()->instance(SettlementRailCapabilityRegistryContract::class, $rails);
    $service = app(ValueFlowPayoutService::class);
    expect(fn () => DB::transaction(fn () => $service->prepare($voucher, $source, 2_500, 'unsupported-rail')))
        ->toThrow(RuntimeException::class, 'exceeds this rail');
    expect(DisbursementReconciliation::query()->count())->toBe(0)
        ->and(app(TreasuryPositionReadModelContract::class)->find($source->positionReference)->balanceMinor)->toBe(10_000);
});

it('uses a stable provider reference within the NetBank 36 character limit', function (): void {
    [$service, $voucher, $source] = valueFlowPayoutFixture();
    $payout = DB::transaction(fn () => $service->prepare($voucher, $source, 2_500, 'reference-length'));
    $provider = fakePayoutProvider()->willReturnPendingResult(provider: 'netbank');
    $service->submit($payout->id);
    expect(strlen($provider->lastRequest->reference))->toBeLessThanOrEqual(36)
        ->and($payout->fresh()->meta['submission_reference'])->toBe($provider->lastRequest->reference)
        ->and($payout->fresh()->provider_reference)->toBe($payout->provider_reference);
    $service->submit($payout->id);
    expect($provider->disburseCallCount)->toBe(1);
});

it('recovers a confirmed unaccepted legacy submission from held funds without reserving twice', function (): void {
    [$service, $voucher, $source] = valueFlowPayoutFixture();
    $payout = DB::transaction(fn () => $service->prepare($voucher, $source, 2_500, 'legacy-reference'));
    $payout->forceFill(['status' => 'failed', 'provider_transaction_id' => $payout->provider_reference,
        'attempt_count' => 1, 'meta' => [...$payout->meta, 'provider_response' => ['received' => true, 'status' => 'failed']]])->save();
    $service->converge($payout);
    expect(fn () => $service->retryUnacceptedSubmission($payout->id, false, 'validation-rejection'))
        ->toThrow(RuntimeException::class, 'explicit no-transfer');
    $provider = fakePayoutProvider()->willReturnSuccessfulResult(provider: 'netbank');
    $service->retryUnacceptedSubmission($payout->id, true, 'validation-rejection');
    expect($payout->fresh()->internal_status)->toBe('finalized')
        ->and($payout->fresh()->attempt_count)->toBe(2)
        ->and($provider->disburseCallCount)->toBe(1)
        ->and(app(TreasuryPositionReadModelContract::class)->find($source->positionReference)->balanceMinor)->toBe(7_500);
    expect(fn () => $service->retryUnacceptedSubmission($payout->id, true, 'validation-rejection'))
        ->toThrow(RuntimeException::class, 'not eligible');
    expect($provider->disburseCallCount)->toBe(1);
});

it('refuses to recover an uncertain submission', function (): void {
    [$service, $voucher, $source] = valueFlowPayoutFixture();
    $payout = DB::transaction(fn () => $service->prepare($voucher, $source, 2_500, 'uncertain-recovery'));
    $provider = fakePayoutProvider()->willThrow();
    $service->submit($payout->id);
    expect(fn () => $service->retryUnacceptedSubmission($payout->id, true, 'unverified'))
        ->toThrow(RuntimeException::class, 'not eligible');
    expect($payout->fresh()->status)->toBe('unknown');
});
