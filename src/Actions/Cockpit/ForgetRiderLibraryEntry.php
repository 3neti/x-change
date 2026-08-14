<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Cockpit;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use LBHurtado\XChange\Models\RiderLibraryEntry;

final class ForgetRiderLibraryEntry
{
    public function handle(Model $owner, RiderLibraryEntry $entry): void
    {
        if (
            $entry->owner_type !== $owner->getMorphClass()
            || (string) $entry->owner_id !== (string) $owner->getKey()
        ) {
            throw new AuthorizationException;
        }

        $entry->delete();
    }
}
