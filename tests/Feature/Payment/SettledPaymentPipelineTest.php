<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Queue;
use LBHurtado\Contact\Models\Contact;
use LBHurtado\EmiCore\Actions\Funding\RecordProviderFundingObservation;
use LBHurtado\EmiCore\Data\Funding\ProviderFundingObservationData;
use LBHurtado\EmiCore\Data\Funding\ProviderPayerIdentityData;
use LBHurtado\EmiCore\Models\ProviderFundingObservation;
use LBHurtado\PaymentGateway\Funding\NetbankFundingProviderAdapter;
use LBHurtado\XChange\Actions\Payment\RecordSettledPayment;
use LBHurtado\XChange\Actions\Payment\RecoverSettledPayerIdentity;
use LBHurtado\XChange\Actions\Payment\RunSettledPaymentPipeline;
use LBHurtado\XChange\Jobs\Payment\ProcessSettledPaymentJob;
use LBHurtado\XChange\Jobs\Payment\RecoverSettledPayerIdentityJob;
use LBHurtado\XChange\Models\FundingIntent;
use LBHurtado\XChange\Models\FundingSettlement;
use LBHurtado\XChange\Models\SettledPaymentProcess;
use LBHurtado\XFeedback\Models\FeedbackDeliveryRecord;

it('records a settled payment once and snapshots the SMS policy', function () {
    Queue::fake();
    config()->set('x-change.payment_feedback.sms.enabled', true);
    config()->set('x-change.payment_feedback.sms.flows.top_up', false);

    $recorder = app(RecordSettledPayment::class);
    $first = $recorder->handle('funding_settlement', 912, 427, 'top_up', 5000);
    config()->set('x-change.payment_feedback.sms.flows.top_up', true);
    $second = $recorder->handle('funding_settlement', 912, 427, 'top_up', 5000);

    expect($first->getKey())->toBe($second->getKey())
        ->and($first->sms_enabled_snapshot)->toBeFalse()
        ->and(SettledPaymentProcess::query()->count())->toBe(1);
    Queue::assertPushed(ProcessSettledPaymentJob::class, 1);
});

it('snapshots authenticated issuance SMS independently of other payment flows', function () {
    Queue::fake();
    config()->set('x-change.payment_feedback.sms.enabled', true);
    config()->set('x-change.payment_feedback.sms.flows.authenticated_issuance', true);

    $process = app(RecordSettledPayment::class)->handle(
        'funding_settlement', 914, 429, 'authenticated_issuance', 5201,
    );

    expect($process->sms_enabled_snapshot)->toBeTrue();
    Queue::assertPushed(ProcessSettledPaymentJob::class, 1);
});

it('runs configured settled payment pipes once', function () {
    $process = SettledPaymentProcess::query()->create([
        'payment_type' => 'funding_settlement',
        'payment_id' => 913,
        'observation_id' => 428,
        'flow' => 'top_up',
        'gross_amount_minor' => 5000,
        'sms_enabled_snapshot' => false,
    ]);
    $calls = 0;
    config()->set('x-change.payment_feedback.pipes', [function (SettledPaymentProcess $payment, Closure $next) use (&$calls): mixed {
        $calls++;

        return $next($payment);
    }]);

    app(RunSettledPaymentPipeline::class)->handle($process->getKey());
    app(RunSettledPaymentPipeline::class)->handle($process->getKey());

    expect($calls)->toBe(1)
        ->and($process->fresh()->status)->toBe('completed');
});

it('requeues only pending payment processes for recovery', function () {
    Queue::fake();
    foreach (['pending', 'completed'] as $offset => $status) {
        SettledPaymentProcess::query()->create([
            'payment_type' => 'funding_settlement',
            'payment_id' => 920 + $offset,
            'observation_id' => 430 + $offset,
            'flow' => 'top_up',
            'gross_amount_minor' => 5000,
            'sms_enabled_snapshot' => false,
            'status' => $status,
        ]);
    }

    $this->artisan('xchange:payment-feedback:replay')->assertSuccessful();

    Queue::assertPushed(ProcessSettledPaymentJob::class, 1);
});

