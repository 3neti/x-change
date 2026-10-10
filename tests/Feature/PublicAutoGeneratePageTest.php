<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\URL;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Actions\Funding\TransitionPayCodeIssuanceFundingOrder;
use LBHurtado\XChange\Enums\PayCodeIssuanceFundingOrderStatus;
use LBHurtado\XChange\Exceptions\TreasuryConfigurationException;
use LBHurtado\XChange\Http\Controllers\Web\Cockpit\CockpitOnDemandIssuanceFundingOrderController;
use LBHurtado\XChange\Models\CommercialPrincipal;
use LBHurtado\XChange\Models\PayCodeIssuanceFundingOrder;
use LBHurtado\XChange\Services\Cockpit\OnDemandIssuanceFundingOrderPresenter;
use LBHurtado\XChange\Services\Commercial\ConfiguredCommercialPrincipalResolver;
use LBHurtado\XChange\Services\PublicIssuance\PublicIssuanceOrderAccess;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

beforeEach(function (): void {
    config()->set('x-change.public_auto_generate.enabled', true);
    config()->set('x-change.commercial.principal.reference', 'commercial-public');
    config()->set('x-change.commercial.principal.legal_name', '3neti R&D OPC');
    config()->set(
        'x-change.commercial.principal.authorization_reference',
        'commissioning:commercial-public:v1',
    );
});

it('renders the public issuance surface without cockpit navigation', function (): void {
    CommercialPrincipal::query()->create([
        'reference' => 'commercial-public',
        'legal_name' => '3neti R&D OPC',
        'authorization_reference' => 'commissioning:commercial-public:v1',
        'active' => true,
        'metadata' => ['interactive_login' => false],
    ]);

    $this->withHeaders([
        'X-Inertia' => 'true',
        'Accept' => 'text/html, application/xhtml+xml',
    ])->get(route('x-change.public-auto-generate.show'))
        ->assertOk()
        ->assertJsonPath('component', 'x-change/public/AutoGenerate')
        ->assertJsonPath('props.surface_profile.kind', 'public_auto_generate')
        ->assertJsonPath('props.surface_profile.show_funding_navigation', false)
        ->assertJsonPath('props.surface_profile.show_engineering_preview', false)
        ->assertJsonPath('props.surface_profile.show_workspace_switcher', false)
        ->assertJsonPath('props.surface_profile.allow_template_management', false)
        ->assertJsonPath('props.startup_mode', 'blank')
        ->assertJsonCount(0, 'props.saved_templates')
        ->assertJsonPath('props.quick_generate_read_model.mutation_contract.route', 'x-change.public-auto-generate.store')
        ->assertJsonPath('props.quick_generate_read_model.mutation_contract.authorization', 'commercial-principal-server-bound')
        ->assertJsonPath('props.on_demand_issuance_policy.basis', 'full_amount')
        ->assertJsonPath('props.public_navigation.pricing_url', '/x/pricing')
        ->assertJsonPath('props.public_navigation.claim_url', '/x/claim')
        ->assertJsonPath('props.public_navigation.login_url', null)
        ->assertJsonPath('props.commercial_principal.reference', 'commercial-public');
});

it('accepts only safe public amount and currency prefill values', function (): void {
    CommercialPrincipal::query()->create([
        'reference' => 'commercial-public',
        'legal_name' => '3neti R&D OPC',
        'authorization_reference' => 'commissioning:commercial-public:v1',
        'active' => true,
        'metadata' => ['interactive_login' => false],
    ]);

    $headers = [
        'X-Inertia' => 'true',
        'Accept' => 'text/html, application/xhtml+xml',
    ];

    $this->withHeaders($headers)
        ->get(route('x-change.public-auto-generate.show', ['amount' => '25.50', 'currency' => 'php']))
        ->assertOk()
        ->assertJsonPath('props.public_prefill.amount', '25.50')
        ->assertJsonPath('props.public_prefill.currency', 'PHP')
        ->assertJsonPath('props.service_discovery.structured_data.@type', 'Service');

    $this->withHeaders($headers)
        ->get(route('x-change.public-auto-generate.show', ['amount' => '-1', 'currency' => 'USD']))
        ->assertOk()
        ->assertJsonPath('props.public_prefill', null);
});

