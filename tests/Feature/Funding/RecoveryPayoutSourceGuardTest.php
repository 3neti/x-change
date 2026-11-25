<?php

declare(strict_types=1);

use LBHurtado\XChange\Actions\Banking\AssignNetbankAccount;
use LBHurtado\XChange\Actions\Disbursement\RetryFailedPayCodeDisbursement;
use LBHurtado\XChange\Models\DisbursementReconciliation;
use LBHurtado\XChange\Models\VoucherBankSource;
use LBHurtado\XChange\Services\Banking\RecoveryPayoutSourceGuard;

it('refuses a recovery when the reconciliation source differs from the pay code source', function () {
    $owner = actingAsTestUser();
    $voucher = issueVoucher(validVoucherInstructions(amount: 5, overrides: ['metadata' => ['issuer_id' => (string) $owner->id]]));
    $source = ['provider' => 'netbank', 'account_number' => '991100001234', 'customer_id' => '1234'];
    VoucherBankSource::query()->create(['voucher_id' => $voucher->id, 'source_ciphertext' => $source]);
    $reconciliation = new DisbursementReconciliation;
    $reconciliation->bank_source_snapshot = [...$source, 'account_number' => '991100009999'];

    expect(fn () => app(RecoveryPayoutSourceGuard::class)->assertMatches($voucher, $reconciliation))
        ->toThrow(RuntimeException::class, 'does not match');
});

it('refuses a recovery when a pay code source exists but the recovery snapshot is missing', function () {
    $owner = actingAsTestUser();
    $voucher = issueVoucher(validVoucherInstructions(amount: 5, overrides: ['metadata' => ['issuer_id' => (string) $owner->id]]));
    VoucherBankSource::query()->create(['voucher_id' => $voucher->id, 'source_ciphertext' => [
        'provider' => 'netbank', 'account_number' => '991100001234', 'customer_id' => '1234',
    ]]);

    expect(fn () => app(RecoveryPayoutSourceGuard::class)->assertMatches($voucher, new DisbursementReconciliation))
        ->toThrow(RuntimeException::class, 'does not match');
});

it('blocks a guarded retry before provider submission when its frozen source differs', function () {
    $owner = actingAsTestUser();
    $voucher = issueVoucher(validVoucherInstructions(amount: 5, overrides: ['metadata' => ['issuer_id' => (string) $owner->id]]));
    $metadata = (array) $voucher->metadata;
    data_set($metadata, 'disbursement.requires_reconciliation', true);
    data_set($metadata, 'treasury.pay_code_reservation.status', 'reserved');
    $voucher->forceFill(['metadata' => $metadata, 'redeemed_at' => now()])->saveQuietly();
    $source = ['provider' => 'netbank', 'account_number' => '991100001234', 'customer_id' => '1234'];
    VoucherBankSource::query()->create(['voucher_id' => $voucher->id, 'source_ciphertext' => $source]);
    DisbursementReconciliation::query()->create([
        'voucher_id' => $voucher->id, 'voucher_code' => $voucher->code, 'claim_type' => 'redeem',
        'provider' => 'unknown', 'provider_reference' => 'fixture-retry', 'status' => 'failed',
        'amount' => 5, 'currency' => 'PHP', 'bank_code' => 'GXCHPHM2XXX', 'settlement_rail' => 'INSTAPAY',
        'attempt_count' => 1, 'attempted_at' => now(), 'needs_review' => false,
        'raw_request' => ['reference' => 'fixture-retry'],
        'bank_source_snapshot' => [...$source, 'account_number' => '991100009999'],
    ]);
    $provider = fakePayoutProvider();

    expect(fn () => app(RetryFailedPayCodeDisbursement::class)->handle($voucher->code, true))
        ->toThrow(RuntimeException::class, 'does not match');
    $provider->assertNoDisbursementAttempted();
});

it('accepts the frozen account only while its treasury binding still matches', function () {
    $owner = actingAsTestUser();
    enableNetbankTreasuryForTests();
    $voucher = issueVoucher(validVoucherInstructions(amount: 5, overrides: ['metadata' => ['issuer_id' => (string) $owner->id]]));
    $metadata = (array) $voucher->metadata;
    data_set($metadata, 'treasury.pay_code_reservation.connection_reference', 'netbank-primary');
    $voucher->forceFill(['metadata' => $metadata])->saveQuietly();
    $source = [
        'provider' => 'netbank', 'account_number' => '991100001234', 'customer_id' => '1234',
        'treasury_connection_reference' => 'netbank-primary', 'treasury_inventory_reference' => 'inventory:netbank:vca-cash',
        'treasury_resource_reference' => 'resource:netbank:corporate-vca',
    ];
    VoucherBankSource::query()->create(['voucher_id' => $voucher->id, 'source_ciphertext' => $source]);
    $reconciliation = new DisbursementReconciliation;
    $reconciliation->bank_source_snapshot = $source;

    app(RecoveryPayoutSourceGuard::class)->assertMatches($voucher, $reconciliation);
    $source['treasury_inventory_reference'] = 'inventory:wrong';
    VoucherBankSource::query()->where('voucher_id', $voucher->id)->firstOrFail()->update(['source_ciphertext' => $source]);
    $reconciliation->bank_source_snapshot = $source;
    expect(fn () => app(RecoveryPayoutSourceGuard::class)->assertMatches($voucher, $reconciliation))
        ->toThrow(RuntimeException::class, 'no longer matches');
});

it('refuses recovery when an assigned treasury has lost its frozen pay code source', function () {
    $owner = actingAsTestUser();
    $assignment = app(AssignNetbankAccount::class)->handle($owner, [
        'account_number' => '991100001234', 'account_name' => 'Example', 'customer_id' => '1234', 'vca_alias' => '54321',
    ]);
    $assignment->forceFill(['treasury_connection_reference' => 'netbank-personal'])->save();
    $voucher = issueVoucher(validVoucherInstructions(amount: 5, overrides: ['metadata' => ['issuer_id' => (string) $owner->id]]));
    $metadata = (array) $voucher->metadata;
    data_set($metadata, 'treasury.pay_code_reservation.connection_reference', 'netbank-personal');
    $voucher->forceFill(['metadata' => $metadata])->saveQuietly();

    expect(fn () => app(RecoveryPayoutSourceGuard::class)->assertMatches($voucher, new DisbursementReconciliation))
        ->toThrow(RuntimeException::class, 'no frozen source');
});
