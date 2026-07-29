<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Campaigns;

use LBHurtado\XCampaign\Models\CampaignWorksheetFulfillment;
use LBHurtado\XChange\Contracts\CampaignBankTransferStatusCheckerContract;

final readonly class ReconcileCampaignBankTransferBatch
{
    public function __construct(private CampaignBankTransferStatusCheckerContract $checker) {}

    /** @return array{completed: int, pending: int, failed: int, blocked: int} */
    public function handle(string $authorizationReference, int $limit = 100): array
    {
        $counts = ['completed' => 0, 'pending' => 0, 'failed' => 0, 'blocked' => 0];
        CampaignWorksheetFulfillment::query()->whereHas('authorization', fn ($query) => $query->where('reference', $authorizationReference))->where('status', 'provider_dispatched')->limit(max(1, min($limit, 500)))->get()->each(function (CampaignWorksheetFulfillment $fulfillment) use (&$counts): void {
            $result = $this->checker->check($fulfillment);
            $status = match ($result->status) {
                'completed' => 'provider_completed', 'failed' => 'provider_dispatch_failed', 'pending' => 'provider_dispatched', default => 'provider_dispatch_blocked'
            };
            $fulfillment->forceFill(['status' => $status, 'metadata' => [...($fulfillment->metadata ?? []), 'provider_reconciliation' => ['status' => $result->status, 'reason' => $result->reason, 'checked_at' => now()->toIso8601String()]]])->save();
            $counts[$result->status === 'completed' ? 'completed' : ($result->status === 'failed' ? 'failed' : ($result->status === 'pending' ? 'pending' : 'blocked'))]++;
        });

        return $counts;
    }
}
