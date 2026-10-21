<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use LBHurtado\Contact\Models\Contact;
use LBHurtado\EmiCore\Data\Funding\ProviderFundingObservationData;
use LBHurtado\EmiCore\Data\Funding\ProviderPayerIdentityData;
use LBHurtado\EmiCore\Models\ProviderFundingObservation;
use LBHurtado\PaymentGateway\Funding\NetbankFundingProviderAdapter;
use LBHurtado\PaymentGateway\Funding\NetbankIncomingPaymentData;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Actions\Payment\RunSettledPaymentPipeline;
use LBHurtado\XChange\Jobs\Feedback\DeliverQueuedFeedbackSmsJob;
use LBHurtado\XChange\Jobs\Funding\ResumeOnDemandPayCodeIssuanceJob;
use LBHurtado\XChange\Jobs\Payment\QueueIssuedPayCodeSmsJob;
use LBHurtado\XChange\Models\Checkout;
use LBHurtado\XChange\Models\CheckoutRefundCase;
use LBHurtado\XChange\Models\CheckoutViewerLink;
use LBHurtado\XChange\Models\CommercialOffering;
use LBHurtado\XChange\Models\CommercialPrincipal;
use LBHurtado\XChange\Models\FundingIntent;
use LBHurtado\XChange\Models\FundingSettlement;
use LBHurtado\XChange\Models\PayCodeIssuanceFundingOrder;
use LBHurtado\XChange\Models\SettledPaymentProcess;
use LBHurtado\XChange\Services\Checkout\CheckoutConsoleAccess;
use LBHurtado\XChange\Services\Checkout\CheckoutConsoleReadModel;
use LBHurtado\XChange\Services\Checkout\CheckoutLifecycle;
use LBHurtado\XChange\Services\Checkout\PublicCheckoutDraftAccess;
use LBHurtado\XChange\Services\Checkout\ReverifyCheckoutQrPayer;
use LBHurtado\XChange\Services\Cockpit\OnDemandIssuanceFundingOrderPresenter;
use LBHurtado\XChange\Services\Commercial\ConfiguredCommercialPrincipalResolver;
use LBHurtado\XChange\Services\Commercial\ProvisionCommercialBaselines;
use LBHurtado\XFeedback\Models\FeedbackDeliveryRecord;

beforeEach(function (): void {
    config()->set('x-change.public_auto_generate.enabled', true);
    config()->set('x-change.commercial.principal.reference', 'commercial-public');
    config()->set('x-change.commercial.principal.legal_name', '3neti R&D OPC');
    config()->set('x-change.commercial.principal.authorization_reference', 'commissioning:commercial-public:v1');
    config()->set('x-change.checkout_console.owner_password', 'password');
});

it('stores one checkout and hides bank instructions until a valid mobile is submitted', function (): void {
    $principal = checkoutTestPrincipal();
    $order = checkoutTestOrder($principal);
    $lifecycle = app(CheckoutLifecycle::class);
    $checkout = $lifecycle->place($order);
    $intent = FundingIntent::query()->create([
        'account_reference' => $order->account_reference,
        'provider_code' => 'netbank',
        'purpose' => 'on_demand_issuance',
        'expected_amount_minor' => 2500,
        'currency' => 'PHP',
        'status' => 'awaiting_funds',
        'version' => 1,
        'idempotency_key_hash' => hash('sha256', 'checkout-test-intent'),
        'idempotency_fingerprint' => str_repeat('e', 64),
        'created_by_type' => $principal::class,
        'created_by_id' => (string) $principal->getKey(),
        'instructions_ciphertext' => ['funding_address' => '1234567890', 'display_data' => ['institution' => 'NetBank']],
        'expires_at' => now()->addMinutes(30),
    ]);
    $order->forceFill(['funding_intent_id' => $intent->getKey()])->saveQuietly();

    expect($lifecycle->place($order)->is($checkout))->toBeTrue()
        ->and(Checkout::query()->count())->toBe(1);

    $before = app(OnDemandIssuanceFundingOrderPresenter::class)->present($order, 'token', true);
    expect($before['checkout']['bank_transfer_unlocked'])->toBeFalse()
        ->and($before['funding_selector']['bank_transfer']['instructions'])->toBe([]);

    $lifecycle->selectBankTransfer($checkout, '09171234567');
    $after = app(OnDemandIssuanceFundingOrderPresenter::class)->present($order->refresh(), 'token', true);
    expect($after['funding_selector']['bank_transfer']['instructions']['funding_address'])->toBe('1234567890');

    $this->expectException(ValidationException::class);
    $lifecycle->selectBankTransfer($checkout, 'not-a-mobile');
});