it('can be disabled with the public issuance kill switch', function (): void {
    config()->set('x-change.public_auto_generate.enabled', false);
    CommercialPrincipal::query()->create([
        'reference' => 'commercial-public',
        'legal_name' => '3neti R&D OPC',
        'authorization_reference' => 'commissioning:commercial-public:v1',
        'active' => true,
        'metadata' => ['interactive_login' => false],
    ]);

    $this->withHeaders([
        'X-Inertia' => 'true',
        'Accept' => 'text/html, application/xhtml+xml',
    ])->get(route('x-change.public-auto-generate.show'))
        ->assertOk()
        ->assertJsonPath('props.quick_generate_read_model.mutation_contract.runtime_enabled', false)
        ->assertJsonPath('props.quick_generate_read_model.mutation_contract.route_url', null);

    $this->postJson(route('x-change.public-auto-generate.store'), [])
        ->assertServiceUnavailable()
        ->assertJsonPath('code', 'PUBLIC_ISSUANCE_PAUSED')
        ->assertJsonPath('message', 'New Pay Code orders are temporarily paused. No payment was requested.');

    $this->post(route('x-change.public-auto-generate.store'), [])->assertNotFound();

    expect(PayCodeIssuanceFundingOrder::query()->count())->toBe(0);
});

it('fails closed when the commissioned commercial principal is unavailable', function (): void {
    expect(fn () => app(ConfiguredCommercialPrincipalResolver::class)->resolve())
        ->toThrow(TreasuryConfigurationException::class);
});

it('binds public funding orders to both a browser session and possession token', function (): void {
    $order = makePublicAutoGenerateFundingOrder();
    $request = request();
    $request->setLaravelSession(app('session')->driver());
    $request->session()->start();

    $access = app(PublicIssuanceOrderAccess::class);
    $token = $access->bind($order, $request);
    $request->headers->set(PublicIssuanceOrderAccess::TokenHeader, $token);

    expect($access->authorize($order->refresh(), $request))->toBe($token)
        ->and($access->active($request)['order']->is($order))->toBeTrue();

    $otherRequest = Request::create('/x/auto-generate', 'GET');
    $otherRequest->setLaravelSession(new Store(
        'other-browser',
        new ArraySessionHandler(120),
    ));
    $otherRequest->session()->start();
    $otherRequest->headers->set(PublicIssuanceOrderAccess::TokenHeader, $token);

    expect(fn () => $access->authorize($order->refresh(), $otherRequest))
        ->toThrow(NotFoundHttpException::class);

    $request->headers->set(PublicIssuanceOrderAccess::TokenHeader, 'wrong-token');

    expect(fn () => $access->authorize($order->refresh(), $request))
        ->toThrow(NotFoundHttpException::class);
});

it('refuses an issuance retry without the order possession token', function (): void {
    provisionPublicAutoGeneratePrincipal();
    $order = makePublicAutoGenerateFundingOrder();

    $this->postJson(route('x-change.public-auto-generate.funding-orders.retry-issuance', [
        'order' => $order->reference,
    ]))->assertNotFound();

    expect($order->refresh()->events()->where('event_type', 'manual_issuance_retry_requested')->exists())
        ->toBeFalse();
});

it('authorizes funding-order access for the non-login commercial principal', function (): void {
    $principal = CommercialPrincipal::query()->create([
        'reference' => 'commercial-public',
        'legal_name' => '3neti R&D OPC',
        'authorization_reference' => 'commissioning:commercial-public:v1',
        'active' => true,
        'metadata' => ['interactive_login' => false],
    ]);
    $order = makePublicAutoGenerateFundingOrder((string) $principal->getKey());
    $request = Request::create('/x/auto-generate/funding-orders/'.$order->reference, 'GET');
    $request->setUserResolver(static fn (): CommercialPrincipal => $principal);

    $response = app(CockpitOnDemandIssuanceFundingOrderController::class)->show(
        $request,
        $order,
        app(OnDemandIssuanceFundingOrderPresenter::class),
    );

    expect($response->getStatusCode())->toBe(200)
        ->and($response->getData(true)['order']['reference'])->toBe($order->reference);
});

it('exposes signed recovery and receipt links without exposing a cockpit URL', function (): void {
    $order = makePublicAutoGenerateFundingOrder();
    $request = request();
    $request->setLaravelSession(app('session')->driver());
    $request->session()->start();

    $token = app(PublicIssuanceOrderAccess::class)->bind($order, $request);
    $projection = app(OnDemandIssuanceFundingOrderPresenter::class)->present(
        $order->refresh(),
        $token,
        true,
    );

    expect(data_get($projection, 'public_links.recovery'))->toContain('signature=')
        ->and(data_get($projection, 'public_links.receipt'))->toContain('signature=')
        ->and(data_get($projection, 'order.voucher.detail_url'))->toBeNull();
});

