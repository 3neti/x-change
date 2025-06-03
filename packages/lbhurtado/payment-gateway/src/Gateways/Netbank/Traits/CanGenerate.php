<?php

namespace LBHurtado\PaymentGateway\Gateways\Netbank\Traits;

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

        $payload = [
            'merchant_name' => $user->merchant->name,
            'merchant_city' => $user->merchant->city,
            'qr_type' => $amount->isZero() ? 'Static' : 'Dynamic',
            'qr_transaction_type' => 'P2M',
            'destination_account' => $this->formatDestinationAccount($account, $user->merchant->code),
            'resolution' => 480,
            'amount' => [
                'cur' => 'PHP',
                'num' => $amount->isZero() ? '' : (string) $amount->getMinorAmount()->toInt(),
            ],
        ];

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'Content-Type' => 'application/json',
        ])->post(config('disbursement.server.qr-end-point'), $payload);

        return 'data:image/png;base64,' . $response->json('qr_code');
    }

    protected function formatDestinationAccount(string $account, ?string $merchantCode): string
    {
        return __(':alias:account', [
            'alias' => config('disbursement.client.alias'),
            'account' => $merchantCode ? $merchantCode[0] . substr($account, 1) : $account,
        ]);
    }
}