it('normalizes and encrypts bank mobile without creating a contact before payment', function (): void {
    $checkout = app(CheckoutLifecycle::class)->place(checkoutTestOrder(checkoutTestPrincipal()));
    $updated = app(CheckoutLifecycle::class)->selectBankTransfer($checkout, '0917 123 4567');

    expect($updated->visitor_mobile_ciphertext)->toBe('639171234567')
        ->and($updated->getRawOriginal('visitor_mobile_ciphertext'))->not->toContain('639171234567')
        ->and($updated->contact_id)->toBeNull();

    $projection = app(OnDemandIssuanceFundingOrderPresenter::class)->present($updated->fundingOrder, 'token', true);
    expect($projection['checkout']['bank_transfer_unlocked'])->toBeTrue();

    app(CheckoutLifecycle::class)->selectQrPh($updated);
    $projection = app(OnDemandIssuanceFundingOrderPresenter::class)->present($updated->fundingOrder, 'token', true);
    expect($projection['checkout']['bank_transfer_unlocked'])->toBeFalse();
});

it('links a QR Ph wallet payer Contact only after confirmed payment evidence', function (): void {
    $order = checkoutTestOrder(checkoutTestPrincipal());
    $checkout = app(CheckoutLifecycle::class)->place($order);
    $observation = ProviderFundingObservation::query()->create([
        'observation_key' => hash('sha256', 'checkout-qr-observation'),
        'provider_code' => 'netbank',
        'provider_transaction_id' => 'NB-CHECKOUT-QR',
        'gross_amount_minor' => 2500,
        'fee_amount_minor' => 0,
        'net_amount_minor' => 2500,
        'currency' => 'PHP',
        'provider_status' => 'settled',
        'occurred_at' => now(),
        'settled_at' => now(),
        'verification_source' => 'transaction_history',
        'payload_hash' => hash('sha256', 'checkout-qr-payload'),
        'metadata' => ['destination_verified' => true],
        'payer_name_ciphertext' => 'Test Payer',
        'payer_account_ciphertext' => '09171234567',
        'payer_institution_ciphertext' => 'GCASH',
    ]);

    expect($checkout->contact_id)->toBeNull();
    app(CheckoutLifecycle::class)->settled($order, $observation);
    app(CheckoutLifecycle::class)->settled($order, $observation);

    $checkout->refresh();
    expect($checkout->contact_id)->not->toBeNull()
        ->and($checkout->contact_source)->toBe('provider_reported')
        ->and($checkout->status)->toBe('settled')
        ->and($checkout->events()->where('event_type', 'payment_settled')->count())->toBe(1)
        ->and($checkout->events()->where('event_type', 'contact_association_completed')->count())->toBe(1)
        ->and(Contact::query()->findOrFail($checkout->contact_id)->mobile)->toBe('09171234567');
});

it('projects the settled payer mobile when the checkout contact link is delayed', function (): void {
    $principal = checkoutTestPrincipal();
    $order = checkoutTestOrder($principal);
    $checkout = app(CheckoutLifecycle::class)->place($order);
    $observation = ProviderFundingObservation::query()->create([
        'observation_key' => hash('sha256', 'checkout-read-model-observation'),
        'provider_code' => 'netbank',
        'provider_transaction_id' => 'NB-CHECKOUT-READ-MODEL',
        'gross_amount_minor' => 2500,
        'fee_amount_minor' => 0,
        'net_amount_minor' => 2500,
        'currency' => 'PHP',
        'provider_status' => 'settled',
        'occurred_at' => now(),
        'settled_at' => now(),
        'verification_source' => 'transaction_history',
        'payload_hash' => hash('sha256', 'checkout-read-model-payload'),
        'metadata' => ['destination_verified' => true],
        'payer_name_ciphertext' => 'Read Model Payer',
        'payer_account_ciphertext' => '09171234567',
        'payer_institution_ciphertext' => 'GCASH',
    ]);

    $intent = FundingIntent::query()->create([
        'account_reference' => $order->account_reference,
        'provider_code' => 'netbank',
        'purpose' => 'on_demand_issuance',
        'expected_amount_minor' => 2500,
        'currency' => 'PHP',
        'status' => 'settled',
        'version' => 1,
        'idempotency_key_hash' => hash('sha256', 'checkout-read-model-intent'),
        'idempotency_fingerprint' => str_repeat('e', 64),
        'created_by_type' => $principal::class,
        'created_by_id' => (string) $principal->getKey(),
        'matched_observation_id' => $observation->getKey(),
        'funding_address_ciphertext' => 'read-model-funding-address',
        'expires_at' => now()->addMinutes(30),
    ]);
    FundingSettlement::query()->create([
        'funding_intent_id' => $intent->getKey(),
        'provider_funding_observation_id' => $observation->getKey(),
        'provider_code' => 'netbank',
        'account_reference' => $order->account_reference,
        'gross_amount_minor' => 2500,
        'net_amount_minor' => 2500,
        'currency' => 'PHP',
        'treasury_inventory_reference' => 'read-model-inventory',
        'treasury_operation_reference' => 'read-model-operation',
        'wallet_transaction_id' => 123456,
        'wallet_transaction_uuid' => '12345678-1234-1234-1234-123456789012',
        'settled_at' => now(),
    ]);
    $order->forceFill(['funding_intent_id' => $intent->getKey()])->saveQuietly();

    app(CheckoutLifecycle::class)->settled($order, $observation);
    $checkout->forceFill(['contact_id' => null])->saveQuietly();

    expect(app(CheckoutConsoleReadModel::class)->forOwner($principal)['rows'][0]['contact'])
        ->toBe('••••4567');
});

