<?php

declare(strict_types=1);

use Bavix\Wallet\Models\Wallet;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Artisan;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\Wallet\Treasury\Enums\TreasuryPositionPurpose;
use LBHurtado\Wallet\Treasury\Models\TreasuryInventory;
use LBHurtado\Wallet\Treasury\Models\TreasuryPosition;
use LBHurtado\XChange\Contracts\TreasuryAccountPortfolioProvisioningContract;
use LBHurtado\XChange\Contracts\VerifiedTreasuryFundingAllocationContract;
use LBHurtado\XChange\Models\VoucherClaim;
use LBHurtado\XChange\Services\Treasury\TreasuryPayCodeAccountingService;
use LBHurtado\XChange\Tests\Fakes\User;

it('releases only eligible unclaimed expired Pay Codes within the configured batch', function () {
    $issuer = actingAsTestUser();
    enableNetbankTreasuryForTests();
    $provider = fakePayoutProvider();
    app(TreasuryAccountPortfolioProvisioningContract::class)->provision(
        $issuer,
        ['netbank-primary'],
    );
    app(VerifiedTreasuryFundingAllocationContract::class)->allocate(
        accountReference: 'wallet:'.$issuer->wallet->uuid,
        provider: 'netbank',
        amountMinor: 50_000,
        currency: 'PHP',
        evidenceReference: 'netbank:scheduled-expiry-release',
    );
    $eligible = scheduledExpiryReleaseVoucher($issuer, now()->subMinutes(2));
    $active = scheduledExpiryReleaseVoucher($issuer, now()->addHour());
    $claimed = scheduledExpiryReleaseVoucher($issuer, now()->subMinute());
    VoucherClaim::query()->create([
        'voucher_id' => $claimed->getKey(),
        'claim_number' => 1,
        'claim_type' => 'redeem',
        'status' => 'pending',
        'requested_amount_minor' => 2_000,
        'currency' => 'PHP',
        'idempotency_key' => 'scheduled-expiry-claim-guard',
        'reference' => 'scheduled-expiry-claim-guard',
    ]);
    $clientFundsBefore = scheduledExpiryPositionBalance(
        $issuer,
        TreasuryPositionPurpose::ClientFunds,
    );
    $reserveBefore = scheduledExpiryPositionBalance(
        $issuer,
        TreasuryPositionPurpose::PayCodeReserve,
    );
    $inventoryBefore = TreasuryInventory::query()->sum('balance_minor');

    $exitCode = Artisan::call('xchange:pay-codes:release-expired', ['--json' => true]);

    expect($exitCode)->toBe(0)
        ->and(Artisan::output())->toContain('"processed":1', '"released":1');

    expect(data_get(
        $eligible->refresh()->metadata,
        'treasury.pay_code_reservation.status',
    ))->toBe('released')
        ->and(data_get(
            $active->refresh()->metadata,
            'treasury.pay_code_reservation.status',
        ))->toBe('reserved')
        ->and(data_get(
            $claimed->refresh()->metadata,
            'treasury.pay_code_reservation.status',
        ))->toBe('reserved')
        ->and(scheduledExpiryPositionBalance(
            $issuer,
            TreasuryPositionPurpose::ClientFunds,
        ))->toBe($clientFundsBefore + 2_000)
        ->and(scheduledExpiryPositionBalance(
            $issuer,
            TreasuryPositionPurpose::PayCodeReserve,
        ))->toBe($reserveBefore - 2_000)
        ->and(TreasuryInventory::query()->sum('balance_minor'))
        ->toBe($inventoryBefore);
    $provider->assertNoDisbursementAttempted();

    $this->artisan('xchange:pay-codes:release-expired', ['--json' => true])
        ->expectsOutputToContain('"processed":0')
        ->assertSuccessful();
});

it('registers package-owned expired Pay Code release every minute', function () {
    config()->set('x-change.treasury.expiry_release.scheduled_enabled', true);
    $event = collect(app(Schedule::class)->events())
        ->first(fn ($event): bool => $event->description === 'xchange:pay-codes:release-expired');

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('* * * * *')
        ->and($event->withoutOverlapping)->toBeTrue()
        ->and($event->onOneServer)->toBeTrue()
        ->and($event->expiresAt)->toBe(5)
        ->and($event->command)->toContain('xchange:pay-codes:release-expired --limit=100');
});

function scheduledExpiryReleaseVoucher(User $issuer, mixed $expiresAt): Voucher
{
    $voucher = issueVoucher(validVoucherInstructions(amount: 20));
    app(TreasuryPayCodeAccountingService::class)->reserve(
        accountOwner: $issuer,
        voucher: $voucher,
        connectionReference: 'netbank-primary',
        providerPrincipalMinor: 2_000,
        currency: 'PHP',
    );
    $voucher->forceFill(['expires_at' => $expiresAt])->saveQuietly();

    return $voucher->refresh();
}

function scheduledExpiryPositionBalance(
    User $owner,
    TreasuryPositionPurpose $purpose,
): int {
    $position = TreasuryPosition::query()
        ->whereMorphedTo('principal', $owner)
        ->where('connection_reference', 'netbank-primary')
        ->where('purpose', $purpose)
        ->sole();

    return (int) Wallet::query()
        ->findOrFail($position->internal_ledger_id)
        ->balance;
}
