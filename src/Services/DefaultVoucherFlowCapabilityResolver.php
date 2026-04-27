<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services;

use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Contracts\VoucherFlowCapabilityResolverContract;
use LBHurtado\XChange\Data\VoucherFlow\VoucherFlowCapabilitiesData;
use LBHurtado\XChange\Enums\VoucherFlowType;

class DefaultVoucherFlowCapabilityResolver implements VoucherFlowCapabilityResolverContract
{
    public function resolve(Voucher $voucher): VoucherFlowCapabilitiesData
    {
        $type = $this->typeOf($voucher);

        $config = (array) config("x-change.voucher_flow_types.canonical.{$type->value}", []);

        return new VoucherFlowCapabilitiesData(
            type: $type,
            label: (string) ($config['label'] ?? $type->label()),
            direction: (string) ($config['direction'] ?? $type->direction()),
            can_disburse: (bool) ($config['can_disburse'] ?? false),
            can_collect: (bool) ($config['can_collect'] ?? false),
            can_settle: (bool) ($config['can_settle'] ?? false),
            supports_open_slices: (bool) ($config['supports_open_slices'] ?? false),
            supports_delegated_spend: (bool) ($config['supports_delegated_spend'] ?? false),
            requires_envelope: (bool) ($config['requires_envelope'] ?? false),
            pay_code_route: (string) ($config['pay_code_route'] ?? 'disburse'),
            qr_type: (string) ($config['qr_type'] ?? 'claim'),
        );
    }

    public function typeOf(Voucher $voucher): VoucherFlowType
    {
        $raw = $this->rawFlowType($voucher);

        return VoucherFlowType::normalize(
            $raw,
            (string) config('x-change.voucher_flow_types.default', 'disbursable')
        );
    }

    public function canDisburse(Voucher $voucher): bool
    {
        return $this->resolve($voucher)->can_disburse;
    }

    public function canCollect(Voucher $voucher): bool
    {
        return $this->resolve($voucher)->can_collect;
    }

    public function canSettle(Voucher $voucher): bool
    {
        return $this->resolve($voucher)->can_settle;
    }

    protected function rawFlowType(Voucher $voucher): ?string
    {
        foreach ([
            'flow_type',
            'voucher_flow_type',
            'voucher_type',
        ] as $attribute) {
            $value = $voucher->getAttribute($attribute);

            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        foreach ([
            'metadata.flow_type',
            'metadata.voucher_flow_type',
            'metadata.voucher_type',
            'meta.flow_type',
            'meta.voucher_flow_type',
            'meta.voucher_type',
        ] as $path) {
            $value = data_get($voucher, $path);

            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return null;
    }
}