it('recovers a missing QR payer Contact only from the same settled provider transaction', function (): void {
    Queue::fake();
    $principal = checkoutTestPrincipal();
    $order = checkoutTestOrder($principal);
    $checkout = app(CheckoutLifecycle::class)->place($order);
    $observation = ProviderFundingObservation::query()->create([
        'observation_key' => hash('sha256', 'checkout-reconcile-observation'),
        'provider_code' => 'netbank',
        'provider_transaction_id' => 'NB-CHECKOUT-RECONCILE',
        'gross_amount_minor' => 2500,
        'fee_amount_minor' => 0,
        'net_amount_minor' => 2500,
        'currency' => 'PHP',
        'provider_status' => 'settled',
        'settled_at' => now(),
        'verification_source' => 'netbank-vca-transaction-history',
        'funding_address' => 'sha256:'.hash('sha256', 'reconcile-funding-address'),
        'payload_hash' => hash('sha256', 'checkout-reconcile-payload'),
    ]);
    $intent = FundingIntent::query()->create([
        'account_reference' => $order->account_reference,
        'provider_code' => 'netbank',
        'purpose' => 'on_demand_issuance',
        'expected_amount_minor' => 2500,
        'currency' => 'PHP',
        'status' => 'settled',
        'idempotency_key_hash' => hash('sha256', 'checkout-reconcile-intent'),
        'idempotency_fingerprint' => str_repeat('e', 64),
        'created_by_type' => $principal::class,
        'created_by_id' => (string) $principal->getKey(),
        'matched_observation_id' => $observation->getKey(),
        'funding_address_ciphertext' => 'reconcile-funding-address',
        'expires_at' => now()->addMinutes(30),
    ]);
    $settlement = FundingSettlement::query()->create([
        'funding_intent_id' => $intent->getKey(),
        'provider_funding_observation_id' => $observation->getKey(),
        'provider_code' => 'netbank',
        'account_reference' => $order->account_reference,
        'gross_amount_minor' => 2500,
        'net_amount_minor' => 2500,
        'currency' => 'PHP',
        'treasury_inventory_reference' => 'reconcile-inventory',
        'treasury_operation_reference' => 'reconcile-operation',
        'wallet_transaction_id' => 123456,
        'wallet_transaction_uuid' => '12345678-1234-1234-1234-123456789012',
        'settled_at' => now(),
    ]);
    $order->forceFill(['funding_intent_id' => $intent->getKey()])->saveQuietly();
    $process = SettledPaymentProcess::query()->create([
        'payment_type' => 'funding_settlement',
        'payment_id' => $settlement->getKey(),
        'observation_id' => $observation->getKey(),
        'flow' => 'public_auto_generate',
        'gross_amount_minor' => 2500,
        'sms_enabled_snapshot' => true,
        'status' => 'completed',
        'sms_status' => 'skipped',
    ]);
    app(CheckoutLifecycle::class)->settled($order, $observation);
    $checkout->refresh()->forceFill(['status' => 'issued'])->save();

    $reverified = new ProviderFundingObservationData(
        provider: 'netbank',
        providerTransactionId: $observation->provider_transaction_id,
        grossAmountMinor: 2500,
        feeAmountMinor: 0,
        netAmountMinor: 2500,
        currency: 'PHP',
        providerStatus: 'settled',
        verificationSource: 'netbank-vca-transaction-history',
        payloadHash: hash('sha256', 'updated-provider-payload'),
        fundingAddress: $observation->funding_address,
        payerIdentity: new ProviderPayerIdentityData(
            name: 'Wallet Payer',
            accountNumber: '09171234567',
            institutionCode: 'GXCHPHM2XXX',
            providerVerified: false,
        ),
    );
    $lifecycle = app(CheckoutLifecycle::class);

    $reverified->fundingAddress = 'sha256:'.hash('sha256', 'different-funding-address');
    expect($lifecycle->reconcileConfirmedQrContact($order, $reverified))->toBeFalse()
        ->and($checkout->refresh()->contact_id)->toBeNull();
    $reverified->fundingAddress = $observation->funding_address;

    $wrongTransaction = clone $reverified;
    $wrongTransaction->providerTransactionId = 'OTHER-TRANSACTION';
    $provider = Mockery::mock(NetbankFundingProviderAdapter::class);
    $provider->shouldReceive('verifyFunding')->twice()->andReturn($wrongTransaction, $reverified);
    $provider->shouldReceive('incomingPayments')->once()->andReturn([
        new NetbankIncomingPaymentData(
            transactionId: $observation->provider_transaction_id,
            amountMinor: 2500,
            currency: 'PHP',
            status: 'settled',
            occurredAt: null,
            settledAt: null,
            referenceId: null,
            settlementRail: 'INSTAPAY',
            payerName: 'Wallet Payer',
            payerAccountNumber: '09171234567',
            payerInstitutionCode: 'GXCHPHM2XXX',
            payerMobile: null,
        ),
    ]);
    app()->instance(NetbankFundingProviderAdapter::class, $provider);

    $recheck = app(ReverifyCheckoutQrPayer::class);

    expect($recheck->handle($order, $observation))->toBeFalse()
        ->and($checkout->refresh()->contact_id)->toBeNull()
        ->and($recheck->handle($order, $observation))->toBeTrue()
        ->and($lifecycle->reconcileConfirmedQrContact($order, $reverified))->toBeFalse()
        ->and($checkout->refresh()->status)->toBe('issued')
        ->and($checkout->contact_source)->toBe('provider_reported')
        ->and(Contact::query()->findOrFail($checkout->contact_id)->name)->toBe('Wallet Payer')
        ->and($checkout->events()->where('event_type', 'contact_association_reconciled')->count())->toBe(1);

    app(RunSettledPaymentPipeline::class)->handle($process->getKey());

    expect($process->fresh()->sms_status)->toBe('awaiting_pay_code')
        ->and(FeedbackDeliveryRecord::query()->where('intent_key', 'payment.settled')->count())->toBe(0);

    $voucher = Voucher::query()->create(['code' => 'READY-123']);
    $order->forceFill([
        'status' => 'issued',
        'voucher_id' => $voucher->getKey(),
        'issued_at' => now(),
    ])->saveQuietly();
    $issuedSms = new QueueIssuedPayCodeSmsJob($order->getKey());
    $checkout->forceFill(['selected_method' => 'bank_transfer'])->save();
    app()->call([$issuedSms, 'handle']);
    expect($process->fresh()->sms_status)->toBe('skipped')
        ->and(FeedbackDeliveryRecord::query()->count())->toBe(0);

    $checkout->forceFill(['selected_method' => 'qr_ph'])->save();
    app()->call([$issuedSms, 'handle']);
    app()->call([$issuedSms, 'handle']);

    expect($process->fresh()->sms_status)->toBe('queued')
        ->and(FeedbackDeliveryRecord::query()->where('intent_key', 'payment.settled')->count())->toBe(1);
    Queue::assertPushed(DeliverQueuedFeedbackSmsJob::class, 1);
    Queue::assertPushed(DeliverQueuedFeedbackSmsJob::class, fn (DeliverQueuedFeedbackSmsJob $job): bool => str_contains($job->message, '₱25.00 Pay Code READY-123')
        && str_contains($job->message, '/x/claim/READY-123'));
});

