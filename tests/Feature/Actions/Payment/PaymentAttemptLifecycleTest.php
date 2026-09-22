<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use LBHurtado\EmiCore\Data\Funding\FundingQrMerchantData;
use LBHurtado\EmiCore\Data\Funding\ProviderFundingObservationData;
use LBHurtado\Merchant\Contracts\MerchantProfileRepositoryContract;
use LBHurtado\PaymentGateway\Funding\NetbankFundingApiClient;
use LBHurtado\PaymentGateway\Funding\NetbankFundingProviderAdapter;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\Voucher\Services\ExecutionEngine;
use LBHurtado\XChange\Actions\Payment\CreatePaymentAttempt;
use LBHurtado\XChange\Actions\Payment\IssuePaymentInstructions;
use LBHurtado\XChange\Actions\Payment\MonitorPaymentAttempt;
use LBHurtado\XChange\Actions\Payment\RecordObservedPaymentTransaction;
use LBHurtado\XChange\Actions\Payment\RecordVoucherCollection;
use LBHurtado\XChange\Actions\Payment\SettleVerifiedPaymentAttempt;
use LBHurtado\XChange\Actions\Payment\VerifyPaymentAttempt;
use LBHurtado\XChange\Data\Payment\ObservedPaymentTransactionData;
use LBHurtado\XChange\Data\Payment\PaymentObservationNoticeData;
use LBHurtado\XChange\Data\Payment\VoucherPaymentResultData;
use LBHurtado\XChange\Enums\PaymentAttemptStatus;
use LBHurtado\XChange\Enums\PaymentVerificationTrigger;
use LBHurtado\XChange\Events\PaymentTransactionObserved;
use LBHurtado\XChange\Models\ObservedPaymentTransaction;
use LBHurtado\XChange\Models\PaymentAttempt;
use LBHurtado\XChange\Models\VoucherCollection;
use LBHurtado\XChange\Services\Funding\FundingProviderAdapterRegistry;
use LBHurtado\XChange\Services\Funding\FundingQrMerchantProfileResolver;
use LBHurtado\XChange\Support\Funding\FundingMerchantSnapshot;
use LBHurtado\XChange\Tests\Fakes\FakeFundingProviderAdapter;
use LBHurtado\XChange\Tests\Fakes\User;
use LBHurtado\XJournal\Models\ExecutionJournalEntry;

beforeEach(function (): void {
    config()->set('x-change.funding.providers.netbank.enabled', true);
    config()->set('x-change.payment.attempts.expires_after_minutes', 15);

    $this->paymentAdapter = new FakeFundingProviderAdapter;
    $this->app->instance(FakeFundingProviderAdapter::class, $this->paymentAdapter);
    $this->app->tag(FakeFundingProviderAdapter::class, 'emi.funding-provider-adapters');
    $this->app->forgetInstance(FundingProviderAdapterRegistry::class);
});

it('records multiple provider payments per QR without duplicating or exposing payer identity', function (): void {
    $voucher = paymentAttemptCollectibleVoucher();
    $attempt = app(CreatePaymentAttempt::class)->handle($voucher, 'netbank', 'monitor-session', 'monitor-request');
    $record = app(RecordObservedPaymentTransaction::class);

    $firstData = new ObservedPaymentTransactionData(
        providerTransactionId: 'netbank-credit-1',
        amountMinor: 2500,
        currency: 'PHP',
        providerStatus: 'settled',
        payerName: 'Apple Hurtado',
        payerAccountNumber: '09175180722',
        payerInstitutionCode: 'GCASH',
    );
    $first = $record->handle($attempt, $firstData);
    $replay = $record->handle($attempt, $firstData);
    $second = $record->handle($attempt, new ObservedPaymentTransactionData(
        providerTransactionId: 'netbank-credit-2',
        amountMinor: 5000,
        currency: 'PHP',
        providerStatus: 'settled',
        payerMobile: '09175180722',
    ));
    $raw = DB::table('x_change_observed_payment_transactions')->find($first->getKey());

    expect($replay->is($first))->toBeTrue()
        ->and($attempt->observedPayments()->count())->toBe(2)
        ->and($second->amount_minor)->toBe(5000)
        ->and($first->payer_mobile_ciphertext)->toBeNull()
        ->and($raw->payer_name_ciphertext)->not->toContain('Apple Hurtado')
        ->and($raw->payer_account_ciphertext)->not->toContain('09175180722')
        ->and($raw->provider_transaction_id_ciphertext)->not->toContain('netbank-credit-1')
        ->and(ExecutionJournalEntry::query()->where('event_type', 'payment.provider_observed')->count())->toBe(2)
        ->and(json_encode(ExecutionJournalEntry::query()->where('event_type', 'payment.provider_observed')->firstOrFail()->toArray()))
        ->not->toContain('Apple Hurtado')
        ->not->toContain('09175180722')
        ->and(array_key_exists('payer_account_ciphertext', $first->toArray()))->toBeFalse();
});

