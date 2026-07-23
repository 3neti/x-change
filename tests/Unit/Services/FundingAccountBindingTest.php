<?php

declare(strict_types=1);

use LBHurtado\XChange\Contracts\FundingAccountCreditContract;
use LBHurtado\XChange\Contracts\FundingAccountRecoveryContract;
use LBHurtado\XChange\Providers\XChangeServiceProvider;
use LBHurtado\XChange\Services\Funding\BavixFundingAccountCredit;

it('resolves both funding account contracts through one ledger service', function () {
    $credit = app(FundingAccountCreditContract::class);
    $recovery = app(FundingAccountRecoveryContract::class);

    expect($credit)
        ->toBeInstanceOf(BavixFundingAccountCredit::class)
        ->toBe($recovery);
});

it('registers funding account fallbacks when published host config is stale', function () {
    config()->set('x-change.services', []);
    config()->set('x-change.service_contracts', []);

    foreach ([
        'x-change.services.funding_account_credit',
        FundingAccountCreditContract::class,
        FundingAccountRecoveryContract::class,
    ] as $binding) {
        app()->offsetUnset($binding);
        app()->forgetInstance($binding);
    }

    $method = new ReflectionMethod(XChangeServiceProvider::class, 'registerFundingAccountServices');
    $method->setAccessible(true);
    $method->invoke(new XChangeServiceProvider(app()));

    $credit = app(FundingAccountCreditContract::class);
    $recovery = app(FundingAccountRecoveryContract::class);

    expect($credit)
        ->toBeInstanceOf(BavixFundingAccountCredit::class)
        ->toBe($recovery);
});
