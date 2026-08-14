<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Cockpit;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use LBHurtado\XChange\Models\RiderLibraryEntry;

final class RiderLibraryReadModel
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function for(?Authenticatable $owner): array
    {
        if (
            ! $owner instanceof Model
            || ! Schema::hasTable((new RiderLibraryEntry)->getTable())
        ) {
            return [];
        }

        return RiderLibraryEntry::query()
            ->whereMorphedTo('owner', $owner)
            ->orderByRaw('CASE WHEN pinned_at IS NULL THEN 1 ELSE 0 END')
            ->orderByDesc('pinned_at')
            ->orderByRaw('CASE WHEN saved_at IS NULL THEN 1 ELSE 0 END')
            ->orderByDesc('saved_at')
            ->orderByDesc('last_used_at')
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn (RiderLibraryEntry $entry): array => [
                'reference' => $entry->reference,
                'kind' => $entry->kind->value,
                'label' => $entry->label_ciphertext,
                'payload' => $entry->content_ciphertext,
                'saved' => $entry->saved_at !== null,
                'pinned' => $entry->pinned_at !== null,
                'use_count' => $entry->use_count,
                'last_used_at' => $entry->last_used_at?->toIso8601String(),
                'updated_at' => $entry->updated_at?->toIso8601String(),
            ])
            ->all();
    }
}
