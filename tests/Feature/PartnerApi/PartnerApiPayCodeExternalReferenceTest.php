<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Hash;
use Laravel\Passport\Client;
use Laravel\Passport\Passport;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Actions\PartnerApi\CreatePartnerApiClient;
use LBHurtado\XChange\Actions\PayCode\GeneratePayCode;
use LBHurtado\XChange\Contracts\VoucherLifecycleServiceContract;
use LBHurtado\XChange\Data\DebitData;
use LBHurtado\XChange\Data\IssuerData;
use LBHurtado\XChange\Data\PayCode\GeneratePayCodeResultData;
use LBHurtado\XChange\Data\PayCodeLinksData;
use LBHurtado\XChange\Data\PricingEstimateData;
use LBHurtado\XChange\Models\PartnerApiClient;
use LBHurtado\XChange\Models\PartnerApiPayCodeReference;
use LBHurtado\XChange\Services\PartnerApi\PartnerPayCodeReferenceService;
use LBHurtado\XChange\Tests\Fakes\User;

/** @return array{User, mixed} */
function bplsAuthenticatePartner(array $scopes, string $name = 'BPLS Client'): array
{
    $issuer = User::query()->create([
        'name' => $name,
        'email' => str()->lower((string) str()->ulid()).'@bpls.example.test',
        'password' => Hash::make('password'),
    ]);
    fundTestUserWallet($issuer);
    $credential = app(CreatePartnerApiClient::class)->handle(
        name: $name,
        issuer: $issuer,
        scopes: $scopes,
    );

    Passport::actingAsClient(Client::query()->findOrFail($credential->client_id), $scopes);

    return [$issuer, $credential];
}

/** @return array<string, mixed> */
function bplsIssuancePayload(string $externalReference, float $amount = 100.00): array
{
    $payload = validVoucherInstructions($amount, 'INSTAPAY', [
        'cash' => ['validation' => ['mobile' => '09171234567']],
        'metadata' => [],
    ])->toArray();
    unset($payload['slice_plan']);
    $payload['external_reference'] = $externalReference;

    return $payload;
}

function bplsIssuanceResult(
    User $issuer,
    string $externalReference,
    string $code,
    float $amount = 100.00,
): GeneratePayCodeResultData {
    $voucher = new Voucher([
        'code' => $code,
        'metadata' => [
            'instructions' => [
                'cash' => ['amount' => $amount, 'currency' => 'PHP'],
                'metadata' => ['custom' => ['external_reference' => $externalReference]],
            ],
        ],
        'state' => 'active',
    ]);
    $voucher->owner()->associate($issuer);
    $voucher->save();

    return new GeneratePayCodeResultData(
        voucher_id: $voucher->getKey(),
        code: $code,
        amount: $amount,
        currency: 'PHP',
        issuer: new IssuerData(id: $issuer->getKey()),
        cost: new PricingEstimateData(currency: 'PHP', base_fee: 1.0, total: 1.0),
        wallet: ['balance_before' => 1000.0, 'balance_after' => 899.0],
        debit: new DebitData(id: 501, amount: $amount + 1.0),
        links: new PayCodeLinksData(
            redeem: "https://example.test/x/claim/{$code}",
            redeem_path: "/x/claim/{$code}",
            pay: "https://example.test/x/pay/{$code}",
            pay_path: "/x/pay/{$code}",
        ),
    );
}

/** @return array<string, mixed> */
function bplsLifecycleDetail(Voucher $voucher, float $amount = 100.00): array
{
    $externalReference = data_get($voucher->metadata, 'instructions.metadata.custom.external_reference');

    return [
        'code' => $voucher->code,
        'amount' => $amount,
        'currency' => 'PHP',
        'operational_status' => ['key' => 'active'],
        'capability' => ['key' => 'disbursement'],
        'party' => null,
        'created_at' => $voucher->created_at?->toISOString(),
        'starts_at' => null,
        'expires_at' => null,
        'redeemed_at' => null,
        'claimed' => false,
        'fully_claimed' => false,
        'attention' => null,
        'external_reference' => $externalReference,
        'consumer_status' => 'payable',
        'collection' => null,
    ];
}