it('refuses to reattribute an observed provider transaction to another QR', function (): void {
    $voucher = paymentAttemptCollectibleVoucher();
    $first = app(CreatePaymentAttempt::class)->handle($voucher, 'netbank', 'monitor-first', 'monitor-first');
    $second = app(CreatePaymentAttempt::class)->handle($voucher, 'netbank', 'monitor-second', 'monitor-second');
    $payment = new ObservedPaymentTransactionData('same-provider-credit', 2500, 'PHP', 'settled');
    app(RecordObservedPaymentTransaction::class)->handle($first, $payment);

    expect(fn () => app(RecordObservedPaymentTransaction::class)->handle($second, $payment))
        ->toThrow(LogicException::class)
        ->and(ObservedPaymentTransaction::query()->count())->toBe(1);
});

it('records provider status changes once without altering collection state', function (): void {
    $voucher = paymentAttemptCollectibleVoucher();
    $attempt = app(CreatePaymentAttempt::class)->handle($voucher, 'netbank', 'status-session', 'status-request');
    $record = app(RecordObservedPaymentTransaction::class);
    $pending = $record->handle($attempt, new ObservedPaymentTransactionData('changing-credit', 2500, 'PHP', 'pending'));
    $settled = $record->handle($attempt, new ObservedPaymentTransactionData('changing-credit', 2500, 'PHP', 'settled'));
    $record->handle($attempt, new ObservedPaymentTransactionData('changing-credit', 2500, 'PHP', 'settled'));
    $record->handle($attempt, new ObservedPaymentTransactionData('changing-credit', 2500, 'PHP', 'pending'));

    expect($settled->is($pending))->toBeTrue()
        ->and($pending->statuses()->pluck('provider_status')->all())->toBe(['pending', 'settled', 'pending'])
        ->and(ExecutionJournalEntry::query()->where('event_type', 'payment.provider_observed')->count())->toBe(3)
        ->and($attempt->fresh()->status)->toBe(PaymentAttemptStatus::PendingInstructions)
        ->and(DB::table('voucher_collections')->count())->toBe(0);

    expect(fn () => $record->handle($attempt, new ObservedPaymentTransactionData('changing-credit', 2600, 'PHP', 'settled')))
        ->toThrow(LogicException::class);
});

it('monitors multiple credits on a settled QR without invoking settlement', function (): void {
    $voucher = paymentAttemptCollectibleVoucher();
    $attempt = app(IssuePaymentInstructions::class)->handle(app(CreatePaymentAttempt::class)->handle(
        $voucher, 'netbank', 'monitor-settled-session', 'monitor-settled-request',
    ));
    PaymentAttempt::query()->whereKey($attempt->getKey())->update(['status' => PaymentAttemptStatus::Settled]);
    $attempt = $attempt->fresh();
    $this->paymentAdapter->incomingPayments = [
        new ObservedPaymentTransactionData('incoming-1', 2500, 'PHP', 'settled'),
        new ObservedPaymentTransactionData('incoming-2', 5000, 'PHP', 'settled'),
    ];

    $first = app(MonitorPaymentAttempt::class)->handle($attempt);
    $replay = app(MonitorPaymentAttempt::class)->handle($attempt);

    expect($first)->toBe(2)
        ->and($replay)->toBe(2)
        ->and($this->paymentAdapter->incomingPaymentCalls)->toBe(2)
        ->and(ObservedPaymentTransaction::query()->count())->toBe(2)
        ->and($attempt->fresh()->last_monitored_at)->not->toBeNull()
        ->and($attempt->fresh()->status)->toBe(PaymentAttemptStatus::Settled)
        ->and(DB::table('voucher_collections')->count())->toBe(0);
});