it('recovers a later NetBank wallet payer for an already settled payment exactly once', function () {
    Queue::fake();
    config()->set('x-feedback.transports.sms.driver', 'engagespark');
    $original = settledPaymentObservationWithoutPayer();
    $process = settledPaymentProcessForRecovery($original);

    app(RunSettledPaymentPipeline::class)->handle($process->getKey());

    expect($process->fresh()->status)->toBe('completed')
        ->and($process->fresh()->sms_status)->toBe('skipped')
        ->and($process->fresh()->contact_id)->toBeNull();
    Queue::assertPushed(RecoverSettledPayerIdentityJob::class, 1);

    $stillMissing = recoveredPayerObservation($original);
    $stillMissing->payerIdentity = null;
    $provider = Mockery::mock(NetbankFundingProviderAdapter::class);
    $provider->shouldReceive('verifyFunding')->twice()->andReturn($stillMissing, recoveredPayerObservation($original));
    app()->instance(NetbankFundingProviderAdapter::class, $provider);

    $recovery = app(RecoverSettledPayerIdentity::class);
    expect($recovery->handle($process->getKey()))->toBeFalse()
        ->and($process->fresh()->observation_id)->toBe($original->getKey())
        ->and($recovery->handle($process->getKey()))->toBeTrue()
        ->and($recovery->handle($process->getKey()))->toBeTrue()
        ->and($process->fresh()->observation_id)->not->toBe($original->getKey())
        ->and($process->fresh()->contact_source)->toBe('provider_reported')
        ->and($process->fresh()->sms_status)->toBe('queued')
        ->and(FeedbackDeliveryRecord::query()->where('intent_key', 'payment.settled')->count())->toBe(1);
});

it('does not recover payer identity from a different provider transaction', function () {
    Queue::fake();
    $original = settledPaymentObservationWithoutPayer();
    $process = settledPaymentProcessForRecovery($original);
    $process->forceFill(['sms_status' => 'skipped', 'status' => 'completed'])->save();
    $different = recoveredPayerObservation($original);
    $different->providerTransactionId = 'another-transaction';
    $provider = Mockery::mock(NetbankFundingProviderAdapter::class);
    $provider->shouldReceive('verifyFunding')->once()->andReturn($different);
    app()->instance(NetbankFundingProviderAdapter::class, $provider);

    expect(app(RecoverSettledPayerIdentity::class)->handle($process->getKey()))->toBeTrue()
        ->and($process->fresh()->observation_id)->toBe($original->getKey())
        ->and($process->fresh()->contact_id)->toBeNull()
        ->and(FeedbackDeliveryRecord::query()->count())->toBe(0);
});

it('offers an explicit replay for a previously skipped payer check', function () {
    Queue::fake();
    $process = settledPaymentProcessForRecovery(settledPaymentObservationWithoutPayer());
    $process->forceFill(['sms_status' => 'skipped', 'status' => 'completed'])->save();

    $this->artisan('xchange:payment-feedback:replay', [
        '--recover-payer' => (string) $process->getKey(),
    ])->assertSuccessful();

    Queue::assertPushed(RecoverSettledPayerIdentityJob::class, 1);
    Queue::assertNotPushed(ProcessSettledPaymentJob::class);
});

it('resumes SMS without another provider read after contact association was saved', function () {
    Queue::fake();
    config()->set('x-feedback.transports.sms.driver', 'engagespark');
    $original = settledPaymentObservationWithoutPayer();
    $process = settledPaymentProcessForRecovery($original);
    $enrichedData = recoveredPayerObservation($original);
    $enrichedData->metadata['normalization_version'] = 'netbank-incoming-credit-v2-payer-recovery-v1';
    $enriched = app(RecordProviderFundingObservation::class)->handle($enrichedData);
    $contact = Contact::fromPhoneNumber(phone('09171234567', 'PH'));
    $process->forceFill([
        'observation_id' => $enriched->getKey(),
        'contact_id' => $contact->getKey(),
        'contact_source' => 'provider_reported',
        'sms_status' => 'skipped',
        'status' => 'completed',
    ])->save();
    $provider = Mockery::mock(NetbankFundingProviderAdapter::class);
    $provider->shouldNotReceive('verifyFunding');
    app()->instance(NetbankFundingProviderAdapter::class, $provider);

    expect(app(RecoverSettledPayerIdentity::class)->handle($process->getKey()))->toBeTrue()
        ->and($process->fresh()->sms_status)->toBe('queued')
        ->and(FeedbackDeliveryRecord::query()->count())->toBe(1);
});

