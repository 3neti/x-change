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

it('returns campaign attribution and campaign aware post issuance links after campaign sourced quick generate', function (): void {
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
                code: 'PC-WAVE-36B',
                amount: $input['cash']['amount'],
                currency: $input['cash']['currency'],
                issuer: new IssuerData(id: data_get($input, 'metadata.issuer_id')),
                cost: new PricingEstimateData(currency: 'PHP', total: 0),
                wallet: [],
                debit: new DebitData,
                links: new PayCodeLinksData(
                    redeem: 'https://example.test/r/PC-WAVE-36B',
                    redeem_path: '/r/PC-WAVE-36B',
                ),
            );
        }
    };

    app()->instance(GeneratePayCode::class, $fakeGeneratePayCode);
    app()->instance(EstimatePayCodeCost::class, cockpitWave36CampaignPricingFake());
    app()->instance(BuildBalanceOverview::class, cockpitWave36CampaignBalanceFake());

    actingAsTestUser();

    $response = $this
        ->withHeaders([
            'Accept' => 'application/json',
            'X-Correlation-ID' => 'corr-wave-36b',
        ])
        ->post(route('x-change.cockpit.quick-generate.store'), [
            'cash' => [
                'amount' => '500.00',
                'currency' => 'PHP',
            ],
            'inputs' => [
                'fields' => [],
            ],
            'feedback' => [
                'mobile' => '09173011987',
            ],
            'rider' => [
                'message' => 'Campaign payout',
            ],
            'metadata' => [
                'campaign' => [
                    'planning_key' => 'plan-wave-36b',
                    'execution_id' => 'exec-wave-36b',
                    'campaign_id' => 'campaign-wave-36b',
                    'audience_id' => 'audience-wave-36b',
                    'recipient_id' => 'recipient-wave-36b',
                    'source' => 'campaign_cockpit',
                    'campaign_payload' => 'must-not-leak',
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
        ->assertJsonPath('campaign_attribution.schema', 'x-change.cockpit.quick-generate-campaign-attribution.v1')
        ->assertJsonPath('campaign_attribution.status', 'available')
        ->assertJsonPath('campaign_attribution.available', true)
        ->assertJsonPath('campaign_attribution.read_only', true)
        ->assertJsonPath('campaign_attribution.mutates_campaign', false)
        ->assertJsonPath('campaign_attribution.planning_key', 'plan-wave-36b')
        ->assertJsonPath('campaign_attribution.execution_id', 'exec-wave-36b')
        ->assertJsonPath('campaign_attribution.campaign_id', 'campaign-wave-36b')
        ->assertJsonPath('campaign_attribution.audience_id', 'audience-wave-36b')
        ->assertJsonPath('campaign_attribution.recipient_id', 'recipient-wave-36b')
        ->assertJsonPath('campaign_attribution.source', 'campaign_cockpit')
        ->assertJsonPath('campaign_attribution.generated_code', 'PC-WAVE-36B')
        ->assertJsonPath('campaign_attribution.template_key', 'ofw-remittance')
        ->assertJsonPath('campaign_attribution.amount', '500.00')
        ->assertJsonPath('campaign_attribution.currency', 'PHP')
        ->assertJsonPath('campaign_attribution.recipient_reference', '09173011987')
        ->assertJsonPath('campaign_attribution.purpose', 'Campaign payout')
        ->assertJsonPath('campaign_attribution.redactions.payloads', 'campaign-attribution-only')
        ->assertJsonPath('post_issuance_navigation.items.2.key', 'campaign_explorer')
        ->assertJsonPath('post_issuance_navigation.items.2.label', 'Return to Campaign Explorer')
        ->assertJsonPath('post_issuance_navigation.items.2.status', 'available')
        ->assertJsonPath('post_issuance_navigation.items.2.enabled', true)
        ->assertJsonPath('post_issuance_navigation.items.2.read_only', true)
        ->assertJsonPath('post_issuance_navigation.items.3.key', 'campaign_dashboard')
        ->assertJsonPath('post_issuance_navigation.items.3.label', 'Return to Campaign Dashboard')
        ->assertJsonPath('post_issuance_navigation.items.3.status', 'available')
        ->assertJsonPath('post_issuance_navigation.items.3.enabled', true)
        ->assertJsonPath('post_issuance_navigation.items.3.read_only', true)
        ->assertJsonPath('post_issuance_navigation.items.3.metadata.recipient_id', 'recipient-wave-36b')
        ->assertJsonMissingPath('campaign_payload')
        ->assertJsonMissingPath('recipient_payload')
        ->assertJsonMissingPath('provider_payload')
        ->assertJsonMissingPath('wallet')
        ->assertJsonMissingPath('raw_payload')
        ->assertJsonMissing(['must-not-leak']);

    $campaignExplorerHref = $response->json('post_issuance_navigation.items.2.href');
    $campaignDashboardHref = $response->json('post_issuance_navigation.items.3.href');

    expect($campaignExplorerHref)
        ->toBeString()
        ->toContain('campaign_id=campaign-wave-36b')
        ->toContain('campaign_audience_id=audience-wave-36b')
        ->toContain('campaign_recipient_id=recipient-wave-36b')
        ->toContain('activity_code=PC-WAVE-36B')
        ->and($campaignDashboardHref)
        ->toBeString()
        ->toContain('campaign_id=campaign-wave-36b')
        ->toContain('campaign_audience_id=audience-wave-36b')
        ->toContain('campaign_recipient_id=recipient-wave-36b')
        ->and($fakeGeneratePayCode->payloads)->toHaveCount(1)
        ->and(data_get($fakeGeneratePayCode->payloads[0], 'metadata.campaign.planning_key'))->toBe('plan-wave-36b')
        ->and(data_get($fakeGeneratePayCode->payloads[0], 'metadata.campaign.source'))->toBe('campaign_cockpit');
});

function cockpitWave36CampaignPricingFake(): EstimatePayCodeCost
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

function cockpitWave36CampaignBalanceFake(): BuildBalanceOverview
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
                    'key' => 'internal',
                    'authority' => 'local_ledger',
                    'source' => 'wallet',
                    'balance' => 10000,
                    'currency' => 'PHP',
                    'is_stale' => false,
                ],
            ];
        }
    };
}
