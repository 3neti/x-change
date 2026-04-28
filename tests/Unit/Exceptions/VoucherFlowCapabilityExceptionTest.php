<?php

declare(strict_types=1);

use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Data\VoucherFlow\VoucherFlowCapabilitiesData;
use LBHurtado\XChange\Enums\VoucherFlowType;
use LBHurtado\XChange\Exceptions\VoucherCannotCollect;
use LBHurtado\XChange\Exceptions\VoucherCannotDisburse;
use LBHurtado\XChange\Exceptions\VoucherRequiresSettlementEnvelope;

function capabilityExceptionTestData(VoucherFlowType $type): VoucherFlowCapabilitiesData
{
    return new VoucherFlowCapabilitiesData(
        type: $type,
        label: $type->label(),
        direction: $type->direction(),
        can_disburse: false,
        can_collect: false,
        can_settle: false,
        supports_open_slices: false,
        supports_delegated_spend: false,
        requires_envelope: false,
        pay_code_route: 'disburse',
        qr_type: 'cash_out',
    );
}

it('creates cannot disburse exception with voucher and capabilities', function () {
    $voucher = new Voucher;
    $voucher->code = 'NO-DISBURSE';

    $capabilities = capabilityExceptionTestData(VoucherFlowType::Collectible);

    $exception = VoucherCannotDisburse::forVoucher($voucher, $capabilities);

    expect($exception->voucher)->toBe($voucher);
    expect($exception->capabilities)->toBe($capabilities);
    expect($exception->getMessage())->toContain('cannot execute outward claims');
});

it('creates cannot collect exception with voucher and capabilities', function () {
    $voucher = new Voucher;
    $voucher->code = 'NO-COLLECT';

    $capabilities = capabilityExceptionTestData(VoucherFlowType::Disbursable);

    $exception = VoucherCannotCollect::forVoucher($voucher, $capabilities);

    expect($exception->voucher)->toBe($voucher);
    expect($exception->capabilities)->toBe($capabilities);
    expect($exception->getMessage())->toContain('cannot collect inward payments');
});

it('creates settlement envelope exception with voucher and capabilities', function () {
    $voucher = new Voucher;
    $voucher->code = 'NEEDS-ENVELOPE';

    $capabilities = capabilityExceptionTestData(VoucherFlowType::Settlement);

    $exception = VoucherRequiresSettlementEnvelope::forVoucher($voucher, $capabilities);

    expect($exception->voucher)->toBe($voucher);
    expect($exception->capabilities)->toBe($capabilities);
    expect($exception->getMessage())->toContain('requires a settlement envelope');
});
