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

it('passes campaign context through quick generate draft compilation without campaign mutation', function () {
    $fakeGeneratePayCode = new class extends GeneratePayCode
    {
        public array $payloads = [];

        public function __construct() {}

        public function handle(array $input): GeneratePayCodeResultData
        {
            $this->payloads[] = $input;

            return new GeneratePayCodeResultData(
                voucher_id: 12345,
                code: 'PC-WAVE-10F',
                amount: $input['cash']['amount'],
                currency: $input['cash']['currency'],
                issuer: new IssuerData(id: data_get($input, 'metadata.issuer_id')),
                cost: new PricingEstimateData(currency: 'PHP', total: 0),
                wallet: [],
                debit: new DebitData,
                links: new PayCodeLinksData(
                    redeem: 'https://example.test/r/PC-WAVE-10F',
                    redeem_path: '/r/PC-WAVE-10F',
                ),
            );
        }
    };

    app()->instance(GeneratePayCode::class, $fakeGeneratePayCode);
    app()->instance(EstimatePayCodeCost::class, cockpitWave10CampaignPricingFake());
    app()->instance(BuildBalanceOverview::class, cockpitWave10CampaignBalanceFake());

    actingAsTestUser();

    $this->withHeader('Accept', 'application/json')
        ->post(route('x-change.cockpit.quick-generate.store'), [
            'cash' => [
                'amount' => '250.00',
                'currency' => 'PHP',
            ],
            'inputs' => [
                'fields' => [],
            ],
            'feedback' => [
                'mobile' => '09173011987',
            ],
            'rider' => [
                'message' => 'Campaign runtime intake',
            ],
            'metadata' => [
                'campaign' => [
                    'planning_key' => 'plan-wave-10f',
                    'execution_id' => 'exec-wave-10f',
                    'campaign_id' => 'campaign-wave-10f',
                    'audience_id' => 'audience-wave-10f',
                    'recipient_id' => 'recipient-wave-10f',
                    'source' => 'x-campaign',
                ],
                'custom' => [
                    'cockpit' => [
                        'template_key' => 'ofw-remittance',
                    ],
                ],
            ],
        ])
        ->assertCreated()
        ->assertJsonPath('draft.status', 'compiled')
        ->assertJsonPath('result.code', 'PC-WAVE-10F')
        ->assertJsonMissingPath('campaign_mutation')
        ->assertJsonMissingPath('campaign_payload');

    expect($fakeGeneratePayCode->payloads)->toHaveCount(1)
        ->and(data_get($fakeGeneratePayCode->payloads[0], 'metadata.campaign.planning_key'))->toBe('plan-wave-10f')
        ->and(data_get($fakeGeneratePayCode->payloads[0], 'metadata.campaign.execution_id'))->toBe('exec-wave-10f')
        ->and(data_get($fakeGeneratePayCode->payloads[0], 'metadata.campaign.campaign_id'))->toBe('campaign-wave-10f')
        ->and(data_get($fakeGeneratePayCode->payloads[0], 'metadata.campaign.audience_id'))->toBe('audience-wave-10f')
        ->and(data_get($fakeGeneratePayCode->payloads[0], 'metadata.campaign.recipient_id'))->toBe('recipient-wave-10f')
        ->and(data_get($fakeGeneratePayCode->payloads[0], 'metadata.campaign.source'))->toBe('x-campaign');
});

function cockpitWave10CampaignPricingFake(): EstimatePayCodeCost
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

function cockpitWave10CampaignBalanceFake(): BuildBalanceOverview
{
    return new class extends BuildBalanceOverview
    {
        public function __construct() {}

        public function handle(mixed $owner, ?string $provider = null, bool $syncIfStale = true, bool $forceSync = false): array
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
