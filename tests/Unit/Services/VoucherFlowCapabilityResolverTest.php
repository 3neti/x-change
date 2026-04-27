<?php

use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Contracts\VoucherFlowCapabilityResolverContract;
use LBHurtado\XChange\Enums\VoucherFlowType;

function fakeVoucherWithFlow(?string $flowType = null, ?string $legacyType = null): Voucher
{
    $voucher = new Voucher;

    $metadata = [];

    if ($flowType !== null) {
        $metadata['flow_type'] = $flowType;
    }

    if ($legacyType !== null) {
        $metadata['voucher_type'] = $legacyType;
    }

    $voucher->setAttribute('metadata', $metadata);

    return $voucher;
}

it('resolves disbursable capabilities', function () {
    $resolver = app(VoucherFlowCapabilityResolverContract::class);

    $voucher = fakeVoucherWithFlow('disbursable');

    $capabilities = $resolver->resolve($voucher);

    expect($capabilities->type)->toBe(VoucherFlowType::Disbursable);
    expect($capabilities->can_disburse)->toBeTrue();
    expect($capabilities->can_collect)->toBeFalse();
    expect($capabilities->pay_code_route)->toBe('disburse');
});

it('resolves collectible capabilities', function () {
    $resolver = app(VoucherFlowCapabilityResolverContract::class);

    $voucher = fakeVoucherWithFlow('collectible');

    $capabilities = $resolver->resolve($voucher);

    expect($capabilities->type)->toBe(VoucherFlowType::Collectible);
    expect($capabilities->can_disburse)->toBeFalse();
    expect($capabilities->can_collect)->toBeTrue();
    expect($capabilities->pay_code_route)->toBe('pay');
});

it('resolves settlement capabilities', function () {
    $resolver = app(VoucherFlowCapabilityResolverContract::class);

    $voucher = fakeVoucherWithFlow('settlement');

    $capabilities = $resolver->resolve($voucher);

    expect($capabilities->type)->toBe(VoucherFlowType::Settlement);
    expect($capabilities->can_disburse)->toBeTrue();
    expect($capabilities->can_collect)->toBeTrue();
    expect($capabilities->can_settle)->toBeTrue();
    expect($capabilities->requires_envelope)->toBeTrue();
});

it('honors legacy aliases', function () {
    $resolver = app(VoucherFlowCapabilityResolverContract::class);

    $redeemable = fakeVoucherWithFlow('redeemable');

    $payable = fakeVoucherWithFlow('payable');

    expect($resolver->typeOf($redeemable))->toBe(VoucherFlowType::Disbursable);
    expect($resolver->typeOf($payable))->toBe(VoucherFlowType::Collectible);
});

it('falls back to config default', function () {
    config()->set('x-change.voucher_flow_types.default', 'collectible');

    $resolver = app(VoucherFlowCapabilityResolverContract::class);

    $voucher = fakeVoucherWithFlow();

    expect($resolver->typeOf($voucher))->toBe(VoucherFlowType::Collectible);
});

it('resolves helper methods', function () {
    $resolver = app(VoucherFlowCapabilityResolverContract::class);

    $disbursable = fakeVoucherWithFlow('disbursable');

    $collectible = fakeVoucherWithFlow('collectible');

    $settlement = fakeVoucherWithFlow('settlement');

    expect($resolver->canDisburse($disbursable))->toBeTrue();
    expect($resolver->canCollect($collectible))->toBeTrue();
    expect($resolver->canSettle($settlement))->toBeTrue();
});
