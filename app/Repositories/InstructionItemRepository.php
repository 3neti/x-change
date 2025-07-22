<?php

namespace App\Repositories;

use Illuminate\Support\Collection;
use App\Models\InstructionItem;

class InstructionItemRepository
{
    /**
     * Retrieve all instruction items.
     *
     * @return Collection|InstructionItem[]
     */
    public function all(): Collection
    {
        return InstructionItem::all();
    }

    /**
     * Find a single InstructionItem by its dot notation index.
     *
     * @param string $index
     * @return InstructionItem|null
     */
    public function findByIndex(string $index): ?InstructionItem
    {
        return InstructionItem::where('index', $index)->first();
    }

    /**
     * Find multiple InstructionItems by an array of indices.
     *
     * @param array $indices
     * @return Collection|InstructionItem[]
     */
    public function findByIndices(array $indices): Collection
    {
        return InstructionItem::whereIn('index', $indices)->get();
    }

    /**
     * Retrieve all instruction items of a given type.
     *
     * @param string $type
     * @return Collection|InstructionItem[]
     */
    public function allByType(string $type): Collection
    {
        return InstructionItem::where('type', $type)->get();
    }

    /**
     * Compute the total cost of the selected instruction items.
     *
     * @param array $indices
     * @return int
     */
    public function totalCost(array $indices): int
    {
        return $this->findByIndices($indices)->sum('price');
    }

    /**
     * Get descriptions from meta for the given instruction item indices.
     *
     * @param array $indices
     * @return array<string, string> // index => description
     */
    public function descriptionsFor(array $indices): array
    {
        return $this->findByIndices($indices)->mapWithKeys(function ($item) {
            return [$item->index => $item->meta['description'] ?? ''];
        })->toArray();
    }
}
