<?php

namespace LBHurtado\PaymentGateway\Services;


use Illuminate\Support\Facades\Config;
use Bavix\Wallet\Interfaces\Wallet;

class SystemUserResolverService
{
    public function resolve(): Wallet
    {
        $modelClass = Config::get('account.system_user.model');
        $identifier = Config::get('account.system_user.identifier');
        $column = Config::get('account.system_user.identifier_column', 'uuid');

        $user = $modelClass::where($column, $identifier)->firstOrFail();

        if (!($user instanceof Wallet)) {
            throw new \InvalidArgumentException('The resolved user must be an instance of Wallet.');
        }

        return $user;

    }
}
