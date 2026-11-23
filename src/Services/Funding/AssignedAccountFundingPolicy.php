<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Funding;

use Illuminate\Database\Eloquent\Model;
use LBHurtado\XChange\Contracts\FundingAccountCreditContract;
use LBHurtado\XChange\Enums\FundingIntentPurpose;
use LBHurtado\XChange\Exceptions\FundingSettlementDenied;
use LBHurtado\XChange\Models\FundingIntent;
use LBHurtado\XChange\Services\Banking\AssignedFundingTreasuryConnection;

final readonly class AssignedAccountFundingPolicy
{
    public function __construct(
        private FundingAccountCreditContract $accounts,
        private AssignedFundingTreasuryConnection $connections,
    ) {}

    public function creditsOwner(FundingIntent $intent): bool
    {
        $snapshot = $intent->destination_snapshot_ciphertext;
        if ($intent->purpose !== FundingIntentPurpose::AccountFunding || $intent->provider_code !== 'netbank'
            || $intent->currency !== 'PHP' || ! is_array($snapshot) || data_get($snapshot, 'mode') !== 'dedicated') {
            return false;
        }

        try {
            $owner = data_get($this->accounts->resolve($intent->account_reference), 'holder');
            if (! $owner instanceof Model) {
                return false;
            }
            $this->connections->resolve($owner, $intent->provider_code, $intent->currency, $snapshot);

            return true;
        } catch (FundingSettlementDenied) {
            return false;
        }
    }
}
