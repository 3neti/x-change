<?php

declare(strict_types=1);

use Bavix\Wallet\Models\Wallet;
use Illuminate\Support\Str;
use LBHurtado\Wallet\Treasury\Enums\TreasuryPositionPurpose;
use LBHurtado\Wallet\Treasury\Models\TreasuryPosition;
use LBHurtado\XChange\Data\FundingDecisionData;
use LBHurtado\XChange\Exceptions\PayCodeIssuanceFailed;
use LBHurtado\XChange\Services\Treasury\PreparePayCodeTreasuryIssuance;
use LBHurtado\XChange\Tests\Fakes\User;

it('uses issuer Client Funds when the source is explicit or omitted', function (?string $sourceAddress, ?string $destinationAddress) {
    enableNetbankTreasuryForTests();
    config()->set('x-change.commercial.enabled', true);
    $issuer = actingAsTestUser(0);
    $issuer->forceFill(['mobile' => '09171234567'])->save();
    fundTestUserClientFunds($issuer, 5_000);
    $flow = array_filter([
        'source' => $sourceAddress,
        'destination' => $destinationAddress,
    ], static fn (?string $address): bool => $address !== null);
    $instructions = validVoucherInstructions(
        amount: 20,
        overrides: $flow === [] ? [] : [
            'execution' => [
                'metadata' => ['value_flow' => $flow],
            ],
        ],
    );
    $voucher = issueVoucher($instructions);

    app(PreparePayCodeTreasuryIssuance::class)->handle(
        $issuer,
        $instructions->toArray(),
        ['voucher_id' => $voucher->getKey(), 'currency' => 'PHP', 'amount' => 20],
        FundingDecisionData::allowed(
            authority: 'local_ledger',
            availableMinor: 5_000,
            requiredMinor: 2_000,
            meta: ['provider' => 'netbank', 'topology' => 'ledger_pooled'],
        ),
    );

    expect(valueFlowIssuerPositionBalance($issuer, TreasuryPositionPurpose::ClientFunds))->toBe(3_000)
        ->and(valueFlowIssuerPositionBalance($issuer, TreasuryPositionPurpose::PayCodeReserve))->toBe(2_000)
        ->and(data_get($voucher->refresh()->metadata, 'treasury.pay_code_reservation.status'))
        ->toBe('reserved');
})->with([
    'legacy default' => [null, null],
    'explicit issuer source' => ['account.mobile:09171234567.client_funds', null],
    'explicit payout destination with default source' => [null, 'instapay.gcash.mobile:09179999999'],
]);

it('rejects an explicit source belonging to a different account before reservation', function () {
    enableNetbankTreasuryForTests();
    config()->set('x-change.commercial.enabled', true);
    $issuer = actingAsTestUser(0);
    $issuer->forceFill(['mobile' => '09171234567'])->save();
    fundTestUserClientFunds($issuer, 5_000);
    $other = User::query()->create([
        'name' => 'Another account',
        'email' => 'value-flow-other-'.Str::uuid().'@example.test',
        'password' => 'not-a-login-credential',
    ]);
    $other->forceFill(['mobile' => '09173011987'])->save();
    fundTestUserClientFunds($other, 5_000);
    $instructions = validVoucherInstructions(
        amount: 20,
        overrides: [
            'execution' => [
                'metadata' => [
                    'value_flow' => ['source' => 'account.mobile:09173011987.client_funds'],
                ],
            ],
        ],
    );
    $voucher = issueVoucher($instructions);

    expect(fn () => app(PreparePayCodeTreasuryIssuance::class)->handle(
        $issuer,
        $instructions->toArray(),
        ['voucher_id' => $voucher->getKey(), 'currency' => 'PHP', 'amount' => 20],
        FundingDecisionData::allowed(
            authority: 'local_ledger',
            availableMinor: 5_000,
            requiredMinor: 2_000,
            meta: ['provider' => 'netbank', 'topology' => 'ledger_pooled'],
        ),
    ))->toThrow(PayCodeIssuanceFailed::class, 'funded issuing Account');

    expect(valueFlowIssuerPositionBalance($issuer, TreasuryPositionPurpose::ClientFunds))->toBe(5_000)
        ->and(valueFlowIssuerPositionBalance($other, TreasuryPositionPurpose::ClientFunds))->toBe(5_000)
        ->and(valueFlowIssuerPositionBalance($issuer, TreasuryPositionPurpose::PayCodeReserve))->toBe(0);
});

function valueFlowIssuerPositionBalance(User $owner, TreasuryPositionPurpose $purpose): int
{
    $position = TreasuryPosition::query()
        ->whereMorphedTo('principal', $owner)
        ->where('connection_reference', 'netbank-primary')
        ->where('purpose', $purpose)
        ->sole();

    return (int) Wallet::query()->findOrFail($position->internal_ledger_id)->balanceInt;
}
