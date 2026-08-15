<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Hash;
use Laravel\Passport\Client;
use Laravel\Passport\Passport;
use LBHurtado\XChange\Actions\PartnerApi\CreatePartnerApiClient;
use LBHurtado\XChange\Actions\PayCode\EstimatePayCodeCost;
use LBHurtado\XChange\Actions\PayCode\GeneratePayCode;
use LBHurtado\XChange\Data\DebitData;
use LBHurtado\XChange\Data\IssuerData;
use LBHurtado\XChange\Data\PayCode\GeneratePayCodeResultData;
use LBHurtado\XChange\Data\PayCodeLinksData;
use LBHurtado\XChange\Data\PricingEstimateData;
use LBHurtado\XChange\Models\PartnerApiClient;
use LBHurtado\XChange\Models\PartnerApiOperation;
use LBHurtado\XChange\Tests\Fakes\User;

function partnerApiIssuer(array $scopes, array $mandate = []): array
{
    $issuer = User::query()->create([
        'name' => 'Saras Issuer',
        'email' => 'saras-'.str()->uuid().'@example.test',
        'password' => Hash::make('password'),
    ]);
    fundTestUserWallet($issuer);

    $credential = app(CreatePartnerApiClient::class)->handle(
        name: 'Saras AI Sandbox',
        issuer: $issuer,
        scopes: $scopes,
        mandate: $mandate,
    );
    $client = Client::query()->findOrFail($credential->client_id);

    Passport::actingAsClient($client, $scopes);

    return [$issuer, $credential];
}

function partnerApiPayCodePayload(float $amount = 100.00): array
{
    return validVoucherInstructions($amount, 'INSTAPAY', [
        'cash' => [
            'validation' => [
                'mobile' => '09171234567',
            ],
        ],
        'metadata' => [],
    ])->toArray();
}

function partnerApiIssueResult(int|string $issuerId): GeneratePayCodeResultData
{
    return new GeneratePayCodeResultData(
        voucher_id: 99,
        code: 'SARS-1234',
        amount: 100.0,
        currency: 'PHP',
        issuer: new IssuerData(id: $issuerId),
        cost: new PricingEstimateData(
            currency: 'PHP',
            base_fee: 1.0,
            total: 1.0,
        ),
        wallet: ['balance_before' => 1000.0, 'balance_after' => 899.0],
        debit: new DebitData(id: 501, amount: 101.0),
        links: new PayCodeLinksData(
            redeem: 'https://example.test/x/claim/SARS-1234',
            redeem_path: '/x/claim/SARS-1234',
        ),
    );
}

it('estimates through the production pricing action with token-bound issuer identity', function () {
    [$issuer] = partnerApiIssuer(['pay-codes:estimate']);

    $estimate = new PricingEstimateData(
        currency: 'PHP',
        base_fee: 15.0,
        total: 15.5,
        pay_code_value: 100.0,
        account_debit: 115.5,
    );
    $action = Mockery::mock(EstimatePayCodeCost::class);
    $action->shouldReceive('handle')->once()->with(Mockery::on(function (array $payload) use ($issuer): bool {
        expect(data_get($payload, 'metadata.issuer_id'))->toBe((string) $issuer->getKey());

        return true;
    }))->andReturn($estimate);
    $this->app->instance(EstimatePayCodeCost::class, $action);

    $this->postJson('/api/partner/v1/pay-code-estimates', partnerApiPayCodePayload())
        ->assertSuccessful()
        ->assertJsonPath('data.currency', 'PHP')
        ->assertJsonPath('data.account_debit', 115.5);
});

