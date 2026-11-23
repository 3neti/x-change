<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use LBHurtado\EmiCore\Contracts\SpecificIncomingPaymentVerifier;
use LBHurtado\EmiCore\Data\Funding\FundingVerificationData;
use LBHurtado\EmiCore\Data\Funding\ProviderFundingObservationData;
use LBHurtado\XChange\Actions\Funding\CreateFundingIntent;
use LBHurtado\XChange\Actions\Funding\MonitorDirectFundingExceptions;
use LBHurtado\XChange\Data\Funding\CreateFundingIntentData;
use LBHurtado\XChange\Data\Payment\ObservedPaymentTransactionData;
use LBHurtado\XChange\Enums\FundingIntentStatus;
use LBHurtado\XChange\Enums\FundingVerificationTrigger;
use LBHurtado\XChange\Jobs\Funding\VerifyFundingIntentJob;
use LBHurtado\XChange\Models\FundingIntent;
use LBHurtado\XChange\Services\Funding\FundingProviderAdapterRegistry;
use LBHurtado\XChange\Tests\Fakes\FakeFundingProviderAdapter;

function directMonitorIntent(FundingIntentStatus $status): FundingIntent
{
    $intent = app(CreateFundingIntent::class)->handle(new CreateFundingIntentData(
        accountReference: 'wallet:account-1001', provider: 'netbank', expectedAmountMinor: 2500,
        currency: 'PHP', idempotencyKey: 'monitor-'.uniqid(), actorType: 'test', actorId: '42',
        expiresAt: now()->addHour()->toDateTimeImmutable(),
    ));
    $intent->forceFill([
        'status' => $status,
        'instructions_created_at' => now()->subDay(),
        'funding_address_ciphertext' => '896280000001',
        'provider_transaction_id' => $status === FundingIntentStatus::Settled ? 'original' : null,
        'destination_snapshot_ciphertext' => [
            'provider' => 'netbank', 'mode' => 'dedicated', 'destinationType' => 'bank_account',
            'accountReference' => 'wallet:account-1001', 'displayReference' => 'account', 'fingerprint' => 'fingerprint',
            'verificationStatus' => 'verified', 'bankAccountNumber' => '113-001-00006-8',
            'metadata' => ['netbank_instruction_mode' => 'direct_qr'],
        ],
        'metadata' => ['direct_qr_monitor' => true],
    ])->saveQuietly();

    return $intent->refresh();
}

function monitorAdapter(): FakeFundingProviderAdapter
{
    return new class extends FakeFundingProviderAdapter implements SpecificIncomingPaymentVerifier
    {
        public bool $destinationVerified = true;

        public string $status = 'settled';

        public int $specificChecks = 0;

        public function verifyIncomingPayment(FundingVerificationData $verification, string $providerTransactionId): ProviderFundingObservationData
        {
            $this->specificChecks++;

            return new ProviderFundingObservationData(provider: 'netbank', providerTransactionId: $providerTransactionId,
                grossAmountMinor: 2500, feeAmountMinor: 0, netAmountMinor: 2500, currency: 'PHP',
                providerStatus: $this->status, verificationSource: 'test', payloadHash: hash('sha256', $providerTransactionId),
                metadata: ['destination_verified' => $this->destinationVerified]);
        }
    };
}

beforeEach(function (): void {
    config()->set('x-change.funding.providers.netbank.enabled', true);
});

it('reviews an additional payment on settled funding exactly once without altering settlement', function (): void {
    $intent = directMonitorIntent(FundingIntentStatus::Settled);
    $adapter = monitorAdapter();
    $adapter->incomingPayments = [
        new ObservedPaymentTransactionData('original', 2500, 'PHP', 'settled'),
        new ObservedPaymentTransactionData('additional', 2500, 'PHP', 'settled'),
    ];
    app()->instance(FundingProviderAdapterRegistry::class, new FundingProviderAdapterRegistry([$adapter]));

    app(MonitorDirectFundingExceptions::class)->handle($intent);
    app(MonitorDirectFundingExceptions::class)->handle($intent);

    expect($intent->refresh()->status)->toBe(FundingIntentStatus::Settled)
        ->and($intent->provider_transaction_id)->toBe('original')
        ->and($intent->suspenseCases()->count())->toBe(1)
        ->and($intent->suspenseCases()->first()->details['automatic_credit'])->toBeFalse()
        ->and($adapter->lastVerification->destination->bankAccountNumber)->toBe('113-001-00006-8');
});

