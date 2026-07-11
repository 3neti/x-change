<?php

declare(strict_types=1);

use LBHurtado\XChange\Actions\PayCode\GeneratePayCode;
use LBHurtado\XChange\Contracts\CockpitOperatorIssuanceActivityJournalHandoffContract;
use LBHurtado\XChange\Contracts\CockpitOperatorIssuanceActivityJournalHandoffStatusProjectorContract;
use LBHurtado\XChange\Contracts\CockpitOperatorIssuanceActivityRecorderContract;
use LBHurtado\XChange\Contracts\CockpitOperatorIssuanceActivityRepositoryContract;
use LBHurtado\XChange\Data\DebitData;
use LBHurtado\XChange\Data\IssuerData;
use LBHurtado\XChange\Data\PayCode\GeneratePayCodeResultData;
use LBHurtado\XChange\Data\PayCodeLinksData;
use LBHurtado\XChange\Data\PricingEstimateData;
use LBHurtado\XChange\Models\CockpitOperatorIssuanceActivity;
use LBHurtado\XChange\Services\Cockpit\DatabaseCockpitOperatorIssuanceActivityJournalHandoffStatusProjector;
use LBHurtado\XChange\Services\Cockpit\DatabaseCockpitOperatorIssuanceActivityRecorder;
use LBHurtado\XChange\Services\Cockpit\DatabaseCockpitOperatorIssuanceActivityRepository;
use LBHurtado\XChange\Services\Cockpit\XJournalCockpitOperatorIssuanceActivityJournalHandoff;
use LBHurtado\XJournal\Models\ExecutionJournalEntry;

it('hydrates dashboard operator activity presentation with recorded x-journal handoff facts', function () {
    enableCockpitXJournalReadModelRuntimeProfile();
    app()->instance(GeneratePayCode::class, cockpitXJournalReadModelGeneratePayCodeAction('PC-XJOURNAL-READMODEL'));

    actingAsTestUser();

    $this->withHeaders([
        'Accept' => 'application/json',
        'Idempotency-Key' => 'quick-generate-xjournal-readmodel',
        'X-Correlation-ID' => 'corr-quick-generate-xjournal-readmodel',
    ])
        ->post(route('x-change.cockpit.quick-generate.store'), cockpitXJournalReadModelPayload())
        ->assertCreated();

    $activity = CockpitOperatorIssuanceActivity::query()->sole();
    $entry = ExecutionJournalEntry::query()->sole();

    $this->withHeader('X-Inertia', 'true')
        ->get(route('x-change.cockpit.dashboard'))
        ->assertOk()
        ->assertJsonPath('component', 'x-change/cockpit/Dashboard')
        ->assertJsonPath('props.operator_issuance_activity_read_model.status', 'available')
        ->assertJsonPath('props.operator_issuance_activity_read_model.presentations.0.id', $activity->activity_id)
        ->assertJsonPath('props.operator_issuance_activity_read_model.presentations.0.code', 'PC-XJOURNAL-READMODEL')
        ->assertJsonPath('props.operator_issuance_activity_read_model.presentations.0.handoffs.journal', 'recorded')
        ->assertJsonPath('props.operator_issuance_activity_read_model.presentations.0.handoffs.action', 'not_wired')
        ->assertJsonPath('props.operator_issuance_activity_read_model.presentations.0.handoffs.feedback', 'not_wired')
        ->assertJsonPath('props.operator_issuance_activity_read_model.presentations.0.metadata.journal_handoff.status', 'recorded')
        ->assertJsonPath('props.operator_issuance_activity_read_model.presentations.0.metadata.journal_handoff.writes_journal', true)
        ->assertJsonPath('props.operator_issuance_activity_read_model.presentations.0.metadata.journal_handoff.journal_entry_id', (string) $entry->getKey())
        ->assertJsonPath('props.operator_issuance_activity_read_model.presentations.0.metadata.journal_handoff.metadata.reference_number', $entry->reference_number)
        ->assertJsonPath('props.operator_issuance_activity_read_model.presentations.0.metadata.journal_handoff.metadata.event_type', 'cockpit.operator_issuance_activity.recorded');
});

function enableCockpitXJournalReadModelRuntimeProfile(): void
{
    config()->set('x-change.cockpit.operator_issuance_activity.repository', 'database');
    config()->set('x-change.cockpit.operator_issuance_activity.recorder', 'database');
    config()->set('x-change.cockpit.operator_issuance_activity.journal_handoff', 'x-journal');
    config()->set('x-change.cockpit.operator_issuance_activity.journal_handoff_status_projector', 'database');

    foreach ([
        CockpitOperatorIssuanceActivityRepositoryContract::class,
        CockpitOperatorIssuanceActivityRecorderContract::class,
        CockpitOperatorIssuanceActivityJournalHandoffContract::class,
        CockpitOperatorIssuanceActivityJournalHandoffStatusProjectorContract::class,
        DatabaseCockpitOperatorIssuanceActivityRepository::class,
        DatabaseCockpitOperatorIssuanceActivityRecorder::class,
        XJournalCockpitOperatorIssuanceActivityJournalHandoff::class,
        DatabaseCockpitOperatorIssuanceActivityJournalHandoffStatusProjector::class,
    ] as $abstract) {
        app()->forgetInstance($abstract);
    }
}

function cockpitXJournalReadModelGeneratePayCodeAction(string $code): GeneratePayCode
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
                voucher_id: 13579,
                code: $this->code,
                amount: $input['cash']['amount'],
                currency: $input['cash']['currency'],
                issuer: new IssuerData(id: data_get($input, 'metadata.issuer_id')),
                cost: new PricingEstimateData(currency: 'PHP', base_fee: 1.25, total: 17.00),
                wallet: [
                    'balance_before' => 10000,
                    'balance_after' => 9975,
                ],
                debit: new DebitData(id: 97531, amount: 25),
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
function cockpitXJournalReadModelPayload(): array
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
