<?php

declare(strict_types=1);

use LBHurtado\XChange\Actions\PayCode\GeneratePayCode;
use LBHurtado\XChange\Contracts\CockpitOperatorIssuanceActivityJournalHandoffContract;
use LBHurtado\XChange\Contracts\CockpitOperatorIssuanceActivityJournalHandoffStatusProjectorContract;
use LBHurtado\XChange\Contracts\CockpitOperatorIssuanceActivityRecorderContract;
use LBHurtado\XChange\Contracts\CockpitOperatorIssuanceActivityRepositoryContract;
use LBHurtado\XChange\Data\Cockpit\CockpitOperatorIssuanceActivityItemData;
use LBHurtado\XChange\Data\Cockpit\CockpitOperatorIssuanceActivityJournalHandoffResultData;
use LBHurtado\XChange\Data\DebitData;
use LBHurtado\XChange\Data\IssuerData;
use LBHurtado\XChange\Data\PayCode\GeneratePayCodeResultData;
use LBHurtado\XChange\Data\PayCodeLinksData;
use LBHurtado\XChange\Data\PricingEstimateData;
use LBHurtado\XChange\Models\CockpitOperatorIssuanceActivity;
use LBHurtado\XChange\Services\Cockpit\DatabaseCockpitOperatorIssuanceActivityJournalHandoffStatusProjector;
use LBHurtado\XChange\Services\Cockpit\DatabaseCockpitOperatorIssuanceActivityRecorder;
use LBHurtado\XChange\Services\Cockpit\DatabaseCockpitOperatorIssuanceActivityRepository;

it('invokes configured journal handoff and persists handoff status after durable activity recording', function () {
    config()->set('x-change.cockpit.operator_issuance_activity.repository', DatabaseCockpitOperatorIssuanceActivityRepository::class);
    config()->set('x-change.cockpit.operator_issuance_activity.recorder', DatabaseCockpitOperatorIssuanceActivityRecorder::class);
    config()->set('x-change.cockpit.operator_issuance_activity.journal_handoff_status_projector', DatabaseCockpitOperatorIssuanceActivityJournalHandoffStatusProjector::class);

    app()->forgetInstance(CockpitOperatorIssuanceActivityRepositoryContract::class);
    app()->forgetInstance(CockpitOperatorIssuanceActivityRecorderContract::class);
    app()->forgetInstance(CockpitOperatorIssuanceActivityJournalHandoffStatusProjectorContract::class);

    $handoff = new class implements CockpitOperatorIssuanceActivityJournalHandoffContract
    {
        public int $calls = 0;

        public ?string $activityId = null;

        public function handoff(CockpitOperatorIssuanceActivityItemData $activity): CockpitOperatorIssuanceActivityJournalHandoffResultData
        {
            $this->calls++;
            $this->activityId = $activity->id;

            return new CockpitOperatorIssuanceActivityJournalHandoffResultData(
                status: 'recorded',
                activity_id: $activity->id,
                correlation_id: $activity->correlation_id,
                journal_entry_id: 'journal-pipeline-1',
                writes_journal: true,
                source: 'test-journal-handoff',
                reason: 'Test journal handoff recorded.',
                metadata: [
                    'reference_number' => 'XJ-PIPE-1',
                    'event_type' => 'cockpit.operator_issuance_activity.recorded',
                    'idempotency_key' => 'pipeline-safe-key',
                ],
            );
        }
    };

    app()->instance(CockpitOperatorIssuanceActivityJournalHandoffContract::class, $handoff);
    app()->instance(GeneratePayCode::class, cockpitJournalPipelineGeneratePayCodeAction('PC-JOURNAL-PIPELINE-1'));

    actingAsTestUser();

    $this->withHeaders([
        'Accept' => 'application/json',
        'Idempotency-Key' => 'journal-pipeline-1',
        'X-Correlation-ID' => 'corr-journal-pipeline-1',
    ])
        ->post(route('x-change.cockpit.quick-generate.store'), cockpitJournalPipelinePayload())
        ->assertCreated()
        ->assertJsonPath('result.code', 'PC-JOURNAL-PIPELINE-1');

    $activity = CockpitOperatorIssuanceActivity::query()->sole();

    expect($handoff->calls)->toBe(1)
        ->and($handoff->activityId)->toBe($activity->activity_id)
        ->and($activity->journal_handoff_status)->toBe('recorded')
        ->and($activity->action_handoff_status)->toBe('not_wired')
        ->and($activity->feedback_handoff_status)->toBe('not_wired')
        ->and($activity->metadata['journal_handoff'])->toMatchArray([
            'status' => 'recorded',
            'journal_entry_id' => 'journal-pipeline-1',
            'writes_journal' => true,
            'source' => 'test-journal-handoff',
            'reason' => 'Test journal handoff recorded.',
            'metadata' => [
                'reference_number' => 'XJ-PIPE-1',
                'event_type' => 'cockpit.operator_issuance_activity.recorded',
                'idempotency_key' => 'pipeline-safe-key',
            ],
        ]);
});

