<?php

declare(strict_types=1);

use Bavix\Wallet\Models\Wallet;
use LBHurtado\Voucher\Enums\VoucherState;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\Wallet\Treasury\Enums\TreasuryPositionPurpose;
use LBHurtado\Wallet\Treasury\Models\TreasuryInventory;
use LBHurtado\Wallet\Treasury\Models\TreasuryPosition;
use LBHurtado\XChange\Contracts\TreasuryAccountPortfolioProvisioningContract;
use LBHurtado\XChange\Contracts\VerifiedTreasuryFundingAllocationContract;
use LBHurtado\XChange\Services\Treasury\TreasuryPayCodeAccountingService;
use LBHurtado\XChange\Tests\Fakes\User;

it('shows Treasury-safe terminal actions for an eligible regular Pay Code', function (): void {
    [$issuer, $voucher] = terminalControlVoucher();

    $this->withHeader('X-Inertia', 'true')
        ->get(route('x-change.cockpit.pay-codes.show', ['code' => $voucher->code]))
        ->assertOk()
        ->assertJsonPath('props.terminal_control.status', 'available')
        ->assertJsonPath('props.terminal_control.can_expire', true)
        ->assertJsonPath('props.terminal_control.can_cancel', true)
        ->assertJsonPath('props.terminal_control.release.amount_minor', 20_000)
        ->assertJsonPath('props.terminal_control.release.from', 'Pay Code Reserve')
        ->assertJsonPath('props.terminal_control.release.to', 'Client Funds')
        ->assertJsonPath('props.terminal_control.release.provider_calls', false)
        ->assertJsonPath('props.terminal_control.release.provider_inventory_changed', false)
        ->assertJsonPath('props.terminal_control.release.issuance_charges_refunded', false);

    expect($issuer)->toBeInstanceOf(User::class);

    $this->withHeader('X-Inertia', 'true')
        ->get(route('x-change.cockpit.pay-codes.index'))
        ->assertOk()
        ->assertJsonPath('props.pay_codes_read_model.records.0.terminal_control.can_expire', true)
        ->assertJsonPath('props.pay_codes_read_model.records.0.terminal_control.can_cancel', true);
});

it('returns terminal actions to the originating filtered Explorer view', function (): void {
    [, $voucher] = terminalControlVoucher();
    $origin = route('x-change.cockpit.pay-codes.index', [
        'search' => $voucher->code,
        'status' => 'active',
    ]);

    $this->from($origin)->post(route(
        'x-change.cockpit.pay-codes.terminal-actions.store',
        ['code' => $voucher->code],
    ), [
        'action' => 'expire',
        'reason' => 'Recipient no longer needs this Pay Code.',
        'confirmed' => true,
    ])->assertRedirect($origin);
});

it('expires a regular Pay Code and releases its reserve to Client Funds', function (): void {
    [$issuer, $voucher] = terminalControlVoucher();
    $clientFundsBefore = terminalControlBalance($issuer, TreasuryPositionPurpose::ClientFunds);
    $reserveBefore = terminalControlBalance($issuer, TreasuryPositionPurpose::PayCodeReserve);
    $inventoryBefore = TreasuryInventory::query()->sum('balance_minor');

    $this->post(route(
        'x-change.cockpit.pay-codes.terminal-actions.store',
        ['code' => $voucher->code],
    ), [
        'action' => 'expire',
        'reason' => 'Recipient no longer needs this Pay Code.',
        'confirmed' => true,
    ])->assertRedirect(route('x-change.cockpit.pay-codes.show', ['code' => $voucher->code]));

    expect($voucher->refresh()->state)->toBe(VoucherState::EXPIRED)
        ->and($voucher->isExpired())->toBeTrue()
        ->and(terminalControlBalance($issuer, TreasuryPositionPurpose::ClientFunds))
        ->toBe($clientFundsBefore + 20_000)
        ->and(terminalControlBalance($issuer, TreasuryPositionPurpose::PayCodeReserve))
        ->toBe($reserveBefore - 20_000)
        ->and(TreasuryInventory::query()->sum('balance_minor'))->toBe($inventoryBefore)
        ->and(data_get($voucher->metadata, 'lifecycle.terminal_actions.0.action'))->toBe('expired')
        ->and(data_get($voucher->metadata, 'treasury.terminal_release.terminal_reason'))->toBe('expired');
});

