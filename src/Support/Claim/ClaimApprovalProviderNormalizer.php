<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Support\Claim;

final class ClaimApprovalProviderNormalizer
{
    /**
     * @var array<string, string>
     */
    private const ALIASES = [
        'paynamics' => 'paynamics',
        'payanamics' => 'paynamics',
        'emi-paynamics' => 'paynamics',
        'emi_paynamics' => 'paynamics',
        'netbank' => 'netbank',
        'emi-netbank' => 'netbank',
        'emi_netbank' => 'netbank',
    ];

    public function normalize(null|string|object $provider): ?string
    {
        if ($provider === null) {
            return null;
        }

        if (is_object($provider)) {
            $provider = $provider::class;
        }

        $key = strtolower(trim($provider));

        if ($key === '') {
            return null;
        }

        if (isset(self::ALIASES[$key])) {
            return self::ALIASES[$key];
        }

        if (str_contains($key, 'paynamics')) {
            return 'paynamics';
        }

        if (str_contains($key, 'netbank')) {
            return 'netbank';
        }

        return $key;
    }
}