it('requires a valid external reference for Partner API issuance', function (): void {
    bplsAuthenticatePartner(['pay-codes:issue']);
    $payload = bplsIssuancePayload('BPLS-2026-0001');
    unset($payload['external_reference']);
    $action = Mockery::mock(GeneratePayCode::class);
    $action->shouldNotReceive('handle');
    $this->app->instance(GeneratePayCode::class, $action);

    $this->postJson('/api/partner/v1/pay-codes', $payload, [
        'Idempotency-Key' => 'bpls-missing-reference',
    ])->assertUnprocessable()->assertJsonValidationErrors('external_reference');
});

it('canonicalizes equivalent issuance terms and hashes payable target amounts', function (): void {
    $references = app(PartnerPayCodeReferenceService::class);
    $implicitRedeemable = bplsIssuancePayload('BPLS-TERMS');
    $explicitRedeemable = $implicitRedeemable;
    $explicitRedeemable['voucher_type'] = 'redeemable';
    data_set($explicitRedeemable, 'cash.currency', 'php');
    $payable = $implicitRedeemable;
    $payable['voucher_type'] = 'payable';
    $payable['target_amount'] = 250.00;

    expect($references->termsHash($implicitRedeemable))
        ->toBe($references->termsHash($explicitRedeemable))
        ->not->toBe($references->termsHash($payable));
});

it('persists and echoes an external reference through issuance and both lookup routes', function (): void {
    [$issuer] = bplsAuthenticatePartner(['pay-codes:issue', 'pay-codes:read']);
    $externalReference = 'BPLS.2026:0002';
    $result = bplsIssuanceResult($issuer, $externalReference, 'BPLS-0002');
    $action = Mockery::mock(GeneratePayCode::class);
    $action->shouldReceive('handle')->once()->with(Mockery::on(function (array $payload) use ($externalReference): bool {
        expect(data_get($payload, 'metadata.custom.external_reference'))->toBe($externalReference);

        return true;
    }))->andReturn($result);
    $this->app->instance(GeneratePayCode::class, $action);

    $this->postJson('/api/partner/v1/pay-codes', bplsIssuancePayload($externalReference), [
        'Idempotency-Key' => 'bpls-issue-0002',
    ])->assertCreated()
        ->assertJsonPath('data.external_reference', $externalReference)
        ->assertJsonPath('data.consumer_status', 'payable');

    $reference = PartnerApiPayCodeReference::query()->sole();
    expect($reference->external_reference)->toBe($externalReference)
        ->and($reference->voucher_id)->toBe($result->voucher_id)
        ->and($reference->terms_hash)->toHaveLength(64);

    $voucher = Voucher::query()->findOrFail($result->voucher_id);
    $lifecycle = Mockery::mock(VoucherLifecycleServiceContract::class);
    $lifecycle->shouldReceive('show')->twice()->andReturn(bplsLifecycleDetail($voucher));
    $this->app->instance(VoucherLifecycleServiceContract::class, $lifecycle);

    foreach ([
        '/api/partner/v1/pay-codes/BPLS-0002',
        '/api/partner/v1/pay-codes/by-reference/'.$externalReference,
    ] as $url) {
        $this->getJson($url)->assertOk()
            ->assertJsonPath('data.code', 'BPLS-0002')
            ->assertJsonPath('data.external_reference', $externalReference)
            ->assertJsonPath('data.consumer_status', 'payable');
    }
});

it('replays the original voucher for identical business terms under a new idempotency key', function (): void {
    [$issuer] = bplsAuthenticatePartner(['pay-codes:issue']);
    $externalReference = 'BPLS-2026-0003';
    $result = bplsIssuanceResult($issuer, $externalReference, 'BPLS-0003');
    $action = Mockery::mock(GeneratePayCode::class);
    $action->shouldReceive('handle')->once()->andReturn($result);
    $this->app->instance(GeneratePayCode::class, $action);
    $payload = bplsIssuancePayload($externalReference);

    $first = $this->postJson('/api/partner/v1/pay-codes', $payload, [
        'Idempotency-Key' => 'bpls-business-first',
    ])->assertCreated();
    $replay = $this->postJson('/api/partner/v1/pay-codes', $payload, [
        'Idempotency-Key' => 'bpls-business-replay',
    ])->assertOk()->assertJsonPath('meta.idempotency.replayed', true);

    expect($replay->json('data.voucher_id'))->toBe($first->json('data.voucher_id'))
        ->and($replay->json('data.code'))->toBe($first->json('data.code'))
        ->and(Voucher::query()->count())->toBe(1)
        ->and(PartnerApiPayCodeReference::query()->count())->toBe(1);
});

