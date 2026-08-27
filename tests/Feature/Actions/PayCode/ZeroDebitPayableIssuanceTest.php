<?php

declare(strict_types=1);

use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Actions\PayCode\GeneratePayCode;
use LBHurtado\XChange\Contracts\ProviderFundingPolicyContract;
use LBHurtado\XChange\Contracts\TreasuryAccountPortfolioProvisioningContract;
use LBHurtado\XChange\Contracts\VerifiedTreasuryFundingAllocationContract;
use LBHurtado\XChange\Data\FundingDecisionData;
use LBHurtado\XChange\Services\Commercial\ProvisionCommercialBaselines;
use LBHurtado\XChange\Tests\Fakes\User;

beforeEach(function (): void {
    config()->set('x-change.lifecycle.defaults.user_model', User::class);
    config()->set('x-change.onboarding.issuer_model', User::class);
    config()->set('x-change.commercial.legal_trace.legal_entity_reference', 'legal-entity:x-change:zero-debit-test');
    config()->set('x-change.commercial.legal_trace.profile_version', 'zero-debit-v1');
    $catalog = config('x-commerce.catalogs.pay_code');
    $catalog['version'] = 277;
    $catalog['items']['voucher_type.payable']['unit_price_minor'] = 0;
    config()->set('x-commerce.catalogs.pay_code', $catalog);

    app(ProvisionCommercialBaselines::class)
        ->provision('commissioning-manifest:zero-debit-payable');
});

it('permits zero-debit payable issuance while compatibility is ahead', function (): void {
    $user = actingAsTestUser(6_000);
    enableNetbankTreasuryForTests();
    config()->set('x-change.commercial.enabled', true);
    app(TreasuryAccountPortfolioProvisioningContract::class)->provision(
        $user,
        ['netbank-primary'],
    );
    app(VerifiedTreasuryFundingAllocationContract::class)->allocate(
        accountReference: 'wallet:'.$user->wallet->uuid,
        provider: 'netbank',
        amountMinor: 5_000,
        currency: 'PHP',
        evidenceReference: 'netbank:payable-issuance:zero-debit',
    );
    $funding = Mockery::mock(ProviderFundingPolicyContract::class);
    $funding->shouldReceive('assertCanIssue')
        ->once()
        ->andReturn(FundingDecisionData::allowed(
            authority: 'local_ledger',
            availableMinor: 5_000,
            requiredMinor: 0,
            currency: 'PHP',
            meta: [
                'provider' => 'netbank',
                'topology' => 'ledger_pooled',
            ],
        ));
    app()->instance(ProviderFundingPolicyContract::class, $funding);
    $wallet = $user->wallet()->where('slug', 'platform')->sole();
    $payload = validPayCodePayload(200, 'INSTAPAY', [
        'voucher_type' => 'payable',
        'target_amount' => 200,
        'metadata' => [
            'issuer_id' => (string) $user->getKey(),
        ],
        'feedback' => [
            'email' => null,
            'mobile' => null,
            'webhook' => null,
        ],
    ]);
    data_set($payload, 'inputs.fields', []);
    $payload['provider'] = 'netbank';

    $result = app(GeneratePayCode::class)->handle($payload);
    $voucher = Voucher::query()->findOrFail($result->voucher_id);

    expect($result->amount)->toBe(200.0)
        ->and($result->cost->account_debit)->toBe(0.0)
        ->and((int) $wallet->refresh()->balanceInt)->toBe(6_000)
        ->and(treasuryClientFundsLedger($user)->getBalanceIntAttribute())->toBe(5_000)
        ->and(data_get($voucher->metadata, 'treasury.pay_code_reservation'))->toBeNull();
});
