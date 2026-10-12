<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Laravel\Passport\Client;
use Laravel\Passport\Passport;
use LBHurtado\XChange\Actions\PartnerApi\CreatePartnerApiClient;
use LBHurtado\XChange\Actions\PayCode\EstimatePayCodeCost;
use LBHurtado\XChange\Data\PricingEstimateData;
use LBHurtado\XChange\Models\Checkout;
use LBHurtado\XChange\Models\CommercialPrincipal;
use LBHurtado\XChange\Models\PartnerApiClient;
use LBHurtado\XChange\Models\PayCodeIssuanceFundingOrder;
use LBHurtado\XChange\Services\Funding\FundingProviderAdapterRegistry;
use LBHurtado\XChange\Tests\Fakes\FakeFundingProviderAdapter;
use LBHurtado\XChange\Tests\Fakes\User;

function fundedOrderClient(array $scopes): PartnerApiClient
{
    $issuer = User::query()->create([
        'name' => 'Funded order agent',
        'email' => 'funded-'.str()->uuid().'@example.test',
        'password' => Hash::make('password'),
    ]);
    $credential = app(CreatePartnerApiClient::class)->handle(
        name: 'Funded order agent',
        issuer: $issuer,
        scopes: $scopes,
    );
    Passport::actingAsClient(Client::query()->findOrFail($credential->client_id), $scopes);

    return PartnerApiClient::query()->where('reference', $credential->reference)->sole();
}

function fundedOrderForClient(PartnerApiClient $client): PayCodeIssuanceFundingOrder
{
    $principal = CommercialPrincipal::query()->create([
        'reference' => 'funded-agent-principal',
        'legal_name' => 'Funded Agent Principal',
        'authorization_reference' => 'commissioning:funded-agent-principal:v1',
        'active' => true,
        'metadata' => ['interactive_login' => false],
    ]);
    $order = PayCodeIssuanceFundingOrder::query()->create([
        'account_reference' => 'wallet:funded-agent-test',
        'issuer_type' => CommercialPrincipal::class,
        'issuer_id' => (string) $principal->getKey(),
        'provider' => 'netbank',
        'connection_reference' => 'netbank-primary',
        'funding_basis' => 'full_amount',
        'instructions_ciphertext' => ['cash' => ['amount' => 50, 'currency' => 'PHP']],
        'instructions_fingerprint' => str_repeat('a', 64),
        'pricing_snapshot_ciphertext' => ['total' => 50],
        'pricing_fingerprint' => str_repeat('b', 64),
        'required_amount_minor' => 5000,
        'reserved_client_funds_minor' => 0,
        'on_demand_amount_minor' => 5000,
        'reconciliation_adjustment_minor' => 0,
        'expected_payment_minor' => 5000,
        'currency' => 'PHP',
        'status' => 'awaiting_payment',
        'version' => 1,
        'idempotency_key_hash' => str_repeat('c', 64),
        'idempotency_fingerprint' => str_repeat('d', 64),
        'expires_at' => now()->addMinutes(30),
        'metadata' => ['source' => 'partner.funded-order'],
    ]);
    Checkout::query()->create([
        'source' => 'partner.funded-order',
        'owner_type' => CommercialPrincipal::class,
        'owner_id' => (string) $principal->getKey(),
        'funding_order_id' => $order->getKey(),
        'partner_api_client_id' => $client->getKey(),
        'status' => 'placed',
        'selected_method' => 'qr_ph',
        'placed_at' => now(),
    ]);

    return $order;
}

it('keeps paid order status readable to its client when new orders are paused', function (): void {
    config()->set('x-change.partner_api.funded_orders.enabled', false);
    $client = fundedOrderClient(['funded-orders:read']);
    $order = fundedOrderForClient($client);

    $this->getJson(route('x-change.partner-api.funded-orders.show', $order->reference))
        ->assertOk()
        ->assertJsonPath('data.reference', $order->reference)
        ->assertJsonPath('data.amount_minor', 5000)
        ->assertJsonPath('data.pay_code', null);
});

it('does not expose another clients order', function (): void {
    $owner = fundedOrderClient(['funded-orders:read']);
    $order = fundedOrderForClient($owner);
    fundedOrderClient(['funded-orders:read']);

    $this->getJson(route('x-change.partner-api.funded-orders.show', $order->reference))
        ->assertNotFound();
});

it('requires the funded order scopes and creation switch', function (): void {
    fundedOrderClient(['capabilities:read']);

    $this->postJson(route('x-change.partner-api.funded-orders.store'), [])
        ->assertForbidden();

    fundedOrderClient(['funded-orders:create']);
    $this->postJson(route('x-change.partner-api.funded-orders.store'), [])
        ->assertNotFound();
});

