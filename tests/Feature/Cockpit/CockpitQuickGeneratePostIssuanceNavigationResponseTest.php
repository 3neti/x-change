<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Storage;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Actions\PayCode\EstimatePayCodeCost;
use LBHurtado\XChange\Actions\PayCode\GeneratePayCode;
use LBHurtado\XChange\Contracts\RiderStampArtifactStoreContract;
use LBHurtado\XChange\Data\DebitData;
use LBHurtado\XChange\Data\IssuerData;
use LBHurtado\XChange\Data\PayCode\GeneratePayCodeResultData;
use LBHurtado\XChange\Data\PayCodeLinksData;
use LBHurtado\XChange\Data\PricingEstimateData;
use LBHurtado\XChange\Models\RiderLibraryEntry;
use LBHurtado\XChange\Services\BuildBalanceOverview;

it('hydrates quick generate post issuance navigation links without adding side effects', function () {
    Storage::fake('local');
    $voucher = issueVoucher(validVoucherInstructions());
    $voucher->forceFill(['code' => 'PC-WAVE-34C'])->save();
    $artifact = app(RiderStampArtifactStoreContract::class)->materialize(
        $voucher,
        route('x-change.claim.show', ['code' => $voucher->code]),
    );

    app()->instance(EstimatePayCodeCost::class, cockpitWave34cPricingFake());
    app()->instance(BuildBalanceOverview::class, cockpitWave34cBalanceFake());
    app()->instance(
        GeneratePayCode::class,
        cockpitWave34cGeneratePayCodeFake($voucher),
    );

    $operator = actingAsTestUser();

    $this->withHeaders([
        'Accept' => 'application/json',
        'Idempotency-Key' => 'wave-34c-rider-library',
    ])
        ->post(route('x-change.cockpit.quick-generate.store'), cockpitWave34cPayload())
        ->assertCreated()
        ->assertJsonPath('result.code', 'PC-WAVE-34C')
        ->assertJsonPath(
            'result.links.share_card',
            route('x-change.claim.share-card.artifact', [
                'code' => 'PC-WAVE-34C',
                'sha256' => $artifact->sha256,
            ]),
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
        ->assertJsonMissingPath('props.last_instructions.instructions.metadata.issuer_id')
        ->assertJsonCount(2, 'props.rider_library')
        ->assertJsonPath('props.rider_library.0.saved', false);

    expect(RiderLibraryEntry::query()->sum('use_count'))->toBe(2);

    $this->withHeaders([
        'Accept' => 'application/json',
        'Idempotency-Key' => 'wave-34c-rider-library',
    ])
        ->post(route('x-change.cockpit.quick-generate.store'), cockpitWave34cPayload())
        ->assertOk()
        ->assertJsonPath('status', 'replayed');

    expect(RiderLibraryEntry::query()->sum('use_count'))->toBe(2);

    $otherOperator = actingAsTestUser();

    expect($otherOperator->is($operator))->toBeFalse();

    $this->withHeader('X-Inertia', 'true')
        ->get(route('x-change.cockpit.quick-generate'))
        ->assertOk()
        ->assertJsonPath('props.last_instructions', null)
        ->assertJsonCount(0, 'props.rider_library');
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

function cockpitWave34cGeneratePayCodeFake(Voucher $voucher): GeneratePayCode
{
    return new class($voucher) extends GeneratePayCode
    {
        public function __construct(private readonly Voucher $voucher) {}

        public function handle(array $input): GeneratePayCodeResultData
        {
            return new GeneratePayCodeResultData(
                voucher_id: $this->voucher->getKey(),
                code: $this->voucher->code,
                amount: $input['cash']['amount'],
                currency: $input['cash']['currency'],
                issuer: new IssuerData(id: data_get($input, 'metadata.issuer_id')),
                cost: new PricingEstimateData(currency: 'PHP', total: 0),
                wallet: ['balance_before' => 100000, 'balance_after' => 99975],
                debit: new DebitData(id: 987, amount: 25),
                links: new PayCodeLinksData(
                    redeem: 'https://example.test/r/'.$this->voucher->code,
                    redeem_path: '/r/'.$this->voucher->code,
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
            'url' => 'https://example.test/after-claim',
            'splash' => 'Welcome to the claim.',
            'splash_format' => 'plain',
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
