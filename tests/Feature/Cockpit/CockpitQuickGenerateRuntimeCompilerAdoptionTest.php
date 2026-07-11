<?php

declare(strict_types=1);

use LBHurtado\XChange\Actions\PayCode\GeneratePayCode;
use LBHurtado\XChange\Data\DebitData;
use LBHurtado\XChange\Data\IssuerData;
use LBHurtado\XChange\Data\PayCode\GeneratePayCodeResultData;
use LBHurtado\XChange\Data\PayCodeLinksData;
use LBHurtado\XChange\Data\PricingEstimateData;

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
            'metadata' => [
                'custom' => [
                    'cockpit' => [
                        'template_key' => 'money-changer',
                        'source' => 'cockpit.quick-generate',
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
        ->and(data_get($fakeGeneratePayCode->payloads[0], 'metadata.custom.cockpit.template_key'))->toBe('money-changer')
        ->and(data_get($fakeGeneratePayCode->payloads[0], '_meta.source'))->toBe('cockpit.quick-generate')
        ->and(data_get($fakeGeneratePayCode->payloads[0], '_meta.idempotency_key'))->toBe('idem-wave-10b')
        ->and(data_get($fakeGeneratePayCode->payloads[0], '_meta.correlation_id'))->toBe('corr-wave-10b');
});

it('fails closed before issuance when the quick generate draft template is not enabled', function () {
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
                        'template_key' => 'settlement-envelope',
                    ],
                ],
            ],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['draft']);

    expect($fakeGeneratePayCode->called)->toBeFalse();
});
