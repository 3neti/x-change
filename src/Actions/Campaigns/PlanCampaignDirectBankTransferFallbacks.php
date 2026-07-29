<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Campaigns;

use Illuminate\Support\Facades\DB;
use LBHurtado\XCampaign\Models\CampaignWorksheetAuthorization;
use LBHurtado\XCampaign\Models\CampaignWorksheetFulfillment;
use RuntimeException;

final class PlanCampaignDirectBankTransferFallbacks
{
    /** @return array{eligible: int, fallback: int} */
    public function handle(string $authorizationReference, int $limit = 500): array
    {
        return DB::transaction(function () use ($authorizationReference, $limit): array {
            $authorization = CampaignWorksheetAuthorization::query()->where('reference', $authorizationReference)->where('status', 'authorized')->first();
            if (! $authorization instanceof CampaignWorksheetAuthorization) {
                throw new RuntimeException('Campaign worksheet authorization is not ready for direct-transfer planning.');
            }

            $eligible = 0;
            $fallback = 0;
            CampaignWorksheetFulfillment::query()
                ->with('row')
                ->where('campaign_worksheet_authorization_id', $authorization->getKey())
                ->where('mode', 'direct_bank_transfer')
                ->where('status', 'planned')
                ->orderBy('id')
                ->limit(max(1, min($limit, 1000)))
                ->get()
                ->each(function (CampaignWorksheetFulfillment $fulfillment) use (&$eligible, &$fallback): void {
                    $destination = $fulfillment->row?->beneficiary_ciphertext['bank_account'] ?? null;
                    if (is_string($destination) && trim($destination) !== '') {
                        $fulfillment->forceFill(['status' => 'awaiting_provider_dispatch', 'metadata' => [...($fulfillment->metadata ?? []), 'disposition' => 'provider_transfer', 'provider_calls' => false]])->save();
                        $eligible++;

                        return;
                    }

                    $fulfillment->forceFill(['status' => 'fallback_planned', 'metadata' => [...($fulfillment->metadata ?? []), 'disposition' => 'pay_code_fallback', 'provider_calls' => false]])->save();
                    $fallback++;
                });

            return ['eligible' => $eligible, 'fallback' => $fallback];
        });
    }
}
