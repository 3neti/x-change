<?php

declare(strict_types=1);

use LBHurtado\XChange\Actions\PayCode\EstimatePayCodeCost;
use LBHurtado\XChange\Actions\PayCode\GeneratePayCode;
use LBHurtado\XChange\Data\Cockpit\CockpitOperatorIssuanceActivityItemData;
use LBHurtado\XChange\Data\DebitData;
use LBHurtado\XChange\Data\IssuerData;
use LBHurtado\XChange\Data\PayCode\GeneratePayCodeResultData;
use LBHurtado\XChange\Data\PayCodeLinksData;
use LBHurtado\XChange\Data\PricingEstimateData;
use LBHurtado\XChange\Services\BuildBalanceOverview;
use LBHurtado\XChange\Services\Cockpit\CockpitOperatorIssuanceActivityHandoffPipeline;

it('aligns operator safe quick generate response metadata with activity metadata', function () {
    $pipeline = new class extends CockpitOperatorIssuanceActivityHandoffPipeline
    {
        public ?CockpitOperatorIssuanceActivityItemData $activity = null;

        public function __construct() {}

        public function process(CockpitOperatorIssuanceActivityItemData $activity): void
        {
            $this->activity = $activity;
        }
    };

    app()->instance(CockpitOperatorIssuanceActivityHandoffPipeline::class, $pipeline);
    app()->instance(EstimatePayCodeCost::class, cockpitWave10ActivityPricingFake());
    app()->instance(BuildBalanceOverview::class, cockpitWave10ActivityBalanceFake());
    app()->instance(GeneratePayCode::class, cockpitWave10ActivityGeneratePayCodeFake());

    actingAsTestUser();

    $this->withHeader('Accept', 'application/json')
        ->post(route('x-change.cockpit.quick-generate.store'), cockpitWave10ActivityPayload())
        ->assertCreated()
        ->assertJsonPath('activity.schema', 'x-change.cockpit.operator-issuance-activity.v1')
        ->assertJsonPath('activity.status', 'recording-attempted-after-issuance')
        ->assertJsonPath('activity.source', 'cockpit.quick-generate')
        ->assertJsonPath('activity.presentation_only', true)
        ->assertJsonPath('activity.metadata_alignment', 'response-and-activity-share-operator-safe-runtime-facts')
        ->assertJsonMissingPath('activity.raw_payload')
        ->assertJsonMissingPath('activity.wallet');

    expect($pipeline->activity)->toBeInstanceOf(CockpitOperatorIssuanceActivityItemData::class)
        ->and($pipeline->activity?->metadata)->toMatchArray([
            'source' => 'x-change.cockpit',
            'presentation_only' => true,
            'recorder' => 'cockpit.operator-issuance-activity.v1',
            'draft_status' => 'compiled',
            'pricing_preflight_status' => 'estimated',
            'funding_preflight_status' => 'checked',
            'activity_schema' => 'x-change.cockpit.operator-issuance-activity.v1',
        ])
        ->and($pipeline->activity?->metadata)->not->toHaveKey('raw_payload')
        ->and($pipeline->activity?->metadata)->not->toHaveKey('wallet');
});

function cockpitWave10ActivityPricingFake(): EstimatePayCodeCost
{
    return new class extends EstimatePayCodeCost
    {
        public function __construct() {}

        public function handle(array $input): PricingEstimateData
        {
            return new PricingEstimateData(currency: 'PHP', base_fee: 1, total: 1);
        }
    };
}

function cockpitWave10ActivityBalanceFake(): BuildBalanceOverview
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

function cockpitWave10ActivityGeneratePayCodeFake(): GeneratePayCode
{
    return new class extends GeneratePayCode
    {
        public function __construct() {}

        public function handle(array $input): GeneratePayCodeResultData
        {
            return new GeneratePayCodeResultData(
                voucher_id: 12345,
                code: 'PC-WAVE-10G',
                amount: $input['cash']['amount'],
                currency: $input['cash']['currency'],
                issuer: new IssuerData(id: data_get($input, 'metadata.issuer_id')),
                cost: new PricingEstimateData(currency: 'PHP', total: 1),
                wallet: [],
                debit: new DebitData,
                links: new PayCodeLinksData(
                    redeem: 'https://example.test/r/PC-WAVE-10G',
                    redeem_path: '/r/PC-WAVE-10G',
                ),
            );
        }
    };
}

/**
 * @return array<string, mixed>
 */
function cockpitWave10ActivityPayload(): array
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
            'message' => 'Wave 10G activity alignment',
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