it('integrates the local NetBank reader with x-change payment persistence', function (): void {
    expect(method_exists(NetbankFundingProviderAdapter::class, 'incomingPayments'))->toBeTrue();

    config()->set('payment-gateway.netbank.funding.corporate_account_number', '113-001-00001-9');
    config()->set('payment-gateway.netbank.funding.corporate_account_name', 'X-Change');
    config()->set('payment-gateway.netbank.funding.vca_alias', '91500');
    $voucher = paymentAttemptCollectibleVoucher();
    $attempt = app(IssuePaymentInstructions::class)->handle(app(CreatePaymentAttempt::class)->handle(
        $voucher, 'netbank', 'reader-session', 'reader-request',
    ));
    $vca = $attempt->funding_address_ciphertext;
    $client = Mockery::mock(NetbankFundingApiClient::class);
    $client->shouldReceive('allTransactions')->once()->with($vca, '113-001-00001-9')
        ->andReturn((function () use ($vca): Generator {
            foreach ([['credit-1', 2500], ['credit-2', 5000]] as [$id, $amount]) {
                yield [
                    'transaction_id' => $id,
                    'type' => 'Credit',
                    'description' => 'EXTERNAL_TRANSFER_INCOMING',
                    'destination_account' => ['account_alias' => $vca],
                    'amount' => ['num' => (string) $amount, 'cur' => 'PHP'],
                    'status' => 'SETTLED',
                    'date' => now()->toIso8601String(),
                    'sender_name' => 'Apple Hurtado',
                    'source_account' => ['account_number' => '09175180722', 'bank_code' => 'GXCHPHM2XXX'],
                ];
            }
        })());
    $this->app->instance(FundingProviderAdapterRegistry::class, new FundingProviderAdapterRegistry([
        new NetbankFundingProviderAdapter($client),
    ]));

    expect(app(MonitorPaymentAttempt::class)->handle($attempt))->toBe(2)
        ->and($attempt->observedPayments()->count())->toBe(2)
        ->and($attempt->observedPayments()->firstOrFail()->payer_mobile_ciphertext)->toBeNull()
        ->and($attempt->fresh()->status)->toBe(PaymentAttemptStatus::AwaitingPayment)
        ->and(DB::table('voucher_collections')->count())->toBe(0);
});

it('does not call NetBank after the QR observation window closes', function (): void {
    $voucher = paymentAttemptCollectibleVoucher();
    $attempt = app(IssuePaymentInstructions::class)->handle(app(CreatePaymentAttempt::class)->handle(
        $voucher, 'netbank', 'monitor-expired-session', 'monitor-expired-request',
    ));
    PaymentAttempt::query()->whereKey($attempt->getKey())->update(['expires_at' => now()->subMinutes(10)]);

    expect(app(MonitorPaymentAttempt::class)->handle($attempt->fresh()))->toBe(0)
        ->and($this->paymentAdapter->incomingPaymentCalls)->toBe(0);
});

it('keeps payer identity out of the private broadcast payload', function (): void {
    $payment = new ObservedPaymentTransactionData(
        'private-transaction', 2500, 'PHP', 'settled',
        payerName: 'Apple Hurtado', payerAccountNumber: '09175180722',
    );
    $event = new PaymentTransactionObserved('App\\Models\\User', '123', new PaymentObservationNoticeData(
        1, 2, 3, $payment->providerStatus, 'provider_payment_observed', now()->toIso8601String(),
    ));
    $payload = json_encode($event->broadcastWith(), JSON_THROW_ON_ERROR);

    expect($payload)->not->toContain('Apple Hurtado')
        ->not->toContain('09175180722')
        ->not->toContain('private-transaction');

    expect(json_encode($event, JSON_THROW_ON_ERROR))->not->toContain('Apple Hurtado')
        ->not->toContain('09175180722')
        ->not->toContain('private-transaction');
});

it('creates an exact Payment Attempt for the collectible balance', function (): void {
    $voucher = paymentAttemptCollectibleVoucher();

    recordPaymentAttemptCollection($voucher, 25.00);

    $attempt = app(CreatePaymentAttempt::class)->handle(
        voucher: $voucher,
        provider: 'netbank',
        browserKey: 'payer-session-1',
        idempotencyKey: 'request-1',
    );

    expect($attempt->status)->toBe(PaymentAttemptStatus::PendingInstructions)
        ->and($attempt->expected_amount_minor)->toBe(7500)
        ->and($attempt->currency)->toBe('PHP')
        ->and($attempt->session_key_hash)->toHaveLength(64)
        ->and($attempt->session_key_hash)->not->toContain('payer-session-1')
        ->and($attempt->events)->toHaveCount(1)
        ->and($attempt->events->first()->event_type)->toBe('created');
});

