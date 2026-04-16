<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services;

use App\Models\InstructionItem;
use Bavix\Wallet\Interfaces\Wallet;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use LBHurtado\XChange\Models\RevenueCollection;
use RuntimeException;
use Throwable;

class InstructionRevenueCollectionService
{
    public function __construct(
        protected InstructionRevenueSnapshotService $snapshot,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function collectByIndex(string $index, ?Wallet $destinationOverride = null, ?string $notes = null): array
    {
        $item = $this->instructionItemModelClass()::query()
            ->where('index', $index)
            ->first();

        if (! $item instanceof Model) {
            throw new RuntimeException("Instruction item [{$index}] not found.");
        }

        return $this->collect($item, $destinationOverride, $notes);
    }

    /**
     * @return array<string, mixed>
     */
    public function collect(Model $item, ?Wallet $destinationOverride = null, ?string $notes = null): array
    {
        $balanceMinor = (int) ($item->wallet?->balance ?? 0);

        if ($balanceMinor <= 0) {
            return [
                'instruction_item_id' => $item->getKey(),
                'index' => $item->index ?? null,
                'name' => $item->name ?? null,
                'collected' => false,
                'skipped' => true,
                'reason' => 'no_balance',
                'message' => "Instruction item [{$item->index}] has no balance to collect.",
                'amount_minor' => 0,
                'amount' => 0.0,
                'transfer_uuid' => null,
                'destination' => null,
                'record_id' => null,
            ];
        }

        $destination = $destinationOverride ?? $this->resolveDestination($item);

        if (! $destination instanceof Wallet) {
            throw new RuntimeException("Unable to resolve destination for instruction item [{$item->index}].");
        }

        return DB::transaction(function () use ($item, $destination, $balanceMinor, $notes): array {
            if (! method_exists($item, 'transfer')) {
                throw new RuntimeException(sprintf(
                    'Instruction item model [%s] does not support transfer().',
                    $item::class,
                ));
            }

            $transfer = $item->transfer(
                $destination,
                $balanceMinor,
                [
                    'reason' => 'instruction_revenue_collection',
                    'instruction_item_id' => $item->getKey(),
                    'instruction_index' => $item->index ?? null,
                    'notes' => $notes,
                ]
            );

            $record = RevenueCollection::query()->create([
                'instruction_item_id' => $item->getKey(),
                'collected_by_user_id' => $destination instanceof Model ? $destination->getKey() : null,
                'destination_type' => get_class($destination),
                'destination_id' => $destination instanceof Model ? $destination->getKey() : null,
                'amount' => abs((int) ($transfer->deposit->amount ?? 0)),
                'transfer_uuid' => $transfer->uuid,
                'notes' => $notes,
            ]);

            Log::info('[InstructionRevenueCollection] Collected', [
                'instruction_item_id' => $item->getKey(),
                'instruction_index' => $item->index ?? null,
                'amount_minor' => $balanceMinor,
                'amount' => $balanceMinor / 100,
                'transfer_uuid' => $transfer->uuid,
                'destination_type' => class_basename($destination),
                'destination_id' => $destination instanceof Model ? $destination->getKey() : null,
            ]);

            return [
                'instruction_item_id' => $item->getKey(),
                'index' => $item->index ?? null,
                'name' => $item->name ?? null,
                'collected' => true,
                'skipped' => false,
                'reason' => null,
                'message' => null,
                'amount_minor' => abs((int) ($transfer->deposit->amount ?? 0)),
                'amount' => abs((int) ($transfer->deposit->amount ?? 0)) / 100,
                'transfer_uuid' => $transfer->uuid,
                'destination' => [
                    'type' => class_basename($destination),
                    'id' => $destination instanceof Model ? $destination->getKey() : null,
                    'name' => $this->destinationName($destination),
                ],
                'record_id' => $record->getKey(),
            ];
        });
    }

    /**
     * @return array{
     *   collected: array<int, array<string, mixed>>,
     *   errors: array<int, array<string, mixed>>
     * }
     */
    public function collectAll(?float $minAmount = null, ?Wallet $destinationOverride = null): array
    {
        $snapshot = $this->snapshot->getPendingRevenue($minAmount, 'PHP');
        $modelClass = $this->instructionItemModelClass();

        $collected = [];
        $errors = [];

        foreach ($snapshot['items'] as $row) {
            $item = $modelClass::query()->find($row['id']);

            if (! $item instanceof Model) {
                $errors[] = [
                    'instruction_item_id' => $row['id'],
                    'index' => $row['index'] ?? null,
                    'error' => 'Instruction item not found during collection.',
                ];

                continue;
            }

            try {
                $collected[] = $this->collect($item, $destinationOverride);
            } catch (Throwable $e) {
                $errors[] = [
                    'instruction_item_id' => $row['id'],
                    'index' => $row['index'] ?? null,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return [
            'collected' => $collected,
            'errors' => $errors,
        ];
    }

    protected function instructionItemModelClass(): string
    {
        $class = (string) config('x-change.revenue.instruction_item_model', InstructionItem::class);

        if ($class === '' || ! class_exists($class)) {
            throw new RuntimeException('Configured revenue instruction item model is invalid.');
        }

        return $class;
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

        $modelClass = (string) config('x-change.revenue.destination.model');
        $identifier = config('x-change.revenue.destination.identifier');
        $column = (string) config('x-change.revenue.destination.identifier_column', 'email');

        if ($modelClass !== '' && class_exists($modelClass) && filled($identifier)) {
            $destination = $modelClass::query()->where($column, $identifier)->first();

            if ($destination instanceof Wallet) {
                return $destination;
            }
        }

        return null;
    }

    protected function destinationName(Wallet $destination): string
    {
        return match (true) {
            $destination instanceof Model && isset($destination->name) && filled($destination->name) => (string) $destination->name,
            $destination instanceof Model && isset($destination->email) && filled($destination->email) => (string) $destination->email,
            method_exists($destination, 'getName') => (string) $destination->getName(),
            default => class_basename($destination),
        };
    }
}
