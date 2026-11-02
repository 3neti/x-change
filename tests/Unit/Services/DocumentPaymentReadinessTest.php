<?php

declare(strict_types=1);

use Illuminate\Validation\ValidationException;
use LBHurtado\XChange\Services\Settlement\DocumentPaymentReadiness;
use LBHurtado\XChange\Services\VoucherIssuancePayloadNormalizer;

it('only blocks payment on missing before-payment documents', function (): void {
    $requirements = [
        ['type' => 'INVOICE', 'timing' => 'optional', 'requires_acceptance' => false, 'contributor' => 'payer'],
        ['type' => 'IDENTITY', 'timing' => 'before_payment', 'requires_acceptance' => false, 'contributor' => 'payer'],
        ['type' => 'RECEIPT', 'timing' => 'later_review', 'requires_acceptance' => true, 'contributor' => 'payer'],
    ];

    expect((new DocumentPaymentReadiness)->missingForPayment($requirements, []))->toBe(['IDENTITY']);
    expect((new DocumentPaymentReadiness)->missingForPayment($requirements, [
        ['type' => 'IDENTITY', 'review_status' => 'pending', 'contributor' => 'payer'],
    ]))->toBe([]);
});

it('requires accepted evidence when review is required and permits resubmission', function (): void {
    $requirements = [['type' => 'IDENTITY', 'timing' => 'before_payment', 'requires_acceptance' => true, 'contributor' => 'payer']];
    $readiness = new DocumentPaymentReadiness;

    expect($readiness->missingForPayment($requirements, [
        ['type' => 'IDENTITY', 'review_status' => 'pending', 'contributor' => 'payer'],
        ['type' => 'IDENTITY', 'review_status' => 'rejected', 'contributor' => 'payer'],
    ]))->toBe(['IDENTITY']);
    expect($readiness->missingForPayment($requirements, [
        ['type' => 'IDENTITY', 'review_status' => 'rejected', 'contributor' => 'payer'],
        ['type' => 'IDENTITY', 'review_status' => 'accepted', 'contributor' => 'payer'],
    ]))->toBe([]);
});

it('rejects malformed requirements rather than silently allowing payment', function (): void {
    (new DocumentPaymentReadiness)->missingForPayment([
        ['type' => 'IDENTITY', 'timing' => 'unknown', 'requires_acceptance' => false, 'contributor' => 'payer'],
    ], []);
})->throws(InvalidArgumentException::class);

it('does not count rejected evidence when acceptance is not required', function (): void {
    $requirements = [['type' => 'IDENTITY', 'timing' => 'before_payment', 'requires_acceptance' => false, 'contributor' => 'payer']];

    expect((new DocumentPaymentReadiness)->missingForPayment($requirements, [
        ['type' => 'IDENTITY', 'review_status' => 'rejected', 'contributor' => 'payer'],
    ]))->toBe(['IDENTITY']);
});

it('does not count evidence submitted by a different participant', function (): void {
    $requirements = [['type' => 'IDENTITY', 'timing' => 'before_payment', 'requires_acceptance' => false, 'contributor' => 'payer']];

    expect((new DocumentPaymentReadiness)->missingForPayment($requirements, [
        ['type' => 'IDENTITY', 'review_status' => 'accepted', 'contributor' => 'claimant'],
    ]))->toBe(['IDENTITY']);
});

it('preserves valid v2 payable requirements through issuance normalization', function (): void {
    $requirements = [['type' => 'INVOICE', 'timing' => 'before_payment', 'requires_acceptance' => true, 'contributor' => 'payer']];
    $input = [
        'voucher_type' => 'payable',
        'execution' => ['schema' => 'voucher.execution.v2', 'driver' => 'default', 'document_requirements' => $requirements],
    ];

    expect(data_get(app(VoucherIssuancePayloadNormalizer::class)->normalize($input), 'execution.document_requirements'))
        ->toBe($requirements);
});

it('rejects invalid document requirements during issuance', function (): void {
    app(VoucherIssuancePayloadNormalizer::class)->normalize([
        'voucher_type' => 'payable',
        'execution' => ['schema' => 'voucher.execution.v2', 'driver' => 'default', 'document_requirements' => [
            ['type' => 'IDENTITY', 'timing' => 'before_payment', 'requires_acceptance' => 'yes', 'contributor' => 'payer'],
        ]],
    ]);
})->throws(ValidationException::class);
