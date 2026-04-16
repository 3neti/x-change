<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services;

use Bavix\Wallet\Interfaces\Wallet;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use LBHurtado\XChange\Data\PricingEstimateData;
use RuntimeException;

class InstructionRevenueAllocatorService
{
    /**
     * @param  Model&Wallet  $issuer
     * @return array{
     *   total_minor:int,
     *   total:float,
     *   currency:string,
     *   allocations:array<int, array<string, mixed>>,
     *   debit:array{id:int|null, amount:string|int|float|null}
     * }
     */
    public function allocate(Model $issuer, PricingEstimateData $estimate, array $context = []): array
    {
        if (! $issuer instanceof Wallet) {
            throw new RuntimeException('Issuer must implement wallet behavior.');
        }

        $currency = (string) ($estimate->currency ?: 'PHP');
        $charges = collect($estimate->charges ?? []);

        if ($charges->isEmpty()) {
            return [
                'total_minor' => 0,
                'total' => 0.0,
                'currency' => $currency,
                'allocations' => [],
                'debit' => [
                    'id' => null,
                    'amount' => '0',
                ],
            ];
        }

        $allocations = [];
        $firstWithdrawId = null;
        $totalMinor = 0;

        foreach ($charges as $charge) {
            $index = (string) data_get($charge, 'index', '');
            $amountMinor = (int) data_get($charge, 'price_minor', 0);

            if ($index === '' || $amountMinor <= 0) {
                continue;
            }

            $instructionItem = $this->resolveInstructionItem($index);

            if (! $instructionItem) {
                throw new RuntimeException("Unable to resolve instruction item for charge [{$index}].");
            }

            if (! $instructionItem instanceof Wallet) {
                throw new RuntimeException("Instruction item [{$index}] is not wallet-enabled.");
            }

            $amount = $amountMinor / 100;

            $transfer = $issuer->transferFloat(
                $instructionItem,
                $amount,
                $this->buildTransferMeta($charge, $context)
            );

            $withdraw = $transfer->withdraw ?? null;
            $deposit = $transfer->deposit ?? null;

            $firstWithdrawId ??= $withdraw->id ?? null;
            $totalMinor += $amountMinor;

            $allocations[] = [
                'index' => $index,
                'instruction_item_id' => $instructionItem->getKey(),
                'instruction_item_name' => $instructionItem->name ?? null,
                'type' => $instructionItem->type ?? data_get($charge, 'type'),
                'label' => data_get($charge, 'label'),
                'amount_minor' => $amountMinor,
                'amount' => $amount,
                'currency' => $currency,
                'transfer_uuid' => $transfer->uuid ?? null,
                'withdraw_id' => $withdraw->id ?? null,
                'deposit_id' => $deposit->id ?? null,
                'meta' => $this->buildTransferMeta($charge, $context),
            ];

            Log::info('[InstructionRevenueAllocator] Charge allocated', [
                'issuer_id' => $issuer->getKey(),
                'instruction_item_id' => $instructionItem->getKey(),
                'index' => $index,
                'amount_minor' => $amountMinor,
                'amount' => $amount,
                'transfer_uuid' => $transfer->uuid ?? null,
            ]);
        }

        return [
            'total_minor' => $totalMinor,
            'total' => $totalMinor / 100,
            'currency' => $currency,
            'allocations' => $allocations,
            'debit' => [
                'id' => $firstWithdrawId,
                'amount' => (string) (-1 * $totalMinor),
            ],
        ];
    }

    protected function resolveInstructionItem(string $index): ?Model
    {
        $modelClass = (string) config(
            'x-change.revenue.instruction_item_model',
            \LBHurtado\Instruction\Models\InstructionItem::class
        );

        if ($modelClass === '' || ! class_exists($modelClass)) {
            throw new RuntimeException('Configured revenue instruction item model is invalid.');
        }

        return $modelClass::query()
            ->where('index', $index)
            ->first();
    }

    /**
     * @param  array<string, mixed>  $charge
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    protected function buildTransferMeta(array $charge, array $context = []): array
    {
        return [
            'reason' => 'pay_code_issuance',
            'charge_index' => data_get($charge, 'index'),
            'charge_label' => data_get($charge, 'label'),
            'charge_type' => data_get($charge, 'type'),
            'price_minor' => (int) data_get($charge, 'price_minor', 0),
            'price' => (float) data_get($charge, 'price', 0),
            'currency' => data_get($charge, 'currency', 'PHP'),
            'quantity' => (int) data_get($charge, 'quantity', 1),
            'idempotency_key' => data_get($context, 'idempotency_key'),
            'correlation_id' => data_get($context, 'correlation_id'),
            'requested_amount' => data_get($context, 'requested_amount'),
            'requested_currency' => data_get($context, 'requested_currency'),
        ];
    }
}
