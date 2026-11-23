<?php

declare(strict_types=1);

use Bavix\Wallet\Models\Wallet;
use Illuminate\Support\Facades\DB;
use LBHurtado\EmiCore\Contracts\PayoutProvider;
use LBHurtado\EmiCore\Data\PayoutRequestData;
use LBHurtado\EmiCore\Data\PayoutResultData;
use LBHurtado\EmiCore\Data\PayoutSourceAccountData;
use LBHurtado\EmiCore\Enums\PayoutStatus;
use LBHurtado\PaymentGateway\Adapters\NetbankPayoutProvider;
use LBHurtado\PaymentGateway\Contracts\PaymentGatewayInterface;
use LBHurtado\PaymentGateway\Contracts\WalletProxy;
use LBHurtado\PaymentGateway\Data\BankData;
use LBHurtado\PaymentGateway\Data\Disburse\DisburseInputData;
use LBHurtado\PaymentGateway\Data\Disburse\DisburseResponseData;
use LBHurtado\PaymentGateway\Data\SettlementBanksData;
use LBHurtado\PaymentGateway\Enums\SettlementRail;
use LBHurtado\XChange\Actions\Banking\AssignNetbankAccount;
use LBHurtado\XChange\Contracts\DisbursementStatusResolverContract;
use LBHurtado\XChange\Models\DisbursementReconciliation;
use LBHurtado\XChange\Models\VoucherBankSource;
use LBHurtado\XChange\Services\Banking\VoucherBankSourceSnapshot;
use LBHurtado\XChange\Services\DefaultDisbursementReconciliationStore;
use LBHurtado\XChange\Services\Treasury\TreasuryProviderConnectionCatalog;
use LBHurtado\XChange\Services\WithdrawalDisbursementExecutor;
use Spatie\LaravelData\DataCollection;

it('freezes an authorized assigned payout source before provider submission', function () {
    $owner = actingAsTestUser();
    $voucher = issueVoucher();
    $assignment = app(AssignNetbankAccount::class)->handle($owner, [
        'account_number' => '991100001234', 'account_name' => 'Example',
        'customer_id' => '1234', 'vca_alias' => '54321',
    ]);
    $assignment->providerAccountLink->forceFill([
        'status' => 'ready', 'ready_at' => now(), 'capabilities' => ['funding', 'disbursement'],
    ])->save();
    $provider = Mockery::mock(PayoutProvider::class);
    $provider->shouldReceive('disburse')->once()->andReturnUsing(function (PayoutRequestData $request): PayoutResultData {
        expect($request->source_account->account_number)->toBe('991100001234');

        return new PayoutResultData('operation', 'uuid', PayoutStatus::PENDING, 'netbank');
    });
    $status = Mockery::mock(DisbursementStatusResolverContract::class);
    $status->shouldReceive('resolveFromGatewayResponse')->once()->andReturn('pending');
    $request = new PayoutRequestData('source-flow', 10, '09170000000', 'GXCHPHM2XXX', 'INSTAPAY',
        source_account: new PayoutSourceAccountData('netbank', '991100001234', '1234'));
    (new WithdrawalDisbursementExecutor($provider, app(DefaultDisbursementReconciliationStore::class), $status))->execute($voucher, $request, 1);

    $row = DisbursementReconciliation::query()->where('voucher_id', $voucher->id)->sole();
    $raw = DB::table('disbursement_reconciliations')->where('id', $row->id)->value('bank_source_snapshot');
    expect($row->bank_source_snapshot['account_number'])->toBe('991100001234')
        ->and($raw)->not->toContain('991100001234')
        ->and(json_encode($row->raw_request))->not->toContain('991100001234');
});

