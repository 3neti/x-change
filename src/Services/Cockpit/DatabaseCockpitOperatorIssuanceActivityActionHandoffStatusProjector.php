<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Cockpit;

use LBHurtado\XChange\Contracts\CockpitOperatorIssuanceActivityActionHandoffStatusProjectorContract;
use LBHurtado\XChange\Data\Cockpit\CockpitOperatorIssuanceActivityActionHandoffResultData;
use LBHurtado\XChange\Data\Cockpit\CockpitOperatorIssuanceActivityActionHandoffStatusProjectionData;
use LBHurtado\XChange\Models\CockpitOperatorIssuanceActivity;

class DatabaseCockpitOperatorIssuanceActivityActionHandoffStatusProjector implements CockpitOperatorIssuanceActivityActionHandoffStatusProjectorContract
{
    public function project(CockpitOperatorIssuanceActivityActionHandoffResultData $result): CockpitOperatorIssuanceActivityActionHandoffStatusProjectionData
    {
        if ($result->activity_id === null || trim($result->activity_id) === '') {
            return $this->notPersisted($result, 'missing_activity_id', 'Action handoff status was not persisted because the handoff result has no activity ID.');
        }

        $activity = CockpitOperatorIssuanceActivity::query()
            ->where('activity_id', $result->activity_id)
            ->first();

        if (! $activity instanceof CockpitOperatorIssuanceActivity) {
            return $this->notPersisted($result, 'missing_activity', 'Action handoff status was not persisted because the durable activity row was not found.');
        }

        $activity->forceFill([
            'action_handoff_status' => $result->status,
            'metadata' => [
                ...($activity->metadata ?? []),
                'action_handoff' => $this->actionHandoffMetadata($result),
            ],
        ])->save();

        return new CockpitOperatorIssuanceActivityActionHandoffStatusProjectionData(
            status: 'persisted',
            activity_id: $result->activity_id,
            correlation_id: $result->correlation_id,
            action_handoff_status: $result->status,
            action_hint_id: $result->action_hint_id,
            action_run_id: $result->action_run_id,
            persists_status: true,
            source: 'database-cockpit-operator-issuance-activity-action-handoff-status-projector',
            reason: 'Action handoff status was persisted to the durable Cockpit activity row.',
            metadata: [
                'action_handoff' => $this->actionHandoffMetadata($result),
            ],
        );
    }

    private function notPersisted(
        CockpitOperatorIssuanceActivityActionHandoffResultData $result,
        string $status,
        string $reason,
    ): CockpitOperatorIssuanceActivityActionHandoffStatusProjectionData {
        return new CockpitOperatorIssuanceActivityActionHandoffStatusProjectionData(
            status: $status,
            activity_id: $result->activity_id,
            correlation_id: $result->correlation_id,
            action_handoff_status: $result->status,
            action_hint_id: $result->action_hint_id,
            action_run_id: $result->action_run_id,
            persists_status: false,
            source: 'database-cockpit-operator-issuance-activity-action-handoff-status-projector',
            reason: $reason,
            metadata: [
                'action_handoff' => $this->actionHandoffMetadata($result),
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function actionHandoffMetadata(CockpitOperatorIssuanceActivityActionHandoffResultData $result): array
    {
        return [
            'status' => $result->status,
            'action_hint_id' => $result->action_hint_id,
            'action_run_id' => $result->action_run_id,
            'action_required' => $result->action_required,
            'executes_action' => $result->executes_action,
            'source' => $result->source,
            'reason' => $result->reason,
            'metadata' => $this->safeMetadata($result->metadata),
        ];
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    private function safeMetadata(array $metadata): array
    {
        $safe = array_intersect_key($metadata, array_flip([
            'event_or_state',
            'actions',
            'composition',
            'safe_diagnostics',
            'exception',
        ]));

        if (isset($safe['actions']) && is_array($safe['actions'])) {
            $safe['actions'] = array_values(array_map(
                fn (mixed $action): array => $this->safeAction($action),
                $safe['actions'],
            ));
        }

        return $safe;
    }

    /**
     * @return array<string, mixed>
     */
    private function safeAction(mixed $action): array
    {
        if (! is_array($action)) {
            return [];
        }

        return array_intersect_key($action, array_flip([
            'key',
            'label',
            'intent',
            'description',
            'run_id',
            'target',
            'run_semantics',
        ]));
    }
}