it('requires a separate owner password and does not treat a commercial principal as a console actor', function (): void {
    $principal = checkoutTestPrincipal();
    $request = Request::create('/x/checkout', 'GET');
    $request->setLaravelSession(app('session')->driver());
    $request->session()->start();
    $request->setUserResolver(static fn (): CommercialPrincipal => $principal);
    $access = app(CheckoutConsoleAccess::class);

    expect($access->owner($request, $principal))->toBeFalse()
        ->and($access->unlock($request, $principal, 'wrong'))->toBeFalse()
        ->and($access->unlock($request, $principal, 'password'))->toBeTrue()
        ->and($access->owner($request, $principal))->toBeTrue();
});

it('fails closed for a weak or missing production console secret', function (): void {
    $principal = checkoutTestPrincipal();
    $request = Request::create('/x/checkout/unlock', 'POST');
    $request->setLaravelSession(app('session')->driver());
    $request->session()->start();
    $originalEnvironment = app()->environment();
    app()->detectEnvironment(static fn (): string => 'production');

    try {
        $access = app(CheckoutConsoleAccess::class);
        expect($access->unlock($request, $principal, 'password'))->toBeFalse();
        config()->set('x-change.checkout_console.owner_password', null);
        expect($access->unlock($request, $principal, 'anything'))->toBeFalse();
        config()->set('x-change.checkout_console.owner_password', 'a-long-production-secret-value');
        expect($access->unlock($request, $principal, 'a-long-production-secret-value'))->toBeTrue();
    } finally {
        app()->detectEnvironment(static fn (): string => $originalEnvironment);
    }
});

it('keeps the bank method route behind the existing guest possession token', function (): void {
    $principal = checkoutTestPrincipal();
    $order = checkoutTestOrder($principal);
    app(CheckoutLifecycle::class)->place($order);

    $this->postJson(route('x-change.public-auto-generate.funding-orders.funding-method', $order->reference), [
        'method' => 'bank_transfer', 'mobile' => '09171234567',
    ])->assertNotFound();
});

it('gates the monitor and keeps expiring viewer links read only', function (): void {
    $principal = checkoutTestPrincipal();
    app(CheckoutLifecycle::class)->place(checkoutTestOrder($principal));

    $headers = ['X-Inertia' => 'true', 'Accept' => 'text/html, application/xhtml+xml'];
    $this->withHeaders($headers)->get(route('x-change.checkout.show'))
        ->assertOk()
        ->assertJsonPath('props.role', 'locked')
        ->assertJsonPath('props.monitor', null);

    $token = str_repeat('A', 64);
    $link = CheckoutViewerLink::query()->create([
        'token_hash' => hash('sha256', $token),
        'owner_type' => $principal::class,
        'owner_id' => (string) $principal->getKey(),
        'label' => 'Beta stakeholder',
        'expires_at' => now()->addHour(),
    ]);

    $this->get(route('x-change.checkout.viewer', ['token' => $token]))
        ->assertOk()
        ->assertJsonPath('props.role', 'viewer')
        ->assertJsonPath('props.monitor.counts.all', 1);

    $checkout = Checkout::query()->firstOrFail();
    $this->post(route('x-change.checkout.retry', ['checkout' => $checkout->reference]))->assertNotFound();

    $link->forceFill(['revoked_at' => now()])->save();
    $this->get(route('x-change.checkout.viewer', ['token' => $token]))->assertNotFound();
});

