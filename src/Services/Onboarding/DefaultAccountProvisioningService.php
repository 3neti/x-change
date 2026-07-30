<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Onboarding;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use LBHurtado\XChange\Contracts\AccountProvisioningContract;
use LBHurtado\XChange\Contracts\TreasuryAccountPortfolioProvisioningContract;
use LBHurtado\XChange\Contracts\WalletProvisioningContract;
use LBHurtado\XChange\Data\Treasury\TreasuryAccountPortfolioData;

final readonly class DefaultAccountProvisioningService implements AccountProvisioningContract
{
    public function __construct(
        private WalletProvisioningContract $accounts,
        private TreasuryAccountPortfolioProvisioningContract $portfolios,
    ) {}

    public function provision(Model $accountOwner): TreasuryAccountPortfolioData
    {
        return DB::transaction(function () use ($accountOwner): TreasuryAccountPortfolioData {
            $this->accounts->open($accountOwner, [
                'wallet' => [
                    'slug' => (string) config(
                        'x-change.onboarding.default_wallet_slug',
                        'platform',
                    ),
                    'name' => (string) config(
                        'x-change.onboarding.default_wallet_name',
                        'Platform Account',
                    ),
                ],
            ]);

            return $this->portfolios->provision($accountOwner);
        }, attempts: 3);
    }
}
