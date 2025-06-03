<?php

namespace LBHurtado\MoneyIssuer\Services;

use LBHurtado\MoneyIssuer\Data\{BalanceData, TransferData};
use Illuminate\Support\Facades\Http;

abstract class MoneyIssuerService implements MoneyIssuerServiceInterface
{
    public function __construct(
        protected string $baseUrl,
        protected string $apiKey
    ) {}

    public function checkBalance(string $account): BalanceData
    {
        $response = Http::withToken($this->apiKey)->get("$this->baseUrl/accounts/$account/balance");

        $data = $response->json();

        return new BalanceData(
            account: $account,
            balance: $data['balance'],
            currency: $data['currency']
        );
    }

    public function deposit(string $account, float $amount, string $currency = 'PHP', array $meta = []): bool
    {
        $response = Http::withToken($this->apiKey)->post("$this->baseUrl/accounts/$account/deposit", [
            'amount' => $amount,
            'currency' => $currency,
            'meta' => $meta,
        ]);

        return $response->successful();
    }

    public function withdraw(string $account, float $amount, string $currency = 'PHP', array $meta = []): bool
    {
        $response = Http::withToken($this->apiKey)->post("$this->baseUrl/accounts/$account/withdraw", [
            'amount' => $amount,
            'currency' => $currency,
            'meta' => $meta,
        ]);

        return $response->successful();
    }

    public function transfer(string $from, string $to, float $amount, string $currency = 'PHP', array $meta = []): TransferData
    {
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
