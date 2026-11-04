<?php

declare(strict_types=1);

use LBHurtado\EmiCore\Contracts\ProviderWalletBalanceReader;
use LBHurtado\EmiCore\Data\Providers\ProviderWalletBalanceData;
use LBHurtado\XChange\Services\SyncPaynamicsWalletBalance;

it('projects a provider wallet balance through the core contract', function (): void {
    $reader = new class implements ProviderWalletBalanceReader
    {
        public function providerCode(): string
        {
            return 'paynamics_constellation';
        }

        public function readWalletBalance(string $walletId, string $fallbackCurrency): ProviderWalletBalanceData
        {
            return new ProviderWalletBalanceData(12.34, $fallbackCurrency, 'customer-1', ['wallet_balance' => '12.34'], ['customer_id' => 'customer-1']);
        }
    };
    $this->app->instance('test.wallet-balance-reader', $reader);
    $this->app->tag('test.wallet-balance-reader', 'emi.provider-wallet-balance-readers');
    $owner = actingAsTestUser();

    $result = app(SyncPaynamicsWalletBalance::class)->handle('wallet-123', $owner);

    expect($result['balance_minor'])->toBe(1234)
        ->and($result['response']['customer_id'])->toBe('customer-1')
        ->and($result['wallet']->provider_wallet_id)->toBe('wallet-123');
});

it('fails closed when no provider wallet balance reader is registered', function (): void {
    expect(fn () => app(SyncPaynamicsWalletBalance::class)->handle('wallet-123', actingAsTestUser()))
        ->toThrow(RuntimeException::class, 'unique wallet balance reader');
});
