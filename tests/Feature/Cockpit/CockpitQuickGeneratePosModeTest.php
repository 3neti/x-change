<?php

declare(strict_types=1);

use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Models\PaymentAttempt;
use LBHurtado\XChange\Services\Commercial\ProvisionCommercialBaselines;
use LBHurtado\XChange\Services\Funding\FundingProviderAdapterRegistry;
use LBHurtado\XChange\Tests\Fakes\FakeFundingProviderAdapter;
use LBHurtado\XChange\Tests\Fakes\User;

beforeEach(function (): void {
    config()->set('x-change.lifecycle.defaults.user_model', User::class);
    config()->set('x-change.onboarding.issuer_model', User::class);
    config()->set('x-change.commercial.legal_trace.legal_entity_reference', 'legal-entity:x-change:pos-test');
    config()->set('x-change.commercial.legal_trace.profile_version', 'pos-test-v1');
    config()->set('x-change.funding.providers.netbank.enabled', true);
    config()->set('x-change.payment.attempts.enabled', true);
    config()->set('x-change.payment.attempts.provider', 'netbank');

    $catalog = config('x-commerce.catalogs.pay_code');
    $catalog['version'] = 272;
    $catalog['items']['voucher_type.payable']['unit_price_minor'] = 0;
    config()->set('x-commerce.catalogs.pay_code', $catalog);

    app(ProvisionCommercialBaselines::class)
        ->provision('commissioning-manifest:cockpit-pos-mode');

    $adapter = new FakeFundingProviderAdapter;
    $this->app->instance(FakeFundingProviderAdapter::class, $adapter);
    $this->app->tag(FakeFundingProviderAdapter::class, 'emi.funding-provider-adapters');
    $this->app->forgetInstance(FundingProviderAdapterRegistry::class);
});

it('issues a payable POS sale and prepares its operator-safe QR Ph attempt', function (): void {
    $operator = actingAsTestUser();

    $issuance = $this->withHeaders([
        'Accept' => 'application/json',
        'Idempotency-Key' => 'cockpit-pos-sale-1042',
    ])->postJson(
        route('x-change.cockpit.quick-generate.store'),
        cockpitPosPayload(),
    )->assertCreated()
        ->assertJsonPath('result.amount', 50)
        ->assertJsonPath('result.currency', 'PHP')
        ->assertJsonPath(
            'result.links.collection_attempt',
            fn (mixed $link): bool => is_string($link)
                && str_contains($link, '/collection-attempts'),
        );

    $code = (string) $issuance->json('result.code');
    $voucher = Voucher::query()->where('code', $code)->sole();

    expect(data_get($voucher->metadata, 'instructions.cash.amount'))->toBe(0)
        ->and(data_get($voucher->metadata, 'instructions.target_amount'))->toBe(50)
        ->and(data_get($voucher->metadata, 'instructions.metadata.custom.external_reference'))->toBe('ORDER-1042 · Snacks')
        ->and(data_get($voucher->metadata, 'instructions.metadata.custom.cockpit.payee.kind'))->toBe('open')
        ->and(data_get($voucher->metadata, 'instructions.metadata.collection_wallet_id'))->toBe((string) $operator->wallet()->where('slug', 'platform')->sole()->getKey());

    $this->postJson((string) $issuance->json('result.links.collection_attempt'))
        ->assertOk()
        ->assertHeader('Cache-Control', 'max-age=0, no-store, private')
        ->assertJsonPath('schema', 'x-change.cockpit.payment-attempt.v1')
        ->assertJsonPath('attempt.status', 'awaiting_payment')
        ->assertJsonPath('attempt.amount_minor', 5000)
        ->assertJsonPath('attempt.qr_code.mime_type', 'image/png')
        ->assertJsonPath('attempt.qr_code.embedded_amount', true)
        ->assertJsonPath(
            'attempt.qr_code.base64_payload',
            fn (mixed $payload): bool => is_string($payload) && $payload !== '',
        );

    expect(PaymentAttempt::query()->where('voucher_id', $voucher->getKey())->count())
        ->toBe(1);

    $this->withHeader('X-Inertia', 'true')
        ->get(route('x-change.cockpit.quick-generate', ['pos_code' => $code]))
        ->assertOk()
        ->assertJsonPath('props.pos_voucher.code', $code)
        ->assertJsonPath('props.pos_voucher.summary.external_reference', 'ORDER-1042 · Snacks')
        ->assertJsonPath('props.pos_voucher.collection.consumer_status', 'processing')
        ->assertJsonPath('props.pos_voucher.collection.target_amount_minor', 5000)
        ->assertJsonPath('props.pos_voucher.collection.is_fully_collected', false);
});