it('replays creation idempotently without creating a second attempt', function (): void {
    $voucher = paymentAttemptCollectibleVoucher();
    $create = app(CreatePaymentAttempt::class);

    $first = $create->handle($voucher, 'netbank', 'payer-session-1', 'request-1');
    $replay = $create->handle($voucher, 'netbank', 'payer-session-1', 'request-1');

    expect($replay->is($first))->toBeTrue()
        ->and(PaymentAttempt::query()->where('voucher_id', $voucher->getKey())->count())->toBe(1);
});

it('issues encrypted provider QR instructions once without Account funding', function (): void {
    $voucher = paymentAttemptCollectibleVoucher();
    $attempt = app(CreatePaymentAttempt::class)->handle(
        $voucher,
        'netbank',
        'payer-session-1',
        'request-1',
    );

    $issued = app(IssuePaymentInstructions::class)->handle($attempt);
    $retry = app(IssuePaymentInstructions::class)->handle($attempt);
    $raw = DB::table('x_change_payment_attempts')->find($attempt->getKey());

    expect($issued->status)->toBe(PaymentAttemptStatus::AwaitingPayment)
        ->and($issued->version)->toBe(2)
        ->and($issued->instructions_ciphertext['qr_code'])->toMatchArray([
            'mime_type' => 'image/png',
            'qr_mode' => 'dynamic',
            'transaction_type' => 'p2m',
            'embedded_amount' => true,
            'provider_generated' => true,
        ])
        ->and($issued->provider_request_id_ciphertext)->toBe('915001234567890123456')
        ->and($raw->provider_request_id_ciphertext)->not->toContain('915001234567890123456')
        ->and($raw->instructions_ciphertext)->not->toContain('iVBORw0KGgo')
        ->and($retry->getKey())->toBe($issued->getKey())
        ->and($retry->events)->toHaveCount(2)
        ->and($this->paymentAdapter->instructionCalls)->toBe(1)
        ->and(DB::table('x_change_funding_intents')->count())->toBe(0)
        ->and(DB::table('x_change_account_funding_receipts')->count())->toBe(0);
});

it('issues a provisional NetBank payer QR with one provider call and no VCA registration', function (): void {
    config()->set('payment-gateway.netbank.funding.reference_key', 'test-payment-reference-key');
    config()->set('payment-gateway.netbank.funding.pre_transaction_validation_enabled', true);
    config()->set('payment-gateway.netbank.funding.exact_limits_enabled', true);
    $qrPayload = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAFgwJ/lDoLpwAAAABJRU5ErkJggg==';
    $user = actingAsTestUser();
    app(MerchantProfileRepositoryContract::class)->updateForUser($user, [
        'name' => 'Lester Store',
        'city' => 'Makati',
        'merchant_category_code' => '5999',
        'merchant_name_template' => '{name}',
    ]);
    $expectedMerchant = app(FundingQrMerchantProfileResolver::class)->resolve($user);
    $client = Mockery::mock(NetbankFundingApiClient::class);
    $client->shouldNotReceive('generateAliasToken');
    $client->shouldNotReceive('registerPreTransactionReference');
    $client->shouldNotReceive('createExactLimit');
    $client->shouldReceive('generateQrCode')
        ->once()
        ->withArgs(fn (string $vcaNumber, int $amountMinor, string $currency, FundingQrMerchantData $merchant): bool => preg_match('/\A91500\d{16}\z/', $vcaNumber) === 1
            && $amountMinor === 10000
            && $currency === 'PHP'
            && FundingMerchantSnapshot::fromData($merchant) === FundingMerchantSnapshot::fromData($expectedMerchant))
        ->andReturn($qrPayload);

    $this->app->instance(NetbankFundingApiClient::class, $client);
    $this->app->instance(
        FundingProviderAdapterRegistry::class,
        new FundingProviderAdapterRegistry([
            new NetbankFundingProviderAdapter($client),
        ]),
    );

    $voucher = paymentAttemptCollectibleVoucherForUser($user);
    $attempt = app(CreatePaymentAttempt::class)->handle(
        $voucher,
        'netbank',
        'payer-session-provisional',
        'request-provisional',
    );

    $issued = app(IssuePaymentInstructions::class)->handle($attempt);
    $replay = app(IssuePaymentInstructions::class)->handle($attempt);
    $raw = DB::table('x_change_payment_attempts')->find($attempt->getKey());

    expect($issued->status)->toBe(PaymentAttemptStatus::AwaitingPayment)
        ->and($issued->expected_amount_minor)->toBe(10000)
        ->and($issued->currency)->toBe('PHP')
        ->and($issued->provider_request_id_ciphertext)->toMatch('/\A91500\d{16}\z/')
        ->and($issued->funding_address_ciphertext)->toBe($issued->provider_request_id_ciphertext)
        ->and($issued->merchant_snapshot_ciphertext)->toBe(FundingMerchantSnapshot::fromData($expectedMerchant))
        ->and($issued->merchant_profile_fingerprint)->toBe($expectedMerchant->profileFingerprint)
        ->and($raw->merchant_snapshot_ciphertext)->not->toContain('Lester Store')
        ->and($replay->merchant_snapshot_ciphertext)->toBe($issued->merchant_snapshot_ciphertext)
        ->and($replay->events)->toHaveCount(2)
        ->and($issued->instructions_ciphertext['qr_code'])->toMatchArray([
            'mime_type' => 'image/png',
            'base64_payload' => $qrPayload,
            'qr_mode' => 'dynamic',
            'transaction_type' => 'p2m',
            'embedded_amount' => true,
            'provider_generated' => true,
        ]);
});

