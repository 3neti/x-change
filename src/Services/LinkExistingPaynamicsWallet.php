<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services;

use Illuminate\Support\Str;
use LBHurtado\XChange\Contracts\ProviderAccountLinkRepositoryContract;
use LBHurtado\XChange\Contracts\ProviderRuntimeSettingsResolverContract;
use LBHurtado\XChange\Enums\ProviderProvisioningMode;
use LBHurtado\XChange\Models\ProviderAccountLink;

class LinkExistingPaynamicsWallet
{
    public function __construct(
        protected SyncPaynamicsWalletBalance $balances,
        protected ProviderAccountLinkRepositoryContract $links,
        protected ProviderRuntimeSettingsResolverContract $settings,
    ) {}

    public function handle(mixed $owner, string $walletId): ProviderAccountLink
    {
        $walletId = Str::upper(trim($walletId));
        $sync = $this->balances->handle($walletId, $owner);

        return $this->links->storeFromProvisioningResult($owner, [
            'provider' => 'paynamics',
            'topology' => $this->settings->topology('paynamics'),
            'purpose' => 'issuer_funding',
            'mode' => ProviderProvisioningMode::WalletResolve->value,
            'status' => 'ready',
            'emi_wallet_id' => $sync['wallet']->getKey(),
            'provider_account_id' => data_get($sync, 'response.customer_id')
                ?? data_get($sync, 'response.customer_no')
                ?? $walletId,
            'provider_wallet_id' => $walletId,
            'verification_status' => 'unverified_manual_link',
            'identity_level' => 'wallet_exists_only',
            'capabilities' => [
                'balance_check',
                'issuer_funding',
                'pay_code_issuance',
            ],
            'metadata' => [
                'link_method' => 'existing_wallet_id',
                'balance_minor' => $sync['balance_minor'],
                'balance' => $sync['balance'],
                'currency' => $sync['currency'],
                'ownership_verification_required' => true,
                'ownership_verification_todo' => 'Ideally confirm ownership using mobile number, email, name, KYC reference, OTP, or another Paynamics-supported challenge.',
            ],
        ]);
    }
}
