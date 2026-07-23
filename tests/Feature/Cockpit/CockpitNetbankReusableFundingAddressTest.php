<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use LBHurtado\XChange\Models\FundingIntent;
use LBHurtado\XChange\Models\StandingFundingAddress;

beforeEach(function () {
    Cache::clear();
    Http::preventStrayRequests();
    config([
        'x-change.funding.standing_addresses.enabled' => true,
        'x-change.funding.standing_addresses.default_recognition_mode' => 'observe_only',
        'x-change.funding.standing_addresses.middleware' => [],
        'payment-gateway.netbank.funding.api_url' => 'https://api.netbank.test',
        'payment-gateway.netbank.funding.token_url' => 'https://auth.netbank.test/oauth2/token',
        'payment-gateway.netbank.funding.client_id' => 'client-id',
        'payment-gateway.netbank.funding.client_secret' => 'client-secret',
        'payment-gateway.netbank.funding.corporate_account_number' => '113001000019',
        'payment-gateway.netbank.funding.corporate_account_name' => 'X Change Treasury',
        'payment-gateway.netbank.funding.vca_alias' => '91500',
        'payment-gateway.netbank.funding.vca_alias_token' => 'alias-token',
        'payment-gateway.netbank.funding.reference_key' => 'reusable-reference-key',
        'payment-gateway.netbank.funding.standing_address.scheme' => 'netbank-mobile-v1',
        'payment-gateway.netbank.funding.standing_address.reference_length' => 11,
        'payment-gateway.netbank.funding.standing_address.hmac_key_id' => null,
        'payment-gateway.netbank.funding.standing_address.hmac_key' => null,
        'payment-gateway.netbank.funding.qr_endpoint' => 'https://api.netbank.test/v1/qrph/generate',
        'payment-gateway.netbank.funding.qr_merchant_name' => 'X Change',
        'payment-gateway.netbank.funding.qr_merchant_city' => 'Manila',
        'payment-gateway.netbank.funding.qr_resolution' => 480,
        'payment-gateway.netbank.funding.connect_timeout_seconds' => 5,
        'payment-gateway.netbank.funding.timeout_seconds' => 10,
        'payment-gateway.netbank.funding.verification_lookback_days' => 7,
    ]);
});

it('generates an owner-stable Account Funding Address without creating or crediting a Funding Intent', function () {
    $operator = actingAsVerifiedFundingOperator();
    $wallet = $operator->wallet;
    $balanceBefore = (int) $wallet->balance;
    $transactionsBefore = $wallet->transactions()->count();

    Http::fake([
        'https://auth.netbank.test/oauth2/token' => Http::response([
            'access_token' => 'access-token',
            'expires_in' => 3600,
        ]),
        'https://api.netbank.test/v1/qrph/generate' => Http::response([
            'qr_code' => reusableFundingTestPng(),
        ]),
    ]);

    $response = $this->postJson(
        route('x-change.cockpit.funding.standing-addresses.netbank.store'),
        ['confirm_account_funding_address' => true],
    );

    $response
        ->assertOk()
        ->assertHeader('Pragma', 'no-cache')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertJsonPath('schema', 'x-change.cockpit.standing-funding-address.v1')
        ->assertJsonPath('address.provider', 'netbank')
        ->assertJsonPath('address.purpose', 'account_funding')
        ->assertJsonPath('address.recognition_mode', 'observe_only')
        ->assertJsonPath('address.status', 'active')
        ->assertJsonPath('address.qr_mode', 'static')
        ->assertJsonPath('address.embedded_amount', false)
        ->assertJsonPath('address.temporary', false)
        ->assertJsonPath('address.funding_intent_created', false)
        ->assertJsonPath('address.automatic_credit_enabled', false)
        ->assertJsonPath(
            'address.qr_code',
            'data:image/png;base64,'.reusableFundingTestPng(),
        );

    expect($response->headers->get('Cache-Control'))->toContain('no-store')
        ->toContain('private');
    expect((string) $response->json('address.funding_address'))->toBe('9150009173011987')
        ->and(FundingIntent::query()->count())->toBe(0)
        ->and((int) $wallet->fresh()->balance)->toBe($balanceBefore)
        ->and($wallet->transactions()->count())->toBe($transactionsBefore);

    $serializedAudit = json_encode($this->fakeAuditLogger()->last(), JSON_THROW_ON_ERROR);

    expect($serializedAudit)
        ->toContain('funding.standing_address.qr_issued')
        ->not->toContain((string) $response->json('address.funding_address'))
        ->not->toContain(reusableFundingTestPng());

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.netbank.test/v1/qrph/generate'
        && $request['qr_type'] === 'Static'
        && $request['amount'] === ['cur' => 'PHP', 'num' => '']);
    Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), '/pre-transaction/'));
});

