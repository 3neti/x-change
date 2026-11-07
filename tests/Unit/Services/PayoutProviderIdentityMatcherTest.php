<?php

declare(strict_types=1);

use LBHurtado\EmiCore\Contracts\PayoutProviderIdentity;
use LBHurtado\EmiPaynamicsConstellation\Support\ConstellationSettlementProvider;
use LBHurtado\Wallet\Treasury\Enums\TreasuryCustodyMode;
use LBHurtado\XChange\Data\Treasury\TreasuryProviderConnectionData;
use LBHurtado\XChange\Enums\TreasuryConnectionMode;
use LBHurtado\XChange\Exceptions\TreasuryConfigurationException;
use LBHurtado\XChange\Models\DisbursementReconciliation;
use LBHurtado\XChange\Services\PayoutProviderIdentityMatcher;
use LBHurtado\XChange\Services\Treasury\TreasuryPayCodeAccountingService;

it('matches an exact payout provider without aliases', function (): void {
    expect(app(PayoutProviderIdentityMatcher::class)->matches('netbank', 'netbank'))->toBeTrue()
        ->and(app(PayoutProviderIdentityMatcher::class)->matches('netbank', 'unknown'))->toBeFalse();
});

it('accepts only an unambiguous provider-owned payout alias', function (): void {
    $identity = new class implements PayoutProviderIdentity
    {
        public function providerCode(): string
        {
            return 'provider_canonical';
        }

        public function acceptsPayoutProvider(string $provider): bool
        {
            return $provider === 'provider_legacy';
        }
    };
    app()->instance('test.payout-identity', $identity);
    app()->tag('test.payout-identity', 'emi.payout-provider-identities');
    $matcher = app(PayoutProviderIdentityMatcher::class);
    expect($matcher->matches('provider_canonical', 'provider_legacy'))->toBeTrue()
        ->and($matcher->matches('other_provider', 'provider_legacy'))->toBeFalse();
});

it('matches the Constellation payout identity to its canonical provider', function (): void {
    app()->tag(ConstellationSettlementProvider::class, 'emi.payout-provider-identities');
    expect(app(PayoutProviderIdentityMatcher::class)->matches('paynamics_constellation', 'paynamics'))->toBeTrue()
        ->and(app(PayoutProviderIdentityMatcher::class)->matches('netbank', 'paynamics'))->toBeFalse();
});

it('rejects an alias claimed by more than one canonical provider', function (): void {
    foreach (['first_provider', 'second_provider'] as $provider) {
        $identity = new class($provider) implements PayoutProviderIdentity
        {
            public function __construct(private string $provider) {}

            public function providerCode(): string
            {
                return $this->provider;
            }

            public function acceptsPayoutProvider(string $provider): bool
            {
                return $provider === 'ambiguous_alias';
            }
        };
        app()->instance('test.identity.'.$provider, $identity);
        app()->tag('test.identity.'.$provider, 'emi.payout-provider-identities');
    }
    expect(app(PayoutProviderIdentityMatcher::class)->matches('first_provider', 'ambiguous_alias'))->toBeFalse();
});

it('accepts provider-owned Constellation identity without bypassing treasury amount checks', function (): void {
    app()->tag(ConstellationSettlementProvider::class, 'emi.payout-provider-identities');
    $voucher = issueVoucher();
    $metadata = (array) $voucher->metadata;
    data_set($metadata, 'disbursement.amount', 20);
    $voucher->forceFill(['metadata' => $metadata])->save();
    $payout = new DisbursementReconciliation([
        'voucher_id' => $voucher->id, 'provider' => 'paynamics', 'status' => 'succeeded',
        'provider_transaction_id' => 'provider-id', 'amount' => 20, 'meta' => [],
    ]);
    $connection = new TreasuryProviderConnectionData(
        'paynamics-primary', 'paynamics_constellation', TreasuryConnectionMode::Required,
        'PHP', 2, 'inventory', 'resource', 'cash_at_bank', TreasuryCustodyMode::ProviderProjection, [],
    );
    $service = app(TreasuryPayCodeAccountingService::class);
    $method = new ReflectionMethod($service, 'settlementAmounts');
    expect($method->invoke($service, $voucher, $payout, $connection))->toBe([2000, 0]);
    $payout->amount = 21;
    expect(fn () => $method->invoke($service, $voucher, $payout, $connection))
        ->toThrow(TreasuryConfigurationException::class);
});
