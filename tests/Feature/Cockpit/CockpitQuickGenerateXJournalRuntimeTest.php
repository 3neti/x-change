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

it('records real quick generate durable activity into x-journal when runtime profile keys are enabled', function () {
    enableCockpitXJournalRuntimeProfile();
    app()->instance(GeneratePayCode::class, cockpitXJournalRuntimeGeneratePayCodeAction('PC-XJOURNAL-RUNTIME'));

    actingAsTestUser();

    $this->withHeaders([
        'Accept' => 'application/json',
        'Idempotency-Key' => 'quick-generate-xjournal-runtime',
        'X-Correlation-ID' => 'corr-quick-generate-xjournal-runtime',
    ])
        ->post(route('x-change.cockpit.quick-generate.store'), cockpitXJournalRuntimePayload())
        ->assertCreated()
        ->assertJsonPath('result.code', 'PC-XJOURNAL-RUNTIME');

    $activity = CockpitOperatorIssuanceActivity::query()->sole();
    $entry = ExecutionJournalEntry::query()->sole();

    expect($activity->subject_reference)->toBe('PC-XJOURNAL-RUNTIME')
        ->and($activity->journal_handoff_status)->toBe('recorded')
        ->and($activity->metadata['journal_handoff']['status'])->toBe('recorded')
        ->and($activity->metadata['journal_handoff']['writes_journal'])->toBeTrue()
        ->and($activity->metadata['journal_handoff']['journal_entry_id'])->toBe((string) $entry->getKey())
        ->and($activity->metadata['journal_handoff']['metadata']['reference_number'])->toBe($entry->reference_number)
        ->and($entry->event_type)->toBe('cockpit.operator_issuance_activity.recorded')
        ->and($entry->subject_id)->toBe('PC-XJOURNAL-RUNTIME')
        ->and($entry->correlation_id)->toBe('corr-quick-generate-xjournal-runtime')
        ->and($entry->payload['amount'])->toBe('25')
        ->and($entry->payload['currency'])->toBe('PHP')
        ->and($entry->metadata['source'])->toBe('x-change.cockpit');
});

it('does not duplicate x-journal entries when quick generate replays idempotently', function () {
    enableCockpitXJournalRuntimeProfile();
    app()->instance(GeneratePayCode::class, cockpitXJournalRuntimeGeneratePayCodeAction('PC-XJOURNAL-REPLAY'));

    actingAsTestUser();

    $headers = [
        'Accept' => 'application/json',
        'Idempotency-Key' => 'quick-generate-xjournal-replay',
        'X-Correlation-ID' => 'corr-quick-generate-xjournal-replay',
    ];

    $this->withHeaders($headers)
        ->post(route('x-change.cockpit.quick-generate.store'), cockpitXJournalRuntimePayload())
        ->assertCreated();

    $this->withHeaders($headers)
        ->post(route('x-change.cockpit.quick-generate.store'), cockpitXJournalRuntimePayload())
        ->assertOk()
        ->assertJsonPath('status', 'replayed');

    expect(CockpitOperatorIssuanceActivity::query()->count())->toBe(1)
        ->and(ExecutionJournalEntry::query()->count())->toBe(1)
        ->and(CockpitOperatorIssuanceActivity::query()->sole()->journal_handoff_status)->toBe('recorded');
});

function enableCockpitXJournalRuntimeProfile(): void
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

function cockpitXJournalRuntimeGeneratePayCodeAction(string $code): GeneratePayCode
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
                voucher_id: 24680,
                code: $this->code,
                amount: $input['cash']['amount'],
                currency: $input['cash']['currency'],
                issuer: new IssuerData(id: data_get($input, 'metadata.issuer_id')),
                cost: new PricingEstimateData(currency: 'PHP', base_fee: 1.25, total: 17.00),
                wallet: [
                    'balance_before' => 10000,
                    'balance_after' => 9975,
                ],
                debit: new DebitData(id: 86420, amount: 25),
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
function cockpitXJournalRuntimePayload(): array
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
