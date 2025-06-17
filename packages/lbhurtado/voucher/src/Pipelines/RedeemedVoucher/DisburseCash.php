<?php

namespace LBHurtado\Voucher\Pipelines\RedeemedVoucher;

use LBHurtado\PaymentGateway\Data\Netbank\Disburse\DisburseResponseData;
use LBHurtado\PaymentGateway\Data\Netbank\Disburse\DisburseInputData;
use LBHurtado\PaymentGateway\Contracts\PaymentGatewayInterface;
use LBHurtado\Wallet\Services\SystemUserResolverService;
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
     * @param  mixed    $voucher  The voucher being processed
     * @param  \Closure $next     Next stage in the pipeline
     * @return mixed              Either the result of $next or early return on failure
     */
    public function handle($voucher, Closure $next)
    {
        Log::debug('[DisburseCash] Starting disbursement for voucher', ['code' => $voucher->code]);

        //TODO: change this to $cash, make it a wallet
        $sender = $voucher->owner;

        $redeemerRelation = $voucher->redeemers->first();
        $contact = $redeemerRelation?->redeemer;
        try {
            $bankAccount = BankAccount::fromBankAccount(Arr::get($redeemerRelation->metadata, 'bank_account', ':'));

        } catch (\Exception $exception) {
            $bankAccount = BankAccount::fromBankAccount($contact->bank_account);
        }

        if (! $contact instanceof Contact) {
            Log::warning('[DisburseCash] No redeemer contact found for voucher', ['code' => $voucher->code]);
            return $next($voucher);
        }

        $cashEntity = $voucher->getEntities(Cash::class)->first();
        if (! $cashEntity) {
            Log::warning('[DisburseCash] No cash entity attached to voucher', ['code' => $voucher->code]);
            return $next($voucher);
        }

        $input = DisburseInputData::from([
            'reference'      => "{$voucher->code}-{$contact->mobile}",
            'amount'         => $cashEntity->amount->getAmount()->toFloat(),
            'account_number' => $bankAccount->getAccountNumber(), //'000661592316',
            'bank'           => $bankAccount->getBankCode(),// 'GXCHPHM2XXX', //'BNORPHMMXXX'
            'via'            => 'INSTAPAY',
        ]);

        Log::debug('[DisburseCash] DisburseInputData prepared', ['input' => $input->toArray()]);

        $response = $this->gateway->disburse($sender, $input);

        // Handle boolean-false failure
        if ($response === false) {
            Log::error('[DisburseCash] Disbursement failed (returned false)', [
                'voucher' => $voucher->code,
                'redeemer'=> $contact->mobile,
                'amount'  => $input->amount,
            ]);
            // Stop the pipeline here. You can either:
            //  - return null or the voucher itself,
            //  - throw an exception to fully abort,
            //  - or push on a “failed” flag in the voucher metadata.
            return null;
        }

        // Handle DisburseResponseData
        if ($response instanceof DisburseResponseData) {
            Log::info('[DisburseCash] Disbursement succeeded', [
                'voucher'       => $voucher->code,
                'uuid'          => $response->uuid,
                'transactionId' => $response->transaction_id,
                'status'        => $response->status,
            ]);


        } else {
            // Defensive catch‐all
            Log::warning('[DisburseCash] Unexpected disburse() return type', [
                'type'    => gettype($response),
                'content' => $response,
            ]);

            return null;
        }

        // And only now continue to the next pipeline stage…
        return $next($voucher);
    }
}
