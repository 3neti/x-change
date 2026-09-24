<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Listeners;

use LBHurtado\XChange\Events\PolicyCompletionOutcomeRecorded;
use LBHurtado\XChange\Jobs\Campaigns\SendDemonstrationPolicySummaryJob;
use LBHurtado\XChange\Models\PolicyCompletionOutcome;
use LBHurtado\XChange\Services\Settlement\DemonstrationPolicySummary;

final readonly class QueueDemonstrationPolicySummary
{
    public function __construct(private DemonstrationPolicySummary $summaries) {}

    public function handle(PolicyCompletionOutcomeRecorded $event): void
    {
        if (! config('x-change.settlement.policy_completion.demonstration_summary.sms_enabled', false)) {
            return;
        }
        $outcome = PolicyCompletionOutcome::query()->where('reference', $event->payload['outcome_reference'] ?? '')->first();
        if ($outcome !== null && $this->summaries->url($outcome) !== null) {
            SendDemonstrationPolicySummaryJob::dispatch($outcome->reference)->afterCommit();
        }
    }
}
