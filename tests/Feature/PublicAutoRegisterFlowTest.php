<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use LBHurtado\EmiCore\Actions\Funding\StoreProviderWebhookReceipt;
use LBHurtado\EmiCore\Data\Funding\ProviderWebhookReceiptData;
use LBHurtado\EmiCore\Data\Funding\ProviderWebhookRequestData;
use LBHurtado\XChange\Actions\Funding\FinalizeFundingSuspenseMonitoring;
use LBHurtado\XChange\Actions\Funding\SettleVerifiedFundingIntent;
use LBHurtado\XChange\Actions\Funding\SimulateQrPhPayment;
use LBHurtado\XChange\Actions\Funding\VerifyFundingWebhookReceipt;
use LBHurtado\XChange\Contracts\AccountBalanceReadModelContract;
use LBHurtado\XChange\Jobs\Funding\VerifyFundingWebhookReceiptJob;
use LBHurtado\XChange\Models\CommercialPrincipal;
use LBHurtado\XChange\Models\PublicAutoRegistration;
use LBHurtado\XChange\Services\Commercial\CommercialPrincipalProvisioningService;
use LBHurtado\XChange\Services\Commercial\ProvisionCommercialBaselines;
use LBHurtado\XChange\Services\Funding\QrPhSimulatorFundingProviderAdapter;
use LBHurtado\XChange\Services\Funding\StandingFundingSyncAdmission;
use LBHurtado\XChange\Tests\Fakes\User as FakeLifecycleUser;

it('reproduces public auto-registration through its HTTP endpoints and mocked provider boundary', function () {
    Http::preventStrayRequests();
    config()->set('x-change.public_auto_register.provider', 'qrph_simulator');
    config()->set('x-change.onboarding.email_fallback_domain', 'example.test');
    config()->set('x-change.public_auto_register.amount_minor', 10_000);
    config()->set('x-change.funding.simulator.enabled', true);
    config()->set('x-change.funding.providers.qrph_simulator.enabled', true);
    config()->set('x-change.public_auto_register.treasury_connection_reference', 'netbank-primary');
    config()->set('x-change.funding.providers.qrph_simulator.treasury', [
        'inventory_reference' => 'inventory:qrph-simulator:vca-cash',
        'settlement_resource_reference' => 'resource:qrph-simulator:corporate-vca',
        'resource_type' => 'cash_at_bank',
    ]);
    config()->set('x-change.funding.simulator.signing_key', 'public-auto-register-http-signing-key');
    config()->set('x-change.funding.simulator.mobile_hash_key', 'public-auto-register-http-mobile-key');
    config()->set('x-change.funding.payer_identity_hash_key', 'public-auto-register-http-payer-key');
    config()->set('x-change.commercial.enabled', true);
    config()->set('x-change.commercial.billing.mode', 'billable');
    enableNetbankTreasuryForTests();
    config()->set('x-change.commercial.principal.reference', 'public-auto-register-http-commercial');
    config()->set('x-change.commercial.principal.legal_name', 'Public Auto-Register HTTP Commercial Principal');
    config()->set('x-change.commercial.principal.authorization_reference', 'commissioning:public-auto-register-http:test');
    config()->set('x-change.public_issuance_escrow.enabled', true);
    config()->set('x-change.public_issuance_escrow.principal.reference', 'public-auto-register-http-escrow');
    config()->set('x-change.public_issuance_escrow.principal.legal_name', 'Public Auto-Register HTTP Escrow');
    config()->set('x-change.public_issuance_escrow.principal.authorization_reference', 'commissioning:public-auto-register-http:escrow');
    CommercialPrincipal::query()->create([
        'reference' => 'public-auto-register-http-escrow',
        'legal_name' => 'Public Auto-Register HTTP Escrow',
        'authorization_reference' => 'commissioning:public-auto-register-http:escrow',
        'active' => true,
        'metadata' => ['interactive_login' => false],
    ]);
    app(ProvisionCommercialBaselines::class)->provision('commissioning-manifest:public-auto-register-http-test');
    app(CommercialPrincipalProvisioningService::class)->provision();

    $operator = FakeLifecycleUser::query()->create([
        'name' => 'HTTP Auto Registration Operator',
        'email' => 'http-auto-register@example.test',
        'password' => bcrypt('password'),
        'mobile' => '639173011987',
        'mobile_verified_at' => now(),
    ]);
    fundTestUserWallet($operator);
    fundTestUserClientFunds($operator, 10_000);
    fundTestUserClientFunds(CommercialPrincipal::query()->where('reference', 'public-auto-register-http-escrow')->firstOrFail(), 10_000);
    $this->actingAs($operator);

    $start = $this->postJson(route('x-change.public-auto-register.start'), [
        'idempotency_key' => 'http-public-auto-register-test',
    ])->assertCreated();

    $reference = (string) $start->json('reference');
    $registration = PublicAutoRegistration::query()->where('reference', $reference)->firstOrFail();
    $payment = app(SimulateQrPhPayment::class)->handle(
        $registration->fundingIntent,
        '09177210752',
        'HTTP QR Ph Payer',
    );
    $request = new ProviderWebhookRequestData(
        provider: 'qrph_simulator',
        rawBody: $payment->rawBody,
        contentType: 'application/json',
        headers: [],
        sourceIp: '127.0.0.1',
        receivedAt: new DateTimeImmutable,
        signature: $payment->signature,
    );
    $adapter = app(QrPhSimulatorFundingProviderAdapter::class);
    $authentication = $adapter->authenticateWebhook($request);
    $event = $adapter->normalizeWebhook(
        ProviderWebhookReceiptData::fromRequest($request, $authentication),
    );
    $receipt = app(StoreProviderWebhookReceipt::class)->handle($request, $authentication, $event);

    (new VerifyFundingWebhookReceiptJob($receipt->getKey()))->handle(
        app(VerifyFundingWebhookReceipt::class),
        app(SettleVerifiedFundingIntent::class),
        app(FinalizeFundingSuspenseMonitoring::class),
        app(StandingFundingSyncAdmission::class),
    );

    $this->getJson(route('x-change.public-auto-register.status', ['registration' => $reference]))
        ->assertOk()
        ->assertJsonPath('status', 'payment_settled');

    $complete = $this->postJson(
        route('x-change.public-auto-register.complete', ['registration' => $reference]),
        ['pin' => '1234'],
    )->assertOk()->assertJsonPath('status', 'active');

    $user = FakeLifecycleUser::query()->findOrFail($complete->json('account.id'));
    $registration->refresh();

    expect(app(AccountBalanceReadModelContract::class)->balanceMinor($user, 'PHP'))->toBe(10_000)
        ->and(data_get($registration->metadata, 'initial_client_funding.operation_reference'))->toBeString();
});
