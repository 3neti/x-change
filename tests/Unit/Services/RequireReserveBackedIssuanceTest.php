<?php

declare(strict_types=1);

use LBHurtado\XChange\Data\FundingDecisionData;
use LBHurtado\XChange\Exceptions\PayCodeIssuanceFailed;
use LBHurtado\XChange\Services\Reserve\RequireReserveBackedIssuance;

it('refuses ordinary dedicated-account disbursement before it can be issued', function (?string $type): void {
    $funding = FundingDecisionData::allowed('local_ledger', 10000, 1000, meta: ['assigned_bank_account_link_id' => 12]);
    expect(fn () => app(RequireReserveBackedIssuance::class)->assertOrdinaryIssuanceAllowed(['voucher_type' => $type], $funding))
        ->toThrow(PayCodeIssuanceFailed::class, 'reserve-backed issuance flow');
})->with([null, 'redeemable']);

it('permits collection and shared-account issuance to use their existing paths', function (?string $type, array $meta): void {
    $funding = FundingDecisionData::allowed('local_ledger', 10000, 1000, meta: $meta);
    app(RequireReserveBackedIssuance::class)->assertOrdinaryIssuanceAllowed(['voucher_type' => $type], $funding);
    expect(true)->toBeTrue();
})->with([['payable', ['assigned_bank_account_link_id' => 12]], ['settlement', ['assigned_bank_account_link_id' => 12]], [null, []]]);
