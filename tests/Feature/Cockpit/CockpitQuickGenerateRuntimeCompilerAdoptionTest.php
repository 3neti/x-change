<?php

declare(strict_types=1);

use LBHurtado\XChange\Actions\PayCode\GeneratePayCode;
use LBHurtado\XChange\Data\DebitData;
use LBHurtado\XChange\Data\IssuerData;
use LBHurtado\XChange\Data\PayCode\GeneratePayCodeResultData;
use LBHurtado\XChange\Data\PayCodeLinksData;
use LBHurtado\XChange\Data\PricingEstimateData;
use LBHurtado\XChange\Services\Commercial\ProvisionCommercialBaselines;

beforeEach(function (): void {
    config()->set('x-change.commercial.legal_trace.legal_entity_reference', 'legal-entity:x-change:runtime-compiler-test');
    config()->set('x-change.commercial.legal_trace.profile_version', 'runtime-compiler-test-v1');

    app(ProvisionCommercialBaselines::class)
        ->provision('commissioning-manifest:runtime-compiler-test');
});

it('uses the quick generate draft factory validator and compiler before existing issuance handoff', function () {
    $fakeGeneratePayCode = new class extends GeneratePayCode
    {
        /**
         * @var array<int, array<string, mixed>>
         */
        public array $payloads = [];

        public function __construct() {}

        /**
         * @param  array<string, mixed>  $input
         */
        public function handle(array $input): GeneratePayCodeResultData
        {
            $this->payloads[] = $input;

            return new GeneratePayCodeResultData(
                voucher_id: 12345,
                code: 'PC-WAVE-10B',
                amount: $input['cash']['amount'],
                currency: $input['cash']['currency'],
                issuer: new IssuerData(id: data_get($input, 'metadata.issuer_id')),
                cost: new PricingEstimateData(currency: 'PHP', base_fee: 1.25, total: 1.25),
                wallet: ['balance_before' => 100000, 'balance_after' => 99975],
                debit: new DebitData(id: 987, amount: 25),
                links: new PayCodeLinksData(
                    redeem: 'https://example.test/r/PC-WAVE-10B',
                    redeem_path: '/r/PC-WAVE-10B',
                ),
            );
        }
    };

    app()->instance(GeneratePayCode::class, $fakeGeneratePayCode);

    actingAsTestUser();

    $this->withHeaders([
        'Accept' => 'application/json',
        'X-Correlation-ID' => 'corr-wave-10b',
        'Idempotency-Key' => 'idem-wave-10b',
    ])
        ->post(route('x-change.cockpit.quick-generate.store'), [
            'cash' => [
                'amount' => '25.00',
                'currency' => 'PHP',
            ],
            'inputs' => [
                'fields' => [],
            ],
            'feedback' => [
                'mobile' => '09173011987',
            ],
            'rider' => [
                'message' => 'Wave 10B compiler adoption',
            ],
            'voucher_type' => 'payable',
            'metadata' => [
                'collection_wallet_id' => 'platform-wallet-12',
                'custom' => [
                    'cockpit' => [
                        'template_key' => 'money-changer',
                        'source' => 'cockpit.quick-generate',
                        'payee' => [
                            'kind' => 'open',
                            'explicit_secret' => false,
                        ],
                    ],
                ],
            ],
        ])
        ->assertCreated()
        ->assertJsonPath('schema', 'x-change.cockpit.quick-generate-existing-issuance-handoff.v1')
        ->assertJsonPath('draft.status', 'compiled')
        ->assertJsonPath('draft.factory', 'CockpitQuickGenerateDraftFactoryContract')
        ->assertJsonPath('draft.compiler', 'CockpitIssuanceDraftCompilerContract')
        ->assertJsonPath('validation.draft_validator', 'CockpitIssuanceDraftValidatorContract')
        ->assertJsonPath('result.code', 'PC-WAVE-10B')
        ->assertJsonMissingPath('validated_payload')
        ->assertJsonMissingPath('raw_payload');

    expect($fakeGeneratePayCode->payloads)->toHaveCount(1)
        ->and(data_get($fakeGeneratePayCode->payloads[0], 'cash.amount'))->toBe('25.00')
        ->and(data_get($fakeGeneratePayCode->payloads[0], 'cash.validation'))->toBe([])
        ->and(data_get($fakeGeneratePayCode->payloads[0], 'inputs.fields'))->toBe([])
        ->and(data_get($fakeGeneratePayCode->payloads[0], 'count'))->toBe(1)
        ->and(data_get($fakeGeneratePayCode->payloads[0], 'feedback.mobile'))->toBe('09173011987')
        ->and(data_get($fakeGeneratePayCode->payloads[0], 'voucher_type'))->toBe('payable')
        ->and(data_get($fakeGeneratePayCode->payloads[0], 'metadata.collection_wallet_id'))->toBe('platform-wallet-12')
        ->and(data_get($fakeGeneratePayCode->payloads[0], 'metadata.custom.cockpit.template_key'))->toBe('money-changer')
        ->and(data_get($fakeGeneratePayCode->payloads[0], '_meta.source'))->toBe('cockpit.quick-generate')
        ->and(data_get($fakeGeneratePayCode->payloads[0], '_meta.idempotency_key'))->toBe('idem-wave-10b')
        ->and(data_get($fakeGeneratePayCode->payloads[0], '_meta.correlation_id'))->toBe('corr-wave-10b');
});

