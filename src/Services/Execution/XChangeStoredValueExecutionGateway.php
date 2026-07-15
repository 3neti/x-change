<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Execution;

use LBHurtado\Voucher\Contracts\StoredValueExecutionGateway;
use LBHurtado\Voucher\Data\ExecutionContextData;
use LBHurtado\Voucher\Exceptions\StoredValueSpendRejectedException;

final class XChangeStoredValueExecutionGateway implements StoredValueExecutionGateway
{
    /**
     * @var array<string, int>
     */
    private array $balances = [];

    public function activate(ExecutionContextData $context, string $executionId): array
    {
        $reference = $this->reference($context);
        $balance = $this->balances[$reference] ?? $this->initialBalance($context);

        $this->balances[$reference] = $balance;

        return [
            'stored_value_reference' => $reference,
            'remaining_balance' => $balance,
            'activated_at' => now()->toISOString(),
            'activation_execution_id' => $executionId,
        ];
    }

    public function spend(ExecutionContextData $context, int $amount, string $executionId): array
    {
        $reference = $this->reference($context);
        $balance = $this->balance($context);

        if ($amount <= 0) {
            throw new StoredValueSpendRejectedException('Stored value spend amount must be positive.');
        }

        if ($amount > $balance) {
            throw new StoredValueSpendRejectedException('Stored value balance is insufficient.');
        }

        $this->balances[$reference] = $balance - $amount;

        return [
            'stored_value_reference' => $reference,
            'remaining_balance' => $this->balances[$reference],
            'last_spend_execution_id' => $executionId,
        ];
    }

    public function replenish(ExecutionContextData $context, int $amount, string $executionId): array
    {
        $reference = $this->reference($context);

        if ($amount <= 0) {
            throw new StoredValueSpendRejectedException('Stored value replenishment amount must be positive.');
        }

        $this->balances[$reference] = $this->balance($context) + $amount;

        return [
            'stored_value_reference' => $reference,
            'remaining_balance' => $this->balances[$reference],
            'last_replenishment_execution_id' => $executionId,
        ];
    }

    public function balance(ExecutionContextData $context): int
    {
        $reference = $this->reference($context);

        return $this->balances[$reference] ?? $this->initialBalance($context);
    }

    private function reference(ExecutionContextData $context): string
    {
        return (string) (
            data_get($context->instruction?->metadata, 'stored_value.reference')
            ?? $context->instruction?->metadata['stored_value_reference']
            ?? $context->voucherCode
        );
    }

    private function initialBalance(ExecutionContextData $context): int
    {
        return max(0, (int) (
            $context->meta['balance']
            ?? $context->meta['initial_balance']
            ?? data_get($context->instruction?->metadata, 'stored_value.initial_balance')
            ?? data_get($context->instruction?->metadata, 'stored_value.max_balance')
            ?? 0
        ));
    }
}
