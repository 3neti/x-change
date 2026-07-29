<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Campaigns;

use Illuminate\Support\Facades\DB;
use LBHurtado\XCampaign\Models\CampaignWorksheetAuthorization;
use RuntimeException;

final class PlanCampaignWorksheetFulfillment
{
    public function handle(string $authorizationReference): int
    {
        return DB::transaction(function () use ($authorizationReference): int {
            $authorization = CampaignWorksheetAuthorization::query()->with('worksheet.rows')->where('reference', $authorizationReference)->lockForUpdate()->first();
            if (! $authorization instanceof CampaignWorksheetAuthorization || $authorization->status !== 'authorized' || $authorization->worksheet === null) {
                throw new RuntimeException('Campaign worksheet authorization is not ready for fulfillment planning.');
            }

            foreach ($authorization->worksheet->rows as $row) {
                $authorization->fulfillments()->firstOrCreate(
                    ['campaign_worksheet_row_id' => $row->getKey()],
                    ['mode' => $authorization->worksheet->fulfillment_mode, 'status' => 'planned', 'metadata' => ['manifest_hash' => $authorization->manifest_hash]],
                );
            }

            return $authorization->fulfillments()->count();
        });
    }
}
