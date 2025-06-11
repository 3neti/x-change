<?php

namespace LBHurtado\PaymentGateway\Gateways\Netbank\Traits;

use LBHurtado\PaymentGateway\Data\Netbank\Generate\GeneratePayloadData;
use LBHurtado\PaymentGateway\Contracts\MerchantInterface;
use Illuminate\Support\Facades\Http;
use Brick\Money\Money;

trait CanGenerate
{
    public function generate(string $account, Money $amount): string
    {
        $user = auth()->user();

        if (!$user instanceof MerchantInterface) {
            throw new \LogicException('Authenticated user must implement MerchantInterface to use this functionality.');
        }

        // Build a unique cache key
        $amountKey = (string) $amount;
        $currency  = $amount->getCurrency()->getCurrencyCode();
        $userKey   = $user->getKey();
        $cacheKey  = "qr:merchant:{$userKey}:{$account}:{$currency}_{$amountKey}";

        return cache()->remember($cacheKey, now()->addMinutes(30), function () use ($user, $account, $amount) {
            // If we missed the cache, generate a new QR
            $token        = $this->getAccessToken();
            $payload_data = GeneratePayloadData::fromUserAccountAmount($user, $account, $amount);
            $payload      = $payload_data->toArray();

            logger()->info('Generating new QR code for deposit', [
                'merchant' => $user->getKey(),
                'account'  => $account,
                'amount'   => $amount->getAmount()->toFloat(),
            ]);

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $token,
                'Content-Type'  => 'application/json',
            ])->post(config('disbursement.server.qr-end-point'), $payload);

            return 'data:image/png;base64,' . $response->json('qr_code');
        });
    }
}
