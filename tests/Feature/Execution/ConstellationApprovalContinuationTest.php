<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use LBHurtado\EmiCore\Contracts\PayoutProvider;
use LBHurtado\EmiCore\Contracts\ProviderReadinessProbe;
use LBHurtado\EmiCore\Contracts\SettlementProviderRegistryContract;
use LBHurtado\EmiCore\Data\PayoutRequestData;
use LBHurtado\EmiCore\Data\Providers\ProviderCapabilityReadinessData;
use LBHurtado\EmiCore\Data\Providers\ProviderReadinessRequestData;
use LBHurtado\EmiCore\Support\SettlementProviderRegistry;
use LBHurtado\EmiPaynamicsConstellation\Adapters\ConstellationPayoutProvider;
use LBHurtado\EmiPaynamicsConstellation\Contracts\ConstellationOtpResolver;
use LBHurtado\EmiPaynamicsConstellation\Exceptions\PendingConstellationOtpException;
use LBHurtado\EmiPaynamicsConstellation\Support\ConstellationSettlementProvider;
use LBHurtado\EmiPaynamicsConstellation\Support\DeferredOtpResolver;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryInventoryOperationContract;
use LBHurtado\Wallet\Treasury\Data\TreasuryInventoryData;
use LBHurtado\Wallet\Treasury\Data\TreasuryInventoryRecognitionData;
use LBHurtado\XChange\Actions\Funding\IssueTreasuryBackedPayCode;
use LBHurtado\XChange\Actions\Redemption\SubmitPayCodeClaim;
use LBHurtado\XChange\Contracts\DisbursementStatusResolverContract;
use LBHurtado\XChange\Contracts\TreasuryAccountPortfolioProvisioningContract;
use LBHurtado\XChange\Contracts\VerifiedTreasuryFundingAllocationContract;
use LBHurtado\XChange\Jobs\Feedback\DeliverQueuedFeedbackSmsJob;
use LBHurtado\XChange\Jobs\Redemption\DispatchVoucherRedemptionFeedbackJob;
use LBHurtado\XChange\Models\DisbursementReconciliation;
use LBHurtado\XChange\Services\DefaultDisbursementReconciliationStore;
use LBHurtado\XChange\Services\PaynamicsWithdrawalOtpApprovalService;
use LBHurtado\XChange\Services\Treasury\SystemPrincipalProvisioningService;
use LBHurtado\XChange\Services\Treasury\TreasuryPayCodeAccountingService;
use LBHurtado\XChange\Services\Treasury\TreasuryPreflightService;
use LBHurtado\XChange\Services\Treasury\TreasuryProviderConnectionCatalog;
use LBHurtado\XChange\Services\Treasury\TreasuryProvisioningService;
use LBHurtado\XChange\Services\WithdrawalDisbursementExecutor;

