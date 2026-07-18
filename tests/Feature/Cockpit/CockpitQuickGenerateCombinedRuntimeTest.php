<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use LBHurtado\XAction\Contracts\ActionRegistryContract;
use LBHurtado\XAction\Contracts\WorkflowActionContract;
use LBHurtado\XAction\Data\ActionContextData;
use LBHurtado\XAction\Data\ActionData;
use LBHurtado\XAction\Data\ActionSubjectData;
use LBHurtado\XAction\Data\ActionTargetData;
use LBHurtado\XChange\Actions\PayCode\GeneratePayCode;
use LBHurtado\XChange\Contracts\CockpitOperatorIssuanceActivityActionHandoffContract;
use LBHurtado\XChange\Contracts\CockpitOperatorIssuanceActivityActionHandoffStatusProjectorContract;
use LBHurtado\XChange\Contracts\CockpitOperatorIssuanceActivityFeedbackHandoffContract;
use LBHurtado\XChange\Contracts\CockpitOperatorIssuanceActivityFeedbackHandoffStatusProjectorContract;
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
use LBHurtado\XChange\Services\Cockpit\DatabaseCockpitOperatorIssuanceActivityActionHandoffStatusProjector;
use LBHurtado\XChange\Services\Cockpit\DatabaseCockpitOperatorIssuanceActivityFeedbackHandoffStatusProjector;
use LBHurtado\XChange\Services\Cockpit\DatabaseCockpitOperatorIssuanceActivityJournalHandoffStatusProjector;
use LBHurtado\XChange\Services\Cockpit\DatabaseCockpitOperatorIssuanceActivityRecorder;
use LBHurtado\XChange\Services\Cockpit\DatabaseCockpitOperatorIssuanceActivityRepository;
use LBHurtado\XChange\Services\Cockpit\XActionCockpitOperatorIssuanceActivityActionHandoff;
use LBHurtado\XChange\Services\Cockpit\XFeedbackCockpitOperatorIssuanceActivityFeedbackHandoff;
use LBHurtado\XChange\Services\Cockpit\XJournalCockpitOperatorIssuanceActivityJournalHandoff;
use LBHurtado\XJournal\Models\ExecutionJournalEntry;

it('records one quick generate activity through journal action and feedback handoffs when the combined profile is enabled', function () {
    Route::get('/x/cockpit/pay-codes/{code}', fn (string $code): string => $code)
        ->name('x-change.cockpit.pay-codes.show');

    app(ActionRegistryContract::class)->register(
        'cockpit.operator_issuance_activity.recorded',
        new CockpitQuickGenerateCombinedRuntimeWorkflowAction,
    );

    enableCockpitCombinedRuntimeProfile();
    app()->instance(GeneratePayCode::class, cockpitCombinedRuntimeGeneratePayCodeAction('PC-COMBINED-RUNTIME'));

    actingAsTestUser();

    $this->withHeaders([
        'Accept' => 'application/json',
        'Idempotency-Key' => 'quick-generate-combined-runtime',
        'X-Correlation-ID' => 'corr-quick-generate-combined-runtime',
    ])
        ->post(route('x-change.cockpit.quick-generate.store'), cockpitCombinedRuntimePayload())
        ->assertCreated()
        ->assertJsonPath('result.code', 'PC-COMBINED-RUNTIME');

    $activity = CockpitOperatorIssuanceActivity::query()->sole();
    $entry = ExecutionJournalEntry::query()->sole();

    expect($activity->subject_reference)->toBe('PC-COMBINED-RUNTIME')
        ->and($activity->journal_handoff_status)->toBe('recorded')
        ->and($activity->action_handoff_status)->toBe('composed')
        ->and($activity->feedback_handoff_status)->toBe('planned')
        ->and($activity->metadata['journal_handoff']['journal_entry_id'])->toBe((string) $entry->getKey())
        ->and($activity->metadata['journal_handoff']['writes_journal'])->toBeTrue()
        ->and($activity->metadata['action_handoff']['action_hint_id'])->toBe('cockpit.pay-code.open')
        ->and($activity->metadata['action_handoff']['executes_action'])->toBeFalse()
        ->and($activity->metadata['feedback_handoff']['feedback_intent_id'])->toBe('cockpit.operator_issuance_activity.recorded')
        ->and($activity->metadata['feedback_handoff']['sends_feedback'])->toBeFalse()
        ->and($activity->metadata['feedback_handoff']['metadata']['channels'])->toBe(['in_app'])
        ->and($entry->event_type)->toBe('cockpit.operator_issuance_activity.recorded')
        ->and($entry->subject_id)->toBe('PC-COMBINED-RUNTIME')
        ->and($entry->correlation_id)->toBe('corr-quick-generate-combined-runtime');
});