it('uses an updated merchant profile only for newly issued Payment Attempts', function (): void {
    $user = actingAsTestUser();
    $profiles = app(MerchantProfileRepositoryContract::class);
    $profiles->updateForUser($user, [
        'name' => 'First Store',
        'city' => 'Makati',
        'merchant_category_code' => '5999',
        'merchant_name_template' => '{name}',
    ]);
    $firstVoucher = paymentAttemptCollectibleVoucherForUser($user);
    $first = app(IssuePaymentInstructions::class)->handle(
        app(CreatePaymentAttempt::class)->handle(
            $firstVoucher,
            'netbank',
            'first-payer-session',
            'first-request',
        ),
    );
    $firstSnapshot = $first->merchant_snapshot_ciphertext;

    $profiles->updateForUser($user, [
        'name' => 'Second Store',
        'city' => 'Pasig',
        'merchant_category_code' => '5812',
        'merchant_name_template' => '{name}',
    ]);
    $secondVoucher = paymentAttemptCollectibleVoucherForUser($user);
    $second = app(IssuePaymentInstructions::class)->handle(
        app(CreatePaymentAttempt::class)->handle(
            $secondVoucher,
            'netbank',
            'second-payer-session',
            'second-request',
        ),
    );

    expect($first->fresh()->merchant_snapshot_ciphertext)->toBe($firstSnapshot)
        ->and($firstSnapshot['displayName'])->toBe('First Store')
        ->and($firstSnapshot['city'])->toBe('Makati')
        ->and($second->merchant_snapshot_ciphertext['displayName'])->toBe('Second Store')
        ->and($second->merchant_snapshot_ciphertext['city'])->toBe('Pasig')
        ->and($second->merchant_profile_fingerprint)->not->toBe($first->merchant_profile_fingerprint)
        ->and($this->paymentAdapter->instructionCalls)->toBe(2);
});

it('fails closed before provider instructions when the merchant profile is inactive', function (): void {
    $user = actingAsTestUser();
    $profile = app(MerchantProfileRepositoryContract::class)->findOrCreateForUser($user);
    $profile->forceFill(['is_active' => false])->save();
    $voucher = paymentAttemptCollectibleVoucherForUser($user);
    $attempt = app(CreatePaymentAttempt::class)->handle(
        $voucher,
        'netbank',
        'inactive-merchant-session',
        'inactive-merchant-request',
    );

    expect(fn () => app(IssuePaymentInstructions::class)->handle($attempt))
        ->toThrow(RuntimeException::class, 'Payment instructions are temporarily unavailable.');

    $failure = $attempt->fresh()->events()->where('event_type', 'provider_instruction_failed')->sole();

    expect($attempt->fresh()->status)->toBe(PaymentAttemptStatus::PendingInstructions)
        ->and($failure->metadata)->toBe([
            'provider' => 'netbank',
            'retryable' => true,
            'failure_stage' => 'merchant_profile',
        ])
        ->and($this->paymentAdapter->instructionCalls)->toBe(0);
});