it('continues a guarded payout through the real Constellation adapter with fake transport', function (): void {
    config()->set('x-change.payout_locality.submission_guard_enabled', true);
    config()->set('constellation.base_url', 'https://provider.invalid');
    config()->set('constellation.username', 'test-user');
    config()->set('constellation.password', 'test-pass');
    config()->set('constellation.merchant_key', 'test-key');
    config()->set('constellation.log_channel', 'single');
    config()->set('constellation.settlement_wallet_id', 'test-wallet');
    config()->set('constellation.bank_map.GXCHPHM2XXX', 'gcash-bank');
    Http::preventStrayRequests();
    Http::fake([
        '*/integration/corp_wallet/view_wallet/*' => Http::response(['success' => true, 'data' => ['wallet_id' => 'test-wallet', 'account_id' => 'test-account']]),
        '*/integration/corp_wallet/request_otp' => Http::response(['success' => true, 'data' => 'OTP requested']),
        '*/integration/corp_wallet/withdraw_not_registered' => Http::response(['success' => true, 'response_code' => 'GR162', 'data' => ['status' => 'PENDING']]),
    ]);
    app()->instance(ConstellationOtpResolver::class, app(DeferredOtpResolver::class));
    $provider = app(ConstellationPayoutProvider::class);
    app()->instance(PayoutProvider::class, $provider);
    $status = Mockery::mock(DisbursementStatusResolverContract::class);
    $status->shouldReceive('resolveFromGatewayResponse')->once()->andReturn('pending');
    $executor = new WithdrawalDisbursementExecutor($provider, app(DefaultDisbursementReconciliationStore::class), $status);
    $voucher = issueVoucher();
    $request = new PayoutRequestData('provider-approval-001', 100, '09170000000', 'GXCHPHM2XXX', 'INSTAPAY');

    expect(fn () => $executor->execute($voucher, $request, 1))->toThrow(PendingConstellationOtpException::class);
    Http::assertSentCount(2);
    expect(app(PaynamicsWithdrawalOtpApprovalService::class)->verify('09170000000', $request->reference, '123456'))->toBeTrue();
    $result = $executor->execute($voucher, $request, 1, true);
    expect($result->response->provider)->toBe('paynamics')->and($result->status)->toBe('pending');
    Http::assertSentCount(4);
    Http::assertSent(fn ($sent): bool => str_ends_with($sent->url(), '/integration/corp_wallet/withdraw_not_registered')
        && $sent['request_id'] === 'provider-approval-001' && $sent['otp'] === '123456'
        && $sent['bank_id'] === 'gcash-bank' && $sent['account_no'] === '09170000000');
    expect(fn () => $executor->execute($voucher, $request, 1, true))->toThrow(RuntimeException::class);
    Http::assertSentCount(4);
    $row = DisbursementReconciliation::query()->where('voucher_id', $voucher->id)->sole();
    expect(json_encode($row->raw_response))->not->toContain('123456')->not->toContain('09170000000');
});

