<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use LBHurtado\XChange\Models\FundingIntent;

beforeEach(function () {
    Cache::clear();
    Http::preventStrayRequests();
    config([
        'x-change.funding.reusable_address.enabled' => true,
        'x-change.funding.reusable_address.middleware' => [],
        'payment-gateway.netbank.funding.api_url' => 'https://api.netbank.test',
        'payment-gateway.netbank.funding.token_url' => 'https://auth.netbank.test/oauth2/token',
        'payment-gateway.netbank.funding.client_id' => 'client-id',
        'payment-gateway.netbank.funding.client_secret' => 'client-secret',
        'payment-gateway.netbank.funding.corporate_account_number' => '113001000019',
        'payment-gateway.netbank.funding.vca_alias' => '91500',
        'payment-gateway.netbank.funding.reference_key' => 'reusable-reference-key',
        'payment-gateway.netbank.funding.qr_endpoint' => 'https://api.netbank.test/v1/qrph/generate',
        'payment-gateway.netbank.funding.qr_merchant_name' => 'X Change',
        'payment-gateway.netbank.funding.qr_merchant_city' => 'Manila',
        'payment-gateway.netbank.funding.qr_resolution' => 480,
        'payment-gateway.netbank.funding.connect_timeout_seconds' => 5,
        'payment-gateway.netbank.funding.timeout_seconds' => 10,
        'payment-gateway.netbank.funding.verification_lookback_days' => 7,
    ]);
});

it('generates an owner-stable temporary static QR without creating or crediting a Funding Intent', function () {
    $operator = actingAsTestUser();
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
        route('x-change.cockpit.funding.reusable-addresses.netbank.store'),
        ['confirm_temporary_reusable_address' => true],
    );

    $response
        ->assertOk()
        ->assertHeader('Pragma', 'no-cache')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertJsonPath('schema', 'x-change.cockpit.netbank-reusable-funding-address.v1')
        ->assertJsonPath('address.provider', 'netbank')
        ->assertJsonPath('address.qr_mode', 'static')
        ->assertJsonPath('address.embedded_amount', false)
        ->assertJsonPath('address.temporary', true)
        ->assertJsonPath('address.funding_intent_created', false)
        ->assertJsonPath('address.automatic_credit_enabled', false)
        ->assertJsonPath(
            'address.qr_code',
            'data:image/png;base64,'.reusableFundingTestPng(),
        );

    expect($response->headers->get('Cache-Control'))->toContain('no-store')
        ->toContain('private');
    expect((string) $response->json('address.funding_address'))->toMatch('/\A91500\d{16}\z/')
        ->and(FundingIntent::query()->count())->toBe(0)
        ->and((int) $wallet->fresh()->balance)->toBe($balanceBefore)
        ->and($wallet->transactions()->count())->toBe($transactionsBefore);

    $serializedAudit = json_encode($this->fakeAuditLogger()->last(), JSON_THROW_ON_ERROR);

    expect($serializedAudit)
        ->toContain('funding.reusable_address.generated')
        ->not->toContain((string) $response->json('address.funding_address'))
        ->not->toContain(reusableFundingTestPng());

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.netbank.test/v1/qrph/generate'
        && $request['qr_type'] === 'Static'
        && $request['amount'] === ['cur' => 'PHP', 'num' => '']);
    Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), '/pre-transaction/'));
});

it('checks authoritative VCA history without exposing raw provider or payer facts', function () {
    $operator = actingAsTestUser();
    $wallet = $operator->wallet;
    $balanceBefore = (int) $wallet->balance;

    Http::fake([
        'https://auth.netbank.test/oauth2/token' => Http::response(['access_token' => 'access-token']),
        'https://api.netbank.test/v1/vca/*/transactions*' => Http::response([
            'transactions' => [[
                'amount' => ['cur' => 'PHP', 'num' => '2500'],
                'date' => '2026-07-23T01:05:00.000Z',
                'description' => 'EXTERNAL_TRANSFER_INCOMING',
                'destination_account' => [
                    'account_alias' => reusableFundingAddressFor($operator),
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
        route('x-change.cockpit.funding.reusable-addresses.netbank.history-checks.store'),
        ['confirm_temporary_reusable_address' => true],
    );

    $response
        ->assertOk()
        ->assertJsonPath('schema', 'x-change.cockpit.netbank-reusable-funding-history.v1')
        ->assertJsonPath('observations.0.gross_amount_minor', 2500)
        ->assertJsonPath('observations.0.gross_amount', '₱25.00')
        ->assertJsonPath('observations.0.provider_status', 'settled')
        ->assertJsonPath('balance_changed', false)
        ->assertJsonPath('funding_intent_created', false);

    $serialized = $response->getContent();

    expect($serialized)
        ->not->toContain('provider-transaction-secret')
        ->not->toContain('Sensitive Payer')
        ->not->toContain('sensitive-payer-account')
        ->not->toContain('sensitive-destination-account')
        ->not->toContain(reusableFundingAddressFor($operator));
    expect(FundingIntent::query()->count())->toBe(0)
        ->and((int) $wallet->fresh()->balance)->toBe($balanceBefore);
});

it('requires explicit acknowledgement and fails closed while disabled', function () {
    actingAsTestUser();

    $this->postJson(
        route('x-change.cockpit.funding.reusable-addresses.netbank.store'),
    )->assertUnprocessable()
        ->assertJsonValidationErrors('confirm_temporary_reusable_address');

    config()->set('x-change.funding.reusable_address.enabled', false);

    $this->postJson(
        route('x-change.cockpit.funding.reusable-addresses.netbank.store'),
        ['confirm_temporary_reusable_address' => true],
    )->assertUnprocessable()
        ->assertJsonValidationErrors('reusable_address');

    Http::assertNothingSent();
});

it('keeps the sensitive QR and address out of initial Inertia props', function () {
    actingAsTestUser();

    $this->withHeader('X-Inertia', 'true')
        ->get(route('x-change.cockpit.funding.index'))
        ->assertOk()
        ->assertJsonPath('props.reusable_funding_address.available', true)
        ->assertJsonPath('props.reusable_funding_address.temporary', true)
        ->assertJsonPath('props.reusable_funding_address.automatic_credit_enabled', false)
        ->assertJsonMissingPath('props.reusable_funding_address.funding_address')
        ->assertJsonMissingPath('props.reusable_funding_address.qr_code');
});

function reusableFundingAddressFor(object $owner): string
{
    $digest = hash_hmac(
        'sha256',
        'reusable-funding-address|'.$owner::class.':'.$owner->getKey(),
        'reusable-reference-key',
        true,
    );
    $numeric = '';

    for ($index = 0; $index < 16; $index++) {
        $numeric .= (string) (ord($digest[$index]) % 10);
    }

    return '91500'.$numeric;
}

function reusableFundingTestPng(): string
{
    return 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAFgwJ/lDoLpwAAAABJRU5ErkJggg==';
}
