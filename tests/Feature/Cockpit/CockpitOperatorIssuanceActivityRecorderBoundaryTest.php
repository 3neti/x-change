<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use LBHurtado\XChange\Actions\PayCode\GeneratePayCode;
use LBHurtado\XChange\Contracts\CockpitOperatorIssuanceActivityRecorderContract;
use LBHurtado\XChange\Data\Cockpit\CockpitOperatorIssuanceActivityItemData;
use LBHurtado\XChange\Data\DebitData;
use LBHurtado\XChange\Data\IssuerData;
use LBHurtado\XChange\Data\PayCode\GeneratePayCodeResultData;
use LBHurtado\XChange\Data\PayCodeLinksData;
use LBHurtado\XChange\Data\PricingEstimateData;
use LBHurtado\XChange\Services\Cockpit\NullCockpitOperatorIssuanceActivityRecorder;

it('binds a null operator issuance activity recorder by default', function () {
    expect(app(CockpitOperatorIssuanceActivityRecorderContract::class))
        ->toBeInstanceOf(NullCockpitOperatorIssuanceActivityRecorder::class);
});

it('records only operator-safe issuance activity facts after a fresh quick generate handoff', function () {
    Carbon::setTestNow('2026-07-10 09:00:00');

    $recorder = new class implements CockpitOperatorIssuanceActivityRecorderContract
    {
        /**
         * @var array<int, array<string, mixed>>
         */
        public array $records = [];

        public function record(CockpitOperatorIssuanceActivityItemData $activity): void
        {
            $this->records[] = $activity->toArray();
        }
    };

    app()->instance(CockpitOperatorIssuanceActivityRecorderContract::class, $recorder);
    app()->instance(GeneratePayCode::class, fakeCockpitGeneratePayCodeAction('PC-ACTIVITY-001'));

    $user = actingAsTestUser();

    $this->withHeaders([
        'Accept' => 'application/json',
        'Idempotency-Key' => 'idem-activity-1',
        'X-Correlation-ID' => 'corr-activity-1',
    ])
        ->post(route('x-change.cockpit.quick-generate.store'), cockpitQuickGeneratePayload())
        ->assertCreated()
        ->assertJsonPath('result.code', 'PC-ACTIVITY-001');

    expect($recorder->records)->toHaveCount(1)
        ->and($recorder->records[0])->toMatchArray([
            'code' => 'PC-ACTIVITY-001',
            'amount' => '25',
            'currency' => 'PHP',
            'status' => 'issued',
            'issued_at' => '2026-07-10T09:00:00+00:00',
            'route' => 'x-change.cockpit.quick-generate.store',
            'correlation_id' => 'corr-activity-1',
            'idempotency_key' => 'idem-activity-1',
            'operator_id' => (string) $user->getAuthIdentifier(),
            'detail_href' => '/x/cockpit/pay-codes/PC-ACTIVITY-001',
            'metadata' => [
                'source' => 'x-change.cockpit',
                'presentation_only' => true,
                'recorder' => 'cockpit.operator-issuance-activity.v1',
            ],
        ])
        ->and($recorder->records[0]['id'])->toBeString()->not->toBeEmpty()
        ->and($recorder->records[0])->not->toHaveKeys([
            'request_payload',
            'validated_payload',
            'raw_payload',
            'provider_payload',
            'wallet',
            'debit',
            'allocations',
            'cost',
            'issuer',
            'secret',
            'recipient_secret',
            'otp',
            'funding_source',
        ]);
});

it('does not record duplicate operator issuance activity when idempotency replays a response', function () {
    $recorder = new class implements CockpitOperatorIssuanceActivityRecorderContract
    {
        public int $records = 0;

        public function record(CockpitOperatorIssuanceActivityItemData $activity): void
        {
            $this->records++;
        }
    };

    app()->instance(CockpitOperatorIssuanceActivityRecorderContract::class, $recorder);
    app()->instance(GeneratePayCode::class, fakeCockpitGeneratePayCodeAction('PC-ACTIVITY-REPLAY'));

    actingAsTestUser();

    $headers = [
        'Accept' => 'application/json',
        'Idempotency-Key' => 'idem-activity-replay',
        'X-Correlation-ID' => 'corr-activity-replay',
    ];

    $this->withHeaders($headers)
        ->post(route('x-change.cockpit.quick-generate.store'), cockpitQuickGeneratePayload())
        ->assertCreated();

    $this->withHeaders($headers)
        ->post(route('x-change.cockpit.quick-generate.store'), cockpitQuickGeneratePayload())
        ->assertOk()
        ->assertJsonPath('status', 'replayed');

    expect($recorder->records)->toBe(1);
});

function fakeCockpitGeneratePayCodeAction(string $code): GeneratePayCode
{
    return new class($code) extends GeneratePayCode
    {
        public function __construct(private readonly string $code) {}

        /**
         * @param  array<string, mixed>  $input
         */
        public function handle(array $input): GeneratePayCodeResultData
        {
            return new GeneratePayCodeResultData(
                voucher_id: 12345,
                code: $this->code,
                amount: $input['cash']['amount'],
                currency: $input['cash']['currency'],
                issuer: new IssuerData(id: data_get($input, 'metadata.issuer_id')),
                cost: new PricingEstimateData(currency: 'PHP', base_fee: 1.25, total: 1.25),
                wallet: [
                    'balance_before' => 100000,
                    'balance_after' => 99975,
                ],
                debit: new DebitData(id: 987, amount: 25),
                links: new PayCodeLinksData(
                    redeem: 'https://example.test/r/'.$this->code,
                    redeem_path: '/r/'.$this->code,
                ),
                allocations: [
                    ['internal' => 'must-not-leak'],
                ],
            );
        }
    };
}

/**
 * @return array<string, mixed>
 */
function cockpitQuickGeneratePayload(): array
{
    return [
        'cash' => [
            'amount' => 25,
            'currency' => 'PHP',
        ],
        'inputs' => [
            'fields' => [],
        ],
        'feedback' => [
            'mobile' => null,
        ],
        'rider' => [
            'message' => null,
        ],
        'secret' => 'must-not-leak',
    ];
}
