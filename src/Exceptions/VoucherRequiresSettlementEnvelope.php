<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Exceptions;

use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Data\Settlement\SettlementEnvelopeReadinessData;
use LBHurtado\XChange\Data\VoucherFlow\VoucherFlowCapabilitiesData;

class VoucherRequiresSettlementEnvelope extends VoucherFlowCapabilityException
{
    public function __construct(
        ?Voucher $voucher = null,
        ?VoucherFlowCapabilitiesData $capabilities = null,
        public readonly ?SettlementEnvelopeReadinessData $readiness = null,
        string $message = 'Voucher flow requires a settlement envelope.',
    ) {
        parent::__construct(
            voucher: $voucher,
            capabilities: $capabilities,
            message: $message,
        );
    }

    public static function forVoucher(
        Voucher $voucher,
        SettlementEnvelopeReadinessData|VoucherFlowCapabilitiesData $readinessOrCapabilities,
    ): self {
        if ($readinessOrCapabilities instanceof VoucherFlowCapabilitiesData) {
            return new self(
                voucher: $voucher,
                capabilities: $readinessOrCapabilities,
                readiness: null,
                message: "Voucher flow [{$readinessOrCapabilities->type->value}] requires a settlement envelope.",
            );
        }

        $missing = $readinessOrCapabilities->missing === []
            ? 'unknown'
            : implode(', ', $readinessOrCapabilities->missing);

        return new self(
            voucher: $voucher,
            capabilities: null,
            readiness: $readinessOrCapabilities,
            message: "Voucher requires a ready settlement envelope before funds may move. Missing: {$missing}.",
        );
    }

    public function context(): array
    {
        if (! $this->readiness) {
            return [
                'voucher_code' => $this->voucher?->code,
                'flow_type' => $this->capabilities?->type->value,
                'requires_envelope' => $this->capabilities?->requires_envelope,
            ];
        }

        return [
            'voucher_code' => $this->voucher?->code,
            ...$this->readiness->toExceptionContext(),
        ];
    }
}
