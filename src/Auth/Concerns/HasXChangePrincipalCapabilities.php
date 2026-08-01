<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Auth\Concerns;

use Bavix\Wallet\Traits\CanPay;
use Bavix\Wallet\Traits\HasWalletFloat;
use LBHurtado\ModelChannel\Traits\HasChannels;
use LBHurtado\Wallet\Traits\HasPlatformWallets;

trait HasXChangePrincipalCapabilities
{
    use CanPay;
    use HasChannels;
    use HasPlatformWallets;
    use HasWalletFloat;

    /**
     * Initialize the attributes required by x-change without replacing host model configuration.
     */
    public function initializeHasXChangePrincipalCapabilities(): void
    {
        $this->mergeFillable([
            'mobile',
            'mobile_verified_at',
            'onboarding_meta',
        ]);
        $this->mergeHidden([
            'two_factor_secret',
            'two_factor_recovery_codes',
        ]);
        $this->mergeCasts([
            'mobile_verified_at' => 'datetime',
            'onboarding_meta' => 'array',
            'two_factor_confirmed_at' => 'datetime',
        ]);
    }
}
