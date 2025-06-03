<?php

namespace LBHurtado\PaymentGateway\Gateways\Netbank\Traits;

use LBHurtado\PaymentGateway\Data\Netbank\DepositResponseData;
use LBHurtado\PaymentGateway\Actions\TopupWalletAction;
use LBHurtado\PaymentGateway\Events\DepositConfirmed;
use Bavix\Wallet\Interfaces\Wallet;
use Illuminate\Support\Facades\Log;

trait CanConfirmDeposit
{
    //TODO: use DepositResponseData instead of array
    public function confirmDeposit(array $payload): bool
    {
        $response = DepositResponseData::from($payload);
        Log::info('Processing Netbank deposit confirmation', $response->toArray());

        $user = app(config('payment-gateway.models.user'))::findByMobile($response->referenceCode);

        if (!$user && ($merchant_code = $response->merchant_details->merchant_code ?? null) && strlen($merchant_code) === 1) {
            $user = app(config('payment-gateway.models.user'))
                ->where('meta->merchant->code', $merchant_code)->first();
//            $user = User::where('meta->merchant->code', $merchant_code)->firstOrFail();
        }

        if (!$user) {
            Log::warning('No user found for reference code or merchant code.');
            return false;
        }

        $this->transferToWallet($user, $response);

        return true;
    }

    protected function transferToWallet(Wallet $user, DepositResponseData $response): void
    {
        $amountFloat = $response->amount;

        $transfer = TopupWalletAction::run($user, $amountFloat);
        $transfer->deposit->meta = $response->toArray();
        $transfer->deposit->save();

        DepositConfirmed::dispatch($transfer->deposit);
    }
}