it('exposes Payment Operations under the canonical operations route', function (): void {
    expect(route('x-change.operations.show'))->toEndWith('/x/operations')
        ->and(route('x-change.operations.pricing'))->toEndWith('/x/operations/pricing')
        ->and(route('x-change.operations.unlock'))->toEndWith('/x/operations/unlock')
        ->and(route('x-change.checkout.show'))->toEndWith('/x/checkout');
});

it('downloads the current pricing template and accepts it as a pricing update', function (): void {
    $principal = new CommercialPrincipal([
        'reference' => 'public-issuance-escrow',
        'legal_name' => 'Public Issuance Escrow',
        'authorization_reference' => 'commissioning:public-issuance-escrow:v1',
        'active' => true,
        'metadata' => ['interactive_login' => false],
    ]);
    $principal->save();
    config()->set('x-change.public_issuance_escrow.enabled', true);
    config()->set('x-change.public_issuance_escrow.principal.reference', 'public-issuance-escrow');
    config()->set('x-change.public_issuance_escrow.principal.legal_name', 'Public Issuance Escrow');
    config()->set('x-change.public_issuance_escrow.principal.authorization_reference', 'commissioning:public-issuance-escrow:v1');
    app(ProvisionCommercialBaselines::class)->provision('commissioning:pricing-template-test');

    $this->post(route('x-change.operations.unlock'), ['password' => 'password'])
        ->assertRedirect();

    $download = $this->get(route('x-change.operations.pricing.template'))
        ->assertOk();
    $csv = $download->streamedContent();

    expect($csv)->toContain('reference,label,category,currency,unit_price,expected_provider_cost,expected_provider_cost_basis')
        ->and($download->headers->get('Content-Disposition'))->toContain('x-change-pricing-template.csv');

    $this->post(route('x-change.operations.pricing.update'), [
        'pricing_file' => UploadedFile::fake()->createWithContent('legacy-pricing.csv', "reference,unit_price\ncash.amount,15.00\n"),
    ])->assertUnprocessable();

    $this->post(route('x-change.operations.pricing.update'), [
        'pricing_file' => UploadedFile::fake()->createWithContent('pricing.csv', $csv),
    ])->assertRedirect()->assertSessionHasNoErrors();

    expect(CommercialOffering::query()->where('status', 'published')->count())->toBeGreaterThan(1);
});

it('scopes the checkout console to the public issuance escrow after cutover', function (): void {
    config()->set('x-change.public_issuance_escrow.enabled', true);
    config()->set('x-change.public_issuance_escrow.principal.reference', 'public-issuance-escrow');

    $principal = CommercialPrincipal::query()->create([
        'reference' => 'public-issuance-escrow',
        'legal_name' => 'Public Issuance Escrow',
        'authorization_reference' => 'commissioning:public-issuance-escrow:v1',
        'active' => true,
        'metadata' => ['interactive_login' => false],
    ]);
    $checkout = app(CheckoutLifecycle::class)->place(checkoutTestOrder($principal));
    $token = str_repeat('E', 64);

    CheckoutViewerLink::query()->create([
        'token_hash' => hash('sha256', $token),
        'owner_type' => $principal::class,
        'owner_id' => (string) $principal->getKey(),
        'label' => 'Public issuance viewer',
        'expires_at' => now()->addHour(),
    ]);

    $this->withHeaders(['X-Inertia' => 'true', 'Accept' => 'text/html, application/xhtml+xml'])
        ->get(route('x-change.checkout.viewer', ['token' => $token]))
        ->assertOk()
        ->assertJsonPath('props.monitor.rows.0.reference', $checkout->reference);
});

it('keeps all owner rows visible after computing attention totals', function (): void {
    $principal = checkoutTestPrincipal();
    $first = app(CheckoutLifecycle::class)->place(checkoutTestOrder($principal));
    $second = app(CheckoutLifecycle::class)->place(checkoutTestOrder($principal));

    $monitor = app(CheckoutConsoleReadModel::class)->forOwner($principal);

    expect($monitor['counts']['all'])->toBe(2)
        ->and($monitor['counts']['attention'])->toBe(0)
        ->and($monitor['pagination']['total'])->toBe(2)
        ->and(array_column($monitor['rows'], 'reference'))->toEqualCanonicalizing([
            $first->reference,
            $second->reference,
        ]);
});

