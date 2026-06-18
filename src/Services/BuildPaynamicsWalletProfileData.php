<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services;

use LBHurtado\XChange\Contracts\ProviderAccountLinkRepositoryContract;

class BuildPaynamicsWalletProfileData
{
    public function __construct(
        protected ProviderAccountLinkRepositoryContract $links,
        protected BuildBalanceOverview $balances,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function handle(mixed $owner): array
    {
        $link = $this->links->findLatestForOwner($owner, 'paynamics');
        $overview = $this->balances->handle($owner, 'paynamics');

        return [
            'provider' => 'paynamics',
            'wallet_id' => $link?->provider_wallet_id,
            'status' => $link?->status,
            'verification_status' => $link?->verification_status,
            'identity_level' => $link?->identity_level,
            'last_synced_at' => $link?->last_synced_at?->toIso8601String(),
            'ownership_verification_required' => (bool) data_get($link?->metadata, 'ownership_verification_required', true),
            'ownership_verification_note' => 'Ideally confirm ownership using mobile number, email, name, KYC reference, OTP, or another Paynamics-supported challenge.',
            'balance_overview' => $overview,
        ];
    }
}
