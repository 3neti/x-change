<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Console\Concerns;

trait ResolvesIssuerAndWalletIdentifiers
{
    /**
     * @return array<string, mixed>
     */
    protected function issuerLookupPayload(): array
    {
        $payload = [];

        $issuer = $this->argument('issuer') ?? $this->option('issuer');

        if (is_string($issuer) && trim($issuer) !== '') {
            $issuer = trim($issuer);

            $payload['issuer'] = $issuer;
            $payload['issuer_id'] = $issuer;
            $payload['id'] = $issuer;
            $payload['external_id'] = $issuer;
        }

        foreach (['external-id' => 'external_id', 'email' => 'email', 'mobile' => 'mobile'] as $option => $field) {
            $value = $this->option($option);

            if (is_string($value) && trim($value) !== '') {
                $payload[$field] = trim($value);
            }
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    protected function walletLookupPayload(): array
    {
        $payload = [];

        $wallet = $this->argument('wallet') ?? $this->option('wallet');

        if (is_string($wallet) && trim($wallet) !== '') {
            $wallet = trim($wallet);

            $payload['wallet_id'] = $wallet;
            $payload['id'] = $wallet;
            $payload['wallet']['id'] = $wallet;
        }

        return $payload;
    }
}