it('shows an issued Pay Code value separately from its payment and reveals the full code only to the owner', function (): void {
    $principal = checkoutTestPrincipal();
    $order = checkoutTestOrder($principal);
    $checkout = app(CheckoutLifecycle::class)->place($order);
    $voucher = Voucher::query()->create(['code' => 'READY-XYZ123']);
    $order->forceFill([
        'status' => 'issued',
        'voucher_id' => $voucher->getKey(),
        'instructions_ciphertext' => ['cash' => ['amount' => 75, 'currency' => 'PHP']],
        'expected_payment_minor' => 9000,
        'issued_at' => now(),
    ])->saveQuietly();

    $headers = ['X-Inertia' => 'true', 'Accept' => 'text/html, application/xhtml+xml'];
    $this->withHeaders($headers)->get(route('x-change.checkout.show'))
        ->assertOk()
        ->assertJsonPath('props.role', 'locked')
        ->assertJsonPath('props.monitor', null);

    $token = str_repeat('B', 64);
    CheckoutViewerLink::query()->create([
        'token_hash' => hash('sha256', $token),
        'owner_type' => $principal::class,
        'owner_id' => (string) $principal->getKey(),
        'label' => 'Beta stakeholder',
        'expires_at' => now()->addHour(),
    ]);
    $this->withHeaders($headers)->get(route('x-change.checkout.viewer', ['token' => $token]))
        ->assertOk()
        ->assertJsonPath('props.monitor.rows.0.reference', $checkout->reference)
        ->assertJsonPath('props.monitor.rows.0.order_reference', $order->reference)
        ->assertJsonPath('props.monitor.rows.0.expected_minor', 9000)
        ->assertJsonPath('props.monitor.rows.0.pay_code.value_minor', 7500)
        ->assertJsonPath('props.monitor.rows.0.pay_code.masked_code', '••••Z123')
        ->assertDontSee('READY-XYZ123');

    $this->post(route('x-change.checkout.unlock'), ['password' => 'password'])->assertRedirect();
    $this->withHeaders($headers)->get(route('x-change.checkout.show'))
        ->assertOk()
        ->assertJsonPath('props.monitor.rows.0.pay_code.code', 'READY-XYZ123');
});

it('leaves Pay Code details empty while checkout issuance is pending', function (): void {
    $principal = checkoutTestPrincipal();
    app(CheckoutLifecycle::class)->place(checkoutTestOrder($principal));

    $monitor = app(CheckoutConsoleReadModel::class)->forOwner($principal);

    expect($monitor['rows'][0]['pay_code'])->toBeNull();
});

it('creates and revokes a viewer link scoped to the commissioned owner', function (): void {
    $principal = checkoutTestPrincipal();

    $this->artisan('x-change:checkout:viewer-link', ['label' => 'Acceptance viewer', '--days' => 1])
        ->assertSuccessful();
    $link = CheckoutViewerLink::query()->firstOrFail();
    expect($link->owner_type)->toBe($principal::class)
        ->and($link->owner_id)->toBe((string) $principal->getKey())
        ->and($link->revoked_at)->toBeNull();

    $this->artisan('x-change:checkout:viewer-link', ['--revoke' => $link->getKey()])
        ->assertSuccessful();
    expect($link->refresh()->revoked_at)->not->toBeNull();
});

it('saves an editable checkout draft while new placement is paused and requires its possession token', function (): void {
    checkoutTestPrincipal();
    config()->set('x-change.public_auto_generate.enabled', false);

    $created = $this->postJson(route('x-change.public-auto-generate.checkouts.store'), [
        'instructions' => ['cash' => ['amount' => 50, 'currency' => 'PHP']],
    ])->assertOk();
    $reference = $created->json('reference');
    $token = $created->json('guest_token');

    expect(Checkout::query()->where('reference', $reference)->value('status'))->toBe('draft')
        ->and(Checkout::query()->where('reference', $reference)->value('funding_order_id'))->toBeNull();

    expect(session()->get('x-change.checkout.draft.reference'))->toBe($reference);
    expect(Checkout::query()->firstOrFail()->session_hash)->toBe(hash('sha256', session()->getId()));

    $request = Request::create('/x/auto-generate/checkouts', 'POST');
    $request->setLaravelSession(app('session')->driver());
    $request->headers->set(PublicCheckoutDraftAccess::TokenHeader, $token);
    $drafts = app(PublicCheckoutDraftAccess::class);
    $principal = app(ConfiguredCommercialPrincipalResolver::class)->resolve();
    expect($drafts->active($request, $principal)['checkout']->reference)->toBe($reference);

    $saved = $drafts->save($request, $principal, ['cash' => ['amount' => 75, 'currency' => 'PHP']]);
    expect($saved['checkout']->reference)->toBe($reference);

    expect(Checkout::query()->count())->toBe(1)
        ->and(Checkout::query()->firstOrFail()->instructions_ciphertext['cash']['amount'])->toBe(75);
});

