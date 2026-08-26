<?php

declare(strict_types=1);

use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Enums\PaymentAttemptStatus;
use LBHurtado\XChange\Models\PaymentAttempt;
use LBHurtado\XChange\Models\VoucherCollection;
use LBHurtado\XChange\Services\PartnerApi\PartnerPayCodeConsumerStatusResolver;

function bplsStatusVoucher(string $code, string $state = 'active'): Voucher
{
    return Voucher::query()->create([
        'code' => $code,
        'metadata' => [
            'instructions' => [
                'cash' => ['amount' => 0.0, 'currency' => 'PHP'],
                'target_amount' => 100.0,
            ],
        ],
        'state' => $state,
    ]);
}

function bplsStatusAttempt(Voucher $voucher, PaymentAttemptStatus $status): PaymentAttempt
{
    $key = (string) str()->uuid();

    return PaymentAttempt::query()->create([
        'voucher_id' => $voucher->getKey(),
        'provider_code' => 'netbank',
        'expected_amount_minor' => 10000,
        'currency' => 'PHP',
        'status' => $status,
        'session_key_hash' => hash('sha256', 'session-'.$key),
        'idempotency_key_hash' => hash('sha256', 'idempotency-'.$key),
        'idempotency_fingerprint' => hash('sha256', 'fingerprint-'.$key),
    ]);
}

it('reports a new collectible Pay Code as payable', function (): void {
    $voucher = bplsStatusVoucher('STATUS-PAYABLE');

    expect(app(PartnerPayCodeConsumerStatusResolver::class)->resolve($voucher))->toBe('payable');
});

it('reports active provider payment processing states as processing', function (PaymentAttemptStatus $status): void {
    $voucher = bplsStatusVoucher('STATUS-'.strtoupper($status->value));
    bplsStatusAttempt($voucher, $status);

    expect(app(PartnerPayCodeConsumerStatusResolver::class)->resolve($voucher))->toBe('processing');
})->with([
    PaymentAttemptStatus::AwaitingPayment,
    PaymentAttemptStatus::Verifying,
    PaymentAttemptStatus::Verified,
]);

it('does not guess failed or pending consumer states from non-authoritative attempt states', function (PaymentAttemptStatus $status): void {
    $voucher = bplsStatusVoucher('STATUS-'.strtoupper($status->value));
    bplsStatusAttempt($voucher, $status);

    expect(app(PartnerPayCodeConsumerStatusResolver::class)->resolve($voucher))->toBe('payable');
})->with([
    PaymentAttemptStatus::PendingInstructions,
    PaymentAttemptStatus::Suspense,
    PaymentAttemptStatus::Expired,
]);

it('reports a fully collected Pay Code as paid', function (): void {
    $voucher = bplsStatusVoucher('STATUS-PAID');
    VoucherCollection::query()->create([
        'voucher_id' => $voucher->getKey(),
        'collection_number' => 1,
        'status' => 'collected',
        'requested_amount_minor' => 10000,
        'collected_amount_minor' => 10000,
        'currency' => 'PHP',
        'provider' => 'netbank',
        'provider_reference' => 'bpls-status-paid',
        'provider_transaction_id' => 'bpls-status-paid',
        'idempotency_key' => 'bpls-status-paid',
        'completed_at' => now(),
    ]);

    expect(app(PartnerPayCodeConsumerStatusResolver::class)->resolve($voucher))->toBe('paid');
});

it('gives cancelled and expired voucher states precedence over payment progress', function (string $state): void {
    $voucher = bplsStatusVoucher('STATUS-'.strtoupper($state), $state);

    expect(app(PartnerPayCodeConsumerStatusResolver::class)->resolve($voucher))->toBe($state);
})->with(['cancelled', 'expired']);
