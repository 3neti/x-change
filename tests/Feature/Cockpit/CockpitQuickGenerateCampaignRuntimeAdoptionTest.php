<?php

declare(strict_types=1);

use LBHurtado\XChange\Actions\PayCode\EstimatePayCodeCost;
use LBHurtado\XChange\Actions\PayCode\GeneratePayCode;
use LBHurtado\XChange\Data\DebitData;
use LBHurtado\XChange\Data\IssuerData;
use LBHurtado\XChange\Data\PayCode\GeneratePayCodeResultData;
use LBHurtado\XChange\Data\PayCodeLinksData;
use LBHurtado\XChange\Data\PricingEstimateData;
use LBHurtado\XChange\Services\BuildBalanceOverview;

it('preserves campaign context and mobile validation through the quick generate runtime handoff', function (): void {
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
                voucher_id: 5252,
                code: 'PC-WAVE-52D',
                amount: $input['cash']['amount'],
                currency: $input['cash']['currency'],
                issuer: new IssuerData(id: data_get($input, 'metadata.issuer_id')),
                cost: new PricingEstimateData(currency: 'PHP', total: 0),
                wallet: [],
                debit: new DebitData,
                links: new PayCodeLinksData(
                    redeem: 'https://example.test/r/PC-WAVE-52D',
                    redeem_path: '/r/PC-WAVE-52D',
                ),
            );
        }
    };

    app()->instance(GeneratePayCode::class, $fakeGeneratePayCode);
    app()->instance(EstimatePayCodeCost::class, cockpitWave52dPricingFake());
    app()->instance(BuildBalanceOverview::class, cockpitWave52dBalanceFake());

    actingAsTestUser();

    $this
        ->withHeader('Accept', 'application/json')
        ->post(route('x-change.cockpit.quick-generate.store'), [
            'cash' => [
                'amount' => '500.00',
                'currency' => 'PHP',
                'validation' => [
                    'mobile' => '09173011987',
                ],
            ],
            'inputs' => [
                'fields' => ['mobile'],
            ],
            'feedback' => [
                'mobile' => '09173011987',
            ],
            'rider' => [
                'message' => 'Campaign runtime adoption',
            ],
            'metadata' => [
                'campaign' => [
                    'planning_key' => 'plan-wave-52d',
                    'execution_id' => 'exec-wave-52d',
                    'campaign_id' => 'campaign-wave-52d',
                    'audience_id' => 'audience-wave-52d',
                    'recipient_id' => 'recipient-wave-52d',
                    'source' => 'campaign_cockpit',
                ],
                'custom' => [
                    'cockpit' => [
                        'template_key' => 'ofw-remittance',
                        'campaign_context' => 'read-model-prefill',
                    ],
                ],
            ],
        ])
        ->assertCreated()
        ->assertJsonPath('result.code', 'PC-WAVE-52D')
        ->assertJsonPath('campaign_attribution.planning_key', 'plan-wave-52d')
        ->assertJsonPath('campaign_attribution.recipient_reference', '09173011987')
        ->assertJsonMissingPath('campaign_mutation')
        ->assertJsonMissingPath('campaign_payload')
        ->assertJsonMissingPath('provider_payload')
        ->assertJsonMissingPath('raw_payload');

    expect($fakeGeneratePayCode->payloads)->toHaveCount(1)
        ->and(data_get($fakeGeneratePayCode->payloads[0], 'metadata.campaign.planning_key'))->toBe('plan-wave-52d')
        ->and(data_get($fakeGeneratePayCode->payloads[0], 'metadata.campaign.execution_id'))->toBe('exec-wave-52d')
        ->and(data_get($fakeGeneratePayCode->payloads[0], 'metadata.campaign.recipient_id'))->toBe('recipient-wave-52d')
        ->and(data_get($fakeGeneratePayCode->payloads[0], 'cash.validation.mobile'))->toBe('09173011987')
        ->and(data_get($fakeGeneratePayCode->payloads[0], 'inputs.fields'))->toBe(['mobile'])
        ->and(data_get($fakeGeneratePayCode->payloads[0], 'feedback.mobile'))->toBe('09173011987');
});

function cockpitWave52dPricingFake(): EstimatePayCodeCost
{
    return new class extends EstimatePayCodeCost
    {
        public function __construct() {}

        public function handle(array $input): PricingEstimateData
        {
            return new PricingEstimateData(currency: 'PHP', total: 0);
        }
    };
}

function cockpitWave52dBalanceFake(): BuildBalanceOverview
{
    return new class extends BuildBalanceOverview
    {
        public function __construct() {}

        public function handle(mixed $owner, ?string $provider = null, bool $syncIfStale = true): array
        {
            return [
                'provider' => 'netbank',
                'topology' => 'ledger_pooled',
                'authority' => 'local_ledger',
                'sync_status' => 'not_required',
                'authoritative' => [
                    'key' => 'local_ledger',
                    'authority' => 'local_ledger',
                    'source' => 'bavix_wallet',
                    'balance' => 10000,
                    'currency' => 'PHP',
                    'is_stale' => false,
                ],
            ];
        }
    };
}
