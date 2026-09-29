<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Funding;

use InvalidArgumentException;
use LBHurtado\EmiCore\Data\Funding\FundingDestinationData;
use LBHurtado\EmiCore\Data\Funding\FundingInstructionRequestData;
use LBHurtado\EmiCore\Data\Funding\FundingInstructionsData;

final class StandingBankTransferFundingInstructionIssuer
{
    public function create(FundingInstructionRequestData $request): FundingInstructionsData
    {
        $destination = $request->destination;

        if ($request->provider !== 'netbank'
            || ! $destination instanceof FundingDestinationData
            || $destination->provider !== 'netbank'
            || $destination->destinationType !== 'bank_account') {
            throw new InvalidArgumentException(
                'Standing bank-transfer instructions require a NetBank bank-account destination.',
            );
        }

        $accountNumber = trim((string) $destination->bankAccountNumber);
        $accountName = trim((string) $destination->bankAccountName);

        if ($accountNumber === '' || $accountName === '') {
            throw new InvalidArgumentException(
                'Standing bank-transfer instructions require an account number and account name.',
            );
        }

        return new FundingInstructionsData(
            provider: 'netbank',
            providerReference: 'bank-transfer:'.$request->fundingReference,
            amountMinor: $request->amountMinor,
            currency: mb_strtoupper($request->currency),
            expiresAt: $request->expiresAt,
            fundingAddress: $accountNumber,
            displayData: [
                'institution' => 'NetBank',
                'account_name' => $accountName,
                'destination_account' => $accountNumber,
                'amount_minor' => $request->amountMinor,
                'currency' => mb_strtoupper($request->currency),
                'payment_reference' => $request->fundingReference,
                'one_time' => true,
                'delivery' => 'bank-transfer',
            ],
        );
    }
}
