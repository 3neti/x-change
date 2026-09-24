<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Listeners;

use LBHurtado\XChange\Events\CompletionClaimEvidenceProjected;
use LBHurtado\XChange\Jobs\Campaigns\CompleteAutomaticDemonstrationPolicyJob;
use LBHurtado\XChange\Models\CompletionClaimEvidenceProjection;
use LBHurtado\XChange\Services\Settlement\AutomaticDemonstrationPolicy;

final readonly class QueueAutomaticDemonstrationPolicy
{
    public function __construct(private AutomaticDemonstrationPolicy $policy) {}

    public function handle(CompletionClaimEvidenceProjected $event): void
    {
        if (! config('x-change.settlement.policy_completion.automatic_demo.enabled', false)) {
            return;
        }
        $projection = CompletionClaimEvidenceProjection::query()->where('reference', $event->payload['projection_reference'] ?? '')->first();
        if ($projection !== null && $this->policy->enabledFor($projection)) {
            CompleteAutomaticDemonstrationPolicyJob::dispatch($projection->reference)->afterCommit();
        }
    }
}