function settledPaymentObservationWithoutPayer(): ProviderFundingObservation
{
    return app(RecordProviderFundingObservation::class)->handle(new ProviderFundingObservationData(
        provider: 'netbank',
        providerTransactionId: 'late-payer-transaction',
        grossAmountMinor: 52100,
        feeAmountMinor: 0,
        netAmountMinor: 52100,
        currency: 'PHP',
        providerStatus: 'settled',
        verificationSource: 'netbank-vca-transaction-history',
        payloadHash: hash('sha256', 'late-payer-enriched-payload'),
        fundingAddress: 'sha256:'.hash('sha256', 'late-payer-vca'),
        settledAt: now()->toDateTimeImmutable(),
        metadata: ['destination_verified' => true, 'normalization_version' => 'netbank-incoming-credit-v2'],
    ));
}

function settledPaymentProcessForRecovery(ProviderFundingObservation $observation): SettledPaymentProcess
{
    $intent = FundingIntent::query()->create([
        'account_reference' => 'late-payer-account',
        'provider_code' => 'netbank',
        'purpose' => 'on_demand_issuance',
        'expected_amount_minor' => 52100,
        'currency' => 'PHP',
        'status' => 'settled',
        'idempotency_key_hash' => hash('sha256', 'late-payer-intent'),
        'idempotency_fingerprint' => str_repeat('e', 64),
        'created_by_type' => 'test',
        'created_by_id' => 'late-payer-actor',
        'funding_address_ciphertext' => 'late-payer-vca',
        'destination_snapshot_ciphertext' => [
            'provider' => 'netbank',
            'mode' => 'account',
            'destinationType' => 'corporate_account',
            'accountReference' => 'late-payer-account',
            'displayReference' => 'late-payer-account',
            'fingerprint' => hash('sha256', 'late-payer-account'),
            'verificationStatus' => 'verified',
        ],
        'expires_at' => now()->addMinutes(30),
    ]);
    $settlement = FundingSettlement::query()->create([
        'funding_intent_id' => $intent->getKey(),
        'provider_funding_observation_id' => $observation->getKey(),
        'provider_code' => 'netbank',
        'account_reference' => 'late-payer-account',
        'gross_amount_minor' => 52100,
        'net_amount_minor' => 52100,
        'currency' => 'PHP',
        'treasury_inventory_reference' => 'late-payer-inventory',
        'treasury_operation_reference' => 'late-payer-operation',
        'wallet_transaction_id' => 123457,
        'wallet_transaction_uuid' => '12345678-1234-1234-1234-123456789013',
        'settled_at' => now(),
    ]);

    return SettledPaymentProcess::query()->create([
        'payment_type' => 'funding_settlement',
        'payment_id' => $settlement->getKey(),
        'observation_id' => $observation->getKey(),
        'flow' => 'authenticated_issuance',
        'gross_amount_minor' => 52100,
        'sms_enabled_snapshot' => true,
    ]);
}

function recoveredPayerObservation(ProviderFundingObservation $original): ProviderFundingObservationData
{
    return new ProviderFundingObservationData(
        provider: 'netbank',
        providerTransactionId: $original->provider_transaction_id,
        grossAmountMinor: 52100,
        feeAmountMinor: 0,
        netAmountMinor: 52100,
        currency: 'PHP',
        providerStatus: 'settled',
        verificationSource: 'netbank-vca-transaction-history',
        payloadHash: hash('sha256', 'late-payer-enriched-payload'),
        fundingAddress: $original->funding_address,
        settledAt: now()->toDateTimeImmutable(),
        metadata: ['destination_verified' => true, 'normalization_version' => 'netbank-incoming-credit-v2'],
        payerIdentity: new ProviderPayerIdentityData(
            name: 'Wallet Payer',
            accountNumber: '09171234567',
            institutionCode: 'GXCHPHM2XXX',
            providerVerified: false,
        ),
    );
}
