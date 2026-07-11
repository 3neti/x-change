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
use LBHurtado\XChange\Contracts\CockpitOperatorIssuanceActivityRecorderContract;
use LBHurtado\XChange\Contracts\CockpitOperatorIssuanceActivityRepositoryContract;
use LBHurtado\XChange\Data\DebitData;
use LBHurtado\XChange\Data\IssuerData;
use LBHurtado\XChange\Data\PayCode\GeneratePayCodeResultData;
use LBHurtado\XChange\Data\PayCodeLinksData;
use LBHurtado\XChange\Data\PricingEstimateData;
use LBHurtado\XChange\Models\CockpitOperatorIssuanceActivity;
use LBHurtado\XChange\Services\Cockpit\DatabaseCockpitOperatorIssuanceActivityActionHandoffStatusProjector;
use LBHurtado\XChange\Services\Cockpit\DatabaseCockpitOperatorIssuanceActivityRecorder;
use LBHurtado\XChange\Services\Cockpit\DatabaseCockpitOperatorIssuanceActivityRepository;
use LBHurtado\XChange\Services\Cockpit\XActionCockpitOperatorIssuanceActivityActionHandoff;

it('records quick generate durable activity action handoff status when x-action profile is enabled', function () {
    Route::get('/x/cockpit/pay-codes/{code}', fn (string $code): string => $code)
        ->name('x-change.cockpit.pay-codes.show');

    app(ActionRegistryContract::class)->register(
        'cockpit.operator_issuance_activity.recorded',
        new CockpitQuickGenerateXActionRuntimeWorkflowAction,
    );

    enableCockpitXActionRuntimeProfile();
    app()->instance(GeneratePayCode::class, cockpitXActionRuntimeGeneratePayCodeAction('PC-XACTION-RUNTIME'));

    actingAsTestUser();

    $this->withHeaders([
        'Accept' => 'application/json',
        'Idempotency-Key' => 'quick-generate-xaction-runtime',
        'X-Correlation-ID' => 'corr-quick-generate-xaction-runtime',
    ])
        ->post(route('x-change.cockpit.quick-generate.store'), cockpitXActionRuntimePayload())
        ->assertCreated()
        ->assertJsonPath('result.code', 'PC-XACTION-RUNTIME');

    $activity = CockpitOperatorIssuanceActivity::query()->sole();

    expect($activity->subject_reference)->toBe('PC-XACTION-RUNTIME')
        ->and($activity->action_handoff_status)->toBe('composed')
        ->and($activity->journal_handoff_status)->toBe('not_wired')
        ->and($activity->metadata['action_handoff']['status'])->toBe('composed')
        ->and($activity->metadata['action_handoff']['action_hint_id'])->toBe('cockpit.pay-code.open')
        ->and($activity->metadata['action_handoff']['action_run_id'])->toBeUuid()
        ->and($activity->metadata['action_handoff']['executes_action'])->toBeFalse()
        ->and($activity->metadata['action_handoff']['metadata']['event_or_state'])->toBe('cockpit.operator_issuance_activity.recorded')
        ->and($activity->metadata['action_handoff']['metadata']['actions'][0]['target']['url'])->toContain('/x/cockpit/pay-codes/PC-XACTION-RUNTIME')
        ->and($activity->metadata['action_handoff']['metadata']['composition']['presentation_only'])->toBeTrue()
        ->and($activity->metadata['action_handoff']['metadata'])->not->toHaveKey('raw_payload')
        ->and($activity->metadata['action_handoff']['metadata'])->not->toHaveKey('provider_payload');
});

function enableCockpitXActionRuntimeProfile(): void
{
    config()->set('x-change.cockpit.operator_issuance_activity.repository', 'database');
    config()->set('x-change.cockpit.operator_issuance_activity.recorder', 'database');
    config()->set('x-change.cockpit.operator_issuance_activity.action_handoff', 'x-action');
    config()->set('x-change.cockpit.operator_issuance_activity.action_handoff_status_projector', 'database');

    foreach ([
        CockpitOperatorIssuanceActivityRepositoryContract::class,
        CockpitOperatorIssuanceActivityRecorderContract::class,
        CockpitOperatorIssuanceActivityActionHandoffContract::class,
        CockpitOperatorIssuanceActivityActionHandoffStatusProjectorContract::class,
        DatabaseCockpitOperatorIssuanceActivityRepository::class,
        DatabaseCockpitOperatorIssuanceActivityRecorder::class,
        XActionCockpitOperatorIssuanceActivityActionHandoff::class,
        DatabaseCockpitOperatorIssuanceActivityActionHandoffStatusProjector::class,
    ] as $abstract) {
        app()->forgetInstance($abstract);
    }
}

function cockpitXActionRuntimeGeneratePayCodeAction(string $code): GeneratePayCode
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
                voucher_id: 11223,
                code: $this->code,
                amount: $input['cash']['amount'],
                currency: $input['cash']['currency'],
                issuer: new IssuerData(id: data_get($input, 'metadata.issuer_id')),
                cost: new PricingEstimateData(currency: 'PHP', base_fee: 1.25, total: 17.00),
                wallet: [
                    'balance_before' => 10000,
                    'balance_after' => 9975,
                ],
                debit: new DebitData(id: 44556, amount: 25),
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
function cockpitXActionRuntimePayload(): array
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

class CockpitQuickGenerateXActionRuntimeWorkflowAction implements WorkflowActionContract
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
