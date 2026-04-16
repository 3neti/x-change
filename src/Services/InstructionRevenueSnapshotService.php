<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services;

use Bavix\Wallet\Interfaces\Wallet;
use Brick\Money\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use RuntimeException;

class InstructionRevenueSnapshotService
{
    /**
     * @return array{
     *   pending: array{
     *     count:int,
     *     total_minor:int,
     *     total:float,
     *     formatted_total:string,
     *     currency:string
     *   },
     *   items: array<int, array<string, mixed>>
     * }
     */
    public function getPendingRevenue(?float $minAmount = null, string $currency = 'PHP'): array
    {
        $modelClass = $this->instructionItemModelClass();

        /** @var \Illuminate\Support\Collection<int, Model> $items */
        $items = $modelClass::query()->get();

        $rows = $items
            ->filter(function (Model $item) use ($minAmount): bool {
                $balance = $this->resolveWalletBalanceFloat($item);
                $min = $minAmount ?? 0.0;

                return $balance > $min;
            })
            ->map(function (Model $item) use ($currency): array {
                $balanceFloat = $this->resolveWalletBalanceFloat($item);
                $balanceMinor = (int) round($balanceFloat * 100);

                $destination = $this->resolveDestination($item);

                return [
                    'id' => $item->getKey(),
                    'name' => (string) ($item->name ?? class_basename($item)),
                    'type' => (string) ($item->type ?? 'unknown'),
                    'index' => (string) ($item->index ?? ''),
                    'balance_minor' => $balanceMinor,
                    'balance' => $balanceFloat,
                    'formatted_balance' => Money::ofMinor($balanceMinor, $currency)->formatTo('en_PH'),
                    'currency' => $currency,
                    'transaction_count' => $this->resolveTransactionCount($item),
                    'destination' => [
                        'type' => $destination ? class_basename($destination) : null,
                        'id' => $destination?->getKey(),
                        'name' => $destination ? $this->getDestinationName($destination) : 'Unresolved',
                        'is_default' => ! method_exists($item, 'revenueDestination') || ! $item->relationLoaded('revenueDestination')
                            ? true
                            : ! $item->getRelation('revenueDestination'),
                    ],
                ];
            })
            ->values();

        $totalMinor = (int) $rows->sum('balance_minor');

        return [
            'pending' => [
                'count' => $rows->count(),
                'total_minor' => $totalMinor,
                'total' => $totalMinor / 100,
                'formatted_total' => Money::ofMinor($totalMinor, $currency)->formatTo('en_PH'),
                'currency' => $currency,
            ],
            'items' => $rows->all(),
        ];
    }

    protected function instructionItemModelClass(): string
    {
        $class = (string) config('x-change.revenue.instruction_item_model', \App\Models\InstructionItem::class);

        if ($class === '' || ! class_exists($class)) {
            throw new RuntimeException('Configured revenue instruction item model is invalid.');
        }

        return $class;
    }

    protected function resolveWalletBalanceFloat(Model $item): float
    {
        if (! isset($item->wallet) || ! $item->wallet) {
            $item->loadMissing('wallet');
        }

        return (float) ($item->wallet?->balanceFloat ?? 0.0);
    }

    protected function resolveTransactionCount(Model $item): int
    {
        if (! isset($item->wallet) || ! $item->wallet) {
            $item->loadMissing('wallet');
        }

        if (! $item->wallet || ! method_exists($item->wallet, 'transactions')) {
            return 0;
        }

        return (int) $item->wallet->transactions()->count();
    }

    protected function resolveDestination(Model $item): ?Wallet
    {
        if (method_exists($item, 'revenueDestination')) {
            $item->loadMissing('revenueDestination');

            $destination = $item->getRelation('revenueDestination');

            if ($destination instanceof Wallet) {
                return $destination;
            }
        }

        $destination = $this->configuredDestination();

        return $destination instanceof Wallet ? $destination : null;
    }

    protected function configuredDestination(): ?Model
    {
        $modelClass = (string) config('x-change.revenue.destination.model');
        $identifier = config('x-change.revenue.destination.identifier');
        $column = (string) config('x-change.revenue.destination.identifier_column', 'email');

        if ($modelClass === '' || ! class_exists($modelClass) || blank($identifier)) {
            return null;
        }

        /** @var class-string<Model> $modelClass */
        return $modelClass::query()->where($column, $identifier)->first();
    }

    protected function getDestinationName(Model $destination): string
    {
        return match (true) {
            isset($destination->name) && filled($destination->name) => (string) $destination->name,
            isset($destination->email) && filled($destination->email) => (string) $destination->email,
            method_exists($destination, 'getName') => (string) $destination->getName(),
            default => class_basename($destination).' #'.$destination->getKey(),
        };
    }
}
