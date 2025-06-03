<?php

namespace LBHurtado\MoneyIssuer\Services;

use LBHurtado\MoneyIssuer\Data\TransferData;
use Illuminate\Support\Facades\Http;

class ICashMoneyIssuerService extends MoneyIssuerService implements MoneyIssuerServiceInterface
{
    public function transfer(string $from, string $to, float $amount, string $currency = 'PHP', array $meta = []): TransferData
    {
        // ICash might use a different endpoint or payload structure
        $response = Http::withToken($this->apiKey)->post("$this->baseUrl/transfer/fund", [
            'source' => $from,
            'destination' => $to,
            'value' => $amount,
            'currency' => $currency,
            'extra' => $meta,
        ]);

        $data = $response->json();

        return new TransferData(
            from_account: $from,
            to_account: $to,
            amount: $amount,
            currency: $currency
        );
    }
}
