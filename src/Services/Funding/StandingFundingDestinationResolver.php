<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Funding;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use LBHurtado\EmiCore\Data\Funding\FundingDestinationData;
use LBHurtado\XChange\Contracts\FundingDestinationResolverContract;
use LBHurtado\XChange\Exceptions\FundingDestinationUnavailable;
use LBHurtado\XChange\Models\FundingDestinationPreference;

final class StandingFundingDestinationResolver
{
    public function __construct(
        private readonly FundingDestinationResolverContract $destinations,
    ) {}

    public function resolve(Model $owner, string $accountReference): FundingDestinationData
    {
        $preference = FundingDestinationPreference::query()
            ->whereMorphedTo('owner', $owner)
            ->where('provider_code', 'netbank')
            ->first();

        if ($preference?->mode === 'dedicated') {
            return $this->destinations->resolve($owner, 'netbank', $accountReference);
        }

        if ($preference !== null && $preference->mode !== 'shared') {
            throw new FundingDestinationUnavailable(
                "Funding destination mode [{$preference->mode}] is unsupported.",
            );
        }

        $accountNumber = $this->requiredConfig(
            'payment-gateway.netbank.funding.corporate_account_number',
        );
        $accountName = $this->requiredConfig(
            'payment-gateway.netbank.funding.corporate_account_name',
        );
        $alias = $this->requiredConfig('payment-gateway.netbank.funding.vca_alias');

        return new FundingDestinationData(
            provider: 'netbank',
            mode: 'shared',
            destinationType: 'bank_account',
            accountReference: $accountReference,
            displayReference: '•••• '.Str::substr($accountNumber, -4)." · VCA {$alias}",
            fingerprint: hash('sha256', implode('|', ['netbank', $accountNumber, $alias])),
            verificationStatus: 'platform_configured',
            bankAccountNumber: $accountNumber,
            bankAccountName: $accountName,
            routingAlias: $alias,
        );
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
}