it('does not invoke journal handoff again when quick generate idempotency replays a stored response', function () {
    $handoff = new class implements CockpitOperatorIssuanceActivityJournalHandoffContract
    {
        public int $calls = 0;

        public function handoff(CockpitOperatorIssuanceActivityItemData $activity): CockpitOperatorIssuanceActivityJournalHandoffResultData
        {
            $this->calls++;

            return new CockpitOperatorIssuanceActivityJournalHandoffResultData(
                status: 'recorded',
                activity_id: $activity->id,
                correlation_id: $activity->correlation_id,
                journal_entry_id: 'journal-replay-safe',
                writes_journal: true,
            );
        }
    };

    app()->instance(CockpitOperatorIssuanceActivityJournalHandoffContract::class, $handoff);
    app()->instance(GeneratePayCode::class, cockpitJournalPipelineGeneratePayCodeAction('PC-JOURNAL-REPLAY'));

    actingAsTestUser();

    $headers = [
        'Accept' => 'application/json',
        'Idempotency-Key' => 'journal-pipeline-replay',
        'X-Correlation-ID' => 'corr-journal-pipeline-replay',
    ];

    $this->withHeaders($headers)
        ->post(route('x-change.cockpit.quick-generate.store'), cockpitJournalPipelinePayload())
        ->assertCreated();

    $this->withHeaders($headers)
        ->post(route('x-change.cockpit.quick-generate.store'), cockpitJournalPipelinePayload())
        ->assertOk()
        ->assertJsonPath('status', 'replayed');

    expect($handoff->calls)->toBe(1);
});

it('keeps quick generate successful and projects failed handoff status when journal handoff throws', function () {
    config()->set('x-change.cockpit.operator_issuance_activity.repository', DatabaseCockpitOperatorIssuanceActivityRepository::class);
    config()->set('x-change.cockpit.operator_issuance_activity.recorder', DatabaseCockpitOperatorIssuanceActivityRecorder::class);
    config()->set('x-change.cockpit.operator_issuance_activity.journal_handoff_status_projector', DatabaseCockpitOperatorIssuanceActivityJournalHandoffStatusProjector::class);

    app()->forgetInstance(CockpitOperatorIssuanceActivityRepositoryContract::class);
    app()->forgetInstance(CockpitOperatorIssuanceActivityRecorderContract::class);
    app()->forgetInstance(CockpitOperatorIssuanceActivityJournalHandoffStatusProjectorContract::class);

    app()->instance(CockpitOperatorIssuanceActivityJournalHandoffContract::class, new class implements CockpitOperatorIssuanceActivityJournalHandoffContract
    {
        public function handoff(CockpitOperatorIssuanceActivityItemData $activity): CockpitOperatorIssuanceActivityJournalHandoffResultData
        {
            throw new RuntimeException('Journal handoff unavailable.');
        }
    });

    app()->instance(GeneratePayCode::class, cockpitJournalPipelineGeneratePayCodeAction('PC-JOURNAL-FAILURE'));

    actingAsTestUser();

    $this->withHeaders([
        'Accept' => 'application/json',
        'Idempotency-Key' => 'journal-pipeline-failure',
        'X-Correlation-ID' => 'corr-journal-pipeline-failure',
    ])
        ->post(route('x-change.cockpit.quick-generate.store'), cockpitJournalPipelinePayload())
        ->assertCreated()
        ->assertJsonPath('result.code', 'PC-JOURNAL-FAILURE');

    $activity = CockpitOperatorIssuanceActivity::query()->sole();

    expect($activity->journal_handoff_status)->toBe('failed_non_blocking')
        ->and($activity->metadata['journal_handoff'])->toMatchArray([
            'status' => 'failed_non_blocking',
            'writes_journal' => false,
            'source' => 'cockpit-operator-issuance-activity-handoff-pipeline',
            'reason' => 'Journal handoff invocation failed without blocking the Cockpit activity flow.',
            'metadata' => [
                'exception' => RuntimeException::class,
            ],
        ]);
});

function cockpitJournalPipelineGeneratePayCodeAction(string $code): GeneratePayCode
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
            );
        }
    };
}

/**
 * @return array<string, mixed>
 */
function cockpitJournalPipelinePayload(): array
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