it('overrides a manipulated foreign collection wallet with the operator wallet', function (): void {
    $operator = actingAsTestUser();
    $operatorWallet = $operator->wallet()->where('slug', 'platform')->sole();
    $foreignOwner = User::query()->create([
        'name' => 'Foreign POS Owner',
        'email' => 'foreign-pos-owner@example.com',
        'password' => 'not-a-login-credential',
    ]);
    $foreignWallet = $foreignOwner->wallet()->firstOrCreate([
        'slug' => 'platform',
    ], [
        'name' => 'Foreign Platform Account',
    ]);
    $payload = cockpitPosPayload();
    data_set(
        $payload,
        'metadata.collection_wallet_id',
        (string) $foreignWallet->getKey(),
    );

    $issuance = $this->withHeaders([
        'Accept' => 'application/json',
        'Idempotency-Key' => 'cockpit-pos-foreign-wallet',
    ])->postJson(
        route('x-change.cockpit.quick-generate.store'),
        $payload,
    )->assertCreated();

    $voucher = Voucher::query()
        ->where('code', $issuance->json('result.code'))
        ->sole();

    expect(data_get(
        $voucher->metadata,
        'instructions.metadata.collection_wallet_id',
    ))->toBe((string) $operatorWallet->getKey())
        ->not->toBe((string) $foreignWallet->getKey());
});

it('conceals a foreign POS status projection', function (): void {
    $owner = actingAsTestUser();
    $voucher = issueVoucher(validVoucherInstructions(
        amount: 0,
        overrides: [
            'voucher_type' => 'payable',
            'target_amount' => 50,
            'metadata' => [
                'issuer_id' => (string) $owner->getKey(),
                'collection_wallet_id' => (string) $owner->wallet()->where('slug', 'platform')->sole()->getKey(),
            ],
        ],
    ));

    $other = actingAsTestUser();
    expect($other->is($owner))->toBeFalse();

    $this->actingAs($other)
        ->withHeader('X-Inertia', 'true')
        ->get(route('x-change.cockpit.quick-generate', ['pos_code' => $voucher->code]))
        ->assertNotFound();
});

/**
 * @return array<string, mixed>
 */
function cockpitPosPayload(): array
{
    return [
        'cash' => [
            'amount' => 50,
            'currency' => 'PHP',
            'validation' => [
                'country' => 'PH',
            ],
        ],
        'inputs' => [
            'fields' => [],
            'requirements' => [],
        ],
        'feedback' => [
            'email' => null,
            'mobile' => null,
            'webhook' => null,
        ],
        'rider' => [
            'message' => null,
            'url' => null,
            'redirect_timeout' => null,
            'splash' => null,
            'splash_timeout' => null,
            'og_source' => null,
        ],
        'count' => 1,
        'voucher_type' => 'payable',
        'target_amount' => 50,
        'metadata' => [
            'custom' => [
                'external_reference' => 'ORDER-1042 · Snacks',
                'cockpit' => [
                    'source' => 'cockpit.quick-generate',
                    'builder' => 'pos',
                    'payee' => [
                        'kind' => 'open',
                        'explicit_secret' => false,
                    ],
                ],
            ],
        ],
    ];
}