it('checks authoritative VCA history without exposing raw provider or payer facts', function () {
    $operator = actingAsVerifiedFundingOperator();
    $wallet = $operator->wallet;
    $balanceBefore = (int) $wallet->balance;

    Http::fake([
        'https://auth.netbank.test/oauth2/token' => Http::response(['access_token' => 'access-token']),
        'https://api.netbank.test/v1/qrph/generate' => Http::response([
            'qr_code' => reusableFundingTestPng(),
        ]),
    ]);

    $addressResponse = $this->postJson(
        route('x-change.cockpit.funding.standing-addresses.netbank.store'),
        ['confirm_account_funding_address' => true],
    )->assertOk();
    $fundingAddress = (string) $addressResponse->json('address.funding_address');

    Http::fake([
        'https://auth.netbank.test/oauth2/token' => Http::response(['access_token' => 'access-token']),
        'https://api.netbank.test/v1/vca/*/transactions*' => Http::response([
            'transactions' => [[
                'amount' => ['cur' => 'PHP', 'num' => '2500'],
                'date' => '2026-07-23T01:05:00.000Z',
                'description' => 'EXTERNAL_TRANSFER_INCOMING',
                'destination_account' => [
                    'account_alias' => $fundingAddress,
                    'account_number' => 'sensitive-destination-account',
                ],
                'sender' => [
                    'name' => 'Sensitive Payer',
                    'account_number' => 'sensitive-payer-account',
                ],
                'status' => 'Settled',
                'status_details' => [[
                    'status' => 'Settled',
                    'updated' => '2026-07-23T01:06:00.000Z',
                ]],
                'transaction_id' => 'provider-transaction-secret',
                'type' => 'Credit',
                'updated' => '2026-07-23T01:06:00.000Z',
            ]],
        ]),
    ]);

    $response = $this->postJson(
        route('x-change.cockpit.funding.standing-addresses.netbank.history-checks.store'),
        ['confirm_account_funding_address' => true],
    );

    $response
        ->assertOk()
        ->assertJsonPath('schema', 'x-change.cockpit.standing-funding-history.v1')
        ->assertJsonPath('observations.0.gross_amount_minor', 2500)
        ->assertJsonPath('observations.0.gross_amount', '₱25.00')
        ->assertJsonPath('observations.0.provider_status', 'observed')
        ->assertJsonPath('sync.observed', 1)
        ->assertJsonPath('balance_changed', false)
        ->assertJsonPath('funding_intent_created', false);

    $serialized = $response->getContent();

    expect($serialized)
        ->not->toContain('provider-transaction-secret')
        ->not->toContain('Sensitive Payer')
        ->not->toContain('sensitive-payer-account')
        ->not->toContain('sensitive-destination-account')
        ->not->toContain($fundingAddress);
    expect(FundingIntent::query()->count())->toBe(0)
        ->and((int) $wallet->fresh()->balance)->toBe($balanceBefore);
});

it('requires explicit acknowledgement and fails closed while disabled', function () {
    actingAsVerifiedFundingOperator();

    $this->postJson(
        route('x-change.cockpit.funding.standing-addresses.netbank.store'),
    )->assertUnprocessable()
        ->assertJsonValidationErrors('confirm_account_funding_address');

    config()->set('x-change.funding.standing_addresses.enabled', false);

    $this->postJson(
        route('x-change.cockpit.funding.standing-addresses.netbank.store'),
        ['confirm_account_funding_address' => true],
    )->assertUnprocessable()
        ->assertJsonValidationErrors('standing_funding_address');

    Http::assertNothingSent();
});

