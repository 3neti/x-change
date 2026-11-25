<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Banking;

use Illuminate\Database\Eloquent\Model;
use LBHurtado\XChange\Contracts\BankingAccountSelectorContract;
use LBHurtado\XChange\Data\Banking\SelectedBankingAccount;
use LBHurtado\XChange\Exceptions\FundingDestinationUnavailable;
use LBHurtado\XChange\Models\FundingDestinationPreference;
use LBHurtado\XChange\Models\ProviderAccountLink;

final class DefaultBankingAccountSelector implements BankingAccountSelectorContract
{
    public function select(Model $owner, string $provider, string $capability, ?int $accountId = null): SelectedBankingAccount
    {
        $provider = strtolower(trim($provider));
        $provider = $provider === 'paynamics' ? 'paynamics_constellation' : $provider;

        if ($accountId === null) {
            $preference = FundingDestinationPreference::query()
                ->whereMorphedTo('owner', $owner)
                ->where('provider_code', $provider)
                ->first();

            if ($preference === null || $preference->mode === 'shared') {
                return new SelectedBankingAccount($provider, 'shared', null);
            }

            if ($preference->mode !== 'dedicated' || $preference->provider_account_link_id === null) {
                throw new FundingDestinationUnavailable('The selected banking account is unavailable.');
            }

            $accountId = $preference->provider_account_link_id;
        }

        $link = ProviderAccountLink::query()->whereMorphedTo('owner', $owner)
            ->where('provider', $provider)->find($accountId);

        if (! $link instanceof ProviderAccountLink || ! $link->isReady()
            || ! in_array($capability, $link->capabilities ?? [], true)) {
            throw new FundingDestinationUnavailable('The selected banking account is unavailable for this operation.');
        }

        return new SelectedBankingAccount($provider, 'dedicated', (int) $link->getKey());
    }
}
