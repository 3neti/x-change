<?php

declare(strict_types=1);

use LBHurtado\XChange\Exceptions\FundingSettlementDenied;
use LBHurtado\XChange\Services\Funding\BavixFundingAccountCredit;

it('resolves numeric wallet keys without comparing them to a uuid column', function (): void {
    $user = actingAsTestUser();
    $wallet = $user->wallet()->where('slug', 'platform')->sole();
    $credit = app(BavixFundingAccountCredit::class);

    expect($credit->resolve('wallet:'.$wallet->getKey())->is($wallet))->toBeTrue()
        ->and($credit->resolve('wallet:'.$wallet->uuid)->is($wallet))->toBeTrue()
        ->and(fn () => $credit->resolve('wallet:not-a-wallet-reference'))
        ->toThrow(FundingSettlementDenied::class, 'reference is invalid');
});
