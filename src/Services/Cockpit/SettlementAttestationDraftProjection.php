<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Cockpit;

final class SettlementAttestationDraftProjection
{
    public function forDraft(array $payload): array
    {
        if ($this->applies($payload)) {
            data_set($payload, 'cash.amount', data_get($payload, 'target_amount'));
        }

        return $payload;
    }

    public function restore(array $payload, array $original): array
    {
        if ($this->applies($original)) {
            data_set($payload, 'cash.amount', data_get($original, 'cash.amount'));
        }

        return $payload;
    }

    private function applies(array $payload): bool
    {
        return (data_get($payload, 'voucher_type') === 'payable'
            && (float) data_get($payload, 'cash.amount', -1) === 0.0
            && (float) data_get($payload, 'target_amount', 0) > 0)
            || (data_get($payload, 'voucher_type') === 'settlement'
            && data_get($payload, 'execution.schema') === 'voucher.execution.v2'
            && data_get($payload, 'execution.settlement.driver') === 'philhealth-bst');
    }
}