it('issues idempotently and never accepts caller-controlled issuer identity', function () {
    [$issuer] = partnerApiIssuer(['pay-codes:issue']);
    $result = partnerApiIssueResult($issuer->getKey());
    $action = Mockery::mock(GeneratePayCode::class);
    $action->shouldReceive('handle')->once()->with(Mockery::on(function (array $payload) use ($issuer): bool {
        expect(data_get($payload, 'metadata.issuer_id'))->toBe((string) $issuer->getKey())
            ->and(data_get($payload, '_meta.idempotency_key'))->toBe('saras-issue-001')
            ->and(data_get($payload, '_meta.correlation_id'))->toBe('saras-run-001');

        return true;
    }))->andReturn($result);
    $this->app->instance(GeneratePayCode::class, $action);

    $payload = partnerApiPayCodePayload();
    $headers = ['Idempotency-Key' => 'saras-issue-001', 'X-Correlation-ID' => 'saras-run-001'];

    $this->postJson('/api/partner/v1/pay-codes', $payload, $headers)
        ->assertCreated()
        ->assertHeader('X-Correlation-ID', 'saras-run-001')
        ->assertJsonPath('data.code', 'SARS-1234')
        ->assertJsonPath('meta.correlation_id', 'saras-run-001')
        ->assertJsonPath('meta.idempotency.replayed', false);

    $this->postJson('/api/partner/v1/pay-codes', $payload, $headers)
        ->assertSuccessful()
        ->assertHeader('X-Correlation-ID', 'saras-run-001')
        ->assertJsonPath('data.code', 'SARS-1234')
        ->assertJsonPath('meta.idempotency.replayed', true);

    expect(PartnerApiOperation::query()->count())->toBe(1)
        ->and(PartnerApiOperation::query()->sole()->principal_minor)->toBe(10000);

    $impersonation = partnerApiPayCodePayload();
    data_set($impersonation, 'metadata.issuer_id', '999999');
    $this->postJson('/api/partner/v1/pay-codes', $impersonation, ['Idempotency-Key' => 'saras-issue-002'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('metadata.issuer_id');
});

it('enforces the append-only daily principal ceiling before issuance', function () {
    [$issuer, $credential] = partnerApiIssuer(['pay-codes:issue'], [
        'maximum_amount_minor' => 5000,
        'daily_principal_limit_minor' => 5000,
    ]);
    $client = PartnerApiClient::query()
        ->where('reference', $credential->reference)
        ->sole();
    PartnerApiOperation::query()->create([
        'partner_api_client_id' => $client->getKey(),
        'operation' => 'pay_code_issued',
        'idempotency_key' => 'earlier-issuance',
        'subject_reference' => 'EARLIER',
        'principal_minor' => 4000,
        'currency' => 'PHP',
        'occurred_at' => now(),
    ]);
    $action = Mockery::mock(GeneratePayCode::class);
    $action->shouldNotReceive('handle');
    $this->app->instance(GeneratePayCode::class, $action);

    $this->postJson('/api/partner/v1/pay-codes', partnerApiPayCodePayload(10.01), [
        'Idempotency-Key' => 'daily-limit-issuance',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('cash.amount');

    expect(PartnerApiOperation::query()->count())->toBe(1);
});

it('enforces mandate amount rail and recipient binding before invoking issuance', function () {
    partnerApiIssuer(['pay-codes:issue'], [
        'maximum_amount_minor' => 5000,
        'settlement_rails' => ['INSTAPAY'],
        'unbound_pay_codes' => false,
    ]);
    $action = Mockery::mock(GeneratePayCode::class);
    $action->shouldNotReceive('handle');
    $this->app->instance(GeneratePayCode::class, $action);

    $tooLarge = partnerApiPayCodePayload(50.01);
    $this->postJson('/api/partner/v1/pay-codes', $tooLarge, ['Idempotency-Key' => 'saras-limit-001'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('cash.amount');

    $wrongRail = partnerApiPayCodePayload(50.00);
    data_set($wrongRail, 'cash.settlement_rail', 'PESONET');
    $this->postJson('/api/partner/v1/pay-codes', $wrongRail, ['Idempotency-Key' => 'saras-limit-002'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('cash.settlement_rail');

    $unbound = partnerApiPayCodePayload(50.00);
    data_set($unbound, 'cash.validation.mobile', null);
    $this->postJson('/api/partner/v1/pay-codes', $unbound, ['Idempotency-Key' => 'saras-limit-003'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('cash.validation');

    $batch = partnerApiPayCodePayload(30.00);
    data_set($batch, 'count', 2);
    $this->postJson('/api/partner/v1/pay-codes', $batch, ['Idempotency-Key' => 'saras-limit-004'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('cash.amount');

    $onboarding = partnerApiPayCodePayload(10.00);
    data_set($onboarding, 'onboarding', true);
    $this->postJson('/api/partner/v1/pay-codes', $onboarding, ['Idempotency-Key' => 'saras-limit-005'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('onboarding');

    $longLived = partnerApiPayCodePayload(10.00);
    data_set($longLived, 'ttl', 'P8D');
    $this->postJson('/api/partner/v1/pay-codes', $longLived, ['Idempotency-Key' => 'saras-limit-006'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('ttl');
});

it('requires an idempotency key and the exact OAuth scope', function () {
    partnerApiIssuer(['pay-codes:estimate']);

    $this->postJson('/api/partner/v1/pay-codes', partnerApiPayCodePayload())
        ->assertForbidden();

    [$issuer] = partnerApiIssuer(['pay-codes:issue']);
    $this->postJson('/api/partner/v1/pay-codes', partnerApiPayCodePayload())
        ->assertUnprocessable()
        ->assertJsonValidationErrors('_partner.idempotency_key');
});
