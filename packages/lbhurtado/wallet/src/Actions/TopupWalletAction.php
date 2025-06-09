<?php

namespace LBHurtado\Wallet\Actions;

use LBHurtado\Wallet\Services\SystemUserResolverService;
use Lorisleiva\Actions\Concerns\AsAction;
use Bavix\Wallet\Interfaces\Wallet;

class TopupWalletAction
{
    use AsAction;

    public function handle(Wallet $user, float $amount): \Bavix\Wallet\Models\Transfer
    {
        $system = app(SystemUserResolverService::class)->resolve();

        return  $system->transferFloat($user, $amount);
    }
}
