<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Campaigns;

use Illuminate\Support\Facades\DB;
use LBHurtado\XCampaign\Models\CampaignWorksheetAuthorization;
use LBHurtado\XCampaign\Models\CampaignWorksheetFulfillment;
use RuntimeException;

final class PlanCampaignPayCodeFallbacks
{
    public function handle(string $authorizationReference, int $limit = 500): int
    {
        return DB::transaction(function () use ($authorizationReference, $limit): int {
            $authorization = CampaignWorksheetAuthorization::query()->where('reference', $authorizationReference)->where('status', 'authorized')->first();
            if (! $authorization instanceof CampaignWorksheetAuthorization) {
                throw new RuntimeException('Campaign worksheet authorization is not ready for Pay Code fallback planning.');
            }

            return CampaignWorksheetFulfillment::query()
                ->where('campaign_worksheet_authorization_id', $authorization->getKey())
                ->whereIn('status', ['provider_dispatch_blocked', 'provider_dispatch_failed'])
                ->limit(max(1, min($limit, 1000)))
                ->update(['status' => 'fallback_planned', 'updated_at' => now()]);
        });
    }
}
