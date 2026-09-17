<?php

declare(strict_types=1);

use LBHurtado\XChange\Actions\Payment\CreatePaymentAttempt;
use LBHurtado\XChange\Actions\Redemption\PrepareVoucherClaimEvidence;
use LBHurtado\XChange\Exceptions\VoucherRequiresSettlementEnvelope;
use LBHurtado\XChange\Models\PaymentAttempt;
use LBHurtado\XChange\Models\VoucherClaim;
use LBHurtado\XChange\Services\SettlementCollectionGate;

beforeEach(function () {
    config()->set('x-change.settlement.default_driver', 'philhealth-bst');
    config()->set('x-change.settlement.drivers_path', settlementEnvelopeDriversPath());
});

it('requires durable complete intake evidence rather than metadata claims of readiness', function (): void {
    $voucher = claimIntakeSettlementVoucher();
    $gate = app(SettlementCollectionGate::class);
    expect($gate->contextFromVoucher($voucher)['driver'])->toBe('claim-intake');

    expect(fn () => $gate->ensureCollectibleSettlementIsReady($voucher, [
        'checklist' => ['claim_intake_complete' => true],
    ]))->toThrow(VoucherRequiresSettlementEnvelope::class);

    app(PrepareVoucherClaimEvidence::class)->handle($voucher, [
        'inputs' => ['name' => 'Demo', 'mobile' => '639171234567'],
    ]);
    expect(fn () => $gate->ensureCollectibleSettlementIsReady($voucher))
        ->toThrow(VoucherRequiresSettlementEnvelope::class);

    app(PrepareVoucherClaimEvidence::class)->handle($voucher, [
        'inputs' => ['email' => 'demo@example.test'],
    ]);
    expect(fn () => $gate->ensureCollectibleSettlementIsReady($voucher))
        ->toThrow(VoucherRequiresSettlementEnvelope::class);

    app(PrepareVoucherClaimEvidence::class)->handle($voucher, ['inputs' => claimIntakeSettlementInputs()]);
    $gate->ensureCollectibleSettlementIsReady($voucher);

    VoucherClaim::query()->where('voucher_id', $voucher->id)->update(['status' => 'execution_failed']);
    expect(fn () => $gate->ensureCollectibleSettlementIsReady($voucher))
        ->toThrow(VoucherRequiresSettlementEnvelope::class);
});

it('does not replace explicit settlement requirements with the intake profile', function (): void {
    $voucher = claimIntakeSettlementVoucher('philhealth-bst');
    app(PrepareVoucherClaimEvidence::class)->handle($voucher, ['inputs' => claimIntakeSettlementInputs()]);
    $gate = app(SettlementCollectionGate::class);
    expect($gate->contextFromVoucher($voucher)['driver'])->toBe('philhealth-bst');
    expect(fn () => $gate->ensureCollectibleSettlementIsReady($voucher))
        ->toThrow(VoucherRequiresSettlementEnvelope::class);
});

it('does not create payment attempts before intake requirements are persisted', function (): void {
    $voucher = claimIntakeSettlementVoucher();
    expect(fn () => app(CreatePaymentAttempt::class)->handle($voucher, 'netbank', 'browser', 'request'))
        ->toThrow(VoucherRequiresSettlementEnvelope::class);
    expect(PaymentAttempt::query()->count())->toBe(0);
});

it('requires verified OTP evidence when the intake instruction asks for it', function (): void {
    $voucher = claimIntakeSettlementVoucher(null, ['name', 'mobile', 'email', 'otp']);
    app(PrepareVoucherClaimEvidence::class)->handle($voucher, ['inputs' => [
        ...claimIntakeSettlementInputs(), 'otp' => ['verified' => false],
    ]]);
    $gate = app(SettlementCollectionGate::class);
    expect(fn () => $gate->ensureCollectibleSettlementIsReady($voucher))
        ->toThrow(VoucherRequiresSettlementEnvelope::class);
    app(PrepareVoucherClaimEvidence::class)->handle($voucher, ['inputs' => [
        ...claimIntakeSettlementInputs(), 'otp' => ['verified' => true],
    ]]);
    $gate->ensureCollectibleSettlementIsReady($voucher);
});

