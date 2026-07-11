<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Cockpit;

use LBHurtado\XChange\Contracts\CockpitOperatorIssuanceActivityRedactionPolicyContract;
use LBHurtado\XChange\Contracts\CockpitOperatorIssuanceActivityRepositoryContract;
use LBHurtado\XChange\Contracts\CockpitOperatorIssuanceActivityRetentionPolicyContract;
use LBHurtado\XChange\Data\Cockpit\CockpitOperatorIssuanceActivityRecordData;
use LBHurtado\XChange\Data\Cockpit\CockpitReadModelQueryData;
use LBHurtado\XChange\Models\CockpitOperatorIssuanceActivity;

class DatabaseCockpitOperatorIssuanceActivityRepository implements CockpitOperatorIssuanceActivityRepositoryContract
{
    public function __construct(
        private readonly CockpitOperatorIssuanceActivityRedactionPolicyContract $redactionPolicy,
        private readonly CockpitOperatorIssuanceActivityRetentionPolicyContract $retentionPolicy,
    ) {}

    public function record(CockpitOperatorIssuanceActivityRecordData $record): CockpitOperatorIssuanceActivityRecordData
    {
        $record = $this->withRetention(
            $this->redactionPolicy->redact($record),
        );

        if (! $this->retentionPolicy->isRetainable($record)) {
            return $record;
        }

        $model = CockpitOperatorIssuanceActivity::query()->updateOrCreate(
            ['activity_id' => $record->activity_id],
            $this->toAttributes($record),
        );

        return $this->fromModel($model->refresh());
    }

    public function findByActivityId(string $activityId): ?CockpitOperatorIssuanceActivityRecordData
    {
        if ($activityId === '') {
            return null;
        }

        $model = CockpitOperatorIssuanceActivity::query()
            ->where('activity_id', $activityId)
            ->first();

        return $model instanceof CockpitOperatorIssuanceActivity
            ? $this->fromModel($model)
            : null;
    }

    /**
     * @return array<int, CockpitOperatorIssuanceActivityRecordData>
     */
    public function recent(CockpitReadModelQueryData $query, int $limit = 25): array
    {
        $builder = CockpitOperatorIssuanceActivity::query();

        if ($query->operatorId !== null) {
            $builder->where('actor_id', $query->operatorId);
        }

        if ($query->correlationId !== null) {
            $builder->where('correlation_id', $query->correlationId);
        }

        if ($query->code !== null) {
            $builder->where('subject_reference', $query->code);
        }

        $filters = $query->operatorActivityFilters;

        if ($filters !== null && ! $filters->isEmpty()) {
            if ($filters->statuses !== []) {
                $builder->whereIn('status', $filters->statuses);
            }

            if ($filters->handoffStatuses !== []) {
                $builder->where(function ($query) use ($filters): void {
                    $query
                        ->whereIn('journal_handoff_status', $filters->handoffStatuses)
                        ->orWhereIn('action_handoff_status', $filters->handoffStatuses)
                        ->orWhereIn('feedback_handoff_status', $filters->handoffStatuses);
                });
            }

            if ($filters->search !== null) {
                $search = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], mb_strtolower($filters->search)).'%';

                $builder->where(function ($query) use ($search): void {
                    $query
                        ->whereRaw('lower(activity_id) like ?', [$search])
                        ->orWhereRaw('lower(actor_id) like ?', [$search])
                        ->orWhereRaw('lower(actor_label) like ?', [$search])
                        ->orWhereRaw('lower(subject_reference) like ?', [$search])
                        ->orWhereRaw('lower(status) like ?', [$search])
                        ->orWhereRaw('lower(severity) like ?', [$search])
                        ->orWhereRaw('lower(correlation_id) like ?', [$search])
                        ->orWhereRaw('lower(summary) like ?', [$search]);
                });
            }
        }

        return $builder
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->limit(max(0, $limit))
            ->get()
            ->map(fn (CockpitOperatorIssuanceActivity $activity): CockpitOperatorIssuanceActivityRecordData => $this->fromModel($activity))
            ->all();
    }

    private function withRetention(CockpitOperatorIssuanceActivityRecordData $record): CockpitOperatorIssuanceActivityRecordData
    {
        return new CockpitOperatorIssuanceActivityRecordData(
            activity_id: $record->activity_id,
            schema: $record->schema,
            actor_id: $record->actor_id,
            actor_label: $record->actor_label,
            source: $record->source,
            subject_type: $record->subject_type,
            subject_reference: $record->subject_reference,
            status: $record->status,
            severity: $record->severity,
            occurred_at: $record->occurred_at,
            idempotency_key_hash: $record->idempotency_key_hash,
            correlation_id: $record->correlation_id,
            causation_id: $record->causation_id,
            summary: $record->summary,
            safe_context: $record->safe_context,
            redaction_flags: $record->redaction_flags,
            journal_handoff_status: $record->journal_handoff_status,
            action_handoff_status: $record->action_handoff_status,
            feedback_handoff_status: $record->feedback_handoff_status,
            retention_until: $this->retentionPolicy->retentionUntil($record),
            metadata: $record->metadata,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function toAttributes(CockpitOperatorIssuanceActivityRecordData $record): array
    {
        return [
            'schema' => $record->schema,
            'actor_id' => $record->actor_id,
            'actor_label' => $record->actor_label,
            'source' => $record->source,
            'subject_type' => $record->subject_type,
            'subject_reference' => $record->subject_reference,
            'status' => $record->status,
            'severity' => $record->severity,
            'occurred_at' => $record->occurred_at,
            'idempotency_key_hash' => $record->idempotency_key_hash,
            'correlation_id' => $record->correlation_id,
            'causation_id' => $record->causation_id,
            'summary' => $record->summary,
            'safe_context' => $record->safe_context,
            'redaction_flags' => $record->redaction_flags,
            'journal_handoff_status' => $record->journal_handoff_status,
            'action_handoff_status' => $record->action_handoff_status,
            'feedback_handoff_status' => $record->feedback_handoff_status,
            'retention_until' => $record->retention_until,
            'metadata' => $record->metadata,
        ];
    }

    private function fromModel(CockpitOperatorIssuanceActivity $activity): CockpitOperatorIssuanceActivityRecordData
    {
        return new CockpitOperatorIssuanceActivityRecordData(
            activity_id: (string) $activity->activity_id,
            schema: (string) $activity->schema,
            actor_id: $activity->actor_id,
            actor_label: $activity->actor_label,
            source: (string) $activity->source,
            subject_type: (string) $activity->subject_type,
            subject_reference: $activity->subject_reference,
            status: (string) $activity->status,
            severity: (string) $activity->severity,
            occurred_at: $activity->occurred_at?->toAtomString(),
            idempotency_key_hash: $activity->idempotency_key_hash,
            correlation_id: $activity->correlation_id,
            causation_id: $activity->causation_id,
            summary: $activity->summary,
            safe_context: $activity->safe_context ?? [],
            redaction_flags: $activity->redaction_flags ?? [],
            journal_handoff_status: (string) $activity->journal_handoff_status,
            action_handoff_status: (string) $activity->action_handoff_status,
            feedback_handoff_status: (string) $activity->feedback_handoff_status,
            retention_until: $activity->retention_until?->toAtomString(),
            metadata: $activity->metadata ?? [],
        );
    }
}
