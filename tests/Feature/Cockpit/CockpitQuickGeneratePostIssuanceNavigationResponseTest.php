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

it('hydrates quick generate post issuance navigation links without adding side effects', function () {
    app()->instance(EstimatePayCodeCost::class, cockpitWave34cPricingFake());
    app()->instance(BuildBalanceOverview::class, cockpitWave34cBalanceFake());
    app()->instance(GeneratePayCode::class, cockpitWave34cGeneratePayCodeFake('PC-WAVE-34C'));

    $operator = actingAsTestUser();

    $this->withHeader('Accept', 'application/json')
        ->post(route('x-change.cockpit.quick-generate.store'), cockpitWave34cPayload())
        ->assertCreated()
        ->assertJsonPath('result.code', 'PC-WAVE-34C')
        ->assertJsonPath(
            'result.links.share_card',
            route('x-change.claim.share-card', ['code' => 'PC-WAVE-34C']),
        )
        ->assertJsonPath('result.links.cockpit_detail', '/x/cockpit/pay-codes/PC-WAVE-34C')
        ->assertJsonPath('result.links.cockpit_distribution', '/x/cockpit/pay-codes/PC-WAVE-34C/distribution')
        ->assertJsonPath('post_issuance_navigation.schema', 'x-change.cockpit.quick-generate-post-issuance-navigation.v1')
        ->assertJsonPath('post_issuance_navigation.status', 'available')
        ->assertJsonPath('post_issuance_navigation.auto_redirect', false)
        ->assertJsonPath('post_issuance_navigation.items.0.key', 'detail')
        ->assertJsonPath('post_issuance_navigation.items.0.href', '/x/cockpit/pay-codes/PC-WAVE-34C')
        ->assertJsonPath('post_issuance_navigation.items.0.enabled', true)
        ->assertJsonPath('post_issuance_navigation.items.0.read_only', true)
        ->assertJsonPath('post_issuance_navigation.items.1.key', 'distribution')
        ->assertJsonPath('post_issuance_navigation.items.1.href', '/x/cockpit/pay-codes/PC-WAVE-34C/distribution')
        ->assertJsonPath('post_issuance_navigation.items.1.enabled', true)
        ->assertJsonPath('post_issuance_navigation.items.1.read_only', true)
        ->assertJsonPath('post_issuance_navigation.redactions.payloads', 'post-issuance-navigation-only')
        ->assertJsonMissingPath('post_issuance_navigation.request_payload')
        ->assertJsonMissingPath('post_issuance_navigation.provider_payload')
        ->assertJsonMissingPath('post_issuance_navigation.wallet')
        ->assertJsonMissingPath('post_issuance_navigation.idempotency_key');

    $this->withHeader('X-Inertia', 'true')
        ->get(route('x-change.cockpit.quick-generate'))
        ->assertOk()
        ->assertJsonPath(
            'props.last_instructions.schema',
            'x-change.cockpit.quick-generate-last-instructions.v1',
        )
        ->assertJsonPath('props.last_instructions.instructions.cash.amount', '25.00')
        ->assertJsonPath(
            'props.last_instructions.instructions.rider.message',
            'Wave 34C post issuance navigation',
        )
        ->assertJsonMissingPath('props.last_instructions.instructions.starts_at')
        ->assertJsonMissingPath('props.last_instructions.instructions.expires_at')
        ->assertJsonMissingPath('props.last_instructions.instructions.cash.validation.secret')
        ->assertJsonMissingPath('props.last_instructions.instructions.cash.validation.mobile')
        ->assertJsonMissingPath('props.last_instructions.instructions.feedback.mobile')
        ->assertJsonMissingPath('props.last_instructions.instructions.feedback.email')
        ->assertJsonMissingPath('props.last_instructions.instructions.metadata.custom.cockpit.recipient_reference')
        ->assertJsonMissingPath('props.last_instructions.instructions.metadata.issuer_id');

    $otherOperator = actingAsTestUser();

    expect($otherOperator->is($operator))->toBeFalse();

    $this->withHeader('X-Inertia', 'true')
        ->get(route('x-change.cockpit.quick-generate'))
        ->assertOk()
        ->assertJsonPath('props.last_instructions', null);
});

function cockpitWave34cPricingFake(): EstimatePayCodeCost
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

function cockpitWave34cBalanceFake(): BuildBalanceOverview
{
    return new class extends BuildBalanceOverview
    {
        public function __construct() {}

        public function handle(
            mixed $owner,
            ?string $provider = null,
            bool $syncIfStale = true,
            bool $forceSync = false,
        ): array {
            return [
                'authority' => 'local_ledger',
                'sync_status' => 'not_required',
                'authoritative' => [
                    'balance' => 10000,
                    'currency' => 'PHP',
                ],
            ];
        }
    };
}

function cockpitWave34cGeneratePayCodeFake(string $code): GeneratePayCode
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
                cost: new PricingEstimateData(currency: 'PHP', total: 0),
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
function cockpitWave34cPayload(): array
{
    return [
        'cash' => [
            'amount' => '25.00',
            'currency' => 'PHP',
            'validation' => [
                'secret' => 'do-not-restore-this-pin',
            ],
        ],
        'inputs' => [
            'fields' => [],
        ],
        'feedback' => [
            'mobile' => '09173011987',
        ],
        'rider' => [
            'message' => 'Wave 34C post issuance navigation',
        ],
        'starts_at' => '2026-07-28T09:00:00+08:00',
        'expires_at' => '2026-07-29T09:00:00+08:00',
        'metadata' => [
            'issuer_id' => 'should-be-server-owned',
            'custom' => [
                'cockpit' => [
                    'template_key' => 'money-changer',
                ],
            ],
        ],
    ];
}
