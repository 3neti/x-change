<?php

declare(strict_types=1);

use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use LBHurtado\Contact\Models\Contact;
use LBHurtado\EmiCore\Contracts\PayoutProvider;
use LBHurtado\EmiCore\Contracts\PayoutProviderIdentity;
use LBHurtado\EmiCore\Enums\PayoutStatus;
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
use LBHurtado\XChange\Contracts\DisbursementReconciliationContract;
use LBHurtado\XChange\Contracts\DisbursementStatusFetcherContract;
use LBHurtado\XChange\Contracts\Execution\StoredValueDestinationAuthorityContract;
use LBHurtado\XChange\Contracts\SettlementRailCapabilityRegistryContract;
use LBHurtado\XChange\Data\Execution\StoredValueDestinationAuthorityData;
use LBHurtado\XChange\Jobs\Payment\ReconcileValueFlowPayoutJob;
use LBHurtado\XChange\Lifecycle\Runners\Support\RecoverySimulationPayoutProvider;
use LBHurtado\XChange\Models\DisbursementReconciliation;
use LBHurtado\XChange\Models\StoredValueHolderBinding;
use LBHurtado\XChange\Models\VoucherCollection;
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

it('accepts a provider-owned payout alias for a value-flow submission', function (): void {
    [$service, $voucher, $source] = valueFlowPayoutFixture();
    $identity = new class implements PayoutProviderIdentity
    {
        public function providerCode(): string
        {
            return 'netbank';
        }

        public function acceptsPayoutProvider(string $provider): bool
        {
            return $provider === 'legacy-netbank-test';
        }
    };
    app()->instance('test.value-flow-payout-identity', $identity);
    app()->tag('test.value-flow-payout-identity', 'emi.payout-provider-identities');
    $payout = DB::transaction(fn () => $service->prepare($voucher, $source, 1_000, 'provider-alias'));
    fakePayoutProvider()->willReturnPendingResult(transactionId: 'alias-transaction', provider: 'legacy-netbank-test');
    $service->submit($payout->id);
    expect($payout->fresh()->status)->toBe('pending')->and($payout->fresh()->provider_transaction_id)->toBe('alias-transaction');
});