it('records campaign recipient attribution in durable operator issuance activity metadata', function () {
    Route::get('/x/cockpit/pay-codes/{code}', fn (string $code): string => $code)
        ->name('x-change.cockpit.pay-codes.show');

    enableCockpitCombinedRuntimeProfile();
    app()->instance(GeneratePayCode::class, cockpitCombinedRuntimeGeneratePayCodeAction('PC-WAVE-43B'));

    actingAsTestUser();

    $payload = cockpitCombinedRuntimePayload();
    data_set($payload, 'cash.amount', '500.00');
    data_set($payload, 'cash.currency', 'PHP');
    data_set($payload, 'feedback.mobile', '09173011987');
    data_set($payload, 'rider.message', 'Campaign payout');
    data_set($payload, 'metadata.custom.cockpit.template_key', 'ofw-remittance');
    data_set($payload, 'metadata.campaign', [
        'planning_key' => 'plan-wave-43b',
        'execution_id' => 'exec-wave-43b',
        'campaign_id' => 'campaign-wave-43b',
        'audience_id' => 'audience-wave-43b',
        'recipient_id' => 'recipient-wave-43b',
        'source' => 'x_campaign_adapter',
    ]);

    $this->withHeaders([
        'Accept' => 'application/json',
        'Idempotency-Key' => 'quick-generate-campaign-recipient-activity-wave-43b',
        'X-Correlation-ID' => 'corr-quick-generate-campaign-recipient-activity-wave-43b',
    ])
        ->post(route('x-change.cockpit.quick-generate.store'), $payload)
        ->assertCreated()
        ->assertJsonPath('result.code', 'PC-WAVE-43B')
        ->assertJsonPath('campaign_attribution.recipient_id', 'recipient-wave-43b');

    $activity = CockpitOperatorIssuanceActivity::query()->sole();

    expect($activity->subject_reference)->toBe('PC-WAVE-43B')
        ->and($activity->metadata['campaign_attribution']['schema'])->toBe('x-change.cockpit.quick-generate-campaign-attribution.v1')
        ->and($activity->metadata['campaign_attribution']['status'])->toBe('available')
        ->and($activity->metadata['campaign_attribution']['read_only'])->toBeTrue()
        ->and($activity->metadata['campaign_attribution']['mutates_campaign'])->toBeFalse()
        ->and($activity->metadata['campaign_attribution']['planning_key'])->toBe('plan-wave-43b')
        ->and($activity->metadata['campaign_attribution']['execution_id'])->toBe('exec-wave-43b')
        ->and($activity->metadata['campaign_attribution']['campaign_id'])->toBe('campaign-wave-43b')
        ->and($activity->metadata['campaign_attribution']['audience_id'])->toBe('audience-wave-43b')
        ->and($activity->metadata['campaign_attribution']['recipient_id'])->toBe('recipient-wave-43b')
        ->and($activity->metadata['campaign_attribution']['source'])->toBe('x_campaign_adapter')
        ->and($activity->metadata['campaign_attribution']['generated_code'])->toBe('PC-WAVE-43B')
        ->and($activity->metadata['campaign_attribution']['template_key'])->toBe('ofw-remittance')
        ->and($activity->metadata['campaign_attribution']['amount'])->toBe('500.00')
        ->and($activity->metadata['campaign_attribution']['currency'])->toBe('PHP')
        ->and($activity->metadata['campaign_attribution']['recipient_reference'])->toBe('09173011987')
        ->and($activity->metadata['campaign_attribution']['purpose'])->toBe('Campaign payout')
        ->and($activity->metadata['campaign_attribution']['redactions']['payloads'])->toBe('campaign-attribution-only')
        ->and($activity->metadata)->not->toHaveKey('raw_payload')
        ->and($activity->metadata)->not->toHaveKey('provider_payload')
        ->and($activity->metadata)->not->toHaveKey('wallet');
});

