<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use LBHurtado\XAction\Contracts\ActionRegistryContract;
use LBHurtado\XAction\Contracts\WorkflowActionContract;
use LBHurtado\XAction\Data\ActionContextData;
use LBHurtado\XAction\Data\ActionData;
use LBHurtado\XAction\Data\ActionSubjectData;
use LBHurtado\XAction\Data\ActionTargetData;
use LBHurtado\XChange\Data\Cockpit\CockpitOperatorIssuanceActivityItemData;
use LBHurtado\XChange\Services\Cockpit\XActionCockpitOperatorIssuanceActivityActionHandoff;

it('composes x-action host actions for operator issuance activity without executing actions', function () {
    Route::get('/x/cockpit/pay-codes/{code}', fn (string $code): string => $code)
        ->name('x-change.cockpit.pay-codes.show');

    app(ActionRegistryContract::class)->register(
        'cockpit.operator_issuance_activity.recorded',
        new CockpitXActionHandoffTestWorkflowAction,
    );

    $result = app(XActionCockpitOperatorIssuanceActivityActionHandoff::class)->handoff(
        new CockpitOperatorIssuanceActivityItemData(
            id: 'activity-x-action-1',
            code: 'PC-XACTION-1',
            amount: '25',
            currency: 'PHP',
            status: 'issued',
            issued_at: '2026-07-11T09:00:00+08:00',
            route: 'cockpit.quick-generate',
            correlation_id: 'corr-x-action-1',
            operator_id: 'operator-1',
            detail_href: '/x/cockpit/pay-codes/PC-XACTION-1',
        ),
    );

    expect($result->status)->toBe('composed')
        ->and($result->activity_id)->toBe('activity-x-action-1')
        ->and($result->correlation_id)->toBe('corr-x-action-1')
        ->and($result->action_hint_id)->toBe('cockpit.pay-code.open')
        ->and($result->action_run_id)->toBeUuid()
        ->and($result->action_required)->toBeFalse()
        ->and($result->executes_action)->toBeFalse()
        ->and($result->source)->toBe('x-action-cockpit-operator-issuance-activity-action-handoff')
        ->and($result->metadata['event_or_state'])->toBe('cockpit.operator_issuance_activity.recorded')
        ->and($result->metadata['actions'])->toHaveCount(1)
        ->and($result->metadata['actions'][0]['key'])->toBe('cockpit.pay-code.open')
        ->and($result->metadata['actions'][0]['label'])->toBe('Open Pay Code')
        ->and($result->metadata['actions'][0]['run_id'])->toBeUuid()
        ->and($result->metadata['actions'][0]['target']['redirectable'])->toBeTrue()
        ->and($result->metadata['actions'][0]['target']['url'])->toContain('/x/cockpit/pay-codes/PC-XACTION-1')
        ->and($result->metadata['composition'])->toBe([
            'presentation_only' => true,
            'durable_run' => false,
            'records_lifecycle' => false,
            'executes_action' => false,
            'authorizes_action' => false,
        ])
        ->and($result->metadata)->not->toHaveKey('raw_payload')
        ->and($result->metadata)->not->toHaveKey('provider_payload')
        ->and($result->metadata)->not->toHaveKey('wallet');
});

it('returns a safe no-action result when x-action has no matching action hints', function () {
    $result = app(XActionCockpitOperatorIssuanceActivityActionHandoff::class)->handoff(
        new CockpitOperatorIssuanceActivityItemData(
            id: 'activity-x-action-empty',
            code: 'PC-XACTION-EMPTY',
            amount: '25',
            currency: 'PHP',
            status: 'issued',
            issued_at: '2026-07-11T09:00:00+08:00',
            route: 'cockpit.quick-generate',
            correlation_id: 'corr-x-action-empty',
            operator_id: 'operator-empty',
        ),
    );

    expect($result->status)->toBe('no_actions')
        ->and($result->activity_id)->toBe('activity-x-action-empty')
        ->and($result->action_hint_id)->toBeNull()
        ->and($result->action_run_id)->toBeNull()
        ->and($result->action_required)->toBeFalse()
        ->and($result->executes_action)->toBeFalse()
        ->and($result->metadata['actions'])->toBe([])
        ->and($result->reason)->toBe('x-action composed no operator actions for this Cockpit activity.');
});

class CockpitXActionHandoffTestWorkflowAction implements WorkflowActionContract
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
            description: 'Open the generated Pay Code in the read-only Cockpit detail screen.',
            surface: 'cockpit',
            permissions: ['cockpit.pay-code.open'],
            meta: [
                'read_only' => true,
            ],
        );
    }
}