it('keeps the sensitive QR and address out of initial Inertia props', function () {
    actingAsVerifiedFundingOperator();

    $this->withHeader('X-Inertia', 'true')
        ->get(route('x-change.cockpit.funding.index'))
        ->assertOk()
        ->assertJsonPath('props.standing_funding_address.available', true)
        ->assertJsonPath('props.standing_funding_address.temporary', false)
        ->assertJsonPath('props.standing_funding_address.purpose', 'account_funding')
        ->assertJsonPath('props.standing_funding_address.recognition_mode', 'observe_only')
        ->assertJsonPath('props.standing_funding_address.automatic_credit_enabled', false)
        ->assertJsonMissingPath('props.standing_funding_address.funding_address')
        ->assertJsonMissingPath('props.standing_funding_address.qr_code');
});

it('fails closed when the configured corporate account name is missing', function () {
    actingAsVerifiedFundingOperator();
    config()->set('payment-gateway.netbank.funding.corporate_account_name');

    $this->withHeader('X-Inertia', 'true')
        ->get(route('x-change.cockpit.funding.index'))
        ->assertOk()
        ->assertJsonPath('props.standing_funding_address.available', false)
        ->assertJsonPath('props.standing_funding_address.status', 'not_configured');
});

it('does not require a VCA alias token for a shared reusable address', function () {
    actingAsVerifiedFundingOperator();
    config()->set('payment-gateway.netbank.funding.vca_alias_token');

    $this->withHeader('X-Inertia', 'true')
        ->get(route('x-change.cockpit.funding.index'))
        ->assertOk()
        ->assertJsonPath('props.standing_funding_address.available', true)
        ->assertJsonPath('props.standing_funding_address.status', 'available');
});

it('requires a verified mobile before creating a mobile-derived address', function () {
    $operator = actingAsTestUser();
    $operator->forceFill([
        'mobile' => '639173011987',
        'mobile_verified_at' => null,
    ])->save();

    $this->withHeader('X-Inertia', 'true')
        ->get(route('x-change.cockpit.funding.index'))
        ->assertOk()
        ->assertJsonPath('props.standing_funding_address.available', false)
        ->assertJsonPath('props.standing_funding_address.status', 'mobile_not_verified');
});

it('keeps a persisted HMAC address stable across key rotation', function () {
    actingAsTestUser();
    config([
        'payment-gateway.netbank.funding.standing_address.scheme' => 'netbank-account-hmac-v2',
        'payment-gateway.netbank.funding.standing_address.hmac_key_id' => 'v2-2026-01',
        'payment-gateway.netbank.funding.standing_address.hmac_key' => 'base64:'.base64_encode(
            str_repeat('a', 32),
        ),
    ]);
    Http::fake([
        'https://auth.netbank.test/oauth2/token' => Http::response([
            'access_token' => 'access-token',
            'expires_in' => 3600,
        ]),
        'https://api.netbank.test/v1/qrph/generate' => Http::response([
            'qr_code' => reusableFundingTestPng(),
        ]),
    ]);

    $first = $this->postJson(
        route('x-change.cockpit.funding.standing-addresses.netbank.store'),
        ['confirm_account_funding_address' => true],
    )->assertOk();
    $firstAddress = (string) $first->json('address.funding_address');

    config([
        'payment-gateway.netbank.funding.standing_address.hmac_key_id' => 'v3-2027-01',
        'payment-gateway.netbank.funding.standing_address.hmac_key' => 'base64:'.base64_encode(
            str_repeat('b', 32),
        ),
    ]);

    $second = $this->postJson(
        route('x-change.cockpit.funding.standing-addresses.netbank.store'),
        ['confirm_account_funding_address' => true],
    )->assertOk();
    $stored = StandingFundingAddress::query()->sole();

    expect($firstAddress)->toMatch('/\A91500\d{11}\z/')
        ->and($second->json('address.funding_address'))->toBe($firstAddress)
        ->and($stored->derivation_scheme)->toBe('netbank-account-hmac-v2')
        ->and($stored->derivation_key_id)->toBe('v2-2026-01')
        ->and($stored->derivation_counter)->toBe(0)
        ->and(StandingFundingAddress::query()->count())->toBe(1);
});

function actingAsVerifiedFundingOperator(): object
{
    $operator = actingAsTestUser();
    $operator->forceFill([
        'mobile' => '639173011987',
        'mobile_verified_at' => now(),
    ])->save();

    return $operator;
}

function reusableFundingTestPng(): string
{
    return 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAFgwJ/lDoLpwAAAABJRU5ErkJggg==';
}
