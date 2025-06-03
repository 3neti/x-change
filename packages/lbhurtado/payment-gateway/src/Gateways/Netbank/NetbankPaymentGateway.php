<?php

namespace LBHurtado\PaymentGateway\Gateways\Netbank;

use LBHurtado\PaymentGateway\Gateways\Netbank\Traits\{CanConfirmDeposit, CanDisburse, CanGenerate};
use LBHurtado\PaymentGateway\Contracts\PaymentGatewayInterface;
use Illuminate\Support\Facades\Http;

class NetbankPaymentGateway implements PaymentGatewayInterface
{
    use CanConfirmDeposit;
    use CanDisburse;
    use CanGenerate;

    protected function getAccessToken(): string
    {
        $credentials = base64_encode(
            config('disbursement.client.id') . ':' . config('disbursement.client.secret')
        );

        $response = Http::withHeaders([
            'Authorization' => 'Basic ' . $credentials,
        ])->asForm()->post(config('disbursement.server.token-end-point'), [
            'grant_type' => 'client_credentials',
        ]);

        return $response->json('access_token');
    }
}