it('settles one exact provider observation into one voucher collection', function (): void {
    $user = actingAsTestUser();
    $voucher = paymentAttemptCollectibleVoucherForUser($user);
    $balanceBefore = (float) $user->wallet->balanceFloat;
    $attempt = issuedPaymentAttempt($voucher);
    $this->paymentAdapter->fundingObservation = exactPaymentObservation($attempt);

    $settled = app(VerifyPaymentAttempt::class)->handle(
        $attempt,
        PaymentVerificationTrigger::Payer,
    );
    $replay = app(VerifyPaymentAttempt::class)->handle(
        $attempt,
        PaymentVerificationTrigger::Payer,
    );

    expect($settled->status)->toBe(PaymentAttemptStatus::Settled)
        ->and($settled->voucher_collection_id)->not->toBeNull()
        ->and($replay->status)->toBe(PaymentAttemptStatus::Settled)
        ->and(VoucherCollection::query()->where('voucher_id', $voucher->getKey())->count())->toBe(1)
        ->and(VoucherCollection::query()->findOrFail($settled->voucher_collection_id)->only([
            'status',
            'collected_amount_minor',
            'currency',
            'provider',
            'idempotency_key',
        ]))->toBe([
            'status' => 'collected',
            'collected_amount_minor' => 10000,
            'currency' => 'PHP',
            'provider' => 'netbank',
            'idempotency_key' => 'payment-attempt:'.$settled->reference,
        ])
        ->and((float) $user->wallet->fresh()->balanceFloat)->toBe($balanceBefore + 100.00)
        ->and(DB::table('x_change_funding_intents')->count())->toBe(0)
        ->and(DB::table('x_change_account_funding_receipts')->count())->toBe(0);
});

it('replays an already-settled Payment Attempt without executing another collection', function (): void {
    $user = actingAsTestUser();
    $voucher = paymentAttemptCollectibleVoucherForUser($user);
    $balanceBefore = (float) $user->wallet->balanceFloat;
    $attempt = issuedPaymentAttempt($voucher);
    $this->paymentAdapter->fundingObservation = exactPaymentObservation($attempt);

    $settled = app(VerifyPaymentAttempt::class)->handle(
        $attempt,
        PaymentVerificationTrigger::Payer,
    );
    $balanceAfterSettlement = (float) $user->wallet->fresh()->balanceFloat;
    $eventCount = $settled->events()->count();
    $engine = Mockery::mock(ExecutionEngine::class);
    $engine->shouldNotReceive('execute');
    app()->instance(ExecutionEngine::class, $engine);

    $replay = app(SettleVerifiedPaymentAttempt::class)->handle(
        $settled,
        PaymentVerificationTrigger::Payer,
    );

    expect($replay->status)->toBe(PaymentAttemptStatus::Settled)
        ->and($replay->voucher_collection_id)->toBe($settled->voucher_collection_id)
        ->and(VoucherCollection::query()->where('voucher_id', $voucher->getKey())->count())->toBe(1)
        ->and($replay->events()->count())->toBe($eventCount)
        ->and($balanceAfterSettlement)->toBe($balanceBefore + 100.00)
        ->and((float) $user->wallet->fresh()->balanceFloat)->toBe($balanceAfterSettlement);
});

it('keeps pending provider history awaiting payment without collection', function (): void {
    $user = actingAsTestUser();
    $voucher = paymentAttemptCollectibleVoucherForUser($user);
    $balanceBefore = (float) $user->wallet->balanceFloat;
    $attempt = issuedPaymentAttempt($voucher);
    $this->paymentAdapter->fundingObservation = exactPaymentObservation(
        $attempt,
        status: 'pending',
        settledAt: null,
    );

    $checked = app(VerifyPaymentAttempt::class)->handle(
        $attempt,
        PaymentVerificationTrigger::Payer,
    );

    expect($checked->status)->toBe(PaymentAttemptStatus::AwaitingPayment)
        ->and($checked->last_checked_at)->not->toBeNull()
        ->and(VoucherCollection::query()->count())->toBe(0)
        ->and((float) $user->wallet->fresh()->balanceFloat)->toBe($balanceBefore);
});