it('cancels a regular Pay Code but rejects a different owner', function (): void {
    [$issuer, $voucher] = terminalControlVoucher();
    $other = User::query()->create([
        'name' => 'Different Account',
        'email' => 'different-account@example.test',
        'password' => 'password',
    ]);
    $this->actingAs($other);

    $this->post(route(
        'x-change.cockpit.pay-codes.terminal-actions.store',
        ['code' => $voucher->code],
    ), [
        'action' => 'cancel',
        'reason' => 'Not this owner.',
        'confirmed' => true,
    ])->assertForbidden();

    expect($voucher->refresh()->state)->toBe(VoucherState::ACTIVE)
        ->and(terminalControlBalance($issuer, TreasuryPositionPurpose::PayCodeReserve))
        ->toBe(20_000);
});

it('presents a cancelled Pay Code as terminal and does not offer cancellation again', function (): void {
    [, $voucher] = terminalControlVoucher();

    $this->post(route(
        'x-change.cockpit.pay-codes.terminal-actions.store',
        ['code' => $voucher->code],
    ), [
        'action' => 'cancel',
        'reason' => 'Recipient details changed.',
        'confirmed' => true,
    ])->assertRedirect(route('x-change.cockpit.pay-codes.show', ['code' => $voucher->code]));

    $this->withHeader('X-Inertia', 'true')
        ->get(route('x-change.cockpit.pay-codes.show', ['code' => $voucher->code]))
        ->assertOk()
        ->assertJsonPath('props.read_model.voucher.status', 'cancelled')
        ->assertJsonPath('props.read_model.voucher.summary.operational_status.key', 'cancelled')
        ->assertJsonPath('props.read_model.voucher.summary.operational_status.is_terminal', true)
        ->assertJsonPath('props.read_model.voucher.summary.operational_status.can_claim', false)
        ->assertJsonPath('props.read_model.voucher.distribution_links.available', false)
        ->assertJsonPath('props.terminal_control.status', 'blocked')
        ->assertJsonPath('props.terminal_control.can_cancel', false);

    $this->withHeader('X-Inertia', 'true')
        ->get(route('x-change.cockpit.pay-codes.index'))
        ->assertOk()
        ->assertJsonPath('props.pay_codes_read_model.records.0.code', $voucher->code)
        ->assertJsonPath('props.pay_codes_read_model.records.0.terminal_control.can_cancel', false)
        ->assertJsonPath('props.pay_codes_read_model.records.0.timing.terminal_at', fn (mixed $value): bool => is_string($value) && $value !== '');
});

/**
 * @return array{0: User, 1: Voucher}
 */
function terminalControlVoucher(): array
{
    $issuer = actingAsTestUser();
    enableNetbankTreasuryForTests();
    fakePayoutProvider();
    app(TreasuryAccountPortfolioProvisioningContract::class)->provision(
        $issuer,
        ['netbank-primary'],
    );
    app(VerifiedTreasuryFundingAllocationContract::class)->allocate(
        accountReference: 'wallet:'.$issuer->wallet->uuid,
        provider: 'netbank',
        amountMinor: 50_000,
        currency: 'PHP',
        evidenceReference: 'netbank:cockpit-terminal-control',
    );
    $voucher = issueVoucher(validVoucherInstructions(amount: 200));
    app(TreasuryPayCodeAccountingService::class)->reserve(
        accountOwner: $issuer,
        voucher: $voucher,
        connectionReference: 'netbank-primary',
        providerPrincipalMinor: 20_000,
        currency: 'PHP',
    );

    return [$issuer, $voucher];
}

function terminalControlBalance(
    User $owner,
    TreasuryPositionPurpose $purpose,
): int {
    $position = TreasuryPosition::query()
        ->whereMorphedTo('principal', $owner)
        ->where('connection_reference', 'netbank-primary')
        ->where('purpose', $purpose)
        ->sole();

    return (int) Wallet::query()->findOrFail($position->internal_ledger_id)->balance;
}
