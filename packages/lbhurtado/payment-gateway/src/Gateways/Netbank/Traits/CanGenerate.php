<?php

namespace LBHurtado\PaymentGateway\Gateways\Netbank\Traits;

use LBHurtado\PaymentGateway\Data\Netbank\Generate\GeneratePayloadData;
use LBHurtado\PaymentGateway\Contracts\HasMerchantInterface;
use Illuminate\Support\Facades\Http;
use Brick\Money\Money;

trait CanGenerate
{
    public function generate(string $account, Money $amount): string
    {
        $user = auth()->user();

        if (!$user instanceof HasMerchantInterface) {
            throw new \LogicException('Authenticated user must implement HasMerchantInterface to use this functionality.');
        }

        $token = $this->getAccessToken();
        $payload_data = GeneratePayloadData::fromUserAccountAmount($user, $account, $amount);
        $payload = $payload_data->toArray();

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'Content-Type'  => 'application/json',
        ])->post(config('disbursement.server.qr-end-point'), $payload);

        return 'data:image/png;base64,' . $response->json('qr_code');
    }
}