it('sends a late payment to review without settling funding', function (FundingIntentStatus $status): void {
    $intent = directMonitorIntent($status);
    $adapter = monitorAdapter();
    $adapter->incomingPayments = [new ObservedPaymentTransactionData('late', 2500, 'PHP', 'settled')];
    app()->instance(FundingProviderAdapterRegistry::class, new FundingProviderAdapterRegistry([$adapter]));

    app(MonitorDirectFundingExceptions::class)->handle($intent);
    app(MonitorDirectFundingExceptions::class)->handle($intent);

    expect($intent->refresh()->status)->toBe(FundingIntentStatus::Suspense)
        ->and($intent->settlement()->exists())->toBeFalse()
        ->and($intent->suspenseCases()->count())->toBe(1);
})->with([FundingIntentStatus::Expired, FundingIntentStatus::Cancelled]);

it('does not open a case from pending or unverified destination evidence', function (): void {
    $intent = directMonitorIntent(FundingIntentStatus::Expired);
    $adapter = monitorAdapter();
    $adapter->incomingPayments = [new ObservedPaymentTransactionData('late', 2500, 'PHP', 'pending')];
    $adapter->status = 'pending';
    app()->instance(FundingProviderAdapterRegistry::class, new FundingProviderAdapterRegistry([$adapter]));
    app(MonitorDirectFundingExceptions::class)->handle($intent);
    expect($intent->refresh()->status)->toBe(FundingIntentStatus::Expired)->and($intent->suspenseCases()->count())->toBe(0);
    $adapter->status = 'settled';
    $adapter->destinationVerified = false;
    expect(fn () => app(MonitorDirectFundingExceptions::class)->handle($intent))->toThrow(LogicException::class);
    expect($intent->refresh()->status)->toBe(FundingIntentStatus::Expired)->and($intent->suspenseCases()->count())->toBe(0);
});

it('runs closed direct QR monitoring through the scheduled verification job', function (): void {
    $intent = directMonitorIntent(FundingIntentStatus::Settled);
    $adapter = monitorAdapter();
    $adapter->incomingPayments = [new ObservedPaymentTransactionData('additional', 2500, 'PHP', 'settled')];
    app()->instance(FundingProviderAdapterRegistry::class, new FundingProviderAdapterRegistry([$adapter]));

    $job = new VerifyFundingIntentJob(
        fundingIntentId: (int) $intent->getKey(), providerCode: 'netbank',
        trigger: FundingVerificationTrigger::Schedule, actorId: 'funding-scheduler',
    );
    app()->call([$job, 'handle']);

    expect($intent->refresh()->status)->toBe(FundingIntentStatus::Settled)
        ->and($intent->suspenseCases()->count())->toBe(1)
        ->and($adapter->specificChecks)->toBe(2);
});

it('records a failed monitoring attempt and does not accept an unverified destination', function (): void {
    $intent = directMonitorIntent(FundingIntentStatus::Expired);
    $adapter = monitorAdapter();
    $adapter->destinationVerified = false;
    $adapter->incomingPayments = [new ObservedPaymentTransactionData('unexpected', 2500, 'PHP', 'settled')];
    app()->instance(FundingProviderAdapterRegistry::class, new FundingProviderAdapterRegistry([$adapter]));

    expect(fn () => app(MonitorDirectFundingExceptions::class)->handle($intent))->toThrow(LogicException::class);
    expect(DB::table('x_change_funding_reconciliation_checks')
        ->where('funding_intent_id', $intent->getKey())->value('last_outcome'))->toBe('direct_qr_monitor_failed');
});

it('refuses to monitor a non-direct destination even if its scheduler marker is set', function (): void {
    $intent = directMonitorIntent(FundingIntentStatus::Expired);
    $snapshot = $intent->destination_snapshot_ciphertext;
    $snapshot['metadata']['netbank_instruction_mode'] = 'registered_vca';
    $intent->forceFill(['destination_snapshot_ciphertext' => $snapshot])->saveQuietly();
    $adapter = monitorAdapter();
    app()->instance(FundingProviderAdapterRegistry::class, new FundingProviderAdapterRegistry([$adapter]));

    expect(fn () => app(MonitorDirectFundingExceptions::class)->handle($intent))->toThrow(LogicException::class);
    expect($adapter->incomingPaymentCalls)->toBe(0)
        ->and($intent->refresh()->status)->toBe(FundingIntentStatus::Expired);
});
