<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Cockpit;

use Illuminate\Database\Eloquent\Model;
use LBHurtado\XChange\Enums\RiderLibraryEntryKind;
use LBHurtado\XChange\Models\RiderLibraryEntry;
use LBHurtado\XChange\Services\Cockpit\RiderLibraryEntryStore;

final readonly class SaveRiderLibraryEntry
{
    public function __construct(
        private RiderLibraryEntryStore $entries,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(Model $owner, array $attributes): RiderLibraryEntry
    {
        return $this->entries->persist(
            owner: $owner,
            kind: RiderLibraryEntryKind::from((string) $attributes['kind']),
            payload: $attributes['payload'],
            label: filled($attributes['label'] ?? null)
                ? (string) $attributes['label']
                : null,
            saved: true,
            used: false,
        );
    }
}
