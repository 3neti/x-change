<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Campaigns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use LBHurtado\Voucher\Actions\GenerateVouchers;
use LBHurtado\Voucher\Data\VoucherInstructionsData;
use LBHurtado\Voucher\Enums\VoucherType;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XCampaign\Models\CampaignWorksheetAuthorization;
use LBHurtado\XCampaign\Models\CampaignWorksheetFulfillment;
use RuntimeException;

final class IssueCampaignWorksheetPayCodes
{
    public function handle(string $authorizationReference, Model $owner, int $limit = 100): int
    {
        if ((string) auth()->id() !== (string) $owner->getKey()) {
            throw new RuntimeException('Campaign Pay Codes must be issued by the worksheet owner.');
        }

        $authorization = CampaignWorksheetAuthorization::query()
            ->with(['worksheet', 'fulfillments.row'])
            ->where('reference', $authorizationReference)
            ->first();
        if (! $authorization instanceof CampaignWorksheetAuthorization || $authorization->status !== 'authorized' || $authorization->worksheet === null) {
            throw new RuntimeException('Campaign worksheet authorization is not ready for Pay Code issuance.');
        }

        $issued = 0;
        foreach ($authorization->fulfillments->filter(fn (CampaignWorksheetFulfillment $fulfillment): bool => (
            $fulfillment->mode === 'pay_code_distribution' && $fulfillment->status === 'planned'
        ) || $fulfillment->status === 'fallback_planned')->take(max(1, min($limit, 500))) as $fulfillment) {
            DB::transaction(function () use ($fulfillment, $authorization, &$issued): void {
                $locked = CampaignWorksheetFulfillment::query()->with('row')->lockForUpdate()->findOrFail($fulfillment->getKey());
                if (! in_array($locked->status, ['planned', 'fallback_planned'], true) || $locked->pay_code !== null || $locked->row === null) {
                    return;
                }

                $beneficiary = $locked->row->beneficiary_ciphertext;
                $voucher = GenerateVouchers::run(VoucherInstructionsData::from([
                    'cash' => ['amount' => $locked->row->amount_minor / 100, 'currency' => $locked->row->currency, 'validation' => ['country' => 'PH', 'mobile' => $beneficiary['mobile'] ?? null]],
                    'inputs' => ['fields' => []], 'feedback' => [], 'rider' => ['message' => $authorization->worksheet?->name],
                    'count' => 1, 'prefix' => 'CAMP', 'mask' => '****', 'voucher_type' => VoucherType::REDEEMABLE->value,
                    'claim' => ['outcomes' => [['key' => 'provider_disbursement']], 'selection' => 'server', 'consumption' => 'one_of', 'default_outcome' => 'provider_disbursement', 'onboarding' => ['mode' => 'if_required'], 'claimant' => ['mode' => 'unbound'], 'profile' => 'voucher.claim.v1'],
                    'metadata' => ['flow_type' => 'campaign_fulfillment', 'issuer_id' => (string) auth()->id(), 'campaign' => ['authorization_reference' => $authorization->reference, 'fulfillment_reference' => $locked->reference, 'manifest_hash' => $authorization->manifest_hash]],
                ]))->first();
                if (! $voucher instanceof Voucher) {
                    throw new RuntimeException('Campaign beneficiary Pay Code could not be issued.');
                }

                $locked->forceFill(['pay_code' => $voucher->code, 'status' => 'issued'])->save();
                $issued++;
            });
        }

        return $issued;
    }
}