it('rejects a source belonging to another user before provider submission', function () {
    actingAsTestUser();
    $voucher = issueVoucher();
    $other = actingAsTestUser();
    $assignment = app(AssignNetbankAccount::class)->handle($other, [
        'account_number' => '991100001234', 'account_name' => 'Example',
        'customer_id' => '1234', 'vca_alias' => '54321',
    ]);
    $assignment->providerAccountLink->forceFill([
        'status' => 'ready', 'ready_at' => now(), 'capabilities' => ['disbursement'],
    ])->save();
    $provider = Mockery::mock(PayoutProvider::class);
    $provider->shouldNotReceive('disburse');
    $status = Mockery::mock(DisbursementStatusResolverContract::class);
    $request = new PayoutRequestData('source-reject', 10, '09170000000', 'GXCHPHM2XXX', 'INSTAPAY',
        source_account: new PayoutSourceAccountData('netbank', '991100001234', '1234'));

    expect(fn () => (new WithdrawalDisbursementExecutor($provider, app(DefaultDisbursementReconciliationStore::class), $status))->execute($voucher, $request, 1))
        ->toThrow(RuntimeException::class, 'payout source is unavailable');
});

it('delivers the frozen assigned source through the NetBank payout adapter', function () {
    config()->set('disbursement.settlement_rails', ['INSTAPAY']);
    cache()->tags(['banks'])->forever('settlement_banks_data', new SettlementBanksData(
        new DataCollection(BankData::class, [
            new BankData('GXCHPHM2XXX', 'GCash', [SettlementRail::INSTAPAY]),
        ]),
    ));
    $owner = actingAsTestUser();
    $voucher = issueVoucher();
    $assignment = app(AssignNetbankAccount::class)->handle($owner, [
        'account_number' => '991100001234', 'account_name' => 'Example',
        'customer_id' => '1234', 'vca_alias' => '54321',
    ]);
    $assignment->providerAccountLink->forceFill([
        'status' => 'ready', 'ready_at' => now(), 'capabilities' => ['disbursement'],
    ])->save();
    $wallet = Mockery::mock(Wallet::class);
    $proxy = Mockery::mock(WalletProxy::class);
    $proxy->shouldReceive('resolve')->once()->andReturn($wallet);
    $gateway = Mockery::mock(PaymentGatewayInterface::class);
    $gateway->shouldReceive('disburse')->once()->with($wallet, Mockery::on(function (DisburseInputData $input): bool {
        return $input->source_account?->account_number === '991100001234'
            && $input->source_account?->customer_id === '1234';
    }))->andReturn(DisburseResponseData::from(['uuid' => 'uuid-test', 'transaction_id' => 'operation-test', 'status' => 'Pending']));
    $status = Mockery::mock(DisbursementStatusResolverContract::class);
    $status->shouldReceive('resolveFromGatewayResponse')->once()->andReturn('pending');
    $status->shouldReceive('resolveFromGatewayException')->andReturnUsing(fn (Throwable $exception) => throw $exception);
    $request = new PayoutRequestData('source-adapter', 10, '09170000000', 'GXCHPHM2XXX', 'INSTAPAY',
        source_account: new PayoutSourceAccountData('netbank', '991100001234', '1234'));

    (new WithdrawalDisbursementExecutor(new NetbankPayoutProvider($gateway, $proxy), app(DefaultDisbursementReconciliationStore::class), $status))
        ->execute($voucher, $request, 1);

    expect(DisbursementReconciliation::query()->where('voucher_id', $voucher->id)->sole()->bank_source_snapshot['account_number'])
        ->toBe('991100001234');
});

