<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Campaigns;

use Illuminate\Support\Facades\DB;
use LBHurtado\XCampaign\Models\CampaignWorksheetAuthorization;
use LBHurtado\XCampaign\Models\CampaignWorksheetFulfillment;
use LBHurtado\XChange\Contracts\CampaignBankTransferDispatcherContract;
use RuntimeException;

final readonly class DispatchCampaignBankTransferBatch
{
    public function __construct(private CampaignBankTransferDispatcherContract $dispatcher) {}

    /** @return array{dispatched: int, blocked: int, failed: int} */
    public function handle(string $authorizationReference, int $limit = 100): array
    {
        $authorization = CampaignWorksheetAuthorization::query()->where('reference', $authorizationReference)->where('status', 'authorized')->first();
        if (! $authorization instanceof CampaignWorksheetAuthorization) {
            throw new RuntimeException('Campaign worksheet authorization is not ready for bank-transfer dispatch.');
        }

        $result = ['dispatched' => 0, 'blocked' => 0, 'failed' => 0];
        CampaignWorksheetFulfillment::query()
            ->where('campaign_worksheet_authorization_id', $authorization->getKey())
            ->where('status', 'awaiting_provider_dispatch')
            ->orderBy('id')
            ->limit(max(1, min($limit, 500)))
            ->get()
            ->each(function (CampaignWorksheetFulfillment $fulfillment) use (&$result): void {
                DB::transaction(function () use ($fulfillment, &$result): void {
                    $locked = CampaignWorksheetFulfillment::query()->with('row')->lockForUpdate()->findOrFail($fulfillment->getKey());
                    if ($locked->status !== 'awaiting_provider_dispatch') {
                        return;
                    }

                    $dispatch = $this->dispatcher->dispatch($locked);
                    $status = match ($dispatch->status) {
                        'dispatched' => 'provider_dispatched',
                        'blocked' => 'provider_dispatch_blocked',
                        default => 'provider_dispatch_failed',
                    };
                    $locked->forceFill([
                        'status' => $status,
                        'provider_transfer_reference' => $dispatch->providerTransferReference,
                        'metadata' => [...($locked->metadata ?? []), 'provider_dispatch' => ['status' => $dispatch->status, 'reason' => $dispatch->reason, 'attempted_at' => now()->toIso8601String()]],
                    ])->save();
                    $result[$dispatch->status === 'dispatched' ? 'dispatched' : ($dispatch->status === 'blocked' ? 'blocked' : 'failed')]++;
                });
            });

        return $result;
    }
}
