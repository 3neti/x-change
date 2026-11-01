<?php

declare(strict_types=1);

use LBHurtado\SettlementEnvelope\Models\Envelope;
use LBHurtado\SettlementEnvelope\Models\EnvelopeAttachment;
use LBHurtado\XChange\Actions\Payment\CreatePaymentAttempt;
use LBHurtado\XChange\Exceptions\VoucherRequiresSettlementEnvelope;
use LBHurtado\XChange\Models\PaymentAttempt;
use LBHurtado\XChange\Services\Settlement\DocumentPaymentGate;

it('blocks a payment attempt until the voucher envelope contains accepted required evidence', function (): void {
    $issuer = actingAsTestUser();
    $voucher = issueVoucher(validVoucherInstructions(overrides: [
        'voucher_type' => 'payable',
        'target_amount' => 100,
        'metadata' => [
            'flow_type' => 'collectible', 'issuer_id' => (string) $issuer->id, 'collection_wallet_id' => $issuer->wallet->id,
        ],
        'execution' => ['schema' => 'voucher.execution.v2', 'driver' => 'default', 'document_requirements' => [[
            'type' => 'IDENTITY', 'timing' => 'before_payment', 'requires_acceptance' => true, 'contributor' => 'payer',
        ]]],
    ]));

    expect(fn () => app(CreatePaymentAttempt::class)->handle($voucher->fresh(), 'netbank', 'browser', 'request'))
        ->toThrow(VoucherRequiresSettlementEnvelope::class);
    expect(PaymentAttempt::query()->count())->toBe(0);

    $envelope = new Envelope([
        'reference_code' => $voucher->code,
        'driver_id' => 'test',
        'driver_version' => '1.0.0',
        'status' => 'draft',
    ]);
    $envelope->reference()->associate($voucher);
    $envelope->save();
    $attachment = EnvelopeAttachment::query()->create([
        'envelope_id' => $envelope->id,
        'doc_type' => 'IDENTITY',
        'original_filename' => 'identity.pdf',
        'file_path' => 'test/identity.pdf',
        'disk' => 'local',
        'mime_type' => 'application/pdf',
        'size' => 10,
        'hash' => str_repeat('a', 64),
        'review_status' => 'pending',
        'metadata' => ['contributor_role' => 'payer'],
    ]);

    expect(fn () => app(DocumentPaymentGate::class)->ensureReady($voucher->fresh()))
        ->toThrow(VoucherRequiresSettlementEnvelope::class);
    $attachment->update(['review_status' => 'accepted']);
    app(DocumentPaymentGate::class)->ensureReady($voucher->fresh());
    expect(true)->toBeTrue();
});

it('leaves existing vouchers without document requirements unchanged', function (): void {
    $voucher = issueVoucher(validVoucherInstructions());

    app(DocumentPaymentGate::class)->ensureReady($voucher);
    expect(true)->toBeTrue();
});

it('fails closed for malformed persisted requirements', function (): void {
    $voucher = issueVoucher(validVoucherInstructions());
    $metadata = $voucher->metadata;
    data_set($metadata, 'instructions.execution.document_requirements', 'invalid');
    $voucher->forceFill(['metadata' => $metadata])->save();

    expect(fn () => app(DocumentPaymentGate::class)->ensureReady($voucher->fresh()))
        ->toThrow(VoucherRequiresSettlementEnvelope::class);
});
