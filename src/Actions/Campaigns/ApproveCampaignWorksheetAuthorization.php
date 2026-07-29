<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Campaigns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use LBHurtado\XCampaign\Models\CampaignWorksheetAuthorization;
use RuntimeException;

final class ApproveCampaignWorksheetAuthorization
{
    public function handle(string $approvalPayCode, Model $officer): CampaignWorksheetAuthorization
    {
        return DB::transaction(function () use ($approvalPayCode, $officer): CampaignWorksheetAuthorization {
            $authorization = CampaignWorksheetAuthorization::query()
                ->with('worksheet')
                ->where('approval_pay_code', trim($approvalPayCode))
                ->lockForUpdate()
                ->first();

            if (! $authorization instanceof CampaignWorksheetAuthorization || $authorization->worksheet === null) {
                throw new RuntimeException('Campaign approval Pay Code was not found.');
            }

            if (
                $authorization->worksheet->owner_type === $officer->getMorphClass()
                && (string) $authorization->worksheet->owner_id === (string) $officer->getKey()
            ) {
                throw new RuntimeException('The worksheet issuer cannot authorize their own campaign.');
            }

            if ($authorization->status === 'authorized') {
                return $authorization;
            }

            if (
                $authorization->status !== 'awaiting_officer'
                || $authorization->worksheet->status !== 'awaiting_authorization'
                || ! hash_equals((string) $authorization->manifest_hash, (string) $authorization->worksheet->rows_hash)
            ) {
                throw new RuntimeException('Campaign approval is no longer valid for this worksheet manifest.');
            }

            $authorization->forceFill([
                'status' => 'authorized',
                'approved_by_type' => $officer->getMorphClass(),
                'approved_by_id' => (string) $officer->getKey(),
                'approved_at' => now(),
            ])->save();
            $authorization->worksheet->forceFill(['status' => 'authorized'])->save();

            return $authorization->refresh();
        });
    }
}
