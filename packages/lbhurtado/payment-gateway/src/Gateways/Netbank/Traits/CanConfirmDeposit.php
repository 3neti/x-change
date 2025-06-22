<?php

namespace LBHurtado\PaymentGateway\Gateways\Netbank\Traits;

use LBHurtado\PaymentGateway\Data\Netbank\Deposit\Helpers\RecipientAccountNumberData;
use LBHurtado\PaymentGateway\Data\Netbank\Deposit\DepositResponseData;
use LBHurtado\PaymentGateway\Services\ReferenceLookup;
use LBHurtado\PaymentGateway\Services\ResolvePayable;
use LBHurtado\PaymentGateway\Tests\Models\User;
use LBHurtado\Wallet\Actions\TopupWalletAction;
use LBHurtado\Wallet\Events\DepositConfirmed;
use Bavix\Wallet\Interfaces\Wallet;
use Illuminate\Support\Facades\Log;

trait CanConfirmDeposit
{

    public function confirmDeposit(array $payload): bool
    {
        $response = DepositResponseData::from($payload);
        Log::info('Processing Netbank deposit confirmation', $response->toArray());

        $dto = RecipientAccountNumberData::fromRecipientAccountNumber(
            $response->recipientAccountNumber
        );

        try {
            $wallet = app(ResolvePayable::class)->execute($dto);
        } catch (\Throwable $e) {
            Log::error('Could not resolve recipient to a wallet', [
                'error' => $e->getMessage(),
                'payload' => $response->toArray(),
            ]);
            return false;
        }

        if (! $wallet instanceof Wallet) {
            Log::warning('No wallet found for reference or mobile', [
                'referenceCode' => $dto->referenceCode,
                'alias'         => $dto->alias,
            ]);
            return false;
        }

        $this->transferToWallet($wallet, $response);

        return true;
    }
//
//    public function confirmDeposit(array $payload): bool
//    {
//        $response = DepositResponseData::from($payload);
//        Log::info('Processing Netbank deposit confirmation', $response->toArray());
//
//
//        $recipientAccountNumberData = RecipientAccountNumberData::fromRecipientAccountNumber($response->recipientAccountNumber);
//        $user = app(ResolvePayable::class)->execute($recipientAccountNumberData);
//
////        $user = app(config('payment-gateway.models.user'))::findByMobile($recipientAccountNumberData->referenceCode);
////        $user = app(config('payment-gateway.models.user'))::findByMobile($response->merchant_details->merchant_account);
//
//        if (!$user) {
//            Log::warning('No user wallet found for mobile or voucher code.');
//            return false;
//        }
//
//        $this->transferToWallet($user, $response);
//
//        return true;
//    }

    protected function transferToWallet(Wallet $user, DepositResponseData $deposit): void
    {
        [ $logMessage, $transaction ] = match (true) {
            $user instanceof \LBHurtado\Cash\Models\Cash => [
                'Treating deposit as loan repayment back into cash wallet',
                $user->depositFloat($deposit->amount),
            ],
            default => [
                'Treating deposit as user top-up',
                TopupWalletAction::run($user, $deposit->amount)->deposit,
            ]
        };
        Log::info($logMessage);

        $transaction->meta = $deposit->toArray();
        $transaction->save();

        DepositConfirmed::dispatch($transaction);
    }
}
