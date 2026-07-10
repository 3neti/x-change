<?php

declare(strict_types=1);

use LBHurtado\XChange\Actions\PayCode\GeneratePayCode;
use LBHurtado\XChange\Contracts\CockpitOperatorIssuanceActivityRecorderContract;
use LBHurtado\XChange\Contracts\CockpitOperatorIssuanceActivityRepositoryContract;
use LBHurtado\XChange\Data\DebitData;
use LBHurtado\XChange\Data\IssuerData;
use LBHurtado\XChange\Data\PayCode\GeneratePayCodeResultData;
use LBHurtado\XChange\Data\PayCodeLinksData;
use LBHurtado\XChange\Data\PricingEstimateData;
use LBHurtado\XChange\Models\CockpitOperatorIssuanceActivity;
use LBHurtado\XChange\Services\Cockpit\DatabaseCockpitOperatorIssuanceActivityRecorder;
use LBHurtado\XChange\Services\Cockpit\DatabaseCockpitOperatorIssuanceActivityRepository;
use LBHurtado\XChange\Services\Cockpit\NullCockpitOperatorIssuanceActivityRecorder;
use LBHurtado\XChange\Services\Cockpit\NullCockpitOperatorIssuanceActivityRepository;

it('keeps durable operator issuance activity persistence disabled by default configuration', function () {
    expect(config('x-change.cockpit.operator_issuance_activity.repository'))->toBeNull()
        ->and(config('x-change.cockpit.operator_issuance_activity.recorder'))->toBeNull()
        ->and(app(CockpitOperatorIssuanceActivityRepositoryContract::class))
        ->toBeInstanceOf(NullCockpitOperatorIssuanceActivityRepository::class)
        ->and(app(CockpitOperatorIssuanceActivityRecorderContract::class))
        ->toBeInstanceOf(NullCockpitOperatorIssuanceActivityRecorder::class);
});

it('resolves durable operator issuance activity services when explicitly configured', function () {
    config()->set('x-change.cockpit.operator_issuance_activity.repository', DatabaseCockpitOperatorIssuanceActivityRepository::class);
    config()->set('x-change.cockpit.operator_issuance_activity.recorder', DatabaseCockpitOperatorIssuanceActivityRecorder::class);

    app()->forgetInstance(CockpitOperatorIssuanceActivityRepositoryContract::class);
    app()->forgetInstance(CockpitOperatorIssuanceActivityRecorderContract::class);

    expect(app(CockpitOperatorIssuanceActivityRepositoryContract::class))
        ->toBeInstanceOf(DatabaseCockpitOperatorIssuanceActivityRepository::class)
        ->and(app(CockpitOperatorIssuanceActivityRecorderContract::class))
        ->toBeInstanceOf(DatabaseCockpitOperatorIssuanceActivityRecorder::class);
});

it('persists quick generate activity only after durable activity services are explicitly configured', function () {
    app()->instance(GeneratePayCode::class, fakeCockpitRuntimeOptInGeneratePayCodeAction('PC-RUNTIME-OPT-IN'));

    actingAsTestUser();

    $this->withHeaders([
        'Accept' => 'application/json',
        'Idempotency-Key' => 'runtime-opt-in-default',
        'X-Correlation-ID' => 'corr-runtime-opt-in-default',
    ])
        ->post(route('x-change.cockpit.quick-generate.store'), cockpitRuntimeOptInPayload())
        ->assertCreated()
        ->assertJsonPath('result.code', 'PC-RUNTIME-OPT-IN');

    expect(CockpitOperatorIssuanceActivity::query()->count())->toBe(0);

    config()->set('x-change.cockpit.operator_issuance_activity.repository', DatabaseCockpitOperatorIssuanceActivityRepository::class);
    config()->set('x-change.cockpit.operator_issuance_activity.recorder', DatabaseCockpitOperatorIssuanceActivityRecorder::class);
    app()->forgetInstance(CockpitOperatorIssuanceActivityRepositoryContract::class);
    app()->forgetInstance(CockpitOperatorIssuanceActivityRecorderContract::class);
    app()->forgetInstance(GeneratePayCode::class);
    app()->instance(GeneratePayCode::class, fakeCockpitRuntimeOptInGeneratePayCodeAction('PC-RUNTIME-OPT-IN-2'));

    $this->withHeaders([
        'Accept' => 'application/json',
        'Idempotency-Key' => 'runtime-opt-in-enabled',
        'X-Correlation-ID' => 'corr-runtime-opt-in-enabled',
    ])
        ->post(route('x-change.cockpit.quick-generate.store'), cockpitRuntimeOptInPayload())
        ->assertCreated()
        ->assertJsonPath('result.code', 'PC-RUNTIME-OPT-IN-2');

    expect(CockpitOperatorIssuanceActivity::query()->count())->toBe(1)
        ->and(CockpitOperatorIssuanceActivity::query()->first()?->subject_reference)->toBe('PC-RUNTIME-OPT-IN-2');
});

function fakeCockpitRuntimeOptInGeneratePayCodeAction(string $code): GeneratePayCode
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
function cockpitRuntimeOptInPayload(): array
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
    ];
}