it('does not expose a selected source account in provider failure messages', function () {
    $owner = actingAsTestUser();
    $voucher = issueVoucher();
    $assignment = app(AssignNetbankAccount::class)->handle($owner, [
        'account_number' => '991100001234', 'account_name' => 'Example',
        'customer_id' => '1234', 'vca_alias' => '54321',
    ]);
    $assignment->providerAccountLink->forceFill([
        'status' => 'ready', 'ready_at' => now(), 'capabilities' => ['disbursement'],
    ])->save();
    $provider = Mockery::mock(PayoutProvider::class);
    $provider->shouldReceive('disburse')->once()->andThrow(new RuntimeException('Provider failed for 991100001234'));
    $status = Mockery::mock(DisbursementStatusResolverContract::class);
    $status->shouldReceive('resolveFromGatewayException')->once()->andReturn('unknown');
    $request = new PayoutRequestData('source-failure', 10, '09170000000', 'GXCHPHM2XXX', 'INSTAPAY',
        source_account: new PayoutSourceAccountData('netbank', '991100001234', '1234'));

    try {
        (new WithdrawalDisbursementExecutor($provider, app(DefaultDisbursementReconciliationStore::class), $status))->execute($voucher, $request, 1);
        test()->fail('Expected the provider error to be surfaced.');
    } catch (RuntimeException $exception) {
        expect($exception->getMessage())->not->toContain('991100001234')
            ->and($exception->getPrevious())->toBeNull();
    }

    $row = DisbursementReconciliation::query()->where('voucher_id', $voucher->id)->sole();
    expect(json_encode($row->raw_response))->not->toContain('991100001234')
        ->and($row->error_message)->not->toContain('991100001234');
});

it('uses the source frozen on the Pay Code even after the account becomes unavailable', function () {
    $owner = actingAsTestUser();
    $voucher = issueVoucher();
    $assignment = app(AssignNetbankAccount::class)->handle($owner, [
        'account_number' => '991100001234', 'account_name' => 'Example',
        'customer_id' => '1234', 'vca_alias' => '54321',
    ]);
    $assignment->providerAccountLink->forceFill([
        'status' => 'ready', 'ready_at' => now(), 'capabilities' => ['disbursement'],
    ])->save();
    $snapshots = app(VoucherBankSourceSnapshot::class);
    $frozen = $snapshots->freeze($voucher, $assignment->provider_account_link_id);
    $assignment->providerAccountLink->forceFill(['status' => 'pending', 'capabilities' => []])->save();
    expect($snapshots->freeze($voucher, $assignment->provider_account_link_id))->toBe($frozen)
        ->and(DB::table('xchange_voucher_bank_sources')->where('voucher_id', $voucher->id)->value('source_ciphertext'))
        ->not->toContain('991100001234');

    $provider = Mockery::mock(PayoutProvider::class);
    $provider->shouldReceive('disburse')->once()->with(Mockery::on(fn (PayoutRequestData $request): bool => $request->source_account?->account_number === '991100001234'
        && $request->source_account?->customer_id === '1234'
    ))->andReturn(new PayoutResultData('operation', 'uuid', PayoutStatus::PENDING, 'netbank'));
    $status = Mockery::mock(DisbursementStatusResolverContract::class);
    $status->shouldReceive('resolveFromGatewayResponse')->once()->andReturn('pending');
    $request = new PayoutRequestData('voucher-source', 10, '09170000000', 'GXCHPHM2XXX', 'INSTAPAY');
    (new WithdrawalDisbursementExecutor($provider, app(DefaultDisbursementReconciliationStore::class), $status))->execute($voucher, $request, 1);
    expect(DisbursementReconciliation::query()->where('voucher_id', $voucher->id)->sole()->bank_source_snapshot)->toBe($frozen);
});

it('rejects an attempt to replace the Pay Code frozen source', function () {
    $owner = actingAsTestUser();
    $voucher = issueVoucher();
    $assignment = app(AssignNetbankAccount::class)->handle($owner, [
        'account_number' => '991100001234', 'account_name' => 'Example',
        'customer_id' => '1234', 'vca_alias' => '54321',
    ]);
    $assignment->providerAccountLink->forceFill([
        'status' => 'ready', 'ready_at' => now(), 'capabilities' => ['disbursement'],
    ])->save();
    $snapshots = app(VoucherBankSourceSnapshot::class);
    $snapshots->freeze($voucher, $assignment->provider_account_link_id);
    expect(fn () => $snapshots->freeze($voucher, $assignment->provider_account_link_id + 1))
        ->toThrow(RuntimeException::class, 'different payout source');

    $provider = Mockery::mock(PayoutProvider::class);
    $provider->shouldNotReceive('disburse');
    $status = Mockery::mock(DisbursementStatusResolverContract::class);
    $request = new PayoutRequestData('voucher-source-conflict', 10, '09170000000', 'GXCHPHM2XXX', 'INSTAPAY',
        source_account: new PayoutSourceAccountData('netbank', '991100009999', '1234'));
    expect(fn () => (new WithdrawalDisbursementExecutor($provider, app(DefaultDisbursementReconciliationStore::class), $status))->execute($voucher, $request, 1))
        ->toThrow(RuntimeException::class, 'does not match');
});

