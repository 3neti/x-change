<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Lifecycle\Runners\Support;

use LBHurtado\XChange\Contracts\PayoutDestinationValidatorContract;
use LBHurtado\XChange\Data\Disbursement\PayoutDestinationValidationData;

final class CommercialSimulationDestinationValidator implements PayoutDestinationValidatorContract
{
    public function validate(
        string $bankCode,
        string $accountNumber,
        string $settlementRail,
        ?string $mobile = null,
    ): PayoutDestinationValidationData {
        return new PayoutDestinationValidationData(
            status: 'format_valid_provider_unverified',
            bankCode: mb_strtoupper(trim($bankCode)),
            accountNumber: preg_replace('/\D+/', '', $accountNumber) ?? '',
            mobile: $mobile,
            message: 'Simulation-only format validation passed.',
            providerVerified: false,
            checks: [
                'account_format' => 'valid',
                'external_provider_call' => false,
            ],
        );
    }
}
