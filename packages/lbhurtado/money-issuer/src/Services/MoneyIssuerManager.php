<?php

namespace LBHurtado\MoneyIssuer\Services;

use Illuminate\Support\Manager;

class MoneyIssuerManager extends Manager
{
    public function getDefaultDriver()
    {
        return config('emi.default');
    }

    public function createNetbankDriver()
    {
        $config = config('emi.drivers.netbank');

        return new NetBankMoneyIssuerService(
            baseUrl: $config['base_url'],
            apiKey: $config['api_key']
        );
    }

    public function createIcashDriver()
    {
        $config = config('emi.drivers.icash');

        return new ICashMoneyIssuerService(
            baseUrl: $config['base_url'],
            apiKey: $config['api_key']
        );
    }
}