it('moves mismatched authoritative payment evidence to suspense', function (): void {
    $voucher = paymentAttemptCollectibleVoucher();
    $attempt = issuedPaymentAttempt($voucher);
    $this->paymentAdapter->fundingObservation = exactPaymentObservation(
        $attempt,
        amountMinor: 9900,
    );

    $checked = app(VerifyPaymentAttempt::class)->handle(
        $attempt,
        PaymentVerificationTrigger::Payer,
    );

    expect($checked->status)->toBe(PaymentAttemptStatus::Suspense)
        ->and($checked->events->last()->event_type)->toBe('provider_payment_mismatch')
        ->and(VoucherCollection::query()->count())->toBe(0);
});

it('expires an unpaid attempt after the configured settlement grace', function (): void {
    config()->set('x-change.payment.attempts.settlement_grace_seconds', 300);
    $voucher = paymentAttemptCollectibleVoucher();
    $attempt = issuedPaymentAttempt($voucher);
    $this->travelTo($attempt->expires_at->addMinutes(6));

    $checked = app(VerifyPaymentAttempt::class)->handle(
        $attempt,
        PaymentVerificationTrigger::Schedule,
    );

    expect($checked->status)->toBe(PaymentAttemptStatus::Expired)
        ->and($checked->expired_at)->not->toBeNull()
        ->and($checked->events->last()->event_type)->toBe('payment_attempt_expired')
        ->and(VoucherCollection::query()->count())->toBe(0);
});

function paymentAttemptCollectibleVoucher(): Voucher
{
    return paymentAttemptCollectibleVoucherForUser(actingAsTestUser());
}

function paymentAttemptCollectibleVoucherForUser(User $user): Voucher
{
    return issueVoucher(validVoucherInstructions(
        amount: 0.00,
        settlementRail: 'INSTAPAY',
        overrides: [
            'target_amount' => 100.00,
            'metadata' => [
                'flow_type' => 'collectible',
                'issuer_id' => (string) $user->id,
                'collection_wallet_id' => $user->wallet->id,
            ],
        ],
    ));
}

function issuedPaymentAttempt(Voucher $voucher): PaymentAttempt
{
    $attempt = app(CreatePaymentAttempt::class)->handle(
        $voucher,
        'netbank',
        'payer-session-1',
        'request-'.str()->uuid(),
    );

    return app(IssuePaymentInstructions::class)->handle($attempt);
}

function exactPaymentObservation(
    PaymentAttempt $attempt,
    string $status = 'settled',
    ?DateTimeImmutable $settledAt = new DateTimeImmutable('2026-07-24T05:00:00+00:00'),
    ?int $amountMinor = null,
): ProviderFundingObservationData {
    $amountMinor ??= $attempt->expected_amount_minor;

    return new ProviderFundingObservationData(
        provider: $attempt->provider_code,
        providerTransactionId: 'payment-transaction-'.str()->uuid(),
        grossAmountMinor: $amountMinor,
        feeAmountMinor: 0,
        netAmountMinor: $amountMinor,
        currency: $attempt->currency,
        providerStatus: $status,
        verificationSource: 'fake-authoritative-vca-history',
        payloadHash: hash('sha256', 'payment-observation-'.str()->uuid()),
        fundingAddress: 'sha256:'.hash('sha256', (string) $attempt->funding_address_ciphertext),
        occurredAt: new DateTimeImmutable('2026-07-24T04:59:00+00:00'),
        settledAt: $settledAt,
        metadata: [
            'destination_verified' => true,
        ],
    );
}

function recordPaymentAttemptCollection(Voucher $voucher, float $amount): void
{
    app(RecordVoucherCollection::class)->handle(
        voucher: $voucher,
        result: new VoucherPaymentResultData(
            voucher_code: (string) $voucher->code,
            status: 'collected',
            amount: $amount,
            currency: 'PHP',
            provider: 'test',
            provider_reference: 'reference-'.str()->uuid(),
            provider_transaction_id: 'transaction-'.str()->uuid(),
        ),
        payload: [
            'amount' => $amount,
        ],
    );
}