function claimIntakeSettlementVoucher(?string $driver = null, array $fields = ['name', 'mobile', 'email']): object
{
    return issueVoucher(validVoucherInstructions(overrides: [
        'inputs' => ['fields' => $fields],
        'claim' => ['outcomes' => [['key' => 'lead_intake']], 'default_outcome' => 'lead_intake'],
        'metadata' => ['flow_type' => 'settlement', 'custom' => ['settlement' => array_filter(['driver' => $driver])]],
    ]));
}

function claimIntakeSettlementInputs(): array
{
    return ['name' => 'Demo', 'mobile' => '639171234567', 'email' => 'demo@example.test'];
}

it('blocks settlement collection when envelope is not ready', function () {
    $voucher = issueVoucher(validVoucherInstructions(
        overrides: [
            'metadata' => [
                'flow_type' => 'settlement',
                'settlement_driver' => 'philhealth-bst',
            ],
        ],
    ));

    $voucher = persistUnreadySettlementEnvelopeEvidence($voucher);

    app(SettlementCollectionGate::class)->ensureCollectibleSettlementIsReady(
        voucher: $voucher,
        context: app(SettlementCollectionGate::class)->contextFromVoucher($voucher),
    );
})->throws(VoucherRequiresSettlementEnvelope::class);

it('allows settlement collection when envelope is ready', function () {
    $voucher = issueVoucher(validVoucherInstructions(
        overrides: [
            'metadata' => [
                'flow_type' => 'settlement',
                'settlement_driver' => 'philhealth-bst',
            ],
        ],
    ));

    $voucher = persistReadySettlementEnvelopeEvidence($voucher);

    app(SettlementCollectionGate::class)->ensureCollectibleSettlementIsReady(
        voucher: $voucher,
        context: app(SettlementCollectionGate::class)->contextFromVoucher($voucher),
    );

    expect(true)->toBeTrue();
});

it('blocks settlement voucher disbursement to claimant', function () {
    $voucher = issueVoucher(validVoucherInstructions(
        overrides: [
            'metadata' => [
                'flow_type' => 'settlement',
                'settlement_driver' => 'philhealth-bst',
            ],
        ],
    ));

    app(SettlementCollectionGate::class)->assertSettlementDoesNotDisburse($voucher);
})->throws(VoucherRequiresSettlementEnvelope::class);

it('does not gate ordinary collectible vouchers with settlement readiness', function () {
    $voucher = issueVoucher(validVoucherInstructions(
        overrides: [
            'metadata' => [
                'flow_type' => 'collectible',
            ],
        ],
    ));

    app(SettlementCollectionGate::class)->ensureCollectibleSettlementIsReady(
        voucher: $voucher,
        context: [],
    );

    expect(true)->toBeTrue();
});

it('does not block ordinary disbursable vouchers as settlement disbursements', function () {
    $voucher = issueVoucher(validVoucherInstructions(
        overrides: [
            'metadata' => [
                'flow_type' => 'disbursable',
            ],
        ],
    ));

    app(SettlementCollectionGate::class)->assertSettlementDoesNotDisburse($voucher);

    expect(true)->toBeTrue();
});

function persistReadySettlementEnvelopeEvidence($voucher): object
{
    $metadata = is_array($voucher->metadata ?? null)
        ? $voucher->metadata
        : [];

    $voucher->forceFill([
        'metadata' => [
            ...$metadata,
            'flow_type' => 'settlement',
            'settlement_driver' => 'philhealth-bst',
            'settlement_payload' => [
                'patient_name' => 'Juan Dela Cruz',
                'patient_mobile' => '09171234567',
            ],
            'settlement_checklist' => [
                'amount_verified' => true,
            ],
        ],
    ])->save();

    return $voucher->refresh();
}

function persistUnreadySettlementEnvelopeEvidence($voucher): object
{
    $metadata = is_array($voucher->metadata ?? null)
        ? $voucher->metadata
        : [];

    $voucher->forceFill([
        'metadata' => [
            ...$metadata,
            'flow_type' => 'settlement',
            'settlement_driver' => 'philhealth-bst',
            'settlement_payload' => [
                'patient_name' => 'Juan Dela Cruz',
                'patient_mobile' => '09171234567',
            ],
            'settlement_checklist' => [
                'amount_verified' => false,
            ],
        ],
    ])->save();

    return $voucher->refresh();
}
