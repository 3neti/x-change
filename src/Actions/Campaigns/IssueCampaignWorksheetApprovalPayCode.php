<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Campaigns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use LBHurtado\Voucher\Actions\GenerateVouchers;
use LBHurtado\Voucher\Data\VoucherInstructionsData;
use LBHurtado\Voucher\Enums\VoucherType;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XCampaign\Models\CampaignWorksheet;
use LBHurtado\XCampaign\Models\CampaignWorksheetAuthorization;
use RuntimeException;

final class IssueCampaignWorksheetApprovalPayCode
{
    public function handle(string $worksheetReference, Model $owner): CampaignWorksheetAuthorization
    {
        return DB::transaction(function () use ($worksheetReference, $owner): CampaignWorksheetAuthorization {
            $worksheet = CampaignWorksheet::query()
                ->with('rows')
                ->where('reference', $worksheetReference)
                ->where('owner_type', $owner->getMorphClass())
                ->where('owner_id', (string) $owner->getKey())
                ->lockForUpdate()
                ->first();

            if (! $worksheet instanceof CampaignWorksheet || $worksheet->status !== 'awaiting_authorization' || $worksheet->rows_hash === null) {
                throw new RuntimeException('The campaign worksheet must be frozen before an approval Pay Code can be issued.');
            }

            $authorization = $worksheet->authorizations()->where('manifest_hash', $worksheet->rows_hash)->lockForUpdate()->first();
            if ($authorization instanceof CampaignWorksheetAuthorization && $authorization->approval_pay_code !== null) {
                return $authorization;
            }

            $authorization ??= $worksheet->authorizations()->create([
                'manifest_hash' => $worksheet->rows_hash,
                'beneficiary_count' => $worksheet->rows->count(),
                'principal_minor' => $worksheet->rows->sum('amount_minor'),
                'currency' => $worksheet->currency,
            ]);

            $requiresOtp = (bool) data_get($worksheet->metadata, 'officer_authorization.require_otp', false);

            $voucher = GenerateVouchers::run(VoucherInstructionsData::from([
                'cash' => ['amount' => 0, 'currency' => $worksheet->currency, 'validation' => ['country' => 'PH']],
                'inputs' => ['fields' => $requiresOtp ? ['otp'] : []], 'feedback' => [], 'rider' => ['message' => 'Campaign officer approval'],
                'count' => 1, 'prefix' => 'APPR', 'mask' => '****', 'voucher_type' => VoucherType::SETTLEMENT->value, 'target_amount' => 0,
                'validation' => $requiresOtp ? ['otp' => ['required' => true, 'on_failure' => 'block']] : null,
                'rules' => ['min_payment' => 0, 'max_payment' => 0, 'allow_overpayment' => false, 'auto_close_on_full_payment' => false],
                'execution' => ['driver' => 'campaign_worksheet_authorization', 'mode' => 'officer_approval', 'metadata' => ['authorization_reference' => $authorization->reference, 'worksheet_reference' => $worksheet->reference, 'manifest_hash' => $worksheet->rows_hash, 'beneficiary_count' => $authorization->beneficiary_count, 'principal_minor' => $authorization->principal_minor, 'currency' => $worksheet->currency]],
                'claim' => ['outcomes' => [['key' => 'authorize_campaign']], 'selection' => 'server', 'consumption' => 'one_of', 'default_outcome' => 'authorize_campaign', 'onboarding' => ['mode' => 'never'], 'claimant' => ['mode' => 'unbound'], 'profile' => 'voucher.claim.v1'],
                'metadata' => [
                    'flow_type' => 'settlement',
                    'campaign_execution' => 'campaign_worksheet_authorization',
                    'issuer_id' => (string) $owner->getKey(),
                ],
            ]))->first();

            if (! $voucher instanceof Voucher) {
                throw new RuntimeException('The campaign approval Pay Code could not be issued.');
            }

            $authorization->forceFill(['approval_pay_code' => $voucher->code])->save();

            return $authorization->refresh();
        });
    }
}