it('stores public checkout drafts against the dedicated escrow principal after cutover', function (): void {
    checkoutTestPrincipal();
    config()->set('x-change.public_issuance_escrow.enabled', true);
    config()->set('x-change.public_issuance_escrow.principal.reference', 'public-issuance-escrow');

    $escrow = CommercialPrincipal::query()->create([
        'reference' => 'public-issuance-escrow',
        'legal_name' => 'Public Issuance Escrow',
        'authorization_reference' => 'commissioning:public-issuance-escrow:v1',
        'active' => true,
    ]);

    $created = $this->postJson(route('x-change.public-auto-generate.checkouts.store'), [
        'instructions' => ['cash' => ['amount' => 50, 'currency' => 'PHP']],
    ])->assertOk();

    expect(Checkout::query()->where('reference', $created->json('reference'))->firstOrFail())
        ->owner_id->toBe((string) $escrow->getKey());
    $instructions = Checkout::query()->where('reference', $created->json('reference'))->firstOrFail()->instructions_ciphertext;
    expect(data_get($instructions, 'metadata.custom.stamp_presentation.schema'))->toBe('x-stamp-presentation/1');
    expect(data_get($instructions, 'metadata.custom.stamp_presentation.resolved.paper'))->toBe(.5);
});

it('backfills existing public orders without duplicating checkout history', function (): void {
    $order = checkoutTestOrder(checkoutTestPrincipal());

    $this->artisan('x-change:checkout:backfill-public', ['--dry-run' => true])->assertSuccessful();
    expect(Checkout::query()->count())->toBe(0);

    $this->artisan('x-change:checkout:backfill-public')->assertSuccessful();
    $checkout = Checkout::query()->where('funding_order_id', $order->getKey())->firstOrFail();
    expect($checkout->events()->count())->toBe(1);

    $this->artisan('x-change:checkout:backfill-public')->assertSuccessful();
    expect(Checkout::query()->count())->toBe(1)
        ->and($checkout->events()->count())->toBe(1);
});

it('flags a paid expired order for manual refund review without offering issuance retry', function (): void {
    $principal = checkoutTestPrincipal();
    $order = checkoutTestOrder($principal);
    $intent = FundingIntent::query()->create([
        'account_reference' => $order->account_reference,
        'provider_code' => 'netbank',
        'purpose' => 'on_demand_issuance',
        'expected_amount_minor' => 2500,
        'currency' => 'PHP',
        'status' => 'settled',
        'idempotency_key_hash' => hash('sha256', 'late-payment-intent'),
        'idempotency_fingerprint' => str_repeat('e', 64),
        'created_by_type' => $principal::class,
        'created_by_id' => (string) $principal->getKey(),
        'expires_at' => now()->subMinute(),
    ]);
    $observation = ProviderFundingObservation::query()->create([
        'observation_key' => hash('sha256', 'late-payment-observation'),
        'provider_code' => 'netbank',
        'provider_transaction_id' => 'NB-LATE-PAYMENT',
        'gross_amount_minor' => 2500,
        'net_amount_minor' => 2500,
        'currency' => 'PHP',
        'provider_status' => 'settled',
        'settled_at' => now(),
        'verification_source' => 'transaction_history',
        'payload_hash' => hash('sha256', 'late-payment-payload'),
    ]);
    FundingSettlement::query()->create([
        'funding_intent_id' => $intent->getKey(),
        'provider_funding_observation_id' => $observation->getKey(),
        'provider_code' => 'netbank',
        'account_reference' => $order->account_reference,
        'gross_amount_minor' => 2500,
        'net_amount_minor' => 2500,
        'currency' => 'PHP',
        'treasury_inventory_reference' => 'late-payment-inventory',
        'treasury_operation_reference' => 'late-payment-operation',
        'wallet_transaction_id' => 123456,
        'wallet_transaction_uuid' => '12345678-1234-1234-1234-123456789012',
        'settled_at' => now(),
    ]);
    $order->forceFill(['funding_intent_id' => $intent->getKey(), 'status' => 'expired'])->saveQuietly();
    $checkout = app(CheckoutLifecycle::class)->place($order);

    $monitor = app(CheckoutConsoleReadModel::class)->forOwner($principal, 'attention');
    expect($monitor['counts']['attention'])->toBe(1)
        ->and($monitor['rows'][0]['reference'])->toBe($checkout->reference)
        ->and($monitor['rows'][0]['refund_eligible'])->toBeTrue();

    $this->post(route('x-change.checkout.unlock'), ['password' => 'password'])->assertRedirect();
    $this->post(route('x-change.checkout.refund.open', ['checkout' => $checkout->reference]), [
        'reason' => 'Verified payment arrived after order expiry.',
    ])->assertRedirect();
    expect($checkout->refundCase()->firstOrFail()->status)->toBe('open');

    $this->post(route('x-change.checkout.refund.record', ['checkout' => $checkout->reference]), [
        'password' => 'password',
        'external_reference' => 'TEST-EXTERNAL-RETURN',
    ])->assertRedirect();
    $this->post(route('x-change.checkout.refund.reconcile', ['checkout' => $checkout->reference]), [
        'password' => 'password',
        'treasury_reference' => 'TEST-TREASURY-RECONCILIATION',
    ])->assertRedirect();
    expect($checkout->refundCase()->firstOrFail()->status)->toBe('reconciled')
        ->and(app(CheckoutConsoleReadModel::class)->forOwner($principal, 'attention')['counts']['attention'])->toBe(0);
});