it('reconciles a delayed payout once without submitting another transfer', function (): void {
    [$service, $voucher, $source] = valueFlowPayoutFixture();
    $payout = DB::transaction(fn () => $service->prepare($voucher, $source, 2_500, 'delayed-status'));
    $provider = fakePayoutProvider()->willReturnPendingResult(provider: 'netbank');
    $service->submit($payout->id);
    $fetcher = Mockery::mock(DisbursementStatusFetcherContract::class);
    $fetcher->shouldReceive('fetch')->twice()->andReturn(['status' => 'pending'], ['status' => 'completed']);
    app()->instance(DisbursementStatusFetcherContract::class, $fetcher);

    $this->artisan('xchange:reconcile:pending', ['--id' => $payout->id, '--status-only' => true])->assertSuccessful();
    expect($payout->fresh()->status)->toBe('pending')
        ->and(TreasuryInventory::query()->sole()->balance_minor)->toBe(10_000);
    $this->artisan('xchange:reconcile:pending', ['--id' => $payout->id, '--status-only' => true])->assertSuccessful();
    $this->artisan('xchange:reconcile:pending', ['--id' => $payout->id, '--status-only' => true])->assertSuccessful();
    expect($payout->fresh()->internal_status)->toBe('finalized')
        ->and(TreasuryInventory::query()->sole()->balance_minor)->toBe(7_500)
        ->and($provider->disburseCallCount)->toBe(1);
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

it('retries a verified rejected payout once using its held recovery balance', function (): void {
    config()->set('x-change.payout_locality.enabled', true);
    [$service, $voucher, $source] = valueFlowPayoutFixture();
    $original = DB::transaction(fn () => $service->prepare($voucher, $source, 2_000, 'rejected-installment'));
    $provider = fakePayoutProvider()->willReturnFailedResult(transactionId: 'rejected-transaction', provider: 'netbank');
    $service->submit($original->id);
    $snapshot = $original->fresh()->payout_identity_snapshot;
    expect(data_get($snapshot, 'recipient.address.source'))->toBe('synthetic')
        ->and($provider->lastRequest->recipient?->address?->source)->toBe('synthetic');
    $provider->nextCheckStatus = PayoutStatus::FAILED;
    $retry = $service->retryRejectedPayout($original->id, 'recipient-confirmed-account-available', 'operator:123');

    expect($retry->id)->not->toBe($original->id)
        ->and($retry->status)->toBe('intent')
        ->and($retry->provider_reference)->not->toBe($original->provider_reference)
        ->and(data_get($retry->meta, 'recovery.original_payout_id'))->toBe($original->id)
        ->and($original->fresh()->internal_status)->toBe('recovery_retried')
        ->and(app(TreasuryPositionReadModelContract::class)->find(data_get($original->meta, 'recovery_position'))->balanceMinor)->toBe(0);
    expect(fn () => $service->retryRejectedPayout($original->id, 'recipient-confirmed-account-available', 'operator:123'))
        ->toThrow(RuntimeException::class);
    $provider->willReturnSuccessfulResult(transactionId: 'retry-transaction', provider: 'netbank');
    $service->submit($retry->id);
    $service->submit($retry->id);
    expect($retry->fresh()->internal_status)->toBe('finalized')
        ->and($retry->fresh()->payout_identity_snapshot)->toBe($snapshot)
        ->and($provider->lastRequest->recipient?->address?->source)->toBe('synthetic')
        ->and($provider->disburseCallCount)->toBe(2);
});

it('simulates a rejected payout and recovery only on an explicitly isolated database', function (): void {
    config()->set('x-change.recovery_simulation.enabled', true);
    config()->set('x-change.recovery_simulation.database', ':memory:');
    app()->instance(PayoutProvider::class, app(RecoverySimulationPayoutProvider::class));
    [$service, $voucher, $source] = valueFlowPayoutFixture();
    $original = DB::transaction(fn () => $service->prepare($voucher, $source, 2_000, 'simulation-installment'));
    $service->submit($original->id);
    expect($original->fresh()->status)->toBe('failed')
        ->and($original->fresh()->internal_status)->toBe('recovery_opened');
    $retry = $service->retryRejectedPayout($original->id, 'simulated evidence', 'operator:test');
    $service->submit($retry->id);
    $service->submit($retry->id);
    expect($retry->fresh()->internal_status)->toBe('finalized')
        ->and($original->fresh()->internal_status)->toBe('recovery_retried')
        ->and(data_get($retry->fresh()->meta, 'recovery.original_payout_id'))->toBe($original->id);
    config()->set('x-change.recovery_simulation.database', '/different.sqlite');
    expect(fn () => app(PayoutProvider::class)->checkStatus((string) $original->fresh()->provider_transaction_id))
        ->toThrow(LogicException::class, 'isolated database');
    config()->set('x-change.recovery_simulation.database', ':memory:');
    $environment = app()->environment();
    try {
        app()->instance('env', 'production');
        expect(fn () => app(PayoutProvider::class)->checkStatus((string) $original->fresh()->provider_transaction_id))
            ->toThrow(LogicException::class, 'isolated database');
    } finally {
        app()->instance('env', $environment);
    }
});

it('refuses a retry when the provider does not confirm the original failure', function (): void {
    [$service, $voucher, $source] = valueFlowPayoutFixture();
    $original = DB::transaction(fn () => $service->prepare($voucher, $source, 2_000, 'uncertain-original'));
    $provider = fakePayoutProvider()->willReturnFailedResult(transactionId: 'uncertain-transaction', provider: 'netbank');
    $service->submit($original->id);
    $provider->nextCheckStatus = PayoutStatus::PENDING;
    expect(fn () => $service->retryRejectedPayout($original->id, 'recipient-confirmed-account-available', 'operator:123'))
        ->toThrow(RuntimeException::class, 'Provider evidence');
    expect($original->fresh()->internal_status)->toBe('recovery_opened')
        ->and(DisbursementReconciliation::query()->count())->toBe(1);
});

it('only lets the voucher owner retry a rejected value-flow payout after confirming the destination is ready', function (): void {
    $this->withoutMiddleware(ThrottleRequests::class);
    [$service, $voucher, $source] = valueFlowPayoutFixture();
    $owner = $voucher->owner;
    $original = DB::transaction(fn () => $service->prepare($voucher, $source, 2_000, 'owner-retry'));
    $provider = fakePayoutProvider()->willReturnFailedResult(transactionId: 'owner-rejected', provider: 'netbank');
    $service->submit($original->id);
    $provider->nextCheckStatus = PayoutStatus::FAILED;
    $url = route('x-change.cockpit.pay-codes.value-flow-payouts.retry', ['code' => $voucher->code, 'payout' => $original->id]);
    $listUrl = route('x-change.cockpit.pay-codes.value-flow-payouts.index', ['code' => $voucher->code]);
    VoucherCollection::query()->create([
        'voucher_id' => $voucher->id, 'collection_number' => 1, 'status' => 'collected',
        'requested_amount_minor' => 2_000, 'collected_amount_minor' => 2_000,
        'currency' => 'PHP', 'provider' => 'netbank', 'completed_at' => now(),
        'meta' => ['posting' => ['payout_reference' => $original->provider_reference]],
    ]);

    $other = actingAsTestUser(0);
    $this->actingAs($other)->getJson($listUrl)->assertNotFound();
    $this->actingAs($other)->postJson($url, ['destination_ready' => true])->assertNotFound();
    $this->actingAs($owner)->postJson(route('x-change.cockpit.pay-codes.value-flow-payouts.retry', ['code' => $voucher->code, 'payout' => $original->id + 1000]), ['destination_ready' => true])->assertNotFound();
    $this->actingAs($owner)->getJson($listUrl)->assertOk()
        ->assertJsonPath('payments.0.delivery_status', 'review')
        ->assertJsonPath('payments.0.retry_url', $url);
    $this->actingAs($owner)->postJson($url, [])->assertUnprocessable();
    expect(DisbursementReconciliation::query()->count())->toBe(1);
    $this->postJson($url, ['destination_ready' => true])->assertAccepted();
    $retry = DisbursementReconciliation::query()->whereKeyNot($original->id)->sole();
    expect(data_get($retry->meta, 'recovery.original_payout_id'))->toBe($original->id)
        ->and(data_get($retry->meta, 'recovery.operator_reference'))->toBe($owner->getMorphClass().':'.$owner->getKey());
    $this->getJson($listUrl)->assertOk()->assertJsonPath('payments.0.delivery_status', 'pending')
        ->assertJsonPath('payments.0.retry_url', null);
    $provider->willReturnSuccessfulResult(transactionId: 'owner-retry-settled', provider: 'netbank');
    $service->submit($retry->id);
    $this->getJson($listUrl)->assertOk()->assertJsonPath('payments.0.delivery_status', 'delivered');
    $voucher->forceFill(['metadata' => ['instructions' => validVoucherInstructions(0, overrides: [
        'voucher_type' => 'payable', 'target_amount' => 20,
        'metadata' => ['collection_wallet_id' => (string) $owner->wallet()->where('slug', 'platform')->sole()->getKey()],
    ])->toArray()]])->save();
    $this->withHeader('X-Inertia', 'true')
        ->get(route('x-change.cockpit.pay-codes.show', ['code' => $voucher->code]))
        ->assertOk()->assertJsonPath('props.read_model.voucher.collection.payout_status', 'completed');
    $this->postJson($url, ['destination_ready' => true])->assertStatus(409);
    expect(DisbursementReconciliation::query()->count())->toBe(2);
});

it('denies the operator recovery workspace by default and allows a host-authorized operator to see masked cases', function (): void {
    [$service, $voucher, $source] = valueFlowPayoutFixture();
    $operator = $voucher->owner;
    $original = DB::transaction(fn () => $service->prepare($voucher, $source, 2_000, 'operator-workspace'));
    fakePayoutProvider()->willReturnFailedResult(transactionId: 'workspace-rejected', provider: 'netbank');
    $service->submit($original->id);
    $url = route('x-change.operations.recovery.index');
    DB::table('x_change_treasury_payout_outbox')->insert([
        'voucher_id' => $voucher->id, 'status' => 'review', 'claim_key_ciphertext' => Crypt::encryptString('private-claim-key'),
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $this->actingAs($operator)->withHeader('X-Inertia', 'true')->get($url)->assertForbidden();
    Gate::define('x-change.recovery.manage', fn ($user): bool => $user->is($operator));
    $this->get($url)->assertOk()->assertJsonPath('component', 'x-change/operations/PaymentRecovery')
        ->assertJsonPath('props.payouts.0.code', $voucher->code)
        ->assertJsonPath('props.payouts.0.status', 'failed')
        ->assertJsonPath('props.treasuryPayoutReviews.0.code', $voucher->code)
        ->assertDontSee('private-claim-key')
        ->assertJsonMissingPath('props.payouts.0.provider_transaction_id');
});

it('checks an existing payout without submitting a new transfer', function (): void {
    [$service, $voucher, $source] = valueFlowPayoutFixture();
    $original = DB::transaction(fn () => $service->prepare($voucher, $source, 2_000, 'status-only-check'));
    $provider = fakePayoutProvider()->willReturnPendingResult(transactionId: 'pending-payout-id', provider: 'netbank');
    $service->submit($original->id);
    $reconciler = Mockery::mock(DisbursementReconciliationContract::class);
    $reconciler->shouldReceive('reconcile')->once()->withArgs(fn ($row): bool => $row->id === $original->id)->andReturn([]);
    (new ReconcileValueFlowPayoutJob($original->id, 'netbank'))->handle($reconciler);
    expect($provider->disburseCallCount)->toBe(1);
});

it('requires operator authorization and an audit reason to retry the original rejected payout', function (): void {
    [$service, $voucher, $source] = valueFlowPayoutFixture();
    $operator = $voucher->owner;
    $original = DB::transaction(fn () => $service->prepare($voucher, $source, 2_000, 'operator-workspace-retry'));
    $provider = fakePayoutProvider()->willReturnFailedResult(transactionId: 'workspace-retry-rejected', provider: 'netbank');
    $service->submit($original->id);
    $provider->nextCheckStatus = PayoutStatus::FAILED;
    $url = route('x-change.operations.recovery.payout.retry', ['payout' => $original->id]);
    $this->actingAs($operator)->post($url, ['reason' => 'Recipient confirmed account available', 'destination_ready' => true])->assertForbidden();
    Gate::define('x-change.recovery.manage', fn ($user): bool => $user->is($operator));
    $this->post($url, ['destination_ready' => true])->assertSessionHasErrors('reason');
    $this->post($url, ['reason' => 'Recipient confirmed account available', 'destination_ready' => true])->assertRedirect();
    $retry = DisbursementReconciliation::query()->whereKeyNot($original->id)->sole();
    expect(data_get($retry->meta, 'recovery.original_payout_id'))->toBe($original->id)
        ->and(data_get($retry->meta, 'recovery.evidence_reference'))->toBe('Recipient confirmed account available')
        ->and(data_get($retry->meta, 'recovery.operator_reference'))->toBe($operator->getMorphClass().':'.$operator->getKey());
});

it('protects payer reports and records the authorized operator review without moving funds', function (): void {
    [, $voucher] = valueFlowPayoutFixture();
    $operator = $voucher->owner;
    $this->postJson(route('x-change.pay.recovery-reports.store', ['code' => $voucher->code]), [
        'transaction_reference' => 'provider-reference-123', 'amount_minor' => 2000,
        'paid_at' => now()->toIso8601String(), 'contact' => 'payer@example.test',
    ])->assertAccepted();
    $report = DB::table('x_change_payment_recovery_reports')->first();
    expect($report->evidence_ciphertext)->not->toContain('provider-reference-123');
    $url = route('x-change.operations.recovery.report.review', ['reference' => $report->reference]);
    $this->actingAs($operator)->post($url, ['note' => 'Evidence reviewed with provider'])->assertForbidden();
    Gate::define('x-change.recovery.manage', fn ($user): bool => $user->is($operator));
    $this->withHeader('X-Inertia', 'true')->get(route('x-change.operations.recovery.index'))
        ->assertOk()->assertJsonPath('props.reports.0.evidence.transaction_reference', 'provider-reference-123');
    $this->post($url, [])->assertSessionHasErrors('note');
    $this->post($url, ['note' => 'Evidence reviewed with provider'])->assertRedirect();
    $reviewed = DB::table('x_change_payment_recovery_reports')->where('id', $report->id)->first();
    expect($reviewed->status)->toBe('reviewed')
        ->and($reviewed->reviewed_by_id)->toBe((string) $operator->getKey())
        ->and(Crypt::decryptString($reviewed->review_note_ciphertext))->toBe('Evidence reviewed with provider')
        ->and(VoucherCollection::query()->count())->toBe(0);
});

it('requires an operator and evidence before the rejected-payout command can prepare a retry', function (): void {
    [$service, $voucher, $source] = valueFlowPayoutFixture();
    $original = DB::transaction(fn () => $service->prepare($voucher, $source, 2_000, 'command-rejected'));
    $provider = fakePayoutProvider()->willReturnFailedResult(transactionId: 'command-rejected-transaction', provider: 'netbank');
    $service->submit($original->id);
    $provider->nextCheckStatus = PayoutStatus::FAILED;
    $arguments = ['voucher' => $voucher->code, '--id' => $original->id, '--retry-rejected' => true, '--confirm-no-transfer' => true, '--evidence' => 'provider-rejection'];
    $this->artisan('xchange:value-flow:recovery', $arguments)->assertFailed();
    expect(DisbursementReconciliation::query()->count())->toBe(1);
    $this->artisan('xchange:value-flow:recovery', [...$arguments, '--operator' => 'operator:123'])->assertSuccessful();
    expect(DisbursementReconciliation::query()->count())->toBe(2);
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

it('routes every stored value spend by its issued destination rather than collector authority', function (bool $external, string $schema): void {
    [$payouts, $voucher, $source] = valueFlowPayoutFixture();
    $owner = $voucher->owner;
    $owner->forceFill(['mobile' => '09171234567'])->save();
    $metadata = $voucher->metadata;
    data_set($metadata, 'instructions.execution.driver', 'stored_value');
    data_set($metadata, 'instructions.execution.schema', $schema);
    if ($schema === 'voucher.execution.v2') {
        data_set($metadata, 'instructions.execution.value_flow', data_get($metadata, 'instructions.execution.metadata.value_flow'));
        data_forget($metadata, 'instructions.execution.metadata.value_flow');
    }
    if (! $external) {
        $path = $schema === 'voucher.execution.v2' ? 'instructions.execution.value_flow.destination' : 'instructions.execution.metadata.value_flow.destination';
        data_set($metadata, $path, 'account.mobile:09171234567.client_funds');
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
})->with([
    'v1 external' => [true, 'voucher.execution.v1'],
    'v1 client funds' => [false, 'voucher.execution.v1'],
    'v2 external' => [true, 'voucher.execution.v2'],
    'v2 client funds' => [false, 'voucher.execution.v2'],
]);

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

it('inspects recovery safely and requires evidence and the exact voucher before retry', function (): void {
    [$service, $voucher, $source] = valueFlowPayoutFixture();
    $payout = DB::transaction(fn () => $service->prepare($voucher, $source, 2_500, 'operator-recovery'));
    $payout->forceFill(['status' => 'failed', 'provider_transaction_id' => $payout->provider_reference,
        'attempt_count' => 1, 'meta' => [...$payout->meta, 'provider_response' => ['received' => true, 'status' => 'failed']]])->save();
    $service->converge($payout);
    $provider = fakePayoutProvider()->willReturnSuccessfulResult(provider: 'netbank');
    $this->artisan('xchange:value-flow:recovery', ['voucher' => $voucher->code, '--json' => true])->assertSuccessful();
    $this->artisan('xchange:value-flow:recovery', ['voucher' => $voucher->code, '--id' => $payout->id, '--retry-unaccepted' => true])->assertFailed();
    $this->artisan('xchange:value-flow:recovery', ['voucher' => 'OTHER', '--id' => $payout->id, '--retry-unaccepted' => true, '--confirm-no-transfer' => true, '--evidence' => 'provider-rejection-1'])->assertFailed();
    expect($provider->disburseCallCount)->toBe(0);
    $this->artisan('xchange:value-flow:recovery', ['voucher' => $voucher->code, '--id' => $payout->id, '--retry-unaccepted' => true, '--confirm-no-transfer' => true, '--evidence' => 'provider-rejection-1'])->assertSuccessful();
    expect($provider->disburseCallCount)->toBe(1)
        ->and($payout->fresh()->internal_status)->toBe('finalized');
    $this->artisan('xchange:value-flow:recovery', ['voucher' => $voucher->code, '--id' => $payout->id, '--retry-unaccepted' => true, '--confirm-no-transfer' => true, '--evidence' => 'provider-rejection-1'])->assertFailed();
    expect($provider->disburseCallCount)->toBe(1);
});