it('continues a Paynamics-configured treasury claim using the real adapter and simulated transport', function (): void {
    Queue::fake([
        DeliverQueuedFeedbackSmsJob::class,
        DispatchVoucherRedemptionFeedbackJob::class,
    ]);
    config()->set('x-change.payout_locality.submission_guard_enabled', true);
    $issuer = actingAsTestUser();
    enableNetbankTreasuryForTests();
    config()->set('x-change.provider_runtime.default_provider', 'paynamics_constellation');
    config()->set('x-change.provider_runtime.providers.paynamics_constellation.enabled', true);
    config()->set('x-change.treasury.connections', [
        'paynamics-primary' => [
            'provider' => 'paynamics_constellation', 'mode' => 'required', 'currency' => 'PHP', 'decimal_places' => 2,
            'inventory_reference' => 'inventory:paynamics:test', 'settlement_resource_reference' => 'resource:paynamics:test',
            'settlement_resource_type' => 'cash_at_bank', 'custody_mode' => 'provider_projection', 'required_capabilities' => [],
        ],
    ]);
    $settlementProvider = app(ConstellationSettlementProvider::class);
    app()->instance(SettlementProviderRegistryContract::class, new SettlementProviderRegistry([$settlementProvider]));
    app()->tag(ConstellationSettlementProvider::class, 'emi.payout-provider-identities');
    $probe = new class implements ProviderReadinessProbe
    {
        public function providerCode(): string
        {
            return 'paynamics_constellation';
        }

        public function checkReadiness(ProviderReadinessRequestData $request): ProviderCapabilityReadinessData
        {
            return new ProviderCapabilityReadinessData(provider: $request->provider, connectionReference: $request->connectionReference, checks: [], issues: [], checkedAt: new DateTimeImmutable);
        }
    };
    app()->instance('test.paynamics-readiness', $probe);
    app()->tag('test.paynamics-readiness', 'emi.provider-readiness-probes');
    foreach ([TreasuryProviderConnectionCatalog::class, TreasuryPreflightService::class, TreasuryPayCodeAccountingService::class, TreasuryProvisioningService::class, TreasuryAccountPortfolioProvisioningContract::class, VerifiedTreasuryFundingAllocationContract::class] as $service) {
        app()->forgetInstance($service);
    }
    app(SystemPrincipalProvisioningService::class)->provision('test:netbank-treasury-system-principal');
    app(TreasuryAccountPortfolioProvisioningContract::class)->provision($issuer, ['paynamics-primary']);
    $inventory = app(TreasuryInventoryOperationContract::class);
    $inventory->registerInventory(new TreasuryInventoryData(inventoryReference: 'inventory:paynamics:test', resourceType: 'cash_at_bank', currency: 'PHP', capacityMinor: 0, status: 'requested', idempotencyKey: 'register-paynamics', externalReference: 'resource:paynamics:test'));
    $inventory->recognize(new TreasuryInventoryRecognitionData(operationReference: 'recognize-paynamics', inventoryReference: 'inventory:paynamics:test', settlementResourceReference: 'resource:paynamics:test', amountMinor: 5000, currency: 'PHP', status: 'requested', idempotencyKey: 'recognize-paynamics-key', externalReference: 'paynamics-fixture'));
    app(VerifiedTreasuryFundingAllocationContract::class)->allocate(accountReference: 'wallet:'.$issuer->wallet->uuid, provider: 'paynamics_constellation', amountMinor: 5000, currency: 'PHP', evidenceReference: 'paynamics-fixture');
    $voucher = app(IssueTreasuryBackedPayCode::class)->handle(issuer: $issuer, instructions: validVoucherInstructions(amount: 20, overrides: ['metadata' => ['flow_type' => 'disbursable', 'issuer_id' => (string) $issuer->id]])->toArray(), expiresAt: now()->addWeek());
    app(TreasuryPayCodeAccountingService::class)->reserve(accountOwner: $issuer, voucher: $voucher, connectionReference: 'paynamics-primary', providerPrincipalMinor: 2000, currency: 'PHP');

    config()->set('constellation.base_url', 'https://provider.invalid');
    config()->set('constellation.username', 'test-user');
    config()->set('constellation.password', 'test-pass');
    config()->set('constellation.merchant_key', 'test-key');
    config()->set('constellation.log_channel', 'single');
    config()->set('constellation.settlement_wallet_id', 'test-wallet');
    config()->set('constellation.bank_map.GXCHPHM2XXX', 'gcash-bank');
    Http::preventStrayRequests();
    Http::fake([
        '*/integration/corp_wallet/view_wallet/*' => Http::response(['success' => true, 'data' => ['wallet_id' => 'test-wallet', 'account_id' => 'test-account']]),
        '*/integration/corp_wallet/request_otp' => Http::response(['success' => true, 'data' => 'OTP requested']),
        '*/integration/corp_wallet/withdraw_not_registered' => Http::response(['success' => true, 'data' => ['status' => 'COMPLETED']]),
    ]);
    app()->instance(ConstellationOtpResolver::class, app(DeferredOtpResolver::class));
    app()->instance(PayoutProvider::class, app(ConstellationPayoutProvider::class));
    $reference = $voucher->code.'-09173011987-S1';
    $payload = ['mobile' => '09173011987', 'recipient_country' => 'PH', 'bank_account' => ['bank_code' => 'GXCHPHM2XXX', 'account_number' => '09173011987'], '_meta' => ['idempotency_key' => 'paynamics-treasury-approval']];
    expect(app(SubmitPayCodeClaim::class)->handle($voucher, $payload)->status)->toBe('approval_required');
    expect(app(PaynamicsWithdrawalOtpApprovalService::class)->verify('09173011987', $reference, '123456'))->toBeTrue();
    $payload['approval'] = ['resume' => true, 'provider' => 'paynamics', 'reference_id' => $reference];
    $payload['otp'] = ['verified' => true];
    $result = app(SubmitPayCodeClaim::class)->handle($voucher->refresh(), $payload);
    expect($result->status)->toBe('succeeded')->and(data_get($voucher->refresh()->metadata, 'treasury.pay_code_reservation.status'))->toBe('settled');
    Http::assertSentCount(4);
});