it('rejects a manual refund case for an expired order without settlement', function (): void {
    $order = checkoutTestOrder(checkoutTestPrincipal());
    $order->forceFill(['status' => 'expired'])->saveQuietly();
    $checkout = app(CheckoutLifecycle::class)->place($order);

    $this->post(route('x-change.checkout.unlock'), ['password' => 'password'])->assertRedirect();
    $this->post(route('x-change.checkout.refund.open', ['checkout' => $checkout->reference]), [
        'reason' => 'This order has no verified settlement.',
    ])->assertStatus(409);
    expect($checkout->refundCase()->exists())->toBeFalse();
});

it('blocks issuance when a refund case exists for the same checkout', function (): void {
    $principal = checkoutTestPrincipal();
    $order = checkoutTestOrder($principal);
    $checkout = app(CheckoutLifecycle::class)->place($order);
    $intent = FundingIntent::query()->create([
        'account_reference' => $order->account_reference,
        'provider_code' => 'netbank',
        'purpose' => 'on_demand_issuance',
        'expected_amount_minor' => 2500,
        'currency' => 'PHP',
        'status' => 'settled',
        'idempotency_key_hash' => hash('sha256', 'refund-test-intent'),
        'idempotency_fingerprint' => str_repeat('e', 64),
        'created_by_type' => $principal::class,
        'created_by_id' => (string) $principal->getKey(),
        'expires_at' => now()->addMinutes(30),
    ]);
    $observation = ProviderFundingObservation::query()->create([
        'observation_key' => hash('sha256', 'refund-test-observation'),
        'provider_code' => 'netbank',
        'provider_transaction_id' => 'NB-REFUND-TEST',
        'gross_amount_minor' => 2500,
        'net_amount_minor' => 2500,
        'currency' => 'PHP',
        'provider_status' => 'settled',
        'settled_at' => now(),
        'verification_source' => 'transaction_history',
        'payload_hash' => hash('sha256', 'refund-test-payload'),
    ]);
    $settlement = FundingSettlement::query()->create([
        'funding_intent_id' => $intent->getKey(),
        'provider_funding_observation_id' => $observation->getKey(),
        'provider_code' => 'netbank',
        'account_reference' => $order->account_reference,
        'gross_amount_minor' => 2500,
        'net_amount_minor' => 2500,
        'currency' => 'PHP',
        'treasury_inventory_reference' => 'test-inventory',
        'treasury_operation_reference' => 'refund-test-operation',
        'wallet_transaction_id' => 123456,
        'wallet_transaction_uuid' => '12345678-1234-1234-1234-123456789012',
        'settled_at' => now(),
    ]);
    $order->forceFill(['funding_intent_id' => $intent->getKey()])->saveQuietly();
    CheckoutRefundCase::query()->create([
        'checkout_id' => $checkout->getKey(),
        'funding_settlement_id' => $settlement->getKey(),
        'status' => 'open',
        'amount_minor' => 2500,
        'currency' => 'PHP',
        'reason' => 'Issuance failed after settled payment.',
        'opened_by' => hash('sha256', 'operator-session'),
    ]);

    expect(fn () => app()->call([new ResumeOnDemandPayCodeIssuanceJob($order->getKey()), 'handle']))
        ->toThrow(RuntimeException::class, 'refund case');
    expect($order->refresh()->voucher_id)->toBeNull();
});

function checkoutTestPrincipal(): CommercialPrincipal
{
    return CommercialPrincipal::query()->create([
        'reference' => 'commercial-public',
        'legal_name' => '3neti R&D OPC',
        'authorization_reference' => 'commissioning:commercial-public:v1',
        'active' => true,
        'metadata' => ['interactive_login' => false],
    ]);
}

function checkoutTestOrder(CommercialPrincipal $principal): PayCodeIssuanceFundingOrder
{
    return PayCodeIssuanceFundingOrder::query()->create([
        'account_reference' => 'wallet:public-test',
        'issuer_type' => $principal::class,
        'issuer_id' => (string) $principal->getKey(),
        'provider' => 'netbank',
        'connection_reference' => 'netbank-primary',
        'funding_basis' => 'full_amount',
        'instructions_ciphertext' => ['cash' => ['amount' => 25, 'currency' => 'PHP']],
        'instructions_fingerprint' => str_repeat('a', 64),
        'pricing_snapshot_ciphertext' => ['total' => 25],
        'pricing_fingerprint' => str_repeat('b', 64),
        'required_amount_minor' => 2500,
        'reserved_client_funds_minor' => 0,
        'on_demand_amount_minor' => 2500,
        'reconciliation_adjustment_minor' => 0,
        'expected_payment_minor' => 2500,
        'currency' => 'PHP',
        'status' => 'awaiting_payment',
        'version' => 1,
        'idempotency_key_hash' => hash('sha256', uniqid()),
        'idempotency_fingerprint' => str_repeat('d', 64),
        'expires_at' => now()->addMinutes(30),
        'metadata' => ['source' => 'public.auto-generate'],
    ]);
}
