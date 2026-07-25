<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryPositionOperationContract;
use LBHurtado\Wallet\Treasury\Data\TreasuryPositionReservationData;
use LBHurtado\Wallet\Treasury\Enums\TreasuryPositionPurpose;
use LBHurtado\XChange\Actions\PayCode\GeneratePayCode;
use LBHurtado\XChange\Contracts\ProviderFundingPolicyContract;
use LBHurtado\XChange\Contracts\TreasuryAccountPortfolioProvisioningContract;
use LBHurtado\XChange\Contracts\VerifiedTreasuryFundingAllocationContract;
use LBHurtado\XChange\Data\FundingDecisionData;
use LBHurtado\XChange\Events\FundingProjectionChanged;
use LBHurtado\XChange\Models\VoucherClaim;
use LBHurtado\XChange\Services\Funding\PayCodeFundingEligibility;
use LBHurtado\XChange\Services\Funding\PayCodeFundingInspectionStore;
use LBHurtado\XChange\Tests\Fakes\User;

it('issues an Account Funding Pay Code with a no-payout commercial profile and Treasury reserve', function () {
    $issuer = actingAsTestUser();
    enableNetbankTreasuryForTests();
    config()->set('x-change.commercial.enabled', true);
    app(TreasuryAccountPortfolioProvisioningContract::class)->provision(
        $issuer,
        ['netbank-primary'],
    );
    app(VerifiedTreasuryFundingAllocationContract::class)->allocate(
        accountReference: 'wallet:'.$issuer->wallet->uuid,
        provider: 'netbank',
        amountMinor: 50_000,
        currency: 'PHP',
        evidenceReference: 'netbank:account-funding-pay-code:issuance',
    );
    $funding = Mockery::mock(ProviderFundingPolicyContract::class);
    $funding->shouldReceive('assertCanIssue')
        ->once()
        ->andReturn(FundingDecisionData::allowed(
            authority: 'local_ledger',
            availableMinor: 50_000,
            requiredMinor: 14_000,
            meta: ['provider' => 'netbank'],
        ));
    app()->instance(ProviderFundingPolicyContract::class, $funding);

    $result = app(GeneratePayCode::class)->handle(validPayCodePayload(
        125,
        'INSTAPAY',
        [
            'inputs' => ['fields' => []],
            'feedback' => [
                'email' => null,
                'mobile' => null,
                'webhook' => null,
            ],
            'rider' => [
                'message' => null,
                'url' => null,
                'redirect_timeout' => null,
                'splash' => null,
                'splash_timeout' => null,
                'og_source' => null,
            ],
            'provider' => 'netbank',
            'metadata' => [
                'issuer_id' => (string) $issuer->getKey(),
                'custom' => [
                    'settlement' => [
                        'destinations' => ['account_funding'],
                        'account_funding' => [
                            'pricing_profile' => 'account-funding-v1',
                        ],
                    ],
                ],
            ],
        ],
    ));

    $voucher = Voucher::query()->findOrFail($result->voucher_id);
    $eligibility = app(PayCodeFundingEligibility::class)->evaluate($voucher);

    expect(collect($result->allocations)->where('category', 'provider_cost'))
        ->toBeEmpty()
        ->and(data_get($voucher->metadata, 'treasury.account_funding.status'))
        ->toBe('ready')
        ->and(data_get($voucher->metadata, 'treasury.pay_code_reservation.amount_minor'))
        ->toBe(12_500)
        ->and($eligibility->status)->toBe('eligible')
        ->and($eligibility->eligible)->toBeTrue()
        ->and(treasuryClientFundsLedger($issuer)->getBalanceIntAttribute())
        ->toBe(50_000 - 12_500 - (int) round($result->cost->total * 100));
});

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
    app(VerifiedTreasuryFundingAllocationContract::class)->allocate(
        accountReference: 'wallet:'.$issuer->wallet->uuid,
        provider: 'netbank',
        amountMinor: 12_500,
        currency: 'PHP',
        evidenceReference: 'netbank:account-funding-pay-code:claim',
    );
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
                'account_funding' => [
                    'status' => 'ready',
                    'destinations' => ['account_funding'],
                    'pricing_profile' => 'account-funding-v1',
                    'provider_cost_minor' => 0,
                    'provider_calls' => false,
                ],
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