it('preserves settlement envelope advanced instruction fields through the existing issuance handoff', function () {
    $fakeGeneratePayCode = new class extends GeneratePayCode
    {
        /**
         * @var array<int, array<string, mixed>>
         */
        public array $payloads = [];

        public function __construct() {}

        /**
         * @param  array<string, mixed>  $input
         */
        public function handle(array $input): GeneratePayCodeResultData
        {
            $this->payloads[] = $input;

            return new GeneratePayCodeResultData(
                voucher_id: 12346,
                code: 'PC-SETTLEMENT-ENVELOPE',
                amount: $input['cash']['amount'],
                currency: $input['cash']['currency'],
                issuer: new IssuerData(id: data_get($input, 'metadata.issuer_id')),
                cost: new PricingEstimateData(currency: 'PHP', base_fee: 1.25, total: 1.25),
                wallet: ['balance_before' => 100000, 'balance_after' => 99000],
                debit: new DebitData(id: 988, amount: 1000),
                links: new PayCodeLinksData(
                    redeem: 'https://example.test/r/PC-SETTLEMENT-ENVELOPE',
                    redeem_path: '/r/PC-SETTLEMENT-ENVELOPE',
                ),
            );
        }
    };

    app()->instance(GeneratePayCode::class, $fakeGeneratePayCode);

    actingAsTestUser();

    $this->withHeaders([
        'Accept' => 'application/json',
        'X-Correlation-ID' => 'corr-settlement-envelope-preview',
        'Idempotency-Key' => 'idem-settlement-envelope-preview',
    ])
        ->post(route('x-change.cockpit.quick-generate.store'), settlementEnvelopeQuickGeneratePreviewPayload())
        ->assertCreated()
        ->assertJsonPath('result.code', 'PC-SETTLEMENT-ENVELOPE');

    expect($fakeGeneratePayCode->payloads)->toHaveCount(1)
        ->and(data_get($fakeGeneratePayCode->payloads[0], 'metadata.custom.cockpit.template_key'))->toBe('settlement-envelope')
        ->and(data_get($fakeGeneratePayCode->payloads[0], 'cash.amount'))->toBe(500)
        ->and(data_get($fakeGeneratePayCode->payloads[0], 'cash.type'))->toBe('settlement_cash')
        ->and(data_get($fakeGeneratePayCode->payloads[0], 'cash.mandates'))->toBe(['settlement-readiness', 'manual-review'])
        ->and(data_get($fakeGeneratePayCode->payloads[0], 'cash.slice_mode'))->toBe('open')
        ->and(data_get($fakeGeneratePayCode->payloads[0], 'cash.max_slices'))->toBe(1)
        ->and(data_get($fakeGeneratePayCode->payloads[0], 'cash.min_withdrawal'))->toBe(25)
        ->and(data_get($fakeGeneratePayCode->payloads[0], 'provider'))->toBe('manual')
        ->and(data_get($fakeGeneratePayCode->payloads[0], 'ttl'))->toBe('P7D')
        ->and(data_get($fakeGeneratePayCode->payloads[0], 'voucher_type'))->toBe('settlement')
        ->and(data_get($fakeGeneratePayCode->payloads[0], 'target_amount'))->toBe(100)
        ->and(data_get($fakeGeneratePayCode->payloads[0], 'rules.auto_close_on_full_payment'))->toBeTrue()
        ->and(data_get($fakeGeneratePayCode->payloads[0], 'validation.selfie.required'))->toBeTrue()
        ->and(data_get($fakeGeneratePayCode->payloads[0], 'execution.schema'))->toBe('voucher.execution.v1')
        ->and(data_get($fakeGeneratePayCode->payloads[0], 'execution.driver'))->toBe('settlement_envelope')
        ->and(data_get($fakeGeneratePayCode->payloads[0], 'execution.pipeline'))->toBe(['readiness', 'authorize', 'execute']);
});

