<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Funding;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use LBHurtado\EmiCore\Data\Funding\FundingDestinationData;
use LBHurtado\XChange\Contracts\FundingDestinationResolverContract;
use LBHurtado\XChange\Exceptions\FundingDestinationUnavailable;
use LBHurtado\XChange\Models\FundingDestinationPreference;
use LBHurtado\XChange\Models\ProviderAccountLink;

class DefaultFundingDestinationResolver implements FundingDestinationResolverContract
{
    public function resolve(mixed $owner, string $provider, string $accountReference): FundingDestinationData
    {
        if (! $owner instanceof Model) {
            return $this->shared($provider, $accountReference);
        }

        $provider = $this->providerCode($provider);
        $preference = FundingDestinationPreference::query()
            ->with('providerAccountLink')
            ->whereMorphedTo('owner', $owner)
            ->where('provider_code', $provider)
            ->first();

        if ($preference === null || $preference->mode === 'shared') {
            return $this->shared($provider, $accountReference);
        }

        if ($preference->mode !== 'dedicated') {
            throw new FundingDestinationUnavailable("Funding destination mode [{$preference->mode}] is unsupported.");
        }

        $link = $preference->providerAccountLink;

        if (! $link instanceof ProviderAccountLink
            || $link->owner_type !== $owner::class
            || (string) $link->owner_id !== (string) $owner->getKey()
            || ! $link->isReady()) {
            throw new FundingDestinationUnavailable(
                'The selected dedicated funding destination is not active.',
            );
        }

        return match ($provider) {
            'netbank' => $this->dedicatedNetbank($link, $accountReference),
            'paynamics_constellation' => $this->dedicatedPaynamics($link, $accountReference),
            default => throw new FundingDestinationUnavailable(
                "Dedicated funding destination [{$provider}] is unsupported.",
            ),
        };
    }

    public function shared(string $provider, string $accountReference): FundingDestinationData
    {
        $provider = $this->providerCode($provider);

        return match ($provider) {
            'netbank' => $this->sharedNetbank($accountReference),
            'paynamics_constellation' => $this->sharedPaynamics($accountReference),
            default => throw new FundingDestinationUnavailable(
                "Shared funding destination [{$provider}] is unsupported.",
            ),
        };
    }

    private function sharedNetbank(string $accountReference): FundingDestinationData
    {
        $accountNumber = $this->requiredConfig('payment-gateway.netbank.funding.corporate_account_number');
        $accountName = $this->requiredConfig('payment-gateway.netbank.funding.corporate_account_name');
        $alias = $this->requiredConfig('payment-gateway.netbank.funding.vca_alias');
        $aliasToken = $this->requiredConfig('payment-gateway.netbank.funding.vca_alias_token');

        return new FundingDestinationData(
            provider: 'netbank',
            mode: 'shared',
            destinationType: 'bank_account',
            accountReference: $accountReference,
            displayReference: $this->maskedAccount($accountNumber)." · VCA {$alias}",
            fingerprint: $this->fingerprint('netbank', $accountNumber, $alias),
            verificationStatus: 'platform_configured',
            bankAccountNumber: $accountNumber,
            bankAccountName: $accountName,
            routingAlias: $alias,
            routingCredential: $aliasToken,
        );
    }

    private function sharedPaynamics(string $accountReference): FundingDestinationData
    {
        $walletId = $this->requiredConfig('constellation.funding.wallet_id');

        return new FundingDestinationData(
            provider: 'paynamics_constellation',
            mode: 'shared',
            destinationType: 'wallet',
            accountReference: $accountReference,
            displayReference: $this->maskedIdentifier($walletId),
            fingerprint: $this->fingerprint('paynamics_constellation', $walletId),
            verificationStatus: 'platform_configured',
            providerWalletId: $walletId,
        );
    }

    private function dedicatedNetbank(
        ProviderAccountLink $link,
        string $accountReference,
    ): FundingDestinationData {
        if (! in_array($link->verification_status, ['verified', 'credential_supplied'], true)) {
            throw new FundingDestinationUnavailable(
                'The dedicated NetBank destination has not completed provider verification.',
            );
        }

        $routing = $link->routing_profile_ciphertext;
        $accountNumber = $this->requiredRouting($routing, 'bank_account_number');
        $accountName = $this->requiredRouting($routing, 'bank_account_name');
        $alias = $this->requiredRouting($routing, 'vca_alias');
        $aliasToken = $this->requiredRouting($routing, 'vca_alias_token');

        return new FundingDestinationData(
            provider: 'netbank',
            mode: 'dedicated',
            destinationType: 'bank_account',
            accountReference: $accountReference,
            displayReference: $link->display_reference
                ?? $this->maskedAccount($accountNumber)." · VCA {$alias}",
            fingerprint: $link->routing_fingerprint
                ?? $this->fingerprint('netbank', $accountNumber, $alias),
            verificationStatus: (string) $link->verification_status,
            providerAccountId: $link->provider_account_id,
            bankAccountNumber: $accountNumber,
            bankAccountName: $accountName,
            routingAlias: $alias,
            routingCredential: $aliasToken,
        );
    }

    private function dedicatedPaynamics(
        ProviderAccountLink $link,
        string $accountReference,
    ): FundingDestinationData {
        if ($link->verification_status !== 'ownership_verified') {
            throw new FundingDestinationUnavailable(
                'Paynamics dedicated funding requires authoritative wallet ownership verification.',
            );
        }

        $walletId = trim((string) $link->provider_wallet_id);

        if ($walletId === '') {
            throw new FundingDestinationUnavailable(
                'The dedicated Paynamics destination has no wallet ID.',
            );
        }

        return new FundingDestinationData(
            provider: 'paynamics_constellation',
            mode: 'dedicated',
            destinationType: 'wallet',
            accountReference: $accountReference,
            displayReference: $link->display_reference ?? $this->maskedIdentifier($walletId),
            fingerprint: $link->routing_fingerprint
                ?? $this->fingerprint('paynamics_constellation', $walletId),
            verificationStatus: 'ownership_verified',
            providerAccountId: $link->provider_account_id,
            providerWalletId: $walletId,
        );
    }

    private function providerCode(string $provider): string
    {
        return match (strtolower(trim($provider))) {
            'paynamics' => 'paynamics_constellation',
            default => strtolower(trim($provider)),
        };
    }

    /**
     * @param  array<string, mixed>|null  $routing
     */
    private function requiredRouting(?array $routing, string $key): string
    {
        $value = data_get($routing, $key);

        if (! is_string($value) || trim($value) === '') {
            throw new FundingDestinationUnavailable(
                "Dedicated funding routing [{$key}] is unavailable.",
            );
        }

        return trim($value);
    }

    private function requiredConfig(string $key): string
    {
        $value = config($key);

        if (! is_string($value) || trim($value) === '') {
            throw new FundingDestinationUnavailable(
                "Shared funding destination configuration [{$key}] is unavailable.",
            );
        }

        return trim($value);
    }

    private function fingerprint(string ...$parts): string
    {
        return hash('sha256', implode('|', $parts));
    }

    private function maskedAccount(string $accountNumber): string
    {
        return '•••• '.Str::substr($accountNumber, -4);
    }

    private function maskedIdentifier(string $identifier): string
    {
        return '•••• '.Str::substr($identifier, -6);
    }
}
