<?php

declare(strict_types=1);

use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Actions\Payment\CreatePaymentAttempt;
use LBHurtado\XChange\Actions\Payment\RecordObservedPaymentTransaction;
use LBHurtado\XChange\Data\Payment\ObservedPaymentTransactionData;
use LBHurtado\XChange\Models\PaymentAttempt;
use LBHurtado\XChange\Services\Funding\FundingProviderAdapterRegistry;
use LBHurtado\XChange\Tests\Fakes\FakeFundingProviderAdapter;
use LBHurtado\XChange\Tests\Fakes\User;

beforeEach(function (): void {
    config()->set('x-change.funding.providers.netbank.enabled', true);
    config()->set('x-change.payment.attempts.enabled', true);
    config()->set('x-change.payment.attempts.provider', 'netbank');

    $adapter = new FakeFundingProviderAdapter;
    $this->app->instance(FakeFundingProviderAdapter::class, $adapter);
    $this->app->tag(FakeFundingProviderAdapter::class, 'emi.funding-provider-adapters');
    $this->app->forgetInstance(FundingProviderAdapterRegistry::class);
});

it('returns operator-safe payment QR instructions and replays one attempt', function (): void {
    $user = actingAsTestUser();
    $voucher = cockpitCollectibleVoucher($user);
    $route = route('x-change.cockpit.pay-codes.collection-attempts.store', [
        'code' => strtolower((string) $voucher->code),
    ]);

    $this->postJson($route)
        ->assertOk()
        ->assertHeader('Cache-Control', 'max-age=0, no-store, private')
        ->assertJsonPath('schema', 'x-change.cockpit.payment-attempt.v1')
        ->assertJsonPath('attempt.status', 'awaiting_payment')
        ->assertJsonPath('attempt.qr_code.mime_type', 'image/png')
        ->assertJsonPath('attempt.qr_code.embedded_amount', true)
        ->assertJsonMissingPath('attempt.provider_request_id')
        ->assertJsonStructure(['attempt' => ['reference', 'qr_code' => ['base64_payload']]]);

    $this->postJson($route)->assertOk();

    expect(PaymentAttempt::query()
        ->where('voucher_id', $voucher->getKey())
        ->count())->toBe(1);
});

it('conceals another operators Pay Code', function (): void {
    $owner = actingAsTestUser();
    $voucher = cockpitCollectibleVoucher($owner);
    $other = actingAsTestUser();

    $this->actingAs($other)
        ->postJson(route('x-change.cockpit.pay-codes.collection-attempts.store', [
            'code' => $voucher->code,
        ]))
        ->assertNotFound();

    expect(PaymentAttempt::query()->count())->toBe(0);
});

it('shows provider-reported transactions only to the Pay Code owner', function (): void {
    $owner = actingAsTestUser();
    $voucher = cockpitCollectibleVoucher($owner);
    $attempt = app(CreatePaymentAttempt::class)->handle($voucher, 'netbank', 'owner-session', 'owner-request');
    app(RecordObservedPaymentTransaction::class)->handle($attempt, new ObservedPaymentTransactionData(
        'provider-credit-1', 2500, 'PHP', 'settled',
        payerName: 'Apple Hurtado', payerAccountNumber: '09175180722', payerInstitutionCode: 'GCASH',
    ));
    $url = route('x-change.cockpit.pay-codes.payment-transactions.index', [
        'code' => $voucher->code,
        'attempt' => $attempt->reference,
    ]);

    $this->getJson($url)->assertOk()
        ->assertHeader('Cache-Control', 'max-age=0, no-store, private')
        ->assertJsonPath('transactions.0.amount_minor', 2500)
        ->assertJsonPath('transactions.0.payer.name', 'Apple Hurtado')
        ->assertJsonPath('transactions.0.payer.account_number', '09175180722')
        ->assertJsonPath('transactions.0.payer.provenance', 'provider_reported')
        ->assertJsonPath('transactions.0.payer.mobile_verified', false);

    $other = actingAsTestUser();
    $this->actingAs($other)->getJson($url)->assertNotFound();
    auth()->logout();
    $this->getJson($url)->assertUnauthorized();
});

it('does not expose an attempt belonging to a different Pay Code', function (): void {
    $owner = actingAsTestUser();
    $first = cockpitCollectibleVoucher($owner);
    $second = cockpitCollectibleVoucher($owner);
    $attempt = app(CreatePaymentAttempt::class)->handle($first, 'netbank', 'first-session', 'first-request');

    $this->getJson(route('x-change.cockpit.pay-codes.payment-transactions.index', [
        'code' => $second->code,
        'attempt' => $attempt->reference,
    ]))->assertNotFound();
});

function cockpitCollectibleVoucher(User $user): Voucher
{
    return issueVoucher(validVoucherInstructions(
        amount: 0.00,
        settlementRail: 'INSTAPAY',
        overrides: [
            'voucher_type' => 'payable',
            'target_amount' => 100.00,
            'metadata' => [
                'flow_type' => 'collectible',
                'issuer_id' => (string) $user->id,
                'collection_wallet_id' => $user->wallet->id,
            ],
        ],
    ));
}