it('issues a settlement envelope quick generate payload through the real issuance path', function () {
    actingAsTestUser();

    $this->withHeaders([
        'Accept' => 'application/json',
        'X-Correlation-ID' => 'corr-real-settlement-envelope-preview',
        'Idempotency-Key' => 'idem-real-settlement-envelope-preview',
    ])
        ->post(route('x-change.cockpit.quick-generate.store'), settlementEnvelopeQuickGeneratePreviewPayload())
        ->assertCreated()
        ->assertJsonPath('status', 'issued')
        ->assertJsonPath('result.currency', 'PHP')
        ->assertJsonPath('result.amount', 500)
        ->assertJsonPath('result.links.redeem_path', fn (string $path): bool => str_starts_with($path, '/x/claim/'))
        ->assertJsonPath('result.links.redeem_path', fn (string $path): bool => ! str_ends_with($path, '/experience'))
        ->assertJsonPath('result.links.redeem', fn (string $url): bool => str_contains($url, '/x/claim/'))
        ->assertJsonPath('result.links.redeem', fn (string $url): bool => ! str_ends_with($url, '/experience'));
});

it('fails closed before issuance when the quick generate draft template is unknown', function () {
    $fakeGeneratePayCode = new class extends GeneratePayCode
    {
        public bool $called = false;

        public function __construct() {}

        public function handle(array $input): GeneratePayCodeResultData
        {
            $this->called = true;

            return new GeneratePayCodeResultData(
                voucher_id: 1,
                code: 'PC-SHOULD-NOT-RUN',
                amount: 1,
                currency: 'PHP',
                issuer: new IssuerData(id: 1),
                cost: new PricingEstimateData,
                wallet: [],
                debit: new DebitData,
                links: new PayCodeLinksData(redeem: '#', redeem_path: '#'),
            );
        }
    };

    app()->instance(GeneratePayCode::class, $fakeGeneratePayCode);

    actingAsTestUser();

    $this->withHeader('Accept', 'application/json')
        ->post(route('x-change.cockpit.quick-generate.store'), [
            'cash' => [
                'amount' => 25,
                'currency' => 'PHP',
            ],
            'inputs' => [
                'fields' => [],
            ],
            'feedback' => [
                'mobile' => '09173011987',
            ],
            'rider' => [
                'message' => null,
            ],
            'metadata' => [
                'custom' => [
                    'cockpit' => [
                        'template_key' => 'imaginary-template',
                    ],
                ],
            ],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['draft']);

    expect($fakeGeneratePayCode->called)->toBeFalse();
});

/**
 * @return array<string, mixed>
 */
function settlementEnvelopeQuickGeneratePreviewPayload(): array
{
    return [
        'cash' => [
            'amount' => 500,
            'currency' => 'PHP',
            'validation' => [
                'country' => 'PH',
            ],
            'fee_strategy' => 'absorb',
            'type' => 'settlement_cash',
            'mandates' => [
                'settlement-readiness',
                'manual-review',
            ],
            'slice_mode' => 'open',
            'max_slices' => 1,
            'min_withdrawal' => 25,
        ],
        'provider' => 'manual',
        'inputs' => [
            'fields' => [
                'signature',
                'kyc',
                'name',
            ],
            'requirements' => [
                'kyc',
                'selfie',
            ],
        ],
        'count' => 1,
        'feedback' => [
            'mobile' => null,
            'email' => null,
            'webhook' => null,
        ],
        'rider' => [
            'message' => 'Settlement envelope readiness check',
            'url' => null,
            'redirect_timeout' => 0,
            'splash' => 'Settlement envelope requires readiness approval.',
            'splash_timeout' => 5,
            'splash_meta' => [
                'sanitized' => true,
            ],
            'og_source' => null,
        ],
        'validation' => [
            'selfie' => [
                'required' => true,
                'on_failure' => 'block',
            ],
        ],
        'metadata' => [
            'collection_wallet_id' => (string) auth()->user()?->wallet()->where('slug', 'platform')->sole()->getKey(),
            'slice_policy' => [
                'mode' => 'open',
                'selection' => 'operator',
                'enforced' => false,
            ],
            'custom' => [
                'cockpit' => [
                    'template_key' => 'settlement-envelope',
                    'source' => 'cockpit.quick-generate',
                    'builder' => 'guided-voucher-instruction-builder',
                    'payee' => [
                        'kind' => 'open',
                        'explicit_secret' => false,
                    ],
                ],
            ],
            'flow_type' => 'settlement-envelope',
        ],
        'ttl' => 'P7D',
        'voucher_type' => 'settlement',
        'target_amount' => 100,
        'rules' => [
            'auto_close_on_full_payment' => true,
        ],
        'execution' => [
            'schema' => 'voucher.execution.v1',
            'driver' => 'settlement_envelope',
            'pipeline' => [
                'readiness',
                'authorize',
                'execute',
            ],
        ],
    ];
}
