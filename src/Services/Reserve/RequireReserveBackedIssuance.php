<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Reserve;

use LBHurtado\XChange\Data\FundingDecisionData;
use LBHurtado\XChange\Exceptions\PayCodeIssuanceFailed;

final class RequireReserveBackedIssuance
{
    /** @param array<string, mixed> $input */
    public function assertOrdinaryIssuanceAllowed(array $input, FundingDecisionData $funding): void
    {
        if (data_get($funding->meta, 'assigned_bank_account_link_id') !== null
            && ! in_array(data_get($input, 'voucher_type'), ['payable', 'settlement'], true)) {
            throw new PayCodeIssuanceFailed('Issuance from this bank account requires the reserve-backed issuance flow.');
        }
    }
}
