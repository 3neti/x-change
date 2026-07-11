<?php

declare(strict_types=1);

use LBHurtado\XChange\Actions\PayCode\GeneratePayCode;
use LBHurtado\XChange\Contracts\CockpitOperatorIssuanceActivityFeedbackHandoffContract;
use LBHurtado\XChange\Contracts\CockpitOperatorIssuanceActivityFeedbackHandoffStatusProjectorContract;
use LBHurtado\XChange\Contracts\CockpitOperatorIssuanceActivityRecorderContract;
use LBHurtado\XChange\Contracts\CockpitOperatorIssuanceActivityRepositoryContract;
use LBHurtado\XChange\Data\DebitData;
use LBHurtado\XChange\Data\IssuerData;
use LBHurtado\XChange\Data\PayCode\GeneratePayCodeResultData;
use LBHurtado\XChange\Data\PayCodeLinksData;
use LBHurtado\XChange\Data\PricingEstimateData;
use LBHurtado\XChange\Models\CockpitOperatorIssuanceActivity;
use LBHurtado\XChange\Services\Cockpit\DatabaseCockpitOperatorIssuanceActivityFeedbackHandoffStatusProjector;
use LBHurtado\XChange\Services\Cockpit\DatabaseCockpitOperatorIssuanceActivityRecorder;
use LBHurtado\XChange\Services\Cockpit\DatabaseCockpitOperatorIssuanceActivityRepository;
use LBHurtado\XChange\Services\Cockpit\XFeedbackCockpitOperatorIssuanceActivityFeedbackHandoff;

it('records quick generate durable activity feedback handoff status when x-feedback profile is enabled', function () {
    enableCockpitXFeedbackRuntimeProfile();
    app()->instance(GeneratePayCode::class, cockpitXFeedbackRuntimeGeneratePayCodeAction('PC-XFEEDBACK-RUNTIME'));

    actingAsTestUser();

    $this->withHeaders([
        'Accept' => 'application/json',
        'Idempotency-Key' => 'quick-generate-xfeedback-runtime',
        'X-Correlation-ID' => 'corr-quick-generate-xfeedback-runtime',
    ])
        ->post(route('x-change.cockpit.quick-generate.store'), cockpitXFeedbackRuntimePayload())
        ->assertCreated()
        ->assertJsonPath('result.code', 'PC-XFEEDBACK-RUNTIME');

    $activity = CockpitOperatorIssuanceActivity::query()->sole();

    expect($activity->subject_reference)->toBe('PC-XFEEDBACK-RUNTIME')
        ->and($activity->feedback_handoff_status)->toBe('planned')
        ->and($activity->journal_handoff_status)->toBe('not_wired')
        ->and($activity->action_handoff_status)->toBe('not_wired')
        ->and($activity->metadata['feedback_handoff']['status'])->toBe('planned')
        ->and($activity->metadata['feedback_handoff']['feedback_intent_id'])->toBe('cockpit.operator_issuance_activity.recorded')
        ->and($activity->metadata['feedback_handoff']['delivery_plan_id'])->toStartWith('plan-')
        ->and($activity->metadata['feedback_handoff']['sends_feedback'])->toBeFalse()
        ->and($activity->metadata['feedback_handoff']['metadata']['delivery_boundary'])->toBe('prepare_only')
        ->and($activity->metadata['feedback_handoff']['metadata']['channels'])->toBe(['in_app'])
        ->and($activity->metadata['feedback_handoff']['metadata']['plan_items'][0]['channel'])->toBe('in_app')
        ->and($activity->metadata['feedback_handoff']['metadata']['composition']['owns_lifecycle_truth'])->toBeFalse()
        ->and($activity->metadata['feedback_handoff']['metadata'])->not->toHaveKey('raw_payload')
        ->and($activity->metadata['feedback_handoff']['metadata'])->not->toHaveKey('provider_payload');
});

function enableCockpitXFeedbackRuntimeProfile(): void
{
    config()->set('x-change.cockpit.operator_issuance_activity.repository', 'database');
    config()->set('x-change.cockpit.operator_issuance_activity.recorder', 'database');
    config()->set('x-change.cockpit.operator_issuance_activity.feedback_handoff', 'x-feedback');
    config()->set('x-change.cockpit.operator_issuance_activity.feedback_handoff_status_projector', 'database');

    foreach ([
        CockpitOperatorIssuanceActivityRepositoryContract::class,
        CockpitOperatorIssuanceActivityRecorderContract::class,
        CockpitOperatorIssuanceActivityFeedbackHandoffContract::class,
        CockpitOperatorIssuanceActivityFeedbackHandoffStatusProjectorContract::class,
        DatabaseCockpitOperatorIssuanceActivityRepository::class,
        DatabaseCockpitOperatorIssuanceActivityRecorder::class,
        XFeedbackCockpitOperatorIssuanceActivityFeedbackHandoff::class,
        DatabaseCockpitOperatorIssuanceActivityFeedbackHandoffStatusProjector::class,
    ] as $abstract) {
        app()->forgetInstance($abstract);
    }
}

function cockpitXFeedbackRuntimeGeneratePayCodeAction(string $code): GeneratePayCode
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
                voucher_id: 22334,
                code: $this->code,
                amount: $input['cash']['amount'],
                currency: $input['cash']['currency'],
                issuer: new IssuerData(id: data_get($input, 'metadata.issuer_id')),
                cost: new PricingEstimateData(currency: 'PHP', base_fee: 1.25, total: 17.00),
                wallet: [
                    'balance_before' => 10000,
                    'balance_after' => 9975,
                ],
                debit: new DebitData(id: 55667, amount: 25),
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
function cockpitXFeedbackRuntimePayload(): array
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
