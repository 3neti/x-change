<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Cockpit;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use LBHurtado\XChange\Models\RiderLibraryEntry;

final class UpdateRiderLibraryEntryPin
{
    public function handle(
        Model $owner,
        RiderLibraryEntry $entry,
        bool $pinned,
    ): RiderLibraryEntry {
        return DB::transaction(function () use ($owner, $entry, $pinned): RiderLibraryEntry {
            $locked = RiderLibraryEntry::query()
                ->lockForUpdate()
                ->findOrFail($entry->getKey());

            if (! $this->belongsTo($locked, $owner)) {
                throw new AuthorizationException;
            }

            if ($pinned) {
                $locked->saved_at ??= now();
                $locked->pinned_at = now();
            } else {
                $locked->pinned_at = null;
            }

            $locked->save();

            return $locked->refresh();
        });
    }

    private function belongsTo(
        RiderLibraryEntry $entry,
        Model $owner,
    ): bool {
        return $entry->owner_type === $owner->getMorphClass()
            && (string) $entry->owner_id === (string) $owner->getKey();
    }
}