it('recovers only a public order through its valid signed link', function (): void {
    provisionPublicAutoGeneratePrincipal();
    $order = makePublicAutoGenerateFundingOrder();
    $bindingRequest = request();
    $bindingRequest->setLaravelSession(app('session')->driver());
    $bindingRequest->session()->start();
    app(PublicIssuanceOrderAccess::class)->bind($order, $bindingRequest);

    $url = URL::temporarySignedRoute(
        'x-change.public-auto-generate.recover',
        now()->addMinute(),
        ['order' => $order->reference],
    );

    $this->get($url)
        ->assertRedirect(route('x-change.public-auto-generate.show'))
        ->assertSessionHas('x-change.public-auto-generate.active-order.reference', $order->reference);

    $this->get($url.'&tampered=1')->assertNotFound();
});

it('recovers an issued public order through its signed redacted receipt', function (): void {
    provisionPublicAutoGeneratePrincipal();
    $order = makePublicAutoGenerateFundingOrder();
    $bindingRequest = request();
    $bindingRequest->setLaravelSession(app('session')->driver());
    $bindingRequest->session()->start();
    app(PublicIssuanceOrderAccess::class)->bind($order, $bindingRequest);
    $voucher = Voucher::query()->create([
        'code' => 'PUB-RCV1',
        'metadata' => ['source' => 'public_recovery_test'],
    ]);
    $order->forceFill([
        'status' => 'issued',
        'voucher_id' => $voucher->getKey(),
        'funded_at' => now(),
        'issued_at' => now(),
    ])->saveQuietly();
    $bindingMetadata = data_get($order->refresh()->metadata, 'public_auto_generate');
    $this->flushSession();

    $url = URL::temporarySignedRoute(
        'x-change.public-auto-generate.recover',
        now()->addMinute(),
        ['order' => $order->reference],
    );

    $response = $this->get($url)
        ->assertRedirect()
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertHeader('Referrer-Policy', 'no-referrer')
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive')
        ->assertSessionMissing('x-change.public-auto-generate.active-order');
    $location = (string) $response->headers->get('Location');

    expect($location)
        ->toContain('/x/auto-generate/receipts/'.$order->reference)
        ->toContain('signature=')
        ->and(URL::hasValidSignature(Request::create($location)))
        ->toBeTrue()
        ->and(data_get($order->refresh()->metadata, 'public_auto_generate'))
        ->toBe($bindingMetadata);

    $this->withHeaders([
        'X-Inertia' => 'true',
        'Accept' => 'text/html, application/xhtml+xml',
    ])->get($location)
        ->assertOk()
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertHeader('Referrer-Policy', 'no-referrer')
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive')
        ->assertJsonPath('component', 'x-change/public/IssuanceReceipt')
        ->assertJsonPath('props.receipt.order_reference', $order->reference)
        ->assertJsonPath('props.receipt.status', 'issued')
        ->assertJsonPath('props.receipt.pay_code.code', 'PUB-RCV1')
        ->assertJsonPath('props.receipt.pay_code.claim_url', route('x-change.claim.show', [
            'code' => 'PUB-RCV1',
        ]))
        ->assertJsonPath('props.receipt.redactions.payer_identity', true)
        ->assertJsonMissingPath('props.receipt.guest_access_token')
        ->assertJsonMissingPath('props.receipt.actions')
        ->assertJsonMissingPath('props.receipt.provider_transaction_id')
        ->assertJsonMissingPath('props.receipt.cockpit_url');

    $this->get($url.'&tampered=1')->assertNotFound();
});

it('redirects non-issued terminal public orders to their signed receipt', function (
    string $status,
): void {
    provisionPublicAutoGeneratePrincipal();
    $order = makePublicAutoGenerateFundingOrder();
    $bindingRequest = request();
    $bindingRequest->setLaravelSession(app('session')->driver());
    $bindingRequest->session()->start();
    app(PublicIssuanceOrderAccess::class)->bind($order, $bindingRequest);
    $order->forceFill([
        'status' => $status,
        'cancelled_at' => $status === 'cancelled' ? now() : null,
        'expired_at' => $status === 'expired' ? now() : null,
    ])->saveQuietly();

    $url = URL::temporarySignedRoute(
        'x-change.public-auto-generate.recover',
        now()->addMinute(),
        ['order' => $order->reference],
    );

    $location = (string) $this->get($url)
        ->assertRedirect()
        ->headers->get('Location');

    $this->withHeaders([
        'X-Inertia' => 'true',
        'Accept' => 'text/html, application/xhtml+xml',
    ])->get($location)
        ->assertOk()
        ->assertJsonPath('props.receipt.status', $status)
        ->assertJsonPath('props.receipt.pay_code', null);
})->with(['cancelled', 'expired']);

