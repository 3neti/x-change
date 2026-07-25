<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryPositionOperationContract;
use LBHurtado\Wallet\Treasury\Data\TreasuryPositionReservationData;
use LBHurtado\Wallet\Treasury\Enums\TreasuryPositionPurpose;
use LBHurtado\XChange\Contracts\TreasuryAccountPortfolioProvisioningContract;
use LBHurtado\XChange\Events\FundingProjectionChanged;
use LBHurtado\XChange\Models\VoucherClaim;
use LBHurtado\XChange\Services\Funding\PayCodeFundingInspectionStore;
use LBHurtado\XChange\Tests\Fakes\User;

it('moves a reserved Pay Code into claimant Client Funds exactly once without provider outflow', function () {
    $issuer = actingAsTestUser();
    $claimant = User::query()->create([
        'name' => 'Account Funding Claimant',
        'email' => 'account-funding-claimant@example.test',
        'password' => 'password',
    ]);
    enableNetbankTreasuryForTests();
    $issuerPortfolio = app(
        TreasuryAccountPortfolioProvisioningContract::class,
    )->provision(
        $issuer,
        ['netbank-primary'],
    );
    app(TreasuryAccountPortfolioProvisioningContract::class)->provision(
        $claimant,
        ['netbank-primary'],
    );
    $clientFunds = collect($issuerPortfolio->positions)->firstWhere(
        'purpose',
        TreasuryPositionPurpose::ClientFunds,
    );
    $source = collect($issuerPortfolio->positions)->firstWhere(
        'purpose',
        TreasuryPositionPurpose::PayCodeReserve,
    );
    treasuryClientFundsLedger($issuer)->deposit(12_500);
    app(TreasuryPositionOperationContract::class)->reserve(
        new TreasuryPositionReservationData(
            operationReference: 'pay-code-position-reservation:claim',
            sourcePositionReference: $clientFunds->positionReference,
            destinationPositionReference: $source->positionReference,
            amountMinor: 12_500,
            currency: 'PHP',
            idempotencyKey: 'pay-code-position-reservation-key:claim',
            externalReference: 'pay-code:claim-test',
        ),
    );
    $voucher = payCodeAccountFundingVoucher($issuer);
    $token = app(PayCodeFundingInspectionStore::class)->issue(
        $voucher,
        $claimant,
    );
    Event::fake([FundingProjectionChanged::class]);

    $response = $this->actingAs($claimant)
        ->post(route('x-change.cockpit.funding.pay-code-claims.store'), [
            'inspection_token' => $token,
        ])
        ->assertRedirect(route('x-change.cockpit.funding.index'))
        ->assertSessionHas('funding_notice');

    expect($response)->not->toBeNull()
        ->and(VoucherClaim::query()->count())->toBe(1)
        ->and(VoucherClaim::query()->sole()->settlement_mode)->toBe('account_funding')
        ->and(VoucherClaim::query()->sole()->meta['provider_calls'])->toBeFalse()
        ->and($voucher->refresh()->redeemed_at)->not->toBeNull();

    $replayToken = app(PayCodeFundingInspectionStore::class)->issue(
        $voucher,
        $claimant,
    );
    $this->actingAs($claimant)
        ->post(route('x-change.cockpit.funding.pay-code-claims.store'), [
            'inspection_token' => $replayToken,
        ])
        ->assertRedirect(route('x-change.cockpit.funding.index'));

    expect(VoucherClaim::query()->count())->toBe(1);
});

function payCodeAccountFundingVoucher(User $issuer): Voucher
{
    return Voucher::query()->forceCreate([
        'code' => 'FUND-CLAIM',
        'owner_type' => $issuer::class,
        'owner_id' => $issuer->getKey(),
        'metadata' => [
            'instructions' => [
                'cash' => ['amount' => 125, 'currency' => 'PHP'],
                'inputs' => ['fields' => []],
                'feedback' => [],
                'rider' => [],
                'metadata' => [
                    'custom' => [
                        'settlement' => [
                            'destinations' => ['account_funding'],
                            'account_funding' => [
                                'pricing_profile' => 'account-funding-v1',
                                'provider_cost_minor' => 0,
                                'provider_calls' => false,
                            ],
                        ],
                    ],
                ],
            ],
            'treasury' => [
                'pay_code_reservation' => [
                    'status' => 'reserved',
                    'provider' => 'netbank',
                    'connection_reference' => 'netbank-primary',
                    'operation_reference' => 'pay-code-position-reservation:claim',
                    'amount_minor' => 12_500,
                    'currency' => 'PHP',
                ],
            ],
        ],
        'state' => 'active',
        'expires_at' => now()->addHour(),
    ]);
}
