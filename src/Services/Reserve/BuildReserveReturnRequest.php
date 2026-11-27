<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Reserve;

use LBHurtado\EmiCore\Data\Providers\AccountTransferRequestData;
use LBHurtado\XChange\Models\ReserveTransfer;
use RuntimeException;

/** Builds a frozen DIRECT return from shared escrow to the originating dedicated account. */
final readonly class BuildReserveReturnRequest
{
    /** @return array{request: AccountTransferRequestData, source_connection: string, destination_connection: string} */
    public function build(ReserveTransfer $origin, string $terminalReason): array
    {
        if ($origin->transfer_kind !== 'issuance' || $origin->status !== 'issued'
            || ! is_int($origin->bank_account_link_id) || ! in_array($terminalReason, ['cancelled', 'expired'], true)) {
            throw new RuntimeException('The issued reserve cannot be returned.');
        }
        $original = AccountTransferRequestData::from($origin->request_ciphertext);
        $sourceAccount = (string) config('omnipay.gateways.netbank.options.sourceAccountNumber');
        $sourceCustomer = (string) config('omnipay.gateways.netbank.options.senderCustomerId');
        $sourceName = (string) config('payment-gateway.netbank.funding.corporate_account_name');
        $bankCode = (string) config('x-change.reserve_transfer.destination_bank_code');
        foreach ([$sourceAccount, $sourceCustomer, $sourceName, $bankCode] as $value) {
            if (trim($value) === '') {
                throw new RuntimeException('The platform reserve return details are incomplete.');
            }
        }
        $reference = 'rrn-'.substr(hash('sha256', $origin->reference.'|'.$terminalReason), 0, 32);
        $request = new AccountTransferRequestData(
            provider: 'netbank',
            reference: $reference,
            idempotencyKey: $reference,
            sourceAccountNumber: $sourceAccount,
            sourceCustomerId: $sourceCustomer,
            destinationAccountNumber: $original->sourceAccountNumber,
            amountMinor: $origin->amount_minor,
            currency: $origin->currency,
            sourceName: $sourceName,
            destinationName: $original->sourceName,
            destinationBankCode: $bankCode,
        );

        return [
            'request' => $request,
            'source_connection' => $origin->reserve_treasury_connection,
            'destination_connection' => $origin->source_treasury_connection,
        ];
    }
}
