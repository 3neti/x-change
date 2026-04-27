<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services;

use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Contracts\SettlementEnvelopeReadinessContract;
use LBHurtado\XChange\Contracts\SettlementExecutionContract;
use LBHurtado\XChange\Data\Settlement\SettlementExecutionResultData;

class DefaultSettlementExecutionService implements SettlementExecutionContract
{
    public function __construct(
        protected SettlementEnvelopeReadinessContract $envelopeReadiness,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function execute(Voucher $voucher, array $payload): SettlementExecutionResultData
    {
        $envelope = $this->envelopeReadiness->check($voucher);

        if (! $envelope->ready) {
            return new SettlementExecutionResultData(
                voucher_code: (string) $voucher->code,
                status: 'blocked',
                message: 'Settlement envelope is not ready.',
                meta: [
                    'missing' => $envelope->missing,
                    'envelope' => $envelope->toArray(),
                ],
            );
        }

        return new SettlementExecutionResultData(
            voucher_code: (string) $voucher->code,
            status: 'pending',
            message: 'Settlement execution is pending.',
            meta: array_filter([
                'settlement_mode' => 'stub',
                'envelope_id' => data_get($envelope->meta, 'envelope_id'),
                'envelope' => $envelope->toArray(),
            ], static fn ($value) => $value !== null),
        );
    }
}
