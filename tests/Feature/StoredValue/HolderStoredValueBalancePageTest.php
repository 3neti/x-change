<?php

declare(strict_types=1);

use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryAllocationActivityReadModelContract;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryAllocationReadModelContract;
use LBHurtado\Wallet\Treasury\Data\TreasuryAllocationActivityItemData;
use LBHurtado\Wallet\Treasury\Data\TreasuryAllocationActivityReadModelData;
use LBHurtado\Wallet\Treasury\Data\TreasuryAllocationActivityReadModelQueryData;
use LBHurtado\Wallet\Treasury\Data\TreasuryAllocationReadModelData;
use LBHurtado\Wallet\Treasury\Data\TreasuryAllocationReadModelQueryData;
use LBHurtado\XChange\Models\StoredValueHolderBinding;
use LBHurtado\XChange\Services\BuildBalanceOverview;
use LBHurtado\XChange\Tests\Fakes\User;

beforeEach(function () {
    app()->instance(TreasuryAllocationReadModelContract::class, new class implements TreasuryAllocationReadModelContract
    {
        public function read(TreasuryAllocationReadModelQueryData $query): TreasuryAllocationReadModelData
        {
            return new TreasuryAllocationReadModelData(
                allocationReference: $query->allocationReference,
                currency: 'PHP',
                allocatedAmountMinor: 100_000,
                drawnAmountMinor: 90_000,
                releasedAmountMinor: 0,
                outstandingAmountMinor: 90_000,
                usableAmountMinor: 10_000,
                sliceCount: 0,
                hasTreasuryFacts: true,
                metadata: [
                    'maximum_amount_minor' => 100_000,
                    'replenishable' => false,
                    'private_position_reference' => 'must-not-leak',
                ],
            );
        }
    });
    app()->instance(TreasuryAllocationActivityReadModelContract::class, new class implements TreasuryAllocationActivityReadModelContract
    {
        public function read(
            TreasuryAllocationActivityReadModelQueryData $query,
        ): TreasuryAllocationActivityReadModelData {
            return new TreasuryAllocationActivityReadModelData(
                hasTreasuryFacts: true,
                movements: [new TreasuryAllocationActivityItemData(
                    type: 'draw',
                    amountMinor: 2_500,
                    currency: 'PHP',
                    balanceBeforeMinor: 12_500,
                    balanceAfterMinor: 10_000,
                    effectiveAt: '2026-08-19T02:00:00+00:00',
                )],
                currentPage: $query->page,
                perPage: $query->perPage,
                total: 1,
                lastPage: 1,
            );
        }
    });
});

it('shows only the authenticated holder reusable balances and sanitized activity', function () {
    $holder = actingAsTestUser();
    $binding = holderStoredValueBinding($holder);

    app()->instance(BuildBalanceOverview::class, new class extends BuildBalanceOverview
    {
        public function __construct() {}

        public function handle(mixed $owner, ?string $provider = null, bool $syncIfStale = true, bool $forceSync = false): array
        {
            return ['authority' => 'local_ledger', 'balances' => []];
        }
    });

    $this->withHeader('X-Inertia', 'true')
        ->get(route('x-change.balances.index'))
        ->assertOk()
        ->assertJsonPath('props.reusable_balances.0.reference', $binding->reference)
        ->assertJsonPath('props.reusable_balances.0.status', 'low_balance')
        ->assertJsonPath('props.reusable_balances.0.available_minor', 10_000)
        ->assertJsonMissingPath('props.reusable_balances.0.allocation_reference')
        ->assertJsonMissingPath('props.reusable_balances.0.holder_id');

    $response = $this->withHeader('X-Inertia', 'true')
        ->get(route('x-change.balances.reusable.show', $binding->reference));

    $response->assertOk()
        ->assertJsonPath('component', 'x-change/balances/StoredValueShow')
        ->assertJsonPath('props.instrument.reference', $binding->reference)
        ->assertJsonPath('props.instrument.activity_available', true)
        ->assertJsonPath('props.instrument.transactions.0.label', 'Purchase')
        ->assertJsonPath('props.instrument.transactions.0.amount_minor', -2_500)
        ->assertJsonPath('props.instrument.transactions.0.balance_after_minor', 10_000)
        ->assertJsonMissingPath('props.instrument.allocation_reference')
        ->assertJsonMissingPath('props.instrument.transactions.0.operation_reference')
        ->assertJsonMissingPath('props.instrument.transactions.0.idempotency_key')
        ->assertDontSee('must-not-leak');
});

it('conceals a reusable balance from every non-holder account', function () {
    $holder = actingAsTestUser();
    $binding = holderStoredValueBinding($holder);
    $other = User::query()->create([
        'name' => 'Other Account',
        'email' => 'other@example.test',
        'password' => bcrypt('password'),
    ]);

    $this->actingAs($other)
        ->withHeader('X-Inertia', 'true')
        ->get(route('x-change.balances.reusable.show', $binding->reference))
        ->assertNotFound();
});

it('shows an unavailable state instead of inventing a zero balance', function () {
    app()->instance(TreasuryAllocationReadModelContract::class, new class implements TreasuryAllocationReadModelContract
    {
        public function read(TreasuryAllocationReadModelQueryData $query): TreasuryAllocationReadModelData
        {
            return new TreasuryAllocationReadModelData(
                allocationReference: $query->allocationReference,
                currency: 'PHP',
                allocatedAmountMinor: 0,
                drawnAmountMinor: 0,
                releasedAmountMinor: 0,
                outstandingAmountMinor: 0,
                usableAmountMinor: 0,
                sliceCount: 0,
                hasTreasuryFacts: false,
            );
        }
    });
    $holder = actingAsTestUser();
    $binding = holderStoredValueBinding($holder);

    $this->withHeader('X-Inertia', 'true')
        ->get(route('x-change.balances.reusable.show', $binding->reference))
        ->assertOk()
        ->assertJsonPath('props.instrument.status', 'unavailable')
        ->assertJsonPath('props.instrument.available_minor', null);
});

function holderStoredValueBinding(User $holder): StoredValueHolderBinding
{
    $voucher = Voucher::query()->create([
        'code' => 'SV'.str()->upper(str()->random(4)),
        'metadata' => [],
        'state' => 'active',
        'expires_at' => now()->addMonth(),
    ]);

    return StoredValueHolderBinding::query()->create([
        'voucher_id' => $voucher->getKey(),
        'allocation_reference' => 'stored-value-allocation:'.str()->uuid(),
        'reservation_operation_reference' => 'reservation:'.str()->uuid(),
        'activation_operation_reference' => 'activation:'.str()->uuid(),
        'holder_type' => $holder->getMorphClass(),
        'holder_id' => (string) $holder->getKey(),
        'holder_principal_reference' => 'principal:holder',
        'holder_authority_reference' => 'authority:holder',
        'currency' => 'PHP',
        'activated_at' => now(),
    ]);
}