it('rejects changed business terms for an existing external reference', function (): void {
    [$issuer] = bplsAuthenticatePartner(['pay-codes:issue']);
    $externalReference = 'BPLS-2026-0004';
    $result = bplsIssuanceResult($issuer, $externalReference, 'BPLS-0004');
    $action = Mockery::mock(GeneratePayCode::class);
    $action->shouldReceive('handle')->once()->andReturn($result);
    $this->app->instance(GeneratePayCode::class, $action);

    $this->postJson('/api/partner/v1/pay-codes', bplsIssuancePayload($externalReference), [
        'Idempotency-Key' => 'bpls-conflict-first',
    ])->assertCreated();
    $this->postJson('/api/partner/v1/pay-codes', bplsIssuancePayload($externalReference, 101.00), [
        'Idempotency-Key' => 'bpls-conflict-second',
    ])->assertConflict()->assertJsonPath('code', 'EXTERNAL_REFERENCE_CONFLICT');

    expect(Voucher::query()->count())->toBe(1)
        ->and(PartnerApiPayCodeReference::query()->count())->toBe(1);
});

it('scopes identical external references independently to each Partner API client', function (): void {
    $externalReference = 'BPLS-2026-SHARED';
    [$firstIssuer] = bplsAuthenticatePartner(['pay-codes:issue'], 'First BPLS Client');
    $firstResult = bplsIssuanceResult($firstIssuer, $externalReference, 'BPLS-A');
    [$secondIssuer] = bplsAuthenticatePartner(['pay-codes:issue'], 'Second BPLS Client');
    $secondResult = bplsIssuanceResult($secondIssuer, $externalReference, 'BPLS-B');
    $action = Mockery::mock(GeneratePayCode::class);
    $action->shouldReceive('handle')->twice()->andReturn($firstResult, $secondResult);
    $this->app->instance(GeneratePayCode::class, $action);

    bplsAuthenticateExistingPartner($firstIssuer, ['pay-codes:issue']);
    $this->postJson('/api/partner/v1/pay-codes', bplsIssuancePayload($externalReference), [
        'Idempotency-Key' => 'bpls-shared-first',
    ])->assertCreated()->assertJsonPath('data.code', 'BPLS-A');

    bplsAuthenticateExistingPartner($secondIssuer, ['pay-codes:issue']);
    $this->postJson('/api/partner/v1/pay-codes', bplsIssuancePayload($externalReference), [
        'Idempotency-Key' => 'bpls-shared-second',
    ])->assertCreated()->assertJsonPath('data.code', 'BPLS-B');

    expect(PartnerApiPayCodeReference::query()->count())->toBe(2);
});

function bplsAuthenticateExistingPartner(User $issuer, array $scopes): void
{
    $credential = app(CreatePartnerApiClient::class)->handle(
        name: 'BPLS Existing Client '.str()->ulid(),
        issuer: $issuer,
        scopes: $scopes,
    );
    Passport::actingAsClient(Client::query()->findOrFail($credential->client_id), $scopes);
}

it('conceals an external reference from another client and returns 404 for an unknown reference', function (): void {
    [$owner, $credential] = bplsAuthenticatePartner(['pay-codes:read'], 'Reference Owner');
    $voucher = bplsIssuanceResult($owner, 'BPLS-PRIVATE', 'BPLS-PRIVATE')->voucher_id;
    $client = PartnerApiClient::query()->where('reference', $credential->reference)->sole();
    PartnerApiPayCodeReference::query()->create([
        'partner_api_client_id' => $client->getKey(),
        'external_reference' => 'BPLS-PRIVATE',
        'voucher_id' => $voucher,
        'terms_hash' => str_repeat('a', 64),
    ]);

    bplsAuthenticatePartner(['pay-codes:read'], 'Different Reference Client');

    $this->getJson('/api/partner/v1/pay-codes/by-reference/BPLS-PRIVATE')->assertNotFound();
    $this->getJson('/api/partner/v1/pay-codes/by-reference/BPLS-UNKNOWN')->assertNotFound();
});
