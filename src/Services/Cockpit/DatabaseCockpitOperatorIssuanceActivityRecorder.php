<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Cockpit;

use LBHurtado\XChange\Contracts\CockpitOperatorIssuanceActivityRecorderContract;
use LBHurtado\XChange\Contracts\CockpitOperatorIssuanceActivityRepositoryContract;
use LBHurtado\XChange\Data\Cockpit\CockpitOperatorIssuanceActivityItemData;
use LBHurtado\XChange\Data\Cockpit\CockpitOperatorIssuanceActivityRecordData;

class DatabaseCockpitOperatorIssuanceActivityRecorder implements CockpitOperatorIssuanceActivityRecorderContract
{
    public function __construct(
        private readonly CockpitOperatorIssuanceActivityRepositoryContract $activities,
    ) {}

    public function record(CockpitOperatorIssuanceActivityItemData $activity): void
    {
        $this->activities->record(new CockpitOperatorIssuanceActivityRecordData(
            activity_id: $activity->id,
            actor_id: $activity->operator_id,
            actor_label: null,
            source: 'cockpit.quick-generate',
            subject_type: 'pay_code',
            subject_reference: $activity->code,
            status: $activity->status,
            severity: $activity->status === 'failed' ? 'warning' : 'info',
            occurred_at: $activity->issued_at,
            idempotency_key_hash: $activity->idempotency_key !== null
                ? hash('sha256', $activity->idempotency_key)
                : null,
            correlation_id: $activity->correlation_id,
            causation_id: null,
            summary: "Pay Code {$activity->code} {$activity->status}",
            safe_context: [
                'code' => $activity->code,
                'amount' => $activity->amount,
                'currency' => $activity->currency,
                'route' => $activity->route,
                'detail_href' => $activity->detail_href,
            ],
            metadata: $activity->metadata,
        ));
    }
}
