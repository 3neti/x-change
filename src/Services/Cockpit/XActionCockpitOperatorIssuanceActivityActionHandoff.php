<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Cockpit;

use LBHurtado\XAction\Contracts\ActionHostComposerContract;
use LBHurtado\XAction\Data\ActionContextData;
use LBHurtado\XAction\Data\ActionHostActionData;
use LBHurtado\XAction\Data\ActionSubjectData;
use LBHurtado\XChange\Contracts\CockpitOperatorIssuanceActivityActionHandoffContract;
use LBHurtado\XChange\Data\Cockpit\CockpitOperatorIssuanceActivityActionHandoffResultData;
use LBHurtado\XChange\Data\Cockpit\CockpitOperatorIssuanceActivityItemData;

class XActionCockpitOperatorIssuanceActivityActionHandoff implements CockpitOperatorIssuanceActivityActionHandoffContract
{
    private const EventOrState = 'cockpit.operator_issuance_activity.recorded';

    public function __construct(
        private readonly ActionHostComposerContract $composer,
    ) {}

    public function handoff(CockpitOperatorIssuanceActivityItemData $activity): CockpitOperatorIssuanceActivityActionHandoffResultData
    {
        $result = $this->composer->compose(
            eventOrState: self::EventOrState,
            subject: $this->subject($activity),
            context: $this->context($activity),
            correlationId: $activity->correlation_id,
            causationId: $activity->id,
            includeDiagnostics: false,
        );

        $actions = array_values(array_map(
            fn (ActionHostActionData $action): array => $this->safeAction($action),
            $result->actions,
        ));

        $firstAction = $result->actions[0] ?? null;

        return new CockpitOperatorIssuanceActivityActionHandoffResultData(
            status: $actions === [] ? 'no_actions' : 'composed',
            activity_id: $activity->id,
            correlation_id: $activity->correlation_id,
            action_hint_id: $firstAction?->action->key,
            action_run_id: $firstAction?->run->run_id,
            action_required: false,
            executes_action: false,
            source: 'x-action-cockpit-operator-issuance-activity-action-handoff',
            reason: $actions === []
                ? 'x-action composed no operator actions for this Cockpit activity.'
                : 'x-action composed presentation-only operator action hints for this Cockpit activity.',
            metadata: [
                'event_or_state' => $result->event_or_state,
                'actions' => $actions,
                'composition' => is_array($result->meta['composition'] ?? null)
                    ? $result->meta['composition']
                    : [],
                'safe_diagnostics' => is_array($result->meta['safe_diagnostics'] ?? null)
                    ? $result->meta['safe_diagnostics']
                    : [],
            ],
        );
    }

    private function subject(CockpitOperatorIssuanceActivityItemData $activity): ActionSubjectData
    {
        return new ActionSubjectData(
            type: 'pay_code',
            id: $activity->code,
            attributes: [
                'code' => $activity->code,
                'amount' => $activity->amount,
                'currency' => $activity->currency,
                'route' => $activity->route,
            ],
            state: [
                'status' => $activity->status,
            ],
            meta: [
                'activity_id' => $activity->id,
                'detail_href' => $activity->detail_href,
            ],
        );
    }

    private function context(CockpitOperatorIssuanceActivityItemData $activity): ActionContextData
    {
        return new ActionContextData(
            actor_type: 'operator',
            actor_id: $activity->operator_id,
            feature_profile: 'cockpit',
            surface: 'cockpit',
            channel: 'web',
            capabilities: [
                'cockpit.pay-code.open',
                'cockpit.operator-issuance-activity.view',
            ],
            meta: [
                'activity_id' => $activity->id,
                'presentation_only' => true,
                'executes_action' => false,
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function safeAction(ActionHostActionData $action): array
    {
        return [
            'key' => $action->action->key,
            'label' => $action->action->label,
            'intent' => $action->action->intent,
            'description' => $action->action->description,
            'run_id' => $action->run->run_id,
            'target' => [
                'type' => $action->target_resolution->type,
                'url' => $action->target_resolution->url,
                'method' => $action->target_resolution->method,
                'redirectable' => $action->target_resolution->redirectable,
                'external' => $action->target_resolution->external,
            ],
            'run_semantics' => is_array($action->meta['run_semantics'] ?? null)
                ? $action->meta['run_semantics']
                : [],
        ];
    }
}
