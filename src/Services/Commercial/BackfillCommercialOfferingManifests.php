<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Commercial;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use LBHurtado\XChange\Models\CommercialOffering;

final readonly class BackfillCommercialOfferingManifests
{
    public function __construct(private CommercialOfferingManifestCompiler $manifests) {}

    public function execute(): int
    {
        $backfilled = 0;

        CommercialOffering::query()
            ->where(function (Builder $query): void {
                $query->whereNull('manifest_schema')
                    ->orWhereNull('manifest_hash')
                    ->orWhereNull('manifest_yaml');
            })
            ->select('id')
            ->lazyById()
            ->each(function (CommercialOffering $offering) use (&$backfilled): void {
                $backfilled += DB::transaction(
                    fn (): int => $this->backfill((int) $offering->getKey()),
                    attempts: 5,
                );
            });

        return $backfilled;
    }

    private function backfill(int $offeringId): int
    {
        $offering = CommercialOffering::query()
            ->lockForUpdate()
            ->findOrFail($offeringId);
        $manifestFields = [
            $offering->manifest_schema,
            $offering->manifest_hash,
            $offering->manifest_yaml,
        ];
        $populatedFields = collect($manifestFields)
            ->filter(fn (mixed $value): bool => is_string($value) && $value !== '')
            ->count();

        if ($populatedFields === count($manifestFields)) {
            return 0;
        }

        if ($populatedFields !== 0) {
            throw new \DomainException(
                "Commercial Offering [{$offering->reference}@{$offering->version}] has incomplete manifest evidence.",
            );
        }

        $snapshot = $offering->offering();

        if (! is_string($offering->snapshot_hash)
            || ! hash_equals($offering->snapshot_hash, $snapshot->snapshotHash())) {
            throw new \DomainException(
                "Commercial Offering [{$offering->reference}@{$offering->version}] conflicts with its persisted snapshot.",
            );
        }

        $manifest = $this->manifests->compile($offering->profile, $snapshot);

        $offering->forceFill([
            'manifest_schema' => $manifest->schema,
            'manifest_hash' => $manifest->hash,
            'manifest_yaml' => $manifest->yaml,
        ])->save();

        return 1;
    }
}
