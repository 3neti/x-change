<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use LBHurtado\XChange\Exceptions\TreasuryConfigurationException;
use LBHurtado\XChange\Http\Controllers\Web\Cockpit\CockpitOnDemandIssuanceFundingOrderController;
use LBHurtado\XChange\Models\CommercialPrincipal;
use LBHurtado\XChange\Models\PayCodeIssuanceFundingOrder;
use LBHurtado\XChange\Services\Cockpit\OnDemandIssuanceFundingOrderPresenter;
use LBHurtado\XChange\Services\Commercial\ConfiguredCommercialPrincipalResolver;
use LBHurtado\XChange\Services\PublicIssuance\PublicIssuanceOrderAccess;
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
        ->assertJsonPath('props.public_navigation.claim_url', '/x/claim')
        ->assertJsonPath('props.public_navigation.login_url', null)
        ->assertJsonPath('props.commercial_principal.reference', 'commercial-public');
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

    $this->postJson(route('x-change.public-auto-generate.store'), [])->assertNotFound();
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