it('records selected local campaign fixture attribution after quick generate submission', function () {
    Route::get('/x/cockpit/pay-codes/{code}', fn (string $code): string => $code)
        ->name('x-change.cockpit.pay-codes.show');

    enableCockpitCombinedRuntimeProfile();
    app()->instance(GeneratePayCode::class, cockpitCombinedRuntimeGeneratePayCodeAction('PC-LOCAL-CAMPAIGN'));

    actingAsTestUser();

    $payload = cockpitCombinedRuntimePayload();
    data_set($payload, 'cash.amount', '500.00');
    data_set($payload, 'cash.currency', 'PHP');
    data_set($payload, 'feedback.mobile', '09173011987');
    data_set($payload, 'rider.message', 'Campaign payout');
    data_set($payload, 'metadata.custom.cockpit.template_key', 'ofw-remittance');
    data_set($payload, 'metadata.custom.cockpit.campaign_context', 'read-model-prefill');
    data_set($payload, 'metadata.campaign', [
        'planning_key' => 'plan-local',
        'execution_id' => 'exec-local',
        'campaign_id' => 'campaign-local',
        'audience_id' => 'audience-local',
        'recipient_id' => null,
        'source' => 'campaign_cockpit',
        'read_only' => true,
        'mutates_campaign' => false,
    ]);

    $this->withHeaders([
        'Accept' => 'application/json',
        'Idempotency-Key' => 'quick-generate-local-campaign-prefill-acceptance',
        'X-Correlation-ID' => 'corr-quick-generate-local-campaign-prefill-acceptance',
    ])
        ->post(route('x-change.cockpit.quick-generate.store'), $payload)
        ->assertCreated()
        ->assertJsonPath('result.code', 'PC-LOCAL-CAMPAIGN')
        ->assertJsonPath('campaign_attribution.status', 'available')
        ->assertJsonPath('campaign_attribution.read_only', true)
        ->assertJsonPath('campaign_attribution.mutates_campaign', false)
        ->assertJsonPath('campaign_attribution.planning_key', 'plan-local')
        ->assertJsonPath('campaign_attribution.execution_id', 'exec-local')
        ->assertJsonPath('campaign_attribution.campaign_id', 'campaign-local')
        ->assertJsonPath('campaign_attribution.audience_id', 'audience-local')
        ->assertJsonPath('campaign_attribution.source', 'campaign_cockpit')
        ->assertJsonPath('campaign_attribution.generated_code', 'PC-LOCAL-CAMPAIGN')
        ->assertJsonPath('campaign_attribution.template_key', 'ofw-remittance')
        ->assertJsonPath('campaign_attribution.amount', '500.00')
        ->assertJsonPath('campaign_attribution.currency', 'PHP')
        ->assertJsonPath('campaign_attribution.recipient_reference', '09173011987')
        ->assertJsonPath('campaign_attribution.purpose', 'Campaign payout')
        ->assertJsonPath('post_issuance_navigation.items.2.key', 'campaign_explorer')
        ->assertJsonPath('post_issuance_navigation.items.2.read_only', true)
        ->assertJsonPath('post_issuance_navigation.items.3.key', 'campaign_dashboard')
        ->assertJsonPath('post_issuance_navigation.items.3.read_only', true)
        ->assertJsonMissingPath('campaign_mutation')
        ->assertJsonMissingPath('campaign_payload')
        ->assertJsonMissingPath('recipient_payload')
        ->assertJsonMissingPath('delivery_payload')
        ->assertJsonMissingPath('provider_payload')
        ->assertJsonMissingPath('wallet');

    $activity = CockpitOperatorIssuanceActivity::query()->sole();

    expect($activity->subject_reference)->toBe('PC-LOCAL-CAMPAIGN')
        ->and($activity->metadata['campaign_attribution']['planning_key'])->toBe('plan-local')
        ->and($activity->metadata['campaign_attribution']['execution_id'])->toBe('exec-local')
        ->and($activity->metadata['campaign_attribution']['campaign_id'])->toBe('campaign-local')
        ->and($activity->metadata['campaign_attribution']['audience_id'])->toBe('audience-local')
        ->and($activity->metadata['campaign_attribution']['source'])->toBe('campaign_cockpit')
        ->and($activity->metadata['campaign_attribution']['read_only'])->toBeTrue()
        ->and($activity->metadata['campaign_attribution']['mutates_campaign'])->toBeFalse()
        ->and($activity->metadata['campaign_attribution']['generated_code'])->toBe('PC-LOCAL-CAMPAIGN')
        ->and($activity->metadata['campaign_attribution']['recipient_reference'])->toBe('09173011987')
        ->and($activity->metadata['campaign_attribution']['redactions']['payloads'])->toBe('campaign-attribution-only')
        ->and($activity->metadata)->not->toHaveKey('campaign_payload')
        ->and($activity->metadata)->not->toHaveKey('recipient_payload')
        ->and($activity->metadata)->not->toHaveKey('provider_payload')
        ->and($activity->metadata)->not->toHaveKey('wallet');
});

