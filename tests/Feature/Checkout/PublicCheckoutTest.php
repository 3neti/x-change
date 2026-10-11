<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use LBHurtado\Contact\Models\Contact;
use LBHurtado\EmiCore\Models\ProviderFundingObservation;
use LBHurtado\XChange\Jobs\Funding\ResumeOnDemandPayCodeIssuanceJob;
use LBHurtado\XChange\Models\Checkout;
use LBHurtado\XChange\Models\CheckoutRefundCase;
use LBHurtado\XChange\Models\CheckoutViewerLink;
use LBHurtado\XChange\Models\CommercialPrincipal;
use LBHurtado\XChange\Models\FundingIntent;
use LBHurtado\XChange\Models\FundingSettlement;
use LBHurtado\XChange\Models\PayCodeIssuanceFundingOrder;
use LBHurtado\XChange\Services\Checkout\CheckoutConsoleAccess;
use LBHurtado\XChange\Services\Checkout\CheckoutConsoleReadModel;
use LBHurtado\XChange\Services\Checkout\CheckoutLifecycle;
use LBHurtado\XChange\Services\Checkout\PublicCheckoutDraftAccess;
use LBHurtado\XChange\Services\Cockpit\OnDemandIssuanceFundingOrderPresenter;
use LBHurtado\XChange\Services\Commercial\ConfiguredCommercialPrincipalResolver;

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
