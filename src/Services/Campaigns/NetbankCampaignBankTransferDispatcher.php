<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Campaigns;

use LBHurtado\EmiCore\Contracts\PayoutProvider;
use LBHurtado\EmiCore\Data\PayoutRequestData;
use LBHurtado\EmiCore\Enums\PayoutStatus;
use LBHurtado\XCampaign\Models\CampaignWorksheetFulfillment;
use LBHurtado\XChange\Contracts\CampaignBankTransferDispatcherContract;
use LBHurtado\XChange\Contracts\CampaignBankTransferDispatchResult;

final readonly class NetbankCampaignBankTransferDispatcher implements CampaignBankTransferDispatcherContract
{
    public function __construct(private PayoutProvider $payouts) {}

    public function dispatch(CampaignWorksheetFulfillment $fulfillment): CampaignBankTransferDispatchResult
    {
        if (! config('x-change.campaigns.netbank_dispatch.enabled', false)) {
            return new CampaignBankTransferDispatchResult('blocked', reason: 'NetBank campaign dispatch is disabled.');
        }

        $beneficiary = $fulfillment->row?->beneficiary_ciphertext ?? [];
        if (blank($beneficiary['bank_account'] ?? null) || blank($beneficiary['bank_code'] ?? null)) {
            return new CampaignBankTransferDispatchResult('blocked', reason: 'Beneficiary bank account and bank code are required for NetBank dispatch.');
        }

        $result = $this->payouts->disburse(new PayoutRequestData(
            reference: 'campaign:'.$fulfillment->reference,
            amount: ($fulfillment->row?->amount_minor ?? 0) / 100,
            account_number: (string) $beneficiary['bank_account'],
            bank_code: (string) $beneficiary['bank_code'],
            settlement_rail: $this->settlementRail((int) ($fulfillment->row?->amount_minor ?? 0)),
            currency: (string) ($fulfillment->row?->currency ?? 'PHP'),
            external_id: $fulfillment->reference,
            mobile: $beneficiary['mobile'] ?? null,
            metadata: ['campaign_fulfillment_reference' => $fulfillment->reference],
        ));

        return new CampaignBankTransferDispatchResult(
            $result->status === PayoutStatus::FAILED ? 'failed' : 'dispatched',
            $result->transaction_id,
            $result->status->getLabel(),
        );
    }

    private function settlementRail(int $amountMinor): string
    {
        return $amountMinor < 5_000_000 ? 'INSTAPAY' : 'PESONET';
    }
}
