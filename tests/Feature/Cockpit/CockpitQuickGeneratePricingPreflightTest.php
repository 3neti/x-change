<?php

declare(strict_types=1);

use LBHurtado\XChange\Actions\PayCode\EstimatePayCodeCost;
use LBHurtado\XChange\Actions\PayCode\GeneratePayCode;
use LBHurtado\XChange\Data\DebitData;
use LBHurtado\XChange\Data\IssuerData;
use LBHurtado\XChange\Data\PayCode\GeneratePayCodeResultData;
use LBHurtado\XChange\Data\PayCodeLinksData;
use LBHurtado\XChange\Data\PricingEstimateData;

it('adds operator safe pricing preflight metadata before quick generate issuance', function () {
    app()->instance(EstimatePayCodeCost::class, new class extends EstimatePayCodeCost
    {
        public function __construct() {}

        public function handle(array $input): PricingEstimateData
        {
            return new PricingEstimateData(
                currency: 'PHP',
                base_fee: 1.25,
                components: ['cash' => 0.50],
                total: 1.75,
            );
        }
    });

    app()->instance(GeneratePayCode::class, cockpitWave10PricingGeneratePayCodeFake('PC-WAVE-10D'));

    actingAsTestUser();

    $this->withHeader('Accept', 'application/json')
        ->post(route('x-change.cockpit.quick-generate.store'), cockpitWave10PricingPayload())
        ->assertCreated()
        ->assertJsonPath('preflight.pricing.status', 'estimated')
        ->assertJsonPath('preflight.pricing.currency', 'PHP')
        ->assertJsonPath('preflight.pricing.base_fee', 1.25)
        ->assertJsonPath('preflight.pricing.total', 1.75)
        ->assertJsonPath('preflight.pricing.components.cash', 0.50)
        ->assertJsonPath('preflight.pricing.blocking', false)
        ->assertJsonPath('preflight.pricing.source', 'EstimatePayCodeCost')
        ->assertJsonPath('result.code', 'PC-WAVE-10D')
        ->assertJsonPath('result.issue_cost.currency', 'PHP')
        ->assertJsonPath('result.issue_cost.total', 1.75)
        ->assertJsonPath('result.issue_cost.charges.0.label', 'Pay Code Generation')
        ->assertJsonPath('result.issue_cost.charges.0.price', 1.25)
        ->assertJsonPath('redactions.cost', 'sanitized-issue-cost-only')
        ->assertJsonMissingPath('preflight.pricing.charges')
        ->assertJsonMissingPath('result.issue_cost.charges.0.commercial_quote_reference')
        ->assertJsonMissingPath('result.issue_cost.charges.0.catalog_item_reference')
        ->assertJsonMissingPath('preflight.pricing.raw_payload');
});

it('keeps quick generate issuance non blocking when pricing preflight is unavailable', function () {
    app()->instance(EstimatePayCodeCost::class, new class extends EstimatePayCodeCost
    {
        public function __construct() {}

        public function handle(array $input): PricingEstimateData
        {
            throw new RuntimeException('pricing unavailable');
        }
    });

    app()->instance(GeneratePayCode::class, cockpitWave10PricingGeneratePayCodeFake('PC-WAVE-10D-FALLBACK'));

    actingAsTestUser();

    $this->withHeader('Accept', 'application/json')
        ->post(route('x-change.cockpit.quick-generate.store'), cockpitWave10PricingPayload())
        ->assertCreated()
        ->assertJsonPath('preflight.pricing.status', 'unavailable')
        ->assertJsonPath('preflight.pricing.blocking', false)
        ->assertJsonPath('preflight.pricing.source', 'EstimatePayCodeCost')
        ->assertJsonPath('preflight.pricing.reason', RuntimeException::class)
        ->assertJsonPath('result.code', 'PC-WAVE-10D-FALLBACK');
});

function cockpitWave10PricingGeneratePayCodeFake(string $code): GeneratePayCode
{
    return new class($code) extends GeneratePayCode
    {
        public function __construct(private readonly string $code) {}

        public function handle(array $input): GeneratePayCodeResultData
        {
            return new GeneratePayCodeResultData(
                voucher_id: 12345,
                code: $this->code,
                amount: $input['cash']['amount'],
                currency: $input['cash']['currency'],
                issuer: new IssuerData(id: data_get($input, 'metadata.issuer_id')),
                cost: new PricingEstimateData(
                    currency: 'PHP',
                    base_fee: 1.25,
                    components: ['cash' => 0.50],
                    total: 1.75,
                    charges: [
                        [
                            'label' => 'Pay Code Generation',
                            'type' => 'generation',
                            'quantity' => 1,
                            'price' => 1.25,
                            'currency' => 'PHP',
                            'catalog_item_reference' => 'cash.amount',
                            'commercial_quote_reference' => 'quote-secret',
                        ],
                    ],
                ),
                wallet: ['balance_before' => 100000, 'balance_after' => 99975],
                debit: new DebitData(id: 987, amount: 25),
                links: new PayCodeLinksData(
                    redeem: 'https://example.test/r/'.$this->code,
                    redeem_path: '/r/'.$this->code,
                ),
            );
        }
    };
}

/**
 * @return array<string, mixed>
 */
function cockpitWave10PricingPayload(): array
{
    return [
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
            'message' => 'Wave 10D pricing preflight',
        ],
        'metadata' => [
            'custom' => [
                'cockpit' => [
                    'template_key' => 'money-changer',
                ],
            ],
        ],
    ];
}
