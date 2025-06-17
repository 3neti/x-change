<?php

namespace LBHurtado\Voucher\Pipelines\RedeemedVoucher;

use LBHurtado\PaymentGateway\Contracts\PaymentGatewayInterface;
use LBHurtado\PaymentGateway\Data\Netbank\Disburse\{
    DisburseResponseData,
    DisburseInputData
};
use LBHurtado\Contact\Classes\BankAccount;
use LBHurtado\Contact\Models\Contact;
use Illuminate\Support\Facades\Log;
use LBHurtado\Cash\Models\Cash;
use Illuminate\Support\Arr;
use Closure;

class DisburseCash
{
    public function __construct(protected PaymentGatewayInterface $gateway) {}

    /**
     * Attempts to disburse the Cash entity attached to the voucher.
     *
     * @param  mixed    $voucher
     * @param  \Closure $next
     * @return mixed
     */
    public function handle($voucher, Closure $next)
    {
        $code = $voucher->code;
        Log::debug('[DisburseCash] Starting', ['voucher' => $code]);

        // 1) Find the Cash entity
        $cash = $voucher->getEntities(Cash::class)->first();
        if (! $cash) {
            Log::warning('[DisburseCash] No Cash entity',     ['voucher' => $code]);
            return $next($voucher);
        }

        // 2) Resolve the redeemer Contact
        $relation = $voucher->redeemers->first();
        $contact  = $relation?->redeemer;
        if (! $contact instanceof Contact) {
            Log::warning('[DisburseCash] No redeemer Contact', ['voucher' => $code]);
            return $next($voucher);
        }

        // 3) Parse bank account (fallback to Contact.bank_account)
        $rawBank = Arr::get($relation->metadata, 'bank_account', $contact->bank_account);
        try {
            $bankAccount = BankAccount::fromBankAccount($rawBank);
        } catch (\Throwable $e) {
            Log::warning('[DisburseCash] Invalid bank_account format, using contact.bank_account', [
                'voucher'      => $code,
                'providedRaw'  => $rawBank,
                'fallbackRaw'  => $contact->bank_account,
            ]);
            $bankAccount = BankAccount::fromBankAccount($contact->bank_account);
        }

        // 4) Build the DisburseInputData
        $input = DisburseInputData::from([
            'reference'      => "{$code}-{$contact->mobile}",
            'amount'         => $cash->amount->getAmount()->toFloat(),
            'account_number' => $bankAccount->getAccountNumber(),
            'bank'           => $bankAccount->getBankCode(),
            'via'            => 'INSTAPAY',
        ]);

        Log::debug('[DisburseCash] Payload ready', ['input' => $input->toArray()]);

        // 5) Call the gateway
        $response = $this->gateway->disburse($cash, $input);

        // 6) Handle failure or unexpected return
        if ($response === false) {
            Log::error('[DisburseCash] Gateway returned false', [
                'voucher' => $code,
                'redeemer'=> $contact->mobile,
                'amount'  => $input->amount,
            ]);
            return null;
        }

        if (! $response instanceof DisburseResponseData) {
            Log::warning('[DisburseCash] Unexpected response type', [
                'voucher' => $code,
                'type'    => gettype($response),
            ]);
            return null;
        }

        // 7) Success
        Log::info('[DisburseCash] Success', [
            'voucher'       => $code,
            'transactionId' => $response->transaction_id,
            'uuid'          => $response->uuid,
            'status'        => $response->status,
        ]);

        return $next($voucher);
    }
}
