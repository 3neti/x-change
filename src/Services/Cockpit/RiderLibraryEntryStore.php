<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Cockpit;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use LBHurtado\XChange\Enums\RiderLibraryEntryKind;
use LBHurtado\XChange\Models\RiderLibraryEntry;

final readonly class RiderLibraryEntryStore
{
    public function __construct(
        private RiderLibraryPayloadNormalizer $normalizer,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function persist(
        Model $owner,
        RiderLibraryEntryKind $kind,
        array $payload,
        ?string $label,
        bool $saved,
        bool $used,
    ): RiderLibraryEntry {
        $normalized = $this->normalizer->normalize($kind, $payload);
        $fingerprint = $this->normalizer->fingerprint($kind, $normalized);
        $normalizedLabel = filled($label)
            ? trim((string) $label)
            : $this->normalizer->defaultLabel($kind, $normalized);

        return DB::transaction(function () use (
            $owner,
            $kind,
            $normalized,
            $fingerprint,
            $normalizedLabel,
            $saved,
            $used,
        ): RiderLibraryEntry {
            $entry = RiderLibraryEntry::query()->firstOrCreate([
                'owner_type' => $owner->getMorphClass(),
                'owner_id' => (string) $owner->getKey(),
                'kind' => $kind->value,
                'content_fingerprint' => $fingerprint,
            ], [
                'format' => $kind === RiderLibraryEntryKind::Splash
                    ? (string) ($normalized['format'] ?? 'plain')
                    : null,
                'content_ciphertext' => $normalized,
                'label_ciphertext' => $normalizedLabel,
            ]);

            $locked = RiderLibraryEntry::query()
                ->lockForUpdate()
                ->findOrFail($entry->getKey());
            $now = now();

            $locked->content_ciphertext = $normalized;
            $locked->format = $kind === RiderLibraryEntryKind::Splash
                ? (string) ($normalized['format'] ?? 'plain')
                : null;

            if ($saved || blank($locked->label_ciphertext)) {
                $locked->label_ciphertext = $normalizedLabel;
            }

            if ($saved) {
                $locked->saved_at ??= $now;
                $locked->pinned_at ??= $now;
            }

            if ($used) {
                $locked->first_used_at ??= $now;
                $locked->last_used_at = $now;
                $locked->use_count = max(0, (int) $locked->use_count) + 1;
            }

            $locked->save();

            if ($used) {
                $this->pruneRecent($owner, $kind);
            }

            return $locked->refresh();
        });
    }

    private function pruneRecent(
        Model $owner,
        RiderLibraryEntryKind $kind,
    ): void {
        $limit = max(1, (int) config(
            'x-change.cockpit.quick_generate.rider_library.recent_limit_per_kind',
            20,
        ));
        $staleIds = RiderLibraryEntry::query()
            ->whereMorphedTo('owner', $owner)
            ->where('kind', $kind->value)
            ->whereNull('saved_at')
            ->whereNull('pinned_at')
            ->orderByDesc('last_used_at')
            ->orderByDesc('id')
            ->get(['id'])
            ->skip($limit)
            ->pluck('id');

        if ($staleIds->isNotEmpty()) {
            RiderLibraryEntry::query()->whereKey($staleIds)->delete();
        }
    }
}