function enableCockpitCombinedRuntimeProfile(): void
{
    config()->set('x-change.cockpit.operator_issuance_activity.repository', 'database');
    config()->set('x-change.cockpit.operator_issuance_activity.recorder', 'database');
    config()->set('x-change.cockpit.operator_issuance_activity.journal_handoff', 'x-journal');
    config()->set('x-change.cockpit.operator_issuance_activity.journal_handoff_status_projector', 'database');
    config()->set('x-change.cockpit.operator_issuance_activity.action_handoff', 'x-action');
    config()->set('x-change.cockpit.operator_issuance_activity.action_handoff_status_projector', 'database');
    config()->set('x-change.cockpit.operator_issuance_activity.feedback_handoff', 'x-feedback');
    config()->set('x-change.cockpit.operator_issuance_activity.feedback_handoff_status_projector', 'database');

    foreach ([
        CockpitOperatorIssuanceActivityRepositoryContract::class,
        CockpitOperatorIssuanceActivityRecorderContract::class,
        CockpitOperatorIssuanceActivityJournalHandoffContract::class,
        CockpitOperatorIssuanceActivityJournalHandoffStatusProjectorContract::class,
        CockpitOperatorIssuanceActivityActionHandoffContract::class,
        CockpitOperatorIssuanceActivityActionHandoffStatusProjectorContract::class,
        CockpitOperatorIssuanceActivityFeedbackHandoffContract::class,
        CockpitOperatorIssuanceActivityFeedbackHandoffStatusProjectorContract::class,
        DatabaseCockpitOperatorIssuanceActivityRepository::class,
        DatabaseCockpitOperatorIssuanceActivityRecorder::class,
        XJournalCockpitOperatorIssuanceActivityJournalHandoff::class,
        DatabaseCockpitOperatorIssuanceActivityJournalHandoffStatusProjector::class,
        XActionCockpitOperatorIssuanceActivityActionHandoff::class,
        DatabaseCockpitOperatorIssuanceActivityActionHandoffStatusProjector::class,
        XFeedbackCockpitOperatorIssuanceActivityFeedbackHandoff::class,
        DatabaseCockpitOperatorIssuanceActivityFeedbackHandoffStatusProjector::class,
    ] as $abstract) {
        app()->forgetInstance($abstract);
    }
}

function cockpitCombinedRuntimeGeneratePayCodeAction(string $code): GeneratePayCode
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
                voucher_id: 33445,
                code: $this->code,
                amount: $input['cash']['amount'],
                currency: $input['cash']['currency'],
                issuer: new IssuerData(id: data_get($input, 'metadata.issuer_id')),
                cost: new PricingEstimateData(currency: 'PHP', base_fee: 1.25, total: 17.00),
                wallet: [
                    'balance_before' => 10000,
                    'balance_after' => 9975,
                ],
                debit: new DebitData(id: 66778, amount: 25),
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
function cockpitCombinedRuntimePayload(): array
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

class CockpitQuickGenerateCombinedRuntimeWorkflowAction implements WorkflowActionContract
{
    public function key(): string
    {
        return 'cockpit.pay-code.open';
    }

    public function supports(ActionSubjectData $subject, ActionContextData $context): bool
    {
        return $subject->type === 'pay_code'
            && $context->surface === 'cockpit'
            && $context->hasCapability('cockpit.pay-code.open');
    }

    public function toActionData(ActionSubjectData $subject, ActionContextData $context): ActionData
    {
        return new ActionData(
            key: $this->key(),
            label: 'Open Pay Code',
            target: new ActionTargetData(
                type: ActionTargetData::TypeRoute,
                route: 'x-change.cockpit.pay-codes.show',
                parameters: ['code' => (string) $subject->id],
            ),
            intent: 'inspect',
            description: 'Open generated Pay Code detail.',
            surface: 'cockpit',
            permissions: ['cockpit.pay-code.open'],
        );
    }
}