it('refuses recovery when the order was not created by public issuance', function (): void {
    provisionPublicAutoGeneratePrincipal();
    $order = makePublicAutoGenerateFundingOrder();
    $url = URL::temporarySignedRoute(
        'x-change.public-auto-generate.recover',
        now()->addMinute(),
        ['order' => $order->reference],
    );

    $this->get($url)->assertNotFound();
});

it('refuses an expired public issuance recovery link', function (): void {
    provisionPublicAutoGeneratePrincipal();
    $order = makePublicAutoGenerateFundingOrder();
    $bindingRequest = request();
    $bindingRequest->setLaravelSession(app('session')->driver());
    $bindingRequest->session()->start();
    app(PublicIssuanceOrderAccess::class)->bind($order, $bindingRequest);
    $url = URL::temporarySignedRoute(
        'x-change.public-auto-generate.recover',
        now()->addSecond(),
        ['order' => $order->reference],
    );

    $this->travel(2)->seconds();

    $this->get($url)->assertNotFound();
});

it('renders a signed redacted public issuance receipt', function (): void {
    provisionPublicAutoGeneratePrincipal();
    $order = makePublicAutoGenerateFundingOrder();
    $bindingRequest = request();
    $bindingRequest->setLaravelSession(app('session')->driver());
    $bindingRequest->session()->start();
    app(PublicIssuanceOrderAccess::class)->bind($order, $bindingRequest);

    $url = URL::temporarySignedRoute(
        'x-change.public-auto-generate.receipt',
        now()->addMinute(),
        ['order' => $order->reference],
    );

    $this->withHeaders([
        'X-Inertia' => 'true',
        'Accept' => 'text/html, application/xhtml+xml',
    ])->get($url)
        ->assertOk()
        ->assertJsonPath('component', 'x-change/public/IssuanceReceipt')
        ->assertJsonPath('props.receipt.order_reference', $order->reference)
        ->assertJsonPath('props.receipt.redactions.payer_identity', true)
        ->assertJsonMissingPath('props.receipt.provider_transaction_id');
});

it('does not offer cancellation once a public order is funded or needs issuance recovery', function (): void {
    $principal = provisionPublicAutoGeneratePrincipal();
    $order = makePublicAutoGenerateFundingOrder((string) $principal->getKey());
    $order->forceFill([
        'status' => 'issuance_attention',
        'funded_at' => now(),
    ])->saveQuietly();

    $projection = app(OnDemandIssuanceFundingOrderPresenter::class)->present($order->refresh(), null, true);

    expect(data_get($projection, 'order.can_cancel'))->toBeFalse();

    $request = Request::create('/x/auto-generate/funding-orders/'.$order->reference, 'DELETE');
    $request->setUserResolver(static fn () => $principal);

    expect(fn () => app()->call([
        app(CockpitOnDemandIssuanceFundingOrderController::class),
        'cancel',
    ], ['request' => $request, 'order' => $order]))->toThrow(ConflictHttpException::class)
        ->and($order->refresh()->status->value)->toBe('issuance_attention');

    expect(fn () => app(TransitionPayCodeIssuanceFundingOrder::class)->handle(
        order: $order,
        status: PayCodeIssuanceFundingOrderStatus::Cancelled,
        eventType: 'cancelled',
        actorType: 'test',
        actorId: 'test',
    ))->toThrow(RuntimeException::class, 'governed recovery or refund');
});

it('keeps prepayment instruction failures cancellable', function (): void {
    $order = makePublicAutoGenerateFundingOrder();
    $order->forceFill(['status' => 'issuance_attention'])->saveQuietly();

    $projection = app(OnDemandIssuanceFundingOrderPresenter::class)->present($order->refresh(), null, true);

    expect(data_get($projection, 'order.can_cancel'))->toBeTrue();
});

function makePublicAutoGenerateFundingOrder(string $issuerId = '1'): PayCodeIssuanceFundingOrder
{
    return PayCodeIssuanceFundingOrder::query()->create([
        'account_reference' => 'wallet:public-test',
        'issuer_type' => CommercialPrincipal::class,
        'issuer_id' => $issuerId,
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
        'idempotency_key_hash' => str_repeat('c', 64),
        'idempotency_fingerprint' => str_repeat('d', 64),
        'expires_at' => now()->addMinutes(30),
        'metadata' => [],
    ]);
}

function provisionPublicAutoGeneratePrincipal(): CommercialPrincipal
{
    return CommercialPrincipal::query()->create([
        'reference' => 'commercial-public',
        'legal_name' => '3neti R&D OPC',
        'authorization_reference' => 'commissioning:commercial-public:v1',
        'active' => true,
        'metadata' => ['interactive_login' => false],
    ]);
}