it('never falls back to the shared bank when an assigned reservation has lost its frozen source', function () {
    $owner = actingAsTestUser();
    $voucher = issueVoucher();
    $assignment = app(AssignNetbankAccount::class)->handle($owner, [
        'account_number' => '991100001234', 'account_name' => 'Example', 'customer_id' => '1234', 'vca_alias' => '54321',
    ]);
    $assignment->forceFill(['treasury_connection_reference' => 'assigned-connection'])->save();
    $metadata = $voucher->metadata;
    data_set($metadata, 'treasury.pay_code_reservation.connection_reference', 'assigned-connection');
    $voucher->forceFill(['metadata' => $metadata])->save();
    $provider = Mockery::mock(PayoutProvider::class);
    $provider->shouldNotReceive('disburse');
    $statuses = Mockery::mock(DisbursementStatusResolverContract::class);
    $request = new PayoutRequestData('missing-frozen-source', 10, '09170000000', 'GXCHPHM2XXX', 'INSTAPAY');
    expect(fn () => (new WithdrawalDisbursementExecutor($provider, app(DefaultDisbursementReconciliationStore::class), $statuses))
        ->execute($voucher, $request, 1))->toThrow(RuntimeException::class, 'no frozen source');
});

it('refuses payout if a frozen Treasury connection is later repointed', function () {
    actingAsTestUser();
    $voucher = issueVoucher();
    $metadata = $voucher->metadata;
    data_set($metadata, 'treasury.pay_code_reservation.connection_reference', 'personal');
    $voucher->forceFill(['metadata' => $metadata])->save();
    VoucherBankSource::query()->create(['voucher_id' => $voucher->id, 'source_ciphertext' => [
        'provider' => 'netbank', 'account_number' => '991100001234', 'customer_id' => '1234',
        'treasury_connection_reference' => 'personal', 'treasury_inventory_reference' => 'inventory:original',
        'treasury_resource_reference' => 'resource:original',
    ]]);
    app()->instance(TreasuryProviderConnectionCatalog::class,
        new TreasuryProviderConnectionCatalog(['personal' => [
            'provider' => 'netbank', 'mode' => 'required', 'currency' => 'PHP',
            'inventory_reference' => 'inventory:changed', 'settlement_resource_reference' => 'resource:changed',
            'settlement_resource_type' => 'cash_at_bank', 'custody_mode' => 'provider_projection',
        ]]));
    $provider = Mockery::mock(PayoutProvider::class);
    $provider->shouldNotReceive('disburse');
    $status = Mockery::mock(DisbursementStatusResolverContract::class);
    $request = new PayoutRequestData('changed-frozen-connection', 10, '09170000000', 'GXCHPHM2XXX', 'INSTAPAY');
    expect(fn () => (new WithdrawalDisbursementExecutor($provider, app(DefaultDisbursementReconciliationStore::class), $status))
        ->execute($voucher, $request, 1))->toThrow(RuntimeException::class, 'no longer matches its frozen source');
});

it('freezes the provider-formatted account number for payout', function () {
    $owner = actingAsTestUser();
    $voucher = issueVoucher();
    $assignment = app(AssignNetbankAccount::class)->handle($owner, [
        'account_number' => '991-100-00123-4', 'account_name' => 'Example',
        'customer_id' => '1234', 'vca_alias' => '54321',
    ]);
    $assignment->providerAccountLink->forceFill([
        'status' => 'ready', 'ready_at' => now(), 'capabilities' => ['disbursement'],
    ])->save();

    $source = app(VoucherBankSourceSnapshot::class)->freeze($voucher, $assignment->provider_account_link_id);

    expect($source['account_number'])->toBe('991-100-00123-4');
});