it('creates and replays a client-bound bank-transfer checkout', function (): void {
    config()->set('x-change.partner_api.funded_orders.enabled', true);
    config()->set('x-change.public_auto_generate.enabled', true);
    config()->set('x-change.issuance_funding.on_demand.enabled', true);
    config()->set('x-change.funding.requests.bank_transfer.enabled', true);
    config()->set('x-change.funding.providers.netbank.enabled', true);
    config()->set('x-change.commercial.principal.reference', 'funded-agent-principal');
    Queue::fake();
    enableNetbankTreasuryForTests();
    $client = fundedOrderClient(['funded-orders:create', 'funded-orders:read']);
    CommercialPrincipal::query()->create([
        'reference' => 'funded-agent-principal',
        'legal_name' => 'Funded Agent Principal',
        'authorization_reference' => 'commissioning:funded-agent-principal:v1',
        'active' => true,
        'metadata' => ['interactive_login' => false],
    ]);
    $adapter = new FakeFundingProviderAdapter;
    $this->app->instance(FakeFundingProviderAdapter::class, $adapter);
    $this->app->tag(FakeFundingProviderAdapter::class, 'emi.funding-provider-adapters');
    $this->app->forgetInstance(FundingProviderAdapterRegistry::class);
    $estimate = Mockery::mock(EstimatePayCodeCost::class);
    $estimate->shouldReceive('handle')->twice()->andReturn(new PricingEstimateData(
        currency: 'PHP',
        pay_code_value: 50,
        account_debit: 50,
    ));
    $this->app->instance(EstimatePayCodeCost::class, $estimate);
    $payload = validVoucherInstructions(50, 'INSTAPAY')->toArray();
    unset($payload['slice_plan']);
    $payload['feedback']['webhook'] = null;
    $payload['funding_method'] = 'bank_transfer';
    $payload['payer_mobile'] = '09175180722';
    $headers = ['Idempotency-Key' => 'funded-order-test-1'];

    $invalid = $payload;
    $invalid['payer_mobile'] = 'not-a-mobile';
    $this->withHeaders($headers)
        ->postJson(route('x-change.partner-api.funded-orders.store'), $invalid)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('payer_mobile');

    $invalid = $payload;
    $invalid['cash']['amount'] = 50.001;
    $this->withHeaders($headers)
        ->postJson(route('x-change.partner-api.funded-orders.store'), $invalid)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('cash.amount');

    $first = $this->withHeaders($headers)
        ->postJson(route('x-change.partner-api.funded-orders.store'), $payload)
        ->assertCreated()
        ->assertJsonPath('data.funding_method', 'bank_transfer');
    $reference = $first->json('data.reference');

    $this->withHeaders($headers)
        ->postJson(route('x-change.partner-api.funded-orders.store'), $payload)
        ->assertOk()
        ->assertJsonPath('data.reference', $reference)
        ->assertJsonPath('meta.idempotency.replayed', true);

    $changed = $payload;
    $changed['cash']['amount'] = 51;
    $this->withHeaders($headers)
        ->postJson(route('x-change.partner-api.funded-orders.store'), $changed)
        ->assertConflict();

    config()->set('x-change.partner_api.funded_orders.maximum_open_per_client', 1);
    $this->withHeaders(['Idempotency-Key' => 'funded-order-test-2'])
        ->postJson(route('x-change.partner-api.funded-orders.store'), $payload)
        ->assertTooManyRequests();

    $this->getJson(route('x-change.partner-api.funded-orders.show', $reference))
        ->assertOk()
        ->assertJsonPath('data.reference', $reference);

    config()->set('x-change.partner_api.funded_orders.maximum_open_per_client', 2);
    config()->set('x-change.issuance_funding.on_demand.fixed_qr_ph.enabled', true);
    config()->set('x-change.issuance_funding.on_demand.fixed_qr_ph.netbank_mode', 'registered_vca');
    $qrPayload = $payload;
    $qrPayload['funding_method'] = 'qr_ph';
    unset($qrPayload['payer_mobile']);
    $qr = $this->withHeaders(['Idempotency-Key' => 'funded-order-test-qr'])
        ->postJson(route('x-change.partner-api.funded-orders.store'), $qrPayload)
        ->assertCreated()
        ->assertJsonPath('data.funding_method', 'qr_ph');
    expect($qr->json('data.funding.qr_code'))->toStartWith('data:image/png;base64,');

    expect(Checkout::query()->where('partner_api_client_id', $client->getKey())->count())->toBe(2)
        ->and(Checkout::query()->where('selected_method', 'bank_transfer')->sole()->visitor_mobile_ciphertext)->toBe('639175180722');

    PayCodeIssuanceFundingOrder::query()->where('reference', $reference)->firstOrFail()
        ->forceFill(['status' => 'funded', 'funded_at' => now()])->saveQuietly();
    $this->getJson(route('x-change.partner-api.funded-orders.show', $reference))
        ->assertOk()
        ->assertJsonPath('data.payment_required', false)
        ->assertJsonPath('data.funding', null);
});
